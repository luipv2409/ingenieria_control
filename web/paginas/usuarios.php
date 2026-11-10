<?php
/**
 * Gestión de usuarios y roles (solo administradores)
 */
$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verificar($_POST['csrf'] ?? '')) { $error = 'Sesión expirada.'; }

    elseif (($_POST['acc'] ?? '') === 'nuevo_usuario') {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $pass = $_POST['password'] ?? '';
        $roles = array_map('intval', (array)($_POST['roles'] ?? []));
        if ($nombre === '' || $apellido === '' || $pass === '' || $correo === '') {
            $error = 'Complete nombre, apellidos, correo y contraseña.';
        } elseif (strlen($pass) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } else {
            try {
                q("INSERT INTO usuario (nombre, apellido, carnet, correo, password_hash, telefono, estado)
                   VALUES (?,?,?,?,?,?,1)",
                  [$nombre, $apellido, trim($_POST['carnet'] ?? '') ?: null, $correo,
                   password_hash($pass, PASSWORD_DEFAULT), trim($_POST['telefono'] ?? '') ?: null]);
                $uid = (int)db()->lastInsertId();
                foreach ($roles as $rid) {
                    q("INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (?,?)", [$uid, $rid]);
                }
                auditoria('USUARIO_CREADO', 'Usuarios');
                $_SESSION['mensaje'] = 'Usuario creado correctamente.';
            } catch (PDOException $ex) {
                $error = 'No se pudo crear (¿correo o carnet duplicado?): ' . $ex->getMessage();
            }
        }
    }
    elseif (($_POST['acc'] ?? '') === 'estado_usuario') {
        $uid = (int)$_POST['id_usuario'];
        $est = (int)$_POST['estado'];
        if ($uid === (int)$_SESSION['usuario_id']) { $error = 'No puede desactivar su propia cuenta.'; }
        else {
            q("UPDATE usuario SET estado = ? WHERE id_usuario = ?", [$est, $uid]);
            auditoria('USUARIO_ESTADO', 'Usuarios');
            $_SESSION['mensaje'] = 'Estado del usuario actualizado.';
        }
    }
    elseif (($_POST['acc'] ?? '') === 'reset_password') {
        $uid = (int)$_POST['id_usuario'];
        $pass = $_POST['password'] ?? '';
        if (strlen($pass) < 6) { $error = 'La contraseña debe tener al menos 6 caracteres.'; }
        else {
            q("UPDATE usuario SET password_hash = ? WHERE id_usuario = ?",
              [password_hash($pass, PASSWORD_DEFAULT), $uid]);
            auditoria('PASSWORD_RESET', 'Usuarios');
            $_SESSION['mensaje'] = 'Contraseña restablecida.';
        }
    }
}
if (!empty($_SESSION['mensaje'])) { $exito = $_SESSION['mensaje']; unset($_SESSION['mensaje']); }
if (!empty($_SESSION['error'])) { $error = $_SESSION['error']; unset($_SESSION['error']); }

$roles = q("SELECT id_rol, nombre, descripcion FROM rol ORDER BY id_rol")->fetchAll();
$usuarios = q("
    SELECT u.id_usuario, u.nombre, u.apellido, u.carnet, u.correo, u.telefono, u.estado, u.fecha_registro,
           (SELECT GROUP_CONCAT(r.nombre ORDER BY r.nombre SEPARATOR ', ')
              FROM usuario_rol ur JOIN rol r ON r.id_rol = ur.id_rol WHERE ur.id_usuario = u.id_usuario) AS roles
    FROM usuario u ORDER BY u.nombre, u.apellido")->fetchAll();

echo (!empty($exito) ? '<div class="alerta ok">' . e($exito) . '</div>' : '');
echo (!empty($error) ? '<div class="alerta error">' . e($error) . '</div>' : '');

echo '<div class="panel"><h2>Crear nuevo usuario</h2>
  <form method="post">
    <input type="hidden" name="acc" value="nuevo_usuario">
    <input type="hidden" name="csrf" value="' . csrf_token() . '">
    <div class="fila">
      <div class="campo"><label>Nombres</label><input name="nombre" required></div>
      <div class="campo"><label>Apellidos</label><input name="apellido" required></div>
      <div class="campo"><label>Carnet</label><input name="carnet"></div>
    </div>
    <div class="fila">
      <div class="campo"><label>Correo</label><input type="email" name="correo" required></div>
      <div class="campo"><label>Teléfono</label><input name="telefono"></div>
      <div class="campo"><label>Contraseña</label><input type="password" name="password" required minlength="6"></div>
    </div>
    <div class="campo"><label>Roles</label>
      <select name="roles[]" multiple size="4">';
foreach ($roles as $r) {
    echo '<option value="' . (int)$r['id_rol'] . '">' . e($r['nombre']) . ' — ' . e($r['descripcion']) . '</option>';
}
echo '  </select></div>
    <button class="boton" type="submit">Crear usuario</button>
  </form></div>';

echo '<div class="panel"><h2>Usuarios del sistema</h2>';
echo '<table class="tabla"><tr><th>Usuario</th><th>Carnet</th><th>Correo</th><th>Teléfono</th>'
   . '<th>Roles</th><th>Registro</th><th>Estado</th><th>Acciones</th></tr>';
foreach ($usuarios as $u) {
    $mio = (int)$u['id_usuario'] === (int)$_SESSION['usuario_id'];
    echo '<tr>
            <td><strong>' . e($u['nombre'] . ' ' . $u['apellido']) . '</strong>' . ($mio ? ' <span class="badge">usted</span>' : '') . '</td>
            <td>' . e($u['carnet']) . '</td>
            <td>' . e($u['correo']) . '</td>
            <td>' . e($u['telefono']) . '</td>
            <td><small>' . e($u['roles']) . '</small></td>
            <td>' . e($u['fecha_registro']) . '</td>
            <td><span class="etiqueta-estado ' . ($u['estado'] ? 'st-verde' : 'st-gris') . '">'
        . ($u['estado'] ? 'Activo' : 'Inactivo') . '</span></td>
            <td>
              <button class="boton mini secundario" onclick="document.getElementById(\'res-' . (int)$u['id_usuario'] . '\').style.display=\'block\'">Cambiar clave</button>
              ' . ($mio ? '' : '<form method="post" style="display:inline">
                  <input type="hidden" name="acc" value="estado_usuario">
                  <input type="hidden" name="csrf" value="' . csrf_token() . '">
                  <input type="hidden" name="id_usuario" value="' . (int)$u['id_usuario'] . '">
                  <input type="hidden" name="estado" value="' . ($u['estado'] ? 0 : 1) . '">
                  <button class="boton mini ' . ($u['estado'] ? 'peligro' : 'verde') . '">' . ($u['estado'] ? 'Desactivar' : 'Activar') . '</button>
                </form>') . '
            </td>
          </tr>
          <tr id="res-' . (int)$u['id_usuario'] . '" style="display:none;background:#f7fafd">
            <td colspan="8">
              <form method="post" style="display:flex;gap:8px;align-items:flex-end">
                <input type="hidden" name="acc" value="reset_password">
                <input type="hidden" name="csrf" value="' . csrf_token() . '">
                <input type="hidden" name="id_usuario" value="' . (int)$u['id_usuario'] . '">
                <div class="campo"><label>Nueva contraseña de ' . e($u['nombre']) . '</label>
                  <input type="password" name="password" required minlength="6"></div>
                <button class="boton mini verde" type="submit">Guardar</button>
              </form>
            </td>
          </tr>';
}
echo '</table></div>';