<?php
/**
 * Enrutador del panel PUNATA VACUNA SEGURA
 * index.php?p=<pagina>
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/mailer.php';

$p = $_GET['p'] ?? (usuario_logueado() ? 'dashboard' : 'login');

// --- Salida ---
if ($p === 'logout') {
    logout();
    header('Location: index.php?p=login');
    exit;
}

// --- Recuperación de contraseña ---
if ($p === 'recuperar') {
    if (usuario_logueado()) {
        header('Location: index.php?p=dashboard');
        exit;
    }
    $mensaje = '';
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verificar($_POST['csrf'] ?? '')) {
            $error = 'Sesión expirada, intente nuevamente.';
        } else {
            $correo = trim($_POST['correo'] ?? '');
            $u = q("SELECT id_usuario, nombre, apellido, correo, estado FROM usuario WHERE correo = ? LIMIT 1", [$correo])->fetch();
            if ($u && (int)$u['estado'] === 1) {
                // Generar contraseña provisional segura (ej: Vacuna*784932)
                $tempPass = 'Vacuna*' . random_int(100000, 999999);
                $hash = password_hash($tempPass, PASSWORD_DEFAULT);
                q("UPDATE usuario SET password_hash = ? WHERE id_usuario = ?", [$hash, (int)$u['id_usuario']]);

                $nombreCompleto = $u['nombre'] . ' ' . $u['apellido'];
                $asunto = "Restablecimiento de Contraseña - Punata Vacuna Segura";
                $cuerpo = '
                <div style="font-family:sans-serif; max-width:500px; margin:0 auto; padding:20px; border:1px solid #e2e8f0; border-radius:8px;">
                  <h2 style="color:#1e3a8a; margin-top:0;">❄ Punata Vacuna Segura</h2>
                  <p>Hola <strong>' . htmlspecialchars($nombreCompleto) . '</strong>,</p>
                  <p>Has solicitado restablecer tu contraseña para ingresar al panel de monitoreo de cadena de frío.</p>
                  <div style="background:#eff6ff; border-left:4px solid #3b82f6; padding:12px; margin:16px 0;">
                    <div style="font-size:12px; color:#64748b;">Tu nueva contraseña provisional es:</div>
                    <div style="font-size:18px; font-weight:bold; color:#1e40af; margin-top:4px; letter-spacing:1px;">' . htmlspecialchars($tempPass) . '</div>
                  </div>
                  <p style="font-size:13px; color:#475569;">
                    Te recomendamos iniciar sesión con esta contraseña y cambiarla inmediatamente en la sección <strong>Mi Perfil</strong>.
                  </p>
                  <p style="text-align:center; margin-top:24px;">
                    <a href="http://localhost:8090/index.php?p=login" style="background:#2563eb; color:white; padding:10px 20px; text-decoration:none; border-radius:6px; font-weight:bold; display:inline-block;">Ir al Inicio de Sesión</a>
                  </p>
                </div>';

                if (enviar_email_smtp($u['correo'], $nombreCompleto, $asunto, $cuerpo)) {
                    auditoria('RECUPERAR_PASSWORD_EMAIL', 'Autenticacion', (int)$u['id_usuario']);
                    $mensaje = 'Se ha enviado una nueva contraseña provisional a su correo (' . htmlspecialchars($u['correo']) . '). Revise su bandeja de entrada.';
                } else {
                    $error = 'No se pudo enviar el correo de recuperación. Verifique la conexión del servidor SMTP.';
                }
            } else {
                // Mensaje genérico para no revelar existencia de correos
                $mensaje = 'Si el correo ingresado se encuentra registrado y activo, recibirá un mensaje con las instrucciones de recuperación.';
            }
        }
    }
    require __DIR__ . '/paginas/recuperar.php';
    exit;
}

// --- Login ---
if ($p === 'login') {
    if (usuario_logueado()) {
        header('Location: index.php?p=dashboard');
        exit;
    }
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verificar($_POST['csrf'] ?? '')) {
            $error = 'Sesión expirada, intente nuevamente.';
        } elseif (login(trim($_POST['correo'] ?? ''), $_POST['password'] ?? '')) {
            header('Location: index.php?p=dashboard');
            exit;
        } else {
            $error = 'Correo o contraseña incorrectos, o usuario inactivo.';
            auditoria('LOGIN_FALLIDO', 'Autenticacion');
        }
    }
    require __DIR__ . '/paginas/login.php';
    exit;
}

// --- Exportaciones: respuesta limpia, antes de la cabecera del panel ---
if ($p === 'reportes' && ($_GET['exp'] ?? '') === 'csv') {
    requiere_login();
    if (!tiene_permiso('ver_reportes')) {
        http_response_code(403);
        echo 'Sin permiso para exportar.';
        exit;
    }
    require __DIR__ . '/paginas/reportes_csv.php';
    exit;
}

// --- Resto de páginas: requieren sesión ---
requiere_login();

$paginas = [
    'dashboard'      => ['perm' => 'ver_panel',           'titulo' => 'Panel general'],
    'lecturas'       => ['perm' => 'ver_lecturas',        'titulo' => 'Lecturas de sensores'],
    'alarmas'        => ['perm' => 'ver_alarmas',         'titulo' => 'Alarmas y respuesta'],
    'notificaciones' => ['perm' => 'ver_alarmas',         'titulo' => 'Notificaciones'],
    'dispositivos'   => ['perm' => 'ver_lecturas',        'titulo' => 'Dispositivos y sensores'],
    'instituciones'  => ['perm' => 'ver_panel',           'titulo' => 'Instituciones y configuración'],
    'vacunas'        => ['perm' => 'ver_panel',           'titulo' => 'Vacunas y lotes'],
    'reportes'       => ['perm' => 'ver_reportes',        'titulo' => 'Reportes e indicadores'],
    'usuarios'       => ['perm' => 'gestionar_usuarios',  'titulo' => 'Usuarios y roles'],
    'auditoria'      => ['perm' => 'auditar',             'titulo' => 'Auditoría del sistema'],
    'perfil'         => ['perm' => '',                    'titulo' => 'Mi perfil'],
];

if (!isset($paginas[$p])) {
    // Nota: no se fija el código HTTP 404 porque Apache (XAMPP) reemplaza el
    // cuerpo por su ErrorDocument; se renderiza una página amigable propia.
    $titulo = 'Página no encontrada';
    $pagina = '';
    if (!usuario_logueado()) {
        header('Location: index.php?p=login');
        exit;
    }
    require __DIR__ . '/parciales/cabecera.php';
    require __DIR__ . '/paginas/404.php';
    require __DIR__ . '/parciales/pie.php';
    exit;
}

$conf = $paginas[$p];
$titulo = $conf['titulo'];
$pagina = $p;

require_once __DIR__ . '/config/auth.php';
if ($conf['perm'] !== '' && !tiene_permiso($conf['perm'])) {
    // Igual que en el 404: se evita el status 403 de Apache para poder
    // mostrar el mensaje de la aplicación en lugar de su ErrorDocument.
    $error_403 = 'No tiene permiso para acceder a este módulo (requiere: ' . $conf['perm'] . ').';
    require __DIR__ . '/parciales/cabecera.php';
    echo '<div class="panel"><h2>Acceso denegado</h2><p>Contacte al administrador del sistema para solicitar el permiso correspondiente.</p></div>';
    require __DIR__ . '/parciales/pie.php';
    exit;
}

require __DIR__ . '/parciales/cabecera.php';
require __DIR__ . '/paginas/' . $p . '.php';
require __DIR__ . '/parciales/pie.php';
