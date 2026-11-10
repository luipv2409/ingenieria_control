<?php
/**
 * Conexión a la base de datos PUNATA VACUNA SEGURA
 */
// Fijar zona horaria de Bolivia (UTC-4)
date_default_timezone_set('America/La_Paz');

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'punata_vacuna_segura';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbPort = (int)(getenv('DB_PORT') ?: 3306);

if (!defined('DB_HOST')) define('DB_HOST', $dbHost);
if (!defined('DB_NAME')) define('DB_NAME', $dbName);
if (!defined('DB_USER')) define('DB_USER', $dbUser);
if (!defined('DB_PASS')) define('DB_PASS', $dbPass);
if (!defined('DB_PORT')) define('DB_PORT', $dbPort);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '-04:00';");
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function valor(string $sql, array $params = [], $def = null)
{
    $v = q($sql, $params)->fetchColumn();
    return ($v === false || $v === null) ? $def : $v;
}

/**
 * Clave compartida para el API de ingesta de datos.
 * Los nodos deben enviar: token = sha256(strtoupper(codigo) . API_SECRET)
 */
const API_SECRET = 'punata-vacuna-segura-2026';

function calcular_token_dispositivo(string $codigo): string
{
    return hash('sha256', strtoupper($codigo) . API_SECRET);
}
