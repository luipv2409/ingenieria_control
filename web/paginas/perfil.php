<?php
/**
 * Mi perfil: datos de la sesión, roles/permisos y cambio de contraseña propia
 */
require_once __DIR__ . '/../config/auth.php';

$idUsuario = (int)$_SESSION['usuario_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acc'] ?? '') === 'cambiar_clave') {
    if (!csrf_verificar($_POST['csrf'] ?? '')) {
        $error = 'Sesión expirada, intente nuevamente.';
    } else {
        $actual = $_POST['password_actual'] ?? '';
        $nueva  = $_POST['password_nueva'] ?? '';
        $repite = $_POST['password_repite'] ?? '';
        $u = q("SELECT password_hash FROM usuario WHERE id_usuario = ?", [$idUsuario])->fetch();
        if (!$u || !password_verify($actual, $u['password_hash'])) {
            $error = 'La contraseña actual no es correcta.';
        } elseif (strlen($nueva) < 6) {
            $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
        } elseif ($nueva !== $repite) {
            $error = 'La confirmación no coincide con la nueva contraseña.';
        } else {
            q("UPDATE usuario SET password_hash = ? WHERE id_usuario = ?",
              [password_hash($nueva, PASSWORD_DEFAULT), $idUsuario]);
            auditoria('PASSWORD_PROPIO', 'Perfil');
            $_SESSION['mensaje'] = 'Contraseña actualizada correctamente.';
            header('Location: index.php?p=perfil');
            exit;
        }
    }
}

$u = q("SELECT nombre, apellido, carnet, correo, telefono, fecha_registro
        FROM usuario WHERE id_usuario = ?", [$idUsuario])->fetch();
$ultimosAccesos = q("SELECT accion, modulo, fecha_hora, ip_origen
                     FROM auditoria_usuario WHERE id_usuario = ?
                     ORDER BY id_auditoria DESC LIMIT 8", [$idUsuario])->fetchAll();

echo (!empty($error) ? '<div class="alerta error">' . e($error) . '</div>' : '');

echo '<div class="dos-columnas">';
echo '<div class="panel"><h2>Datos de la cuenta</h2>
      <table class="tabla">
        <tr><th>Nombre completo</th><td>' . e(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')) . '</td></tr>
        <tr><th>Carnet</th><td>' . e($u['carnet'] ?? '—') . '</td></tr>
        <tr><th>Correo</th><td>' . e($u['correo'] ?? '—') . '</td></tr>
        <tr><th>Teléfono</th><td>' . e($u['telefono'] ?? '—') . '</td></tr>
        <tr><th>Registro</th><td>' . e($u['fecha_registro'] ?? '—') . '</td></tr>
        <tr><th>Roles</th><td><span class="badge">' . e(implode(', ', $_SESSION['roles'] ?? [])) . '</span></td></tr>
      </table>
      <h2 style="margin-top:16px">Permisos asignados</h2>
      <p>' . ($_SESSION['permisos']
            ? implode(' ', array_map(fn($p) => '<span class="badge">' . e($p) . '</span>', $_SESSION['permisos']))
            : 'Sin permisos asignados.') . '</p>
      </div>';

echo '<div class="panel"><h2>Cambiar mi contraseña</h2>
      <form method="post">
        <input type="hidden" name="acc" value="cambiar_clave">
        <input type="hidden" name="csrf" value="' . csrf_token() . '">
        <div class="campo"><label>Contraseña actual</label>
          <input type="password" name="password_actual" required></div>
        <div class="campo"><label>Nueva contraseña</label>
          <input type="password" name="password_nueva" required minlength="6"></div>
        <div class="campo"><label>Repetir nueva contraseña</label>
          <input type="password" name="password_repite" required minlength="6"></div>
        <button class="boton verde" type="submit">Actualizar contraseña</button>
      </form>
      </div>';
echo '</div>';

echo '<div class="panel"><h2>Mi actividad reciente</h2>';
if (!$ultimosAccesos) {
    echo '<p>Sin actividad registrada.</p>';
} else {
    echo '<table class="tabla"><tr><th>Fecha y hora</th><th>Acción</th><th>Módulo</th><th>IP origen</th></tr>';
    foreach ($ultimosAccesos as $a) {
        echo '<tr><td>' . e($a['fecha_hora']) . '</td><td><span class="badge">' . e($a['accion']) . '</span></td>'
           . '<td>' . e($a['modulo']) . '</td><td>' . e($a['ip_origen']) . '</td></tr>';
    }
    echo '</table>';
}
echo '</div>';
