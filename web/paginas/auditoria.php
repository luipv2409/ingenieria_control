<?php
/**
 * Auditoría del sistema
 */
$modulo = trim($_GET['modulo'] ?? '');
$usuario = (int)($_GET['usuario'] ?? 0);

$where = 'WHERE 1';
$params = [];
if ($modulo !== '') {
    $where .= ' AND a.modulo = ?'; $params[] = $modulo;
}
if ($usuario > 0) {
    $where .= ' AND a.id_usuario = ?'; $params[] = $usuario;
}

$registros = q("
    SELECT a.*, u.nombre, u.apellido, r.nombre AS rol
    FROM auditoria_usuario a
    LEFT JOIN usuario u ON u.id_usuario = a.id_usuario
    LEFT JOIN usuario_rol ur ON ur.id_usuario = a.id_usuario
    LEFT JOIN rol r ON r.id_rol = ur.id_rol
    {$where}
    ORDER BY a.id_auditoria DESC LIMIT 300", $params)->fetchAll();

$modulos = q("SELECT DISTINCT modulo FROM auditoria_usuario WHERE modulo IS NOT NULL ORDER BY modulo")->fetchAll(PDO::FETCH_COLUMN);
$usuarios = q("SELECT u.id_usuario, u.nombre, u.apellido FROM usuario u
               WHERE u.id_usuario IN (SELECT id_usuario FROM auditoria_usuario WHERE id_usuario IS NOT NULL)
               ORDER BY u.nombre")->fetchAll();

echo '<div class="panel">
  <form method="get" action="index.php">
    <input type="hidden" name="p" value="auditoria">
    <div class="fila">
      <div class="campo"><label>Módulo</label>
        <select name="modulo"><option value="">Todos</option>';
foreach ($modulos as $m) {
    echo '<option value="' . e($m) . '"' . ($modulo === $m ? ' selected' : '') . '>' . e($m) . '</option>';
}
echo '</select></div>
      <div class="campo"><label>Usuario</label>
        <select name="usuario"><option value="0">Todos</option>';
foreach ($usuarios as $u) {
    echo '<option value="' . (int)$u['id_usuario'] . '"' . ($usuario === (int)$u['id_usuario'] ? ' selected' : '') . '>'
        . e($u['nombre'] . ' ' . $u['apellido']) . '</option>';
}
echo '</select></div>
      <div class="campo" style="justify-content:flex-end"><label>&nbsp;</label><button class="boton" type="submit">Filtrar</button></div>
    </div>
  </form>
</div>';

echo '<div class="panel"><h2>Registro de auditoría (' . count($registros) . ' eventos recientes)</h2>';
if (!$registros) {
    echo '<p>Sin eventos para los filtros.</p>';
} else {
    echo '<table class="tabla"><tr><th>Fecha y hora</th><th>Usuario</th><th>Rol</th><th>Acción</th>'
       . '<th>Módulo</th><th>IP origen</th></tr>';
    foreach ($registros as $a) {
        echo '<tr><td>' . e($a['fecha_hora']) . '</td>'
           . '<td>' . e($a['nombre'] ? $a['nombre'] . ' ' . $a['apellido'] : '(sistema)') . '</td>'
           . '<td>' . e($a['rol']) . '</td>'
           . '<td><span class="badge">' . e($a['accion']) . '</span></td>'
           . '<td>' . e($a['modulo']) . '</td>'
           . '<td>' . e($a['ip_origen']) . '</td></tr>';
    }
    echo '</table>';
}
echo '</div>';