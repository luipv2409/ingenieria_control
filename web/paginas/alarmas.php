<?php
/**
 * Gestión de alarmas y acciones de respuesta
 */
$error = '';
$exito = '';

// --- Responder una alarma ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acc'] ?? '') === 'responder') {
    if (!csrf_verificar($_POST['csrf'] ?? '')) {
        $error = 'Sesión expirada, intente nuevamente.';
    } elseif (!tiene_permiso('responder_alarmas')) {
        $error = 'No tiene permiso para responder alarmas.';
    } else {
        $idAlarma   = (int)$_POST['id_alarma'];
        $idProtocolo= (int)$_POST['id_protocolo'];
        $idEstado   = (int)$_POST['id_estado'];
        $obs        = trim($_POST['observacion'] ?? '');
        $al = q("SELECT * FROM alarma WHERE id_alarma = ?", [$idAlarma])->fetch();
        if (!$al) {
            $error = 'La alarma seleccionada no existe.';
        } elseif (!$obs) {
            $error = 'Debe escribir una observación de la acción realizada.';
        } else {
            q("INSERT INTO accion_respuesta (id_alarma, id_usuario, id_protocolo, observacion)
               VALUES (?,?,?,?)", [$idAlarma, $_SESSION['usuario_id'], $idProtocolo, $obs]);
            q("UPDATE alarma SET id_estado_alarma = ? WHERE id_alarma = ?", [$idEstado, $idAlarma]);
            auditoria('RESPUESTA_REGISTRADA', 'Alarmas');
            $exito = 'Respuesta registrada correctamente para la alarma #' . $idAlarma . '.';
            $_SESSION['mensaje'] = $exito;
            header('Location: index.php?p=alarmas');
            exit;
        }
    }
}

if (!empty($_SESSION['mensaje'])) {
    $exito = $_SESSION['mensaje']; unset($_SESSION['mensaje']);
}
if (!empty($_SESSION['error'])) {
    $error = $_SESSION['error']; unset($_SESSION['error']);
}

