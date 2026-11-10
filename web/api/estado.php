<?php
/**
 * Estado en vivo de la cadena de frío (para el simulador y el dashboard)
 * GET /api/estado.php?codigo=NODO-REMOTO-01&token=<sha256(codigo+secret)>
 *    -> JSON con última temperatura, energía y alarmas abiertas por dispositivo
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Autenticación por token de nodo (RNF-10: seguridad de las interfaces de datos)
$codigo = trim((string)($_GET['codigo'] ?? ''));
$token  = trim((string)($_GET['token'] ?? ''));
if ($codigo === '' || $token === '' || !hash_equals(calcular_token_dispositivo($codigo), $token)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Token inválido o ausente']);
    exit;
}

$dispositivos = q("
    SELECT d.id_dispositivo, d.codigo, COALESCE(d.modelo, d.codigo) AS nombre, i.nombre AS institucion,
           cfg.temperatura_min, cfg.temperatura_max
    FROM dispositivo d
    JOIN institucion_salud i ON i.id_institucion = d.id_institucion
    LEFT JOIN configuracion_control cfg ON cfg.id_institucion = i.id_institucion AND cfg.estado = 1
    ORDER BY d.id_dispositivo")->fetchAll();

$salida = [];
foreach ($dispositivos as $d) {
    $st = q("SELECT l.valor, l.fecha_hora
             FROM lectura_sensor l JOIN sensor s ON s.id_sensor = l.id_sensor
             WHERE s.id_dispositivo = ? AND s.id_tipo_sensor = 1
             ORDER BY l.fecha_hora DESC LIMIT 1", [$d['id_dispositivo']]);
    $ultTemp = $st->fetch();
    $st = q("SELECT estado, voltaje, fecha_hora FROM estado_energia WHERE id_dispositivo = ?
             ORDER BY fecha_hora DESC LIMIT 1", [$d['id_dispositivo']]);
    $en = $st->fetch();
    $st = q("SELECT t.nombre AS tipo, a.nivel, a.fecha_hora
             FROM alarma a
             JOIN tipo_alarma t ON t.id_tipo_alarma = a.id_tipo_alarma
             JOIN estado_alarma e ON e.id_estado_alarma = a.id_estado_alarma
             WHERE a.id_dispositivo = ? AND e.nombre IN ('ACTIVA','NOTIFICADA','EN_ATENCION')
             ORDER BY a.fecha_hora DESC", [$d['id_dispositivo']]);
    $alarmas = $st->fetchAll();
    $estado = 'NORMAL';
    if (empty($ultTemp))                  $estado = 'SIN_DATOS';
    elseif ($ultTemp['valor'] > (float)$d['temperatura_max']
         || $ultTemp['valor'] < (float)$d['temperatura_min']) $estado = 'ALARMA';
    elseif ($en && $en['estado'] === 'AUSENTE')               $estado = 'SIN_ENERGIA';
    elseif ($ultTemp['valor'] >= (float)$d['temperatura_max'] - 0.8
         || $ultTemp['valor'] <= (float)$d['temperatura_min'] + 0.8) $estado = 'ADVERTENCIA';

    $salida[] = [
        'dispositivo' => $d['codigo'],
        'nombre'      => $d['nombre'],
        'institucion' => $d['institucion'],
        'estado'      => $estado,
        'temperatura' => $ultTemp ? (float)$ultTemp['valor'] : null,
        'fecha_lectura' => $ultTemp ? $ultTemp['fecha_hora'] : null,
        'rango'       => [(float)$d['temperatura_min'], (float)$d['temperatura_max']],
        'energia'     => $en ? ['estado' => $en['estado'], 'voltaje' => $en['voltaje']] : null,
        'alarmas_abiertas' => $alarmas,
    ];
}

echo json_encode(['ok' => true, 'fecha' => date('Y-m-d H:i:s'), 'dispositivos' => $salida], JSON_UNESCAPED_UNICODE);