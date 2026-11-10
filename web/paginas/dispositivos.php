<?php
/**
 * Dispositivos (nodos ESP32) y sus sensores
 */
$error = '';
$exito = '';

$puedeGestionar = tiene_permiso('gestionar_dispositivos');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acc'] ?? '') === 'nuevo_dispositivo') {
    if (!csrf_verificar($_POST['csrf'] ?? '')) { $error = 'Sesión expirada.'; }
    else {
        $codigo = trim($_POST['codigo'] ?? '');
        $idIns  = (int)$_POST['id_institucion'];
        if ($codigo === '' || $idIns <= 0) { $error = 'Complete código e institución.'; }
        else {
            try {
                q("INSERT INTO dispositivo (id_institucion, codigo, modelo, numero_serie, fecha_instalacion, estado)
                   VALUES (?,?,?,?,?, 'ACTIVO')",
                  [$idIns, $codigo, trim($_POST['modelo'] ?? '') ?: null,
                   trim($_POST['numero_serie'] ?? '') ?: null,
                   trim($_POST['fecha_instalacion'] ?? '') ?: null]);
                auditoria('DEVICE_CREADO', 'Dispositivos');
                $exito = 'Nodo ' . $codigo . ' registrado.';
            } catch (PDOException $ex) {
                $error = 'No se pudo registrar: ' . $ex->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acc'] ?? '') === 'nuevo_sensor') {
    if (!csrf_verificar($_POST['csrf'] ?? '')) { $error = 'Sesión expirada.'; }
    else {
        $idDev  = (int)$_POST['id_dispositivo'];
        $idTip  = (int)$_POST['id_tipo_sensor'];
        if ($idDev <= 0 || $idTip <= 0) { $error = 'Complete dispositivo y tipo de sensor.'; }
        else {
            try {
                q("INSERT INTO sensor (id_dispositivo, id_tipo_sensor, modelo, numero_serie, fecha_instalacion, estado)
                   VALUES (?,?,?,?,?, 'ACTIVO')",
                  [$idDev, $idTip, trim($_POST['modelo'] ?? '') ?: null,
                   trim($_POST['numero_serie'] ?? '') ?: null,
                   trim($_POST['fecha_instalacion'] ?? '') ?: null]);
                auditoria('SENSOR_CREADO', 'Dispositivos');
                $exito = 'Sensor registrado en el nodo.';
            } catch (PDOException $ex) {
                $error = 'No se pudo registrar el sensor: ' . $ex->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acc'] ?? '') === 'calibracion') {
    if (!csrf_verificar($_POST['csrf'] ?? '')) { $error = 'Sesión expirada.'; }
    elseif (!$puedeGestionar) { $error = 'No tiene permiso para registrar calibraciones.'; }
    else {
        $idSensor = (int)$_POST['id_sensor'];
        $valorRef = ($_POST['valor_referencia'] ?? '') !== '' ? (float)$_POST['valor_referencia'] : null;
        $fecha    = trim($_POST['fecha_calibracion'] ?? '') ?: date('Y-m-d H:i:s');
        if ($idSensor <= 0) { $error = 'Seleccione un sensor válido.'; }
        else {
            q("INSERT INTO calibracion_sensor (id_sensor, fecha_calibracion, valor_referencia, observacion, responsable)
               VALUES (?,?,?,?,?)",
              [$idSensor, $fecha, $valorRef, trim($_POST['observacion'] ?? '') ?: null,
               $_SESSION['usuario_nombre'] ?? null]);
            auditoria('CALIBRACION_REGISTRADA', 'Dispositivos');
            $_SESSION['mensaje'] = 'Calibración registrada para el sensor seleccionado.';
            header('Location: index.php?p=dispositivos');
            exit;
        }
    }
}

$hostHeader = $_SERVER['HTTP_HOST'] ?? 'localhost:8090';
$hostParts = explode(':', $hostHeader);
$detectedIp = $hostParts[0];

echo '<div class="panel" style="background:#f8fafc; border-left:4px solid #2563eb; margin-bottom:20px;">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
    <div>
      <h3 style="margin:0 0 6px; color:#1e3a8a;">📡 Conexión de Nodos ESP32 a este Servidor Docker</h3>
      <p style="margin:0 0 8px; font-size:13px; color:#475569;">
        Para que el ESP32 transmita datos vía WiFi a este contenedor, debe apuntar a la <strong>IP local de esta Laptop</strong> en el puerto <strong>8090</strong>:
      </p>
      <div style="display:inline-flex; align-items:center; gap:10px; background:#e0e7ff; padding:8px 14px; border-radius:6px; font-family:monospace; font-size:14px; color:#1e40af;">
        <span>IP Laptop: <strong id="lblIpLaptop">' . ($detectedIp !== 'localhost' && $detectedIp !== '127.0.0.1' ? e($detectedIp) : '192.168.1.36 (o tu IP WiFi actual)') . '</strong></span>
        <span>·</span>
        <span>Puerto: <strong>8090</strong></span>
        <span>·</span>
        <span>Ruta: <strong>/api/ingesta.php</strong></span>
      </div>
    </div>
    <div style="text-align:right;">
      <small style="color:#64748b; display:block;">¿Cambiaste de red o laptop?</small>
      <span style="font-size:12px; color:#0f766e; font-weight:600;">Presiona BOOT (GPIO 0) en el ESP32 por 3s</span>
    </div>
  </div>
</div>';

echo (!empty($exito) ? '<div class="alerta ok">' . e($exito) . '</div>' : '');
echo (!empty($error) ? '<div class="alerta error">' . e($error) . '</div>' : '');

// Formulario nuevo dispositivo
if ($puedeGestionar) {
    $instituciones = q("SELECT id_institucion, nombre FROM institucion_salud WHERE estado = 1 ORDER BY nombre")->fetchAll();
    echo '<div class="panel"><h2>Registrar nuevo nodo ESP32</h2>
      <form method="post">
        <input type="hidden" name="acc" value="nuevo_dispositivo">
        <input type="hidden" name="csrf" value="' . csrf_token() . '">
        <div class="fila">
          <div class="campo"><label>Código del nodo</label><input name="codigo" required placeholder="NODO-003"></div>
          <div class="campo"><label>Institución</label>
            <select name="id_institucion" required>';
    foreach ($instituciones as $i) {
        echo '<option value="' . (int)$i['id_institucion'] . '">' . e($i['nombre']) . '</option>';
    }
    echo '</select></div>
          <div class="campo"><label>Modelo</label><input name="modelo" value="ESP32 DevKit V1"></div>
          <div class="campo"><label>Número de serie</label><input name="numero_serie" placeholder="ESP32-..."></div>
          <div class="campo"><label>Fecha instalación</label><input type="date" name="fecha_instalacion"></div>
          <div class="campo" style="justify-content:flex-end"><label>&nbsp;</label><button class="boton" type="submit">Registrar</button></div>
        </div>
      </form></div>';
}

$tiposSensor = q("SELECT id_tipo_sensor, nombre FROM tipo_sensor ORDER BY id_tipo_sensor")->fetchAll();

$dispositivos = q("
    SELECT d.*, i.nombre AS institucion
    FROM dispositivo d JOIN institucion_salud i ON i.id_institucion = d.id_institucion
    ORDER BY d.codigo")->fetchAll();

echo '<div class="panel"><h2>Nodos del piloto</h2>';
if (!$dispositivos) {
    echo '<p>No hay dispositivos registrados.</p>';
} else {
    echo '<table class="tabla"><tr><th>Código</th><th>Institución</th><th>Modelo</th><th>Serie</th>'
       . '<th>Instalación</th><th>Estado</th><th>Sensores</th></tr>';
    foreach ($dispositivos as $d) {
        $sens = q("SELECT s.*, t.nombre AS tipo, t.id_tipo_sensor FROM sensor s
                   JOIN tipo_sensor t ON t.id_tipo_sensor = s.id_tipo_sensor
                   WHERE s.id_dispositivo = ? ORDER BY s.id_sensor", [(int)$d['id_dispositivo']])->fetchAll();
        $estCls = $d['estado'] === 'ACTIVO' ? 'st-verde' : 'st-gris';
        echo '<tr>
                <td><strong>' . e($d['codigo']) . '</strong></td>
                <td>' . e($d['institucion']) . '</td>
                <td>' . e($d['modelo']) . '</td>
                <td>' . e($d['numero_serie']) . '</td>
                <td>' . e($d['fecha_instalacion']) . '</td>
                <td><span class="etiqueta-estado ' . $estCls . '">' . e($d['estado']) . '</span></td>
                <td>';
        if ($sens) {
            echo '<ul style="margin:0;padding-left:16px">';
            foreach ($sens as $s) {
                $cls = $s['estado'] === 'ACTIVO' ? 'st-verde' : 'st-gris';
                echo '<li>' . e($s['tipo']) . ' · ' . e($s['numero_serie'])
                   . ' <span class="etiqueta-estado ' . $cls . '">' . e($s['estado']) . '</span></li>';
            }
            echo '</ul>';
            if ($puedeGestionar) {
                echo '<button class="boton mini secundario" onclick="document.getElementById(\'adds-' . (int)$d['id_dispositivo'] . '\').style.display=\'block\'">+ sensor</button>';
                echo '<div id="adds-' . (int)$d['id_dispositivo'] . '" style="display:none;margin-top:8px">
                  <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
                    <input type="hidden" name="acc" value="nuevo_sensor">
                    <input type="hidden" name="csrf" value="' . csrf_token() . '">
                    <input type="hidden" name="id_dispositivo" value="' . (int)$d['id_dispositivo'] . '">
                    <select name="id_tipo_sensor">';
                foreach ($tiposSensor as $ts) {
                    echo '<option value="' . (int)$ts['id_tipo_sensor'] . '">' . e($ts['nombre']) . '</option>';
                }
                echo '  </select>
                    <input name="numero_serie" placeholder="Serie del sensor" size="12">
                    <input name="modelo" placeholder="Modelo" size="14">
                    <button class="boton mini verde" type="submit">Añadir</button>
                  </form></div>';
            }
        } else {
            echo '—';
        }
        echo '</td></tr>';
    }
    echo '</table>';
}
echo '<p class="pie-mapa">Cada nodo del piloto incluye: 2 sondas DS18B20 (temperatura), 1 sensor de puerta '
    . '(reed switch) y 1 sensor aislado de energía — tal como se describe en la arquitectura funcional.',
    '</p></div>';

// ---- Calibración de sensores ----
$todosSensores = q("
    SELECT s.id_sensor, s.numero_serie, t.nombre AS tipo, d.codigo AS nodo
    FROM sensor s
    JOIN tipo_sensor t ON t.id_tipo_sensor = s.id_tipo_sensor
    JOIN dispositivo d ON d.id_dispositivo = s.id_dispositivo
    ORDER BY d.codigo, t.nombre, s.numero_serie")->fetchAll();

$ultimaCalib = q("
    SELECT id_sensor, MAX(fecha_calibracion) AS ultima
    FROM calibracion_sensor GROUP BY id_sensor")->fetchAll();
$porSensor = [];
foreach ($ultimaCalib as $uc) { $porSensor[$uc['id_sensor']] = $uc['ultima']; }

if ($puedeGestionar) {
    echo '<div class="panel"><h2>Registrar calibración de sensor</h2>
      <form method="post">
        <input type="hidden" name="acc" value="calibracion">
        <input type="hidden" name="csrf" value="' . csrf_token() . '">
        <div class="fila">
          <div class="campo" style="min-width:260px"><label>Sensor</label>
            <select name="id_sensor" required>';
    foreach ($todosSensores as $s) {
        echo '<option value="' . (int)$s['id_sensor'] . '">' . e($s['nodo'] . ' · ' . $s['tipo'] . ' · ' . $s['numero_serie']) . '</option>';
    }
    echo '    </select></div>
          <div class="campo"><label>Fecha y hora</label>
            <input type="datetime-local" name="fecha_calibracion" value="' . date('Y-m-d\TH:i') . '"></div>
          <div class="campo"><label>Valor de referencia (°C)</label>
            <input type="number" step="0.01" name="valor_referencia" placeholder="4.00"></div>
          <div class="campo" style="flex:2;min-width:220px"><label>Observación</label>
            <input type="text" name="observacion" placeholder="Instrumento patrón, ajuste realizado..."></div>
          <div class="campo" style="justify-content:flex-end"><label>&nbsp;</label>
            <button class="boton verde" type="submit">Guardar calibración</button></div>
        </div>
      </form></div>';
}

echo '<div class="panel"><h2>Sensores y última calibración</h2>';
echo '<table class="tabla"><tr><th>Nodo</th><th>Tipo</th><th>Serie</th><th>Última calibración</th><th>Antigüedad</th><th>Estado</th></tr>';
foreach ($todosSensores as $s) {
    $ultima = $porSensor[$s['id_sensor']] ?? null;
    if ($ultima) {
        $dias = (int)(new DateTime())->diff(new DateTime($ultima))->days;
        $cls = $dias > 365 ? 'st-rojo' : ($dias > 180 ? 'st-ambar' : 'st-verde');
        $antig = $dias . ' días';
    } else {
        $dias = null; $cls = 'st-gris'; $antig = '—';
    }
    echo '<tr><td>' . e($s['nodo']) . '</td><td>' . e($s['tipo']) . '</td><td>' . e($s['numero_serie']) . '</td>'
       . '<td>' . e($ultima ?? 'Sin registro') . '</td><td>' . e($antig) . '</td>'
       . '<td><span class="etiqueta-estado ' . $cls . '">'
       . ($dias === null ? 'Pendiente' : ($dias > 365 ? 'Vencida' : ($dias > 180 ? 'Próxima' : 'Vigente')))
       . '</span></td></tr>';
}
echo '</table>';

$calibraciones = q("
    SELECT c.fecha_calibracion, c.valor_referencia, c.observacion, c.responsable,
           s.numero_serie, t.nombre AS tipo, d.codigo AS nodo
    FROM calibracion_sensor c
    JOIN sensor s ON s.id_sensor = c.id_sensor
    JOIN tipo_sensor t ON t.id_tipo_sensor = s.id_tipo_sensor
    JOIN dispositivo d ON d.id_dispositivo = s.id_dispositivo
    ORDER BY c.fecha_calibracion DESC LIMIT 50")->fetchAll();
if ($calibraciones) {
    echo '<h2 style="margin-top:18px">Historial de calibraciones</h2>';
    echo '<table class="tabla"><tr><th>Fecha</th><th>Nodo</th><th>Sensor</th><th class="num">Referencia</th>'
       . '<th>Responsable</th><th>Observación</th></tr>';
    foreach ($calibraciones as $c) {
        echo '<tr><td>' . e($c['fecha_calibracion']) . '</td><td>' . e($c['nodo']) . '</td>'
           . '<td>' . e($c['tipo'] . ' · ' . $c['numero_serie']) . '</td>'
           . '<td class="num">' . ($c['valor_referencia'] !== null ? number_format((float)$c['valor_referencia'], 2) . ' °C' : '—') . '</td>'
           . '<td>' . e($c['responsable']) . '</td><td>' . e($c['observacion']) . '</td></tr>';
    }
    echo '</table>';
}
echo '<p class="pie-mapa">La calibración periódica de las sondas DS18B20 respalda la trazabilidad de la medición '
   . '(RF-01 y actividad F). Se recomienda recalibrar al menos cada 12 meses.</p></div>';