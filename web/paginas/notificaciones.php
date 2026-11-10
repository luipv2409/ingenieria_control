<?php
/**
 * Notificaciones al usuario según su rol y permiso (RF-08)
 * Permite marcar como leídas las notificaciones generadas por las alarmas.
 */
require_once __DIR__ . '/../config/auth.php';

$idUsuario = (int)$_SESSION['usuario_id'];

// --- Marcar una notificación o todas como leídas ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acc'] ?? '') === 'leer') {
    if (!csrf_verificar($_POST['csrf'] ?? '')) {
        $_SESSION['error'] = 'Sesión expirada, intente nuevamente.';
    } else {
        $idNotif = (int)($_POST['id_notificacion'] ?? 0);
        if ($idNotif > 0) {
            q("UPDATE notificacion SET estado = 'LEIDA'
               WHERE id_notificacion = ? AND id_usuario = ?", [$idNotif, $idUsuario]);
            auditoria('NOTIFICACION_LEIDA', 'Notificaciones');
            $_SESSION['mensaje'] = 'Notificación marcada como leída.';
        } else {
            q("UPDATE notificacion SET estado = 'LEIDA'
               WHERE id_usuario = ? AND estado <> 'LEIDA'", [$idUsuario]);
            auditoria('NOTIFICACIONES_LEIDAS', 'Notificaciones');
            $_SESSION['mensaje'] = 'Todas las notificaciones fueron marcadas como leídas.';
        }
    }
    header('Location: index.php?p=notificaciones');
    exit;
}

$fEstado = $_GET['estado'] ?? '';
$where = 'WHERE n.id_usuario = ?';
$params = [$idUsuario];
if ($fEstado !== '') {
    $where .= ' AND n.estado = ?';
    $params[] = $fEstado;
}

$notificaciones = q("
    SELECT n.id_notificacion, n.fecha_envio, n.medio, n.estado,
           a.id_alarma, a.fecha_hora, a.nivel, a.descripcion,
           t.nombre AS tipo, d.codigo AS dispositivo, i.nombre AS institucion
    FROM notificacion n
    JOIN alarma a        ON a.id_alarma = n.id_alarma
    JOIN tipo_alarma t   ON t.id_tipo_alarma = a.id_tipo_alarma
    JOIN dispositivo d   ON d.id_dispositivo = a.id_dispositivo
    JOIN institucion_salud i ON i.id_institucion = d.id_institucion
    {$where}
    ORDER BY n.fecha_envio DESC, n.id_notificacion DESC
    LIMIT 300", $params)->fetchAll();

$pendientes = (int)valor("SELECT COUNT(*) FROM notificacion WHERE id_usuario = ? AND estado <> 'LEIDA'", [$idUsuario]);

echo '<div class="panel">
  <form method="get" action="index.php">
    <input type="hidden" name="p" value="notificaciones">
    <div class="fila">
      <div class="campo"><label>Estado</label>
        <select name="estado">
          <option value="">Todas</option>
          <option value="ENTREGADA"' . ($fEstado === 'ENTREGADA' ? ' selected' : '') . '>No leídas</option>
          <option value="LEIDA"' . ($fEstado === 'LEIDA' ? ' selected' : '') . '>Leídas</option>
        </select></div>
      <div class="campo" style="justify-content:flex-end"><label>&nbsp;</label>
        <button class="boton" type="submit">Filtrar</button></div>
    </div>
  </form>
</div>';

echo '<div class="panel">
  <h2>Mis notificaciones · ' . $pendientes . ' sin leer de ' . count($notificaciones) . ' mostradas</h2>';
if (!$notificaciones) {
    echo '<p>No tiene notificaciones con este filtro. Las notificaciones se generan automáticamente '
       . 'cuando un nodo registra una alarma.</p>';
} else {
    echo '<table class="tabla"><tr><th>Fecha de envío</th><th>Alarma</th><th>Tipo</th><th>Nivel</th>'
       . '<th>Dispositivo</th><th>Institución</th><th>Medio</th><th>Estado</th><th>Acción</th></tr>';
    foreach ($notificaciones as $n) {
        $nivelCls = $n['nivel'] === 'CRITICA' ? 'st-rojo' : 'st-ambar';
        $esPendiente = $n['estado'] !== 'LEIDA';
        $estCls = $esPendiente ? 'st-ambar' : 'st-gris';
        echo '<tr' . ($esPendiente ? ' style="background:#fffdf5"' : '') . '>
                <td>' . e($n['fecha_envio']) . '</td>
                <td><a href="index.php?p=alarmas">#' . (int)$n['id_alarma'] . '</a><br><small>' . e($n['fecha_hora']) . '</small></td>
                <td>' . e(str_replace('_', ' ', $n['tipo'])) . '</td>
                <td><span class="etiqueta-estado ' . $nivelCls . '">' . e($n['nivel']) . '</span></td>
                <td>' . e($n['dispositivo']) . '</td>
                <td>' . e($n['institucion']) . '</td>
                <td>' . e($n['medio']) . '</td>
                <td><span class="etiqueta-estado ' . $estCls . '">' . e($n['estado']) . '</span></td>
                <td>';
        if ($esPendiente) {
            echo '<form method="post" style="display:inline">
                    <input type="hidden" name="acc" value="leer">
                    <input type="hidden" name="csrf" value="' . csrf_token() . '">
                    <input type="hidden" name="id_notificacion" value="' . (int)$n['id_notificacion'] . '">
                    <button class="boton mini secundario" type="submit">Marcar leída</button>
                  </form>';
        } else {
            echo '—';
        }
        echo '</td></tr>';
    }
    echo '</table>';
    if ($pendientes > 0) {
        echo '<div class="botones">
                <form method="post">
                  <input type="hidden" name="acc" value="leer">
                  <input type="hidden" name="csrf" value="' . csrf_token() . '">
                  <button class="boton verde" type="submit">Marcar todas como leídas</button>
                </form>
              </div>';
    }
}
echo '<p class="pie-mapa">Las notificaciones se dirigen a los usuarios con el permiso '
   . '<em>responder_alarmas</em> según su rol (RF-08). El canal «PANEL» corresponde al tablero web.</p></div>';
