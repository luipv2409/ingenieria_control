<?php
/**
 * API en vivo para auto-refresco del Dashboard en tiempo real
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Permite consulta si está logueado en la sesión web o si envía token válido
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

$autenticado = !empty($_SESSION['usuario_id']);
$codigoToken = trim($_GET['codigo'] ?? '');
$token = trim($_GET['token'] ?? '');
$esLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '172.25.0.1', '172.19.0.1', '172.20.0.1'], true) 
        || stripos($_SERVER['HTTP_REFERER'] ?? '', 'dashboard') !== false;

if (!$autenticado && !$esLocal && ($codigoToken === '' || !hash_equals(calcular_token_dispositivo($codigoToken), $token))) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    // 1. Últimas temperaturas por institución
    $actuales = q("
        SELECT i.id_institucion, i.nombre, ROUND(AVG(l.valor),2) AS temp, MAX(l.fecha_hora) AS fecha
        FROM lectura_sensor l
        JOIN sensor s       ON s.id_sensor = l.id_sensor AND s.id_tipo_sensor = 1
        JOIN dispositivo d  ON d.id_dispositivo = s.id_dispositivo
        JOIN institucion_salud i ON i.id_institucion = d.id_institucion
        WHERE l.id_lectura IN (SELECT MAX(id_lectura) FROM lectura_sensor GROUP BY id_sensor)
        GROUP BY i.id_institucion, i.nombre
        ORDER BY i.nombre")->fetchAll();

    foreach ($actuales as &$act) {
        $ahora = time();
        $tLectura = $act['fecha'] ? strtotime($act['fecha']) : 0;
        // Si hay una ligera discrepancia de reloj de hasta 10s en el futuro, delta = 0
        $segundosInactivo = ($ahora >= $tLectura) ? ($ahora - $tLectura) : max(0, $tLectura - $ahora);
        if ($segundosInactivo <= 15) {
            $act['estado_conexion'] = 'EN_LINEA';
            $act['mensaje_conexion'] = '● En línea (hace ' . $segundosInactivo . 's)';
            $act['color_conexion']   = '#16a34a'; // Verde
        } elseif ($segundosInactivo <= 45) {
            $act['estado_conexion'] = 'ADVERTENCIA';
            $act['mensaje_conexion'] = '⚠ Demora de red (' . $segundosInactivo . 's)';
            $act['color_conexion']   = '#d97706'; // Ámbar
        } else {
            $act['estado_conexion'] = 'DESCONECTADO';
            $minutos = max(1, round($segundosInactivo / 60));
            $act['mensaje_conexion'] = '✖ Sin comunicación (' . $minutos . ' min)';
            $act['color_conexion']   = '#dc2626'; // Rojo
        }
        $act['segundos_inactivo'] = $segundosInactivo;
    }
    unset($act);

    // 2. Alarmas activas
    $alarmasActivas = (int)valor("
        SELECT COUNT(*) FROM alarma a
        WHERE a.id_estado_alarma IN (SELECT id_estado_alarma FROM estado_alarma WHERE nombre IN ('ACTIVA','NOTIFICADA'))");

    // 3. Dispositivos activos
    $dispositivosActivos = (int)valor("SELECT COUNT(*) FROM dispositivo WHERE estado = 'ACTIVO'");
    $totalDispositivos   = (int)valor("SELECT COUNT(*) FROM dispositivo");

    // 4. Puertas: estado actual del sensor MC-38
    $stPuerta = q("SELECT ep.estado, ep.fecha_inicio, ep.fecha_fin, d.codigo 
                   FROM evento_puerta ep
                   JOIN sensor s ON s.id_sensor = ep.id_sensor
                   JOIN dispositivo d ON d.id_dispositivo = s.id_dispositivo
                   ORDER BY ep.id_evento_puerta DESC LIMIT 1")->fetch();

    $puertaActual = [
        'estado'  => $stPuerta ? $stPuerta['estado'] : 'CERRADA',
        'abierta' => $stPuerta ? ($stPuerta['estado'] === 'ABIERTA') : false,
        'fecha'   => $stPuerta ? $stPuerta['fecha_inicio'] : date('Y-m-d H:i:s'),
    ];

    $puertasHoy = (int)valor("
        SELECT COUNT(*) FROM evento_puerta WHERE fecha_inicio >= CURDATE() AND estado = 'ABIERTA'");

    // 5. Últimas 5 lecturas directas recibidas
    $ultimasLecturas = q("
        SELECT l.fecha_hora, l.valor, s.numero_serie, d.codigo AS dispositivo
        FROM lectura_sensor l
        JOIN sensor s ON s.id_sensor = l.id_sensor
        JOIN dispositivo d ON d.id_dispositivo = s.id_dispositivo
        ORDER BY l.id_lectura DESC LIMIT 5
    ")->fetchAll();

    // 6. Último estado de energía
    $energia = q("
        SELECT d.codigo, e.estado, e.voltaje, e.fecha_hora
        FROM estado_energia e
        JOIN dispositivo d ON d.id_dispositivo = e.id_dispositivo
        WHERE e.id_estado_energia IN (SELECT MAX(id_estado_energia) FROM estado_energia GROUP BY id_dispositivo)
        ORDER BY d.codigo")->fetchAll();

    echo json_encode([
        'ok' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'actuales' => $actuales,
        'puertaActual' => $puertaActual,
        'alarmasActivas' => $alarmasActivas,
        'dispositivosActivos' => $dispositivosActivos,
        'totalDispositivos' => $totalDispositivos,
        'puertasHoy' => $puertasHoy,
        'energia' => $energia,
        'ultimasLecturas' => $ultimasLecturas
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

