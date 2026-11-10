<?php
/**
 * Vacunas, lotes y movimientos
 */
$error = '';
$exito = '';
$puedeGestionar = tiene_permiso('gestionar_vacunas');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acc'] ?? '') === 'movimiento') {
    if (!csrf_verificar($_POST['csrf'] ?? '')) { $error = 'Sesión expirada.'; }
    elseif (!$puedeGestionar) { $error = 'No tiene permiso para registrar movimientos.'; }
    else {
        $idLote = (int)$_POST['id_lote'];
        $tipo   = trim($_POST['tipo_movimiento'] ?? '');
        $cant   = (int)$_POST['cantidad'];
        if ($idLote <= 0 || $tipo === '' || $cant <= 0) { $error = 'Complete lote, tipo y cantidad (>0).'; }
        else {
            q("INSERT INTO movimiento_vacuna (id_lote, id_usuario, tipo_movimiento, cantidad, observacion)
               VALUES (?,?,?,?,?)",
              [$idLote, $_SESSION['usuario_id'], $tipo, $cant, trim($_POST['observacion'] ?? '') ?: null]);
            auditoria('MOVIMIENTO_REGISTRADO', 'Vacunas');
            $_SESSION['mensaje'] = 'Movimiento de ' . $cant . ' dosis registrado.';
            header('Location: index.php?p=vacunas');
            exit;
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acc'] ?? '') === 'almacenamiento') {
    if (!csrf_verificar($_POST['csrf'] ?? '')) { $error = 'Sesión expirada.'; }
    elseif (!$puedeGestionar) { $error = 'No tiene permiso para gestionar almacenamiento.'; }
    else {
        $idLote = (int)($_POST['id_lote'] ?? 0);
        $idDev  = (int)($_POST['id_dispositivo'] ?? 0);
        $cant   = (int)($_POST['cantidad'] ?? 0);
        $fecha  = trim($_POST['fecha_ingreso'] ?? '') ?: date('Y-m-d H:i:s');
        if ($idLote <= 0 || $idDev <= 0 || $cant <= 0) {
            $error = 'Complete lote, refrigerador (nodo) y cantidad (>0).';
        } else {
            q("INSERT INTO almacenamiento_vacuna (id_lote, id_dispositivo, fecha_ingreso, cantidad)
               VALUES (?,?,?,?)", [$idLote, $idDev, $fecha, $cant]);
            auditoria('ALMACENAMIENTO_INGRESO', 'Vacunas');
            $_SESSION['mensaje'] = 'Lote almacenado en el nodo seleccionado.';
            header('Location: index.php?p=vacunas');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acc'] ?? '') === 'retirar') {
    if (!csrf_verificar($_POST['csrf'] ?? '')) { $error = 'Sesión expirada.'; }
    elseif (!$puedeGestionar) { $error = 'No tiene permiso para gestionar almacenamiento.'; }
    else {
        $idAlm = (int)($_POST['id_almacenamiento'] ?? 0);
        q("UPDATE almacenamiento_vacuna SET fecha_salida = NOW()
           WHERE id_almacenamiento = ? AND fecha_salida IS NULL", [$idAlm]);
        auditoria('ALMACENAMIENTO_RETIRO', 'Vacunas');
        $_SESSION['mensaje'] = 'Se registró la salida del lote del refrigerador.';
        header('Location: index.php?p=vacunas');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acc'] ?? '') === 'nueva_vacuna') {
    if (!csrf_verificar($_POST['csrf'] ?? '')) { $error = 'Sesión expirada.'; }
    elseif (!$puedeGestionar) { $error = 'No tiene permiso para gestionar vacunas.'; }
    else {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') { $error = 'El nombre de la vacuna es obligatorio.'; }
        else {
            q("INSERT INTO vacuna (nombre, fabricante, temperatura_min, temperatura_max, descripcion)
               VALUES (?,?,?,?,?)",
              [$nombre, trim($_POST['fabricante'] ?? '') ?: null,
               ($_POST['temperatura_min'] ?? '') !== '' ? (float)$_POST['temperatura_min'] : null,
               ($_POST['temperatura_max'] ?? '') !== '' ? (float)$_POST['temperatura_max'] : null,
               trim($_POST['descripcion'] ?? '') ?: null]);
            auditoria('VACUNA_CREADA', 'Vacunas');
            $_SESSION['mensaje'] = 'Vacuna registrada correctamente.';
            header('Location: index.php?p=vacunas');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acc'] ?? '') === 'nuevo_lote') {
    if (!csrf_verificar($_POST['csrf'] ?? '')) { $error = 'Sesión expirada.'; }
    elseif (!$puedeGestionar) { $error = 'No tiene permiso para gestionar lotes.'; }
    else {
        $idVac = (int)($_POST['id_vacuna'] ?? 0);
        $cod   = trim($_POST['codigo_lote'] ?? '');
        $cant  = (int)($_POST['cantidad_inicial'] ?? 0);
        if ($idVac <= 0 || $cod === '' || $cant <= 0) {
            $error = 'Complete vacuna, código de lote y cantidad inicial (>0).';
        } else {
            try {
                q("INSERT INTO lote_vacuna (id_vacuna, codigo_lote, fecha_fabricacion, fecha_vencimiento, cantidad_inicial, estado)
                   VALUES (?,?,?,?,?,?)",
                  [$idVac, $cod, trim($_POST['fecha_fabricacion'] ?? '') ?: null,
                   trim($_POST['fecha_vencimiento'] ?? '') ?: null, $cant,
                   trim($_POST['estado'] ?? '') ?: 'ACTIVO']);
                auditoria('LOTE_CREADO', 'Vacunas');
                $_SESSION['mensaje'] = 'Lote ' . $cod . ' registrado.';
                header('Location: index.php?p=vacunas');
                exit;
            } catch (PDOException $ex) {
                $error = 'No se pudo registrar el lote (¿código duplicado?): ' . $ex->getMessage();
            }
        }
    }
}

echo (!empty($exito) ? '<div class="alerta ok">' . e($exito) . '</div>' : '');
echo (!empty($error) ? '<div class="alerta error">' . e($error) . '</div>' : '');

$vacunas = q("
    SELECT v.*,
      (SELECT COUNT(*) FROM lote_vacuna lv WHERE lv.id_vacuna = v.id_vacuna) AS lotes,
      (SELECT COALESCE(SUM(lv.cantidad_inicial),0) FROM lote_vacuna lv WHERE lv.id_vacuna = v.id_vacuna) AS dosis
    FROM vacuna v ORDER BY v.nombre")->fetchAll();

if ($puedeGestionar) {
    echo '<div class="dos-columnas">
      <div class="panel"><h2>Registrar nueva vacuna</h2>
        <form method="post">
          <input type="hidden" name="acc" value="nueva_vacuna">
          <input type="hidden" name="csrf" value="' . csrf_token() . '">
          <div class="fila">
            <div class="campo"><label>Nombre</label><input name="nombre" required placeholder="Ej. Antirrábica"></div>
            <div class="campo"><label>Fabricante</label><input name="fabricante"></div>
          </div>
          <div class="fila">
            <div class="campo"><label>Temp. mínima (°C)</label><input type="number" step="0.01" name="temperatura_min" value="2.00"></div>
            <div class="campo"><label>Temp. máxima (°C)</label><input type="number" step="0.01" name="temperatura_max" value="8.00"></div>
          </div>
          <div class="campo"><label>Descripción</label><input name="descripcion" placeholder="Enfermedad que previene"></div>
          <button class="boton" type="submit">Registrar vacuna</button>
        </form>
      </div>

      <div class="panel"><h2>Registrar nuevo lote</h2>
        <form method="post">
          <input type="hidden" name="acc" value="nuevo_lote">
          <input type="hidden" name="csrf" value="' . csrf_token() . '">
          <div class="campo"><label>Vacuna</label>
            <select name="id_vacuna" required>';
    foreach ($vacunas as $v) {
        echo '<option value="' . (int)$v['id_vacuna'] . '">' . e($v['nombre']) . '</option>';
    }
    echo '  </select></div>
          <div class="fila">
            <div class="campo"><label>Código de lote</label><input name="codigo_lote" required placeholder="LOTE-2026-001"></div>
            <div class="campo"><label>Cantidad inicial</label><input type="number" min="1" name="cantidad_inicial" required></div>
          </div>
          <div class="fila">
            <div class="campo"><label>Fabricación</label><input type="date" name="fecha_fabricacion"></div>
            <div class="campo"><label>Vencimiento</label><input type="date" name="fecha_vencimiento"></div>
          </div>
          <div class="campo"><label>Estado</label>
            <select name="estado"><option>ACTIVO</option><option>CUARENTENA</option><option>AGOTADO</option></select></div>
          <button class="boton verde" type="submit">Registrar lote</button>
        </form>
      </div>
    </div>';
}

echo '<div class="panel"><h2>Vacunas del programa</h2>';
echo '<table class="tabla"><tr><th>Vacuna</th><th>Fabricante</th><th class="num">T° mín</th>'
   . '<th class="num">T° máx</th><th class="num">Lotes</th><th class="num">Dosis (inicial)</th></tr>';
foreach ($vacunas as $v) {
    echo '<tr><td><strong>' . e($v['nombre']) . '</strong><br><small>' . e($v['descripcion']) . '</small></td>'
       . '<td>' . e($v['fabricante']) . '</td>'
       . '<td class="num">' . ($v['temperatura_min'] !== null ? number_format((float)$v['temperatura_min'], 2) : '—') . '</td>'
       . '<td class="num">' . ($v['temperatura_max'] !== null ? number_format((float)$v['temperatura_max'], 2) : '—') . '</td>'
       . '<td class="num">' . (int)$v['lotes'] . '</td>'
       . '<td class="num">' . number_format((int)$v['dosis']) . '</td></tr>';
}
echo '</table></div>';

$lotes = q("
    SELECT lv.id_lote, lv.codigo_lote, lv.cantidad_inicial, lv.fecha_vencimiento, lv.estado,
           v.nombre AS vacuna,
           lv.cantidad_inicial + COALESCE((
             SELECT SUM(CASE WHEN m.tipo_movimiento IN ('INGRESO','TRASLADO_ENTRADA','DEVOLUCION')
                             THEN m.cantidad ELSE -m.cantidad END)
             FROM movimiento_vacuna m WHERE m.id_lote = lv.id_lote), 0) AS stock
    FROM lote_vacuna lv JOIN vacuna v ON v.id_vacuna = lv.id_vacuna
    ORDER BY lv.fecha_vencimiento")->fetchAll();

echo '<div class="dos-columnas">';
echo '<div class="panel"><h2>Lotes y stock</h2>';
echo '<table class="tabla"><tr><th>Lote</th><th>Vacuna</th><th class="num">Stock</th>'
   . '<th>Vencimiento</th><th>Estado</th></tr>';
foreach ($lotes as $l) {
    $venc = new DateTime($l['fecha_vencimiento']);
    $clase = $venc < new DateTime() ? 'st-rojo'
           : ($venc < new DateTime('+3 months') ? 'st-ambar' : 'st-verde');
    $stCls = $l['stock'] <= 0 ? 'st-rojo' : 'st-gris';
    echo '<tr><td><strong>' . e($l['codigo_lote']) . '</strong></td>'
       . '<td>' . e($l['vacuna']) . '</td>'
       . '<td class="num"><span class="etiqueta-estado ' . $stCls . '">' . (int)$l['stock'] . '</span></td>'
       . '<td>' . e($l['fecha_vencimiento']) . ' <span class="etiqueta-estado ' . $clase . '">'
       . (new DateTime())->diff($venc)->days . ' d</span></td>'
       . '<td>' . e($l['estado']) . '</td></tr>';
}
echo '</table></div>';

echo '<div class="panel"><h2>Registrar movimiento</h2>';
if ($puedeGestionar) {
    echo '<form method="post">
      <input type="hidden" name="acc" value="movimiento">
      <input type="hidden" name="csrf" value="' . csrf_token() . '">
      <div class="campo"><label>Lote</label>
        <select name="id_lote">';
    foreach ($lotes as $l) {
        echo '<option value="' . (int)$l['id_lote'] . '">' . e($l['codigo_lote']) . ' · ' . e($l['vacuna']) . '</option>';
    }
    echo '  </select></div>
      <div class="campo"><label>Tipo de movimiento</label>
        <select name="tipo_movimiento">';
    foreach (['INGRESO', 'APLICACION', 'SALIDA', 'TRASLADO', 'PERDIDA', 'DEVOLUCION'] as $t) {
        echo '<option>' . $t . '</option>';
    }
    echo '  </select></div>
      <div class="campo"><label>Cantidad (dosis)</label>
        <input type="number" min="1" name="cantidad" required></div>
      <div class="campo"><label>Observación</label>
        <input type="text" name="observacion" placeholder="Campaña, jornada, traslado..."></div>
      <button class="boton verde" type="submit">Registrar movimiento</button>
    </form>';
} else {
    echo '<p>El rol actual no permite registrar movimientos. Solicite el permiso <em>gestionar_vacunas</em>.</p>';
}
echo '</div></div>';

// ---- Almacenamiento por refrigerador (nodo) ----
$dispositivos = q("
    SELECT d.id_dispositivo, d.codigo, i.nombre AS institucion
    FROM dispositivo d JOIN institucion_salud i ON i.id_institucion = d.id_institucion
    WHERE d.estado = 'ACTIVO' ORDER BY d.codigo")->fetchAll();

$almacenados = q("
    SELECT av.id_almacenamiento, av.fecha_ingreso, av.cantidad,
           lv.codigo_lote, v.nombre AS vacuna, d.codigo AS nodo, i.nombre AS institucion
    FROM almacenamiento_vacuna av
    JOIN lote_vacuna lv ON lv.id_lote = av.id_lote
    JOIN vacuna v ON v.id_vacuna = lv.id_vacuna
    JOIN dispositivo d ON d.id_dispositivo = av.id_dispositivo
    JOIN institucion_salud i ON i.id_institucion = d.id_institucion
    WHERE av.fecha_salida IS NULL
    ORDER BY i.nombre, d.codigo, lv.fecha_vencimiento")->fetchAll();

echo '<div class="dos-columnas">';
echo '<div class="panel"><h2>Lotes almacenados actualmente</h2>';
if (!$almacenados) {
    echo '<p>No hay lotes registrados dentro de refrigeradores.</p>';
} else {
    echo '<table class="tabla"><tr><th>Refrigerador (nodo)</th><th>Lote</th><th>Vacuna</th>'
       . '<th class="num">Cantidad</th><th>Ingreso</th><th>Acción</th></tr>';
    foreach ($almacenados as $a) {
        echo '<tr><td>' . e($a['nodo']) . '<br><small>' . e($a['institucion']) . '</small></td>'
           . '<td>' . e($a['codigo_lote']) . '</td><td>' . e($a['vacuna']) . '</td>'
           . '<td class="num">' . (int)$a['cantidad'] . '</td><td>' . e($a['fecha_ingreso']) . '</td><td>';
        if ($puedeGestionar) {
            echo '<form method="post" style="display:inline">
                    <input type="hidden" name="acc" value="retirar">
                    <input type="hidden" name="csrf" value="' . csrf_token() . '">
                    <input type="hidden" name="id_almacenamiento" value="' . (int)$a['id_almacenamiento'] . '">
                    <button class="boton mini peligro" type="submit">Registrar salida</button>
                  </form>';
        } else { echo '—'; }
        echo '</td></tr>';
    }
    echo '</table>';
}
echo '</div>';

echo '<div class="panel"><h2>Asignar lote a refrigerador</h2>';
if ($puedeGestionar) {
    echo '<form method="post">
      <input type="hidden" name="acc" value="almacenamiento">
      <input type="hidden" name="csrf" value="' . csrf_token() . '">
      <div class="campo"><label>Lote</label>
        <select name="id_lote">';
    foreach ($lotes as $l) {
        echo '<option value="' . (int)$l['id_lote'] . '">' . e($l['codigo_lote'] . ' · ' . $l['vacuna']) . '</option>';
    }
    echo '  </select></div>
      <div class="campo"><label>Refrigerador (nodo)</label>
        <select name="id_dispositivo">';
    foreach ($dispositivos as $d) {
        echo '<option value="' . (int)$d['id_dispositivo'] . '">' . e($d['codigo'] . ' · ' . $d['institucion']) . '</option>';
    }
    echo '  </select></div>
      <div class="campo"><label>Cantidad (dosis)</label>
        <input type="number" min="1" name="cantidad" required></div>
      <div class="campo"><label>Fecha de ingreso</label>
        <input type="datetime-local" name="fecha_ingreso" value="' . date('Y-m-d\TH:i') . '"></div>
      <button class="boton verde" type="submit">Registrar almacenamiento</button>
    </form>';
} else {
    echo '<p>El rol actual no permite gestionar almacenamiento. Solicite el permiso <em>gestionar_vacunas</em>.</p>';
}
echo '</div></div>';

$movimientos = q("
    SELECT m.fecha_hora, m.tipo_movimiento, m.cantidad, m.observacion,
           lv.codigo_lote, v.nombre AS vacuna, u.nombre AS usuario
    FROM movimiento_vacuna m
    JOIN lote_vacuna lv ON lv.id_lote = m.id_lote
    JOIN vacuna v ON v.id_vacuna = lv.id_vacuna
    JOIN usuario u ON u.id_usuario = m.id_usuario
    ORDER BY m.fecha_hora DESC LIMIT 15")->fetchAll();

echo '<div class="panel"><h2>Movimientos recientes</h2>';
if (!$movimientos) { echo '<p>Sin movimientos.</p>'; }
else {
    echo '<table class="tabla"><tr><th>Fecha</th><th>Lote</th><th>Vacuna</th><th>Tipo</th>'
       . '<th class="num">Cantidad</th><th>Usuario</th><th>Observación</th></tr>';
    foreach ($movimientos as $m) {
        echo '<tr><td>' . e($m['fecha_hora']) . '</td><td>' . e($m['codigo_lote']) . '</td>'
           . '<td>' . e($m['vacuna']) . '</td><td><span class="badge">' . e($m['tipo_movimiento']) . '</span></td>'
           . '<td class="num">' . (int)$m['cantidad'] . '</td>'
           . '<td>' . e($m['usuario']) . '</td><td>' . e($m['observacion']) . '</td></tr>';
    }
    echo '</table>';
}
echo '</div>';