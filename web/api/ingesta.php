<?php
/**
 * API de ingesta de datos de los nodos PUNATA VACUNA SEGURA
 *
 * Los nodos ESP32 (o el simulador) envían un POST con JSON:
 * {
 *   "codigo": "NODO-REMOTO-01",
 *   "token":   token = sha256(mayusculas(codigo) . API_SECRET),
 *   "lecturas": [ {"sensor":"DS18B20-LC-1","valor":4.52,"fecha":"2026-10-07 10:00:00","calidad":"VALIDO"}, ... ],
 *   "energia": {"estado":"PRESENTE","voltaje":220,"fecha":"..."},
 *   "puerta":  {"abierta":false,"fecha":"..."},
 *   "alertas": [ {"tipo":"TEMPERATURA_ALTA","nivel":"CRITICA","descripcion":"..."} ]
 * }
 *
 * Respuesta: {"ok":true, "insertados":N, "alarmas":[...]}
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$body = file_get_contents('php://input');
$dato = json_decode($body, true);
if (!is_array($dato)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

$codigo = trim((string)($dato['codigo'] ?? ''));
$token  = trim((string)($dato['token'] ?? ''));

if ($codigo === '' || $token === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Faltan codigo y token']);
    exit;
}

if (!hash_equals(calcular_token_dispositivo($codigo), $token)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Token inválido']);
    exit;
}

$dev = q("SELECT d.id_dispositivo, d.id_institucion, d.codigo
          FROM dispositivo d WHERE d.codigo = ? AND d.estado = 'ACTIVO' LIMIT 1", [$codigo])->fetch();
if (!$dev) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Dispositivo no encontrado o inactivo']);
    exit;
}

$insertados = 0;
$alarmas = [];
$errors = [];

// Configuración de control de la institución
$cfg = q("SELECT temperatura_min, temperatura_max, tiempo_alerta_minutos, id_configuracion
           FROM configuracion_control
           WHERE id_institucion = ? AND estado = 1 AND (fecha_fin IS NULL OR fecha_fin > NOW())
           ORDER BY id_configuracion DESC LIMIT 1", [$dev['id_institucion']])->fetch();

function sensor_por_serie(PDO $pdo, int $idDev, string $serie): ?array
{
    $st = $pdo->prepare("SELECT s.id_sensor, s.id_tipo_sensor, s.id_dispositivo
                         FROM sensor s WHERE s.id_dispositivo = ? AND s.numero_serie = ? AND s.estado = 'ACTIVO'");
    $st->execute([$idDev, $serie]);
    return $st->fetch() ?: null;
}

function estado_open(PDO $pdo, int $idDev, string $tipoNombre): ?array
{
    $st = $pdo->prepare("
        SELECT a.id_alarma
        FROM alarma a
        JOIN tipo_alarma t ON t.id_tipo_alarma = a.id_tipo_alarma
        JOIN estado_alarma e ON e.id_estado_alarma = a.id_estado_alarma
        WHERE a.id_dispositivo = ? AND t.nombre = ?
          AND e.nombre IN ('ACTIVA','NOTIFICADA','EN_ATENCION')
        ORDER BY a.id_alarma DESC LIMIT 1");
    $st->execute([$idDev, $tipoNombre]);
    return $st->fetch() ?: null;
}

function cerrar_alarmas_tipo(PDO $pdo, int $idDev, string $tipoNombre, ?string $razon = null): int
{
    $st = $pdo->prepare("
        UPDATE alarma a
        JOIN tipo_alarma t ON t.id_tipo_alarma = a.id_tipo_alarma
        JOIN estado_alarma e ON e.id_estado_alarma = a.id_estado_alarma
        SET a.id_estado_alarma = (SELECT id_estado_alarma FROM estado_alarma WHERE nombre = 'RESUELTA')
        WHERE a.id_dispositivo = ? AND t.nombre = ? AND e.nombre IN ('ACTIVA','NOTIFICADA','EN_ATENCION')");
    $st->execute([$idDev, $tipoNombre]);
    return $st->rowCount();
}

function notificar_alarma(PDO $pdo, int $idAlarma): int
{
    // Notifica a todos los usuarios activos con permiso responder_alarmas (RF-08)
    $st = $pdo->prepare("
        INSERT INTO notificacion (id_alarma, id_usuario, medio, estado)
        SELECT ?, u.id_usuario, 'PANEL', 'ENTREGADA'
        FROM usuario u
        WHERE u.estado = 1
          AND EXISTS (
            SELECT 1 FROM usuario_rol ur
            JOIN rol_permiso rp ON rp.id_rol = ur.id_rol
            JOIN permiso p      ON p.id_permiso = rp.id_permiso
            WHERE ur.id_usuario = u.id_usuario AND p.nombre = 'responder_alarmas')");
    $st->execute([$idAlarma]);
    return $st->rowCount();
}

function crear_alarma(PDO $pdo, int $idDev, string $tipoNombre, string $nivel, string $desc, string $fecha): ?int
{
    $st = $pdo->prepare("SELECT id_tipo_alarma FROM tipo_alarma WHERE nombre = ?");
    $st->execute([$tipoNombre]);
    $tid = $st->fetchColumn();
    if (!$tid) return null;
    $st = $pdo->prepare("SELECT id_estado_alarma FROM estado_alarma WHERE nombre = 'ACTIVA'");
    $st->execute();
    $eid = $st->fetchColumn();
    $st = $pdo->prepare("INSERT INTO alarma (id_tipo_alarma, id_estado_alarma, id_dispositivo, fecha_hora, nivel, descripcion)
                         VALUES (?,?,?,?,?,?)");
    $st->execute([$tid, $eid, $idDev, $fecha, $nivel, mb_substr($desc, 0, 250)]);
    $id = (int)$pdo->lastInsertId();
    
    // 1. Notificación en Panel Web
    notificar_alarma($pdo, $id);

    // 2. Notificación vía Email a usuarios responsables
    $stDev = $pdo->prepare("SELECT codigo FROM dispositivo WHERE id_dispositivo = ?");
    $stDev->execute([$idDev]);
    $codigoDev = $stDev->fetchColumn() ?: "DISP-$idDev";

    notificar_alarma_por_email($pdo, $id, $tipoNombre, $nivel, $desc, $codigoDev, $fecha);

    return $id;
}

// ---------------- LECTURAS ----------------
foreach ((array)($dato['lecturas'] ?? []) as $lec) {
    $serie = (string)($lec['sensor'] ?? '');
    $fecha = (string)($lec['fecha'] ?? date('Y-m-d H:i:s'));
    $valor = (float)($lec['valor'] ?? 0);
    $calidad = (string)($lec['calidad'] ?? 'VALIDO');
    if ($serie === '') continue;

    $sensor = sensor_por_serie(db(), (int)$dev['id_dispositivo'], $serie);
    if (!$sensor) { $errors[] = "sensor desconocido: $serie"; continue; }

    q("INSERT INTO lectura_sensor (id_sensor, fecha_hora, valor, calidad_dato) VALUES (?,?,?,?)",
      [(int)$sensor['id_sensor'], $fecha, $valor, $calidad]);
    $insertados++;

    // Lógica de alarmas por temperatura
    if ((int)$sensor['id_tipo_sensor'] === 1 && $cfg) {
        $tipoOut = null;
        if ($valor > (float)$cfg['temperatura_max']) $tipoOut = 'TEMPERATURA_ALTA';
        if ($valor < (float)$cfg['temperatura_min']) $tipoOut = 'TEMPERATURA_BAJA';
        if ($tipoOut !== null) {
            if (!estado_open(db(), (int)$dev['id_dispositivo'], $tipoOut)) {
                $nivel = ((abs($valor - (float)$cfg['temperatura_min']) > 1.5)
                        || (abs($valor - (float)$cfg['temperatura_max']) > 1.5)) ? 'CRITICA' : 'ADVERTENCIA';
                $id = crear_alarma(db(), (int)$dev['id_dispositivo'], $tipoOut, $nivel,
                                   "Temperatura de $valor °C fuera del rango autorizado (" .
                                   $cfg['temperatura_min'] . '–' . $cfg['temperatura_max'] . ' °C)', $fecha);
                if ($id) $alarmas[] = $id;
            }
        } else {
            $cerradas = 0;
            $cerradas += cerrar_alarmas_tipo(db(), (int)$dev['id_dispositivo'], 'TEMPERATURA_ALTA');
            $cerradas += cerrar_alarmas_tipo(db(), (int)$dev['id_dispositivo'], 'TEMPERATURA_BAJA');
            if ($cerradas > 0) $alarmas[] = "resueltas:$cerradas";
        }
    }
}

// ---------------- ENERGÍA ----------------
if (!empty($dato['energia'])) {
    $en = $dato['energia'];
    $estado = mb_strtoupper((string)($en['estado'] ?? 'PRESENTE'));
    $fecha = (string)($en['fecha'] ?? date('Y-m-d H:i:s'));
    $voltaje = isset($en['voltaje']) ? (float)$en['voltaje'] : null;
    q("INSERT INTO estado_energia (id_dispositivo, fecha_hora, estado, voltaje) VALUES (?,?,?,?)",
      [(int)$dev['id_dispositivo'], $fecha, $estado, $voltaje]);
    $insertados++;
    if ($estado === 'AUSENTE' && !estado_open(db(), (int)$dev['id_dispositivo'], 'CORTE_ENERGIA')) {
        $id = crear_alarma(db(), (int)$dev['id_dispositivo'], 'CORTE_ENERGIA', 'CRITICA',
                           'Corte de energía eléctrica detectado en el nodo ' . $codigo, $fecha);
        if ($id) $alarmas[] = $id;
    }
    if ($estado === 'PRESENTE') {
        $c = cerrar_alarmas_tipo(db(), (int)$dev['id_dispositivo'], 'CORTE_ENERGIA');
        if ($c) $alarmas[] = "resueltas:$c";
    }
}

// ---------------- PUERTA ----------------
if (isset($dato['puerta'])) {
    $pu = $dato['puerta'];
    $fecha = (string)($pu['fecha'] ?? date('Y-m-d H:i:s'));
    $abierta = (bool)($pu['abierta'] ?? false);
    // sensor de puerta del dispositivo
    $st = q("SELECT id_sensor FROM sensor WHERE id_dispositivo = ? AND id_tipo_sensor = 2 AND estado='ACTIVO' LIMIT 1",
            [(int)$dev['id_dispositivo']]);
    $sId = $st->fetchColumn();
    if ($sId) {
        $ultimo = q("SELECT * FROM evento_puerta WHERE id_sensor = ? ORDER BY id_evento_puerta DESC LIMIT 1",
                    [(int)$sId])->fetch();
        if ($abierta) {
            if (!$ultimo || $ultimo['fecha_fin'] !== null) {
                q("INSERT INTO evento_puerta (id_sensor, fecha_inicio, estado) VALUES (?,?, 'ABIERTA')", [(int)$sId, $fecha]);
                $insertados++;
            }
        } else {
            if ($ultimo && $ultimo['fecha_fin'] === null) {
                q("UPDATE evento_puerta SET fecha_fin = ?, duracion_segundos = TIMESTAMPDIFF(SECOND, fecha_inicio, ?), estado = 'CERRADA'
                   WHERE id_evento_puerta = ?", [$fecha, $fecha, (int)$ultimo['id_evento_puerta']]);
                $insertados++;
            }
        }
    }
}

// ---------------- ALERTAS EXPLÍCITAS ----------------
foreach ((array)($dato['alertas'] ?? []) as $al) {
    $tipo = (string)($al['tipo'] ?? '');
    $nivel = (string)($al['nivel'] ?? 'ADVERTENCIA');
    $desc = (string)($al['descripcion'] ?? '');
    $fecha = (string)($al['fecha'] ?? date('Y-m-d H:i:s'));
    if ($tipo === '') continue;
    if (!estado_open(db(), (int)$dev['id_dispositivo'], $tipo)) {
        $id = crear_alarma(db(), (int)$dev['id_dispositivo'], $tipo, $nivel, $desc, $fecha);
        if ($id) $alarmas[] = $id;
    }
}

echo json_encode([
    'ok'         => true,
    'dispositivo'=> $codigo,
    'insertados' => $insertados,
    'alarmas'    => $alarmas,
    'errores'    => $errors,
], JSON_UNESCAPED_UNICODE);