$fEstado = $_GET['estado'] ?? '';
$where = '';
$params = [];
if ($fEstado !== '') {
    $where = 'WHERE e.nombre = ?';
    $params[] = $fEstado;
}
$alarmas = q("
    SELECT a.id_alarma, a.fecha_hora, a.nivel, a.descripcion,
           t.id_tipo_alarma, t.nombre AS tipo, e.id_estado_alarma, e.nombre AS estado,
           d.codigo AS dispositivo, i.nombre AS institucion,
           (SELECT COUNT(*) FROM accion_respuesta ar WHERE ar.id_alarma = a.id_alarma) AS acciones
    FROM alarma a
    JOIN tipo_alarma t   ON t.id_tipo_alarma = a.id_tipo_alarma
    JOIN estado_alarma e ON e.id_estado_alarma = a.id_estado_alarma
    JOIN dispositivo d   ON d.id_dispositivo = a.id_dispositivo
    JOIN institucion_salud i ON i.id_institucion = d.id_institucion
    {$where}
    ORDER BY a.fecha_hora DESC LIMIT 200", $params)->fetchAll();

$protocolos = q("SELECT id_protocolo, id_tipo_alarma, nombre FROM protocolo_respuesta ORDER BY nombre")->fetchAll();
$porTipo = [];
foreach ($protocolos as $pr) {
    $porTipo[$pr['id_tipo_alarma']][] = $pr;
}
$estados = q("SELECT id_estado_alarma, nombre FROM estado_alarma ORDER BY id_estado_alarma")->fetchAll();

echo (!empty($exito) ? '<div class="alerta ok">' . e($exito) . '</div>' : '');
echo (!empty($error) ? '<div class="alerta error">' . e($error) . '</div>' : '');

echo '<div class="panel">
  <form method="get" action="index.php">
    <input type="hidden" name="p" value="alarmas">
    <div class="fila">
      <div class="campo"><label>Filtrar por estado</label>
        <select name="estado">
          <option value="">Todos los estados</option>';
foreach ($estados as $es) {
    echo '<option value="' . e($es['nombre']) . '"' . ($fEstado === $es['nombre'] ? ' selected' : '') . '>'
        . e($es['nombre']) . '</option>';
}
echo '  </select></div>
      <div class="campo" style="justify-content:flex-end"><label>&nbsp;</label>
        <button class="boton" type="submit">Filtrar</button></div>
    </div>
  </form>
</div>';

echo '<div class="panel"><h2>Alarmas del sistema</h2>';
if (!$alarmas) {
    echo '<p>No hay alarmas registradas.</p>';
} else {
    echo '<table class="tabla"><tr>
            <th>#</th><th>Fecha y hora</th><th>Tipo</th><th>Nivel</th><th>Dispositivo</th>
            <th>Institución</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr>';
    foreach ($alarmas as $a) {
        $nivelCls = $a['nivel'] === 'CRITICA' ? 'st-rojo' : 'st-ambar';
        $estCls = $a['estado'] === 'RESUELTA' ? 'st-verde'
                : ($a['estado'] === 'FALSA_ALARMA' ? 'st-gris' : ($a['estado'] === 'ACTIVA' ? 'st-rojo' : 'st-ambar'));
        $puedeResponder = tiene_permiso('responder_alarmas')
            && !in_array($a['estado'], ['RESUELTA', 'FALSA_ALARMA'], true);
        echo '<tr>
                <td>' . (int)$a['id_alarma'] . '</td>
                <td>' . e($a['fecha_hora']) . '</td>
                <td>' . e(str_replace('_', ' ', $a['tipo'])) . '</td>
                <td><span class="etiqueta-estado ' . $nivelCls . '">' . e($a['nivel']) . '</span></td>
                <td>' . e($a['dispositivo']) . '</td>
                <td>' . e($a['institucion']) . '</td>
                <td>' . e($a['descripcion']) . '</td>
                <td><span class="etiqueta-estado ' . $estCls . '">' . e($a['estado']) . '</span></td>
                <td>';
        if ($a['acciones'] > 0) {
            echo '<span class="badge">' . (int)$a['acciones'] . ' acción/es</span> ';
        }
        if ($puedeResponder) {
            echo '<button class="boton mini" onclick="document.getElementById(\'resp-' . (int)$a['id_alarma'] . '\').style.display=\'block\'">Atender</button>';
        } else {
            echo '<span style="color:#b9c4d0">—</span>';
        }
        echo '</td></tr>';

        if ($puedeResponder) {
            echo '<tr id="resp-' . (int)$a['id_alarma'] . '" style="display:none;background:#f7fafd">
                    <td colspan="9"><form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                      <input type="hidden" name="p" value="alarmas">
                      <input type="hidden" name="acc" value="responder">
                      <input type="hidden" name="csrf" value="' . csrf_token() . '">
                      <input type="hidden" name="id_alarma" value="' . (int)$a['id_alarma'] . '">
                      <div class="campo" style="min-width:220px"><label>Protocolo aplicado</label>
                        <select name="id_protocolo">';
            foreach ($porTipo[$a['id_tipo_alarma']] ?? [] as $pr) {
                echo '<option value="' . (int)$pr['id_protocolo'] . '">' . e($pr['nombre']) . '</option>';
            }
            if (empty($porTipo[$a['id_tipo_alarma']])) {
                echo '<option value="">(sin protocolo asignado)</option>';
            }
            echo '      </select></div>
                      <div class="campo" style="min-width:140px"><label>Nuevo estado</label>
                        <select name="id_estado">';
            foreach ($estados as $es) {
                if (!in_array($es['nombre'], ['ACTIVA', 'NOTIFICADA'], true)) {
                    echo '<option value="' . (int)$es['id_estado_alarma'] . '">' . e($es['nombre']) . '</option>';
                }
            }
            echo '      </select></div>
                      <div class="campo" style="flex:1;min-width:260px"><label>Observación de la acción</label>
                        <input type="text" name="observacion" required placeholder="Registre la acción aplicada">
                      </div>
                      <button class="boton verde" type="submit">Registrar</button>
                    </form></td></tr>';
        }
    }
    echo '</table>';
}
echo '<p class="pie-mapa">Las alarmas se generan automáticamente por el nodo cuando la temperatura sale del rango '
    . 'configurado (2 °C–8 °C) durante el tiempo de alerta (por defecto 5 minutos).</p></div>';