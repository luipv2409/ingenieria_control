<?php
/**
 * Autenticación, permisos y auditoría del panel PUNATA VACUNA SEGURA
 */
require_once __DIR__ . '/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_verificar(?string $t): bool
{
    return !empty($t) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function auditoria(string $accion, string $modulo, ?int $idUsuario = null): void
{
    if ($idUsuario === null) {
        $idUsuario = $_SESSION['usuario_id'] ?? null;
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
    q('INSERT INTO auditoria_usuario (id_usuario, accion, modulo, ip_origen) VALUES (?,?,?,?)',
      [$idUsuario, $accion, $modulo, $ip]);
}

function login(string $correo, string $password): bool
{
    $u = q('SELECT id_usuario, nombre, apellido, correo, password_hash, estado
              FROM usuario WHERE correo = ? LIMIT 1', [$correo])->fetch();
    if (!$u || (int)$u['estado'] !== 1) {
        return false;
    }
    if (!password_verify($password, $u['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int)$u['id_usuario'];
    $_SESSION['usuario_nombre'] = $u['nombre'] . ' ' . $u['apellido'];
    $_SESSION['usuario_correo'] = $u['correo'];

    // roles y permisos
    $_SESSION['roles'] = q('SELECT r.nombre FROM rol r
                            JOIN usuario_rol ur ON ur.id_rol = r.id_rol
                            WHERE ur.id_usuario = ?', [$u['id_usuario']])
                            ->fetchAll(PDO::FETCH_COLUMN);
    $_SESSION['permisos'] = q('SELECT DISTINCT p.nombre FROM permiso p
                               JOIN rol_permiso rp ON rp.id_permiso = p.id_permiso
                               JOIN usuario_rol ur ON ur.id_rol = rp.id_rol
                               WHERE ur.id_usuario = ?', [$u['id_usuario']])
                               ->fetchAll(PDO::FETCH_COLUMN);
    auditoria('LOGIN_EXITOSO', 'Autenticacion', (int)$u['id_usuario']);
    return true;
}

function logout(): void
{
    if (!empty($_SESSION['usuario_id'])) {
        auditoria('LOGOUT', 'Autenticacion');
    }
    $_SESSION = [];
    session_destroy();
}

function usuario_logueado(): bool
{
    return !empty($_SESSION['usuario_id']);
}

function tiene_permiso(string $permiso): bool
{
    return in_array($permiso, $_SESSION['permisos'] ?? [], true);
}

function tiene_rol(string $rol): bool
{
    return in_array($rol, $_SESSION['roles'] ?? [], true);
}

function requiere_login(): void
{
    if (!usuario_logueado()) {
        header('Location: index.php?p=login');
        exit;
    }
}

function requiere_permiso(string $permiso): void
{
    requiere_login();
    if (!tiene_permiso($permiso)) {
        http_response_code(403);
        $GLOBALS['error_403'] = 'No tiene permiso para acceder a este módulo (' . $permiso . ').';
    }
}
