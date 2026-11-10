<?php
/**
 * Herramienta de prueba de envío de correo SMTP
 * Uso: /api/test_mail.php?user=micorreo@gmail.com&dest=destinatario@correo.com
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';

header('Content-Type: application/json; charset=utf-8');

$user = trim($_GET['user'] ?? '');
if ($user !== '') {
    putenv("SMTP_USER=$user");
    putenv("SMTP_FROM=$user");
}

$dest = trim($_GET['dest'] ?? ($user ?: 'gunnar.pecho@ulat.edu.bo'));

$asunto = "Prueba de Alerta SMTP - Punata Vacuna Segura (" . date('H:i:s') . ")";
$cuerpo = "<h3>¡El sistema de correo SMTP de Punata Vacuna Segura funciona correctamente!</h3>"
        . "<p>Este es un correo de prueba enviado desde el contenedor Docker utilizando la contraseña de aplicación de Gmail.</p>"
        . "<p><strong>Fecha y hora:</strong> " . date('Y-m-d H:i:s') . "</p>";

$ok = enviar_email_smtp($dest, "Usuario de Prueba", $asunto, $cuerpo);

echo json_encode([
    'ok' => $ok,
    'smtp_user_utilizado' => getenv('SMTP_USER') ?: 'no definido',
    'destinatario' => $dest,
    'mensaje' => $ok ? 'Correo enviado exitosamente.' : 'Error al enviar correo. Verifique si el usuario de Gmail coincide con la cuenta donde se generó la contraseña de aplicación.'
], JSON_UNESCAPED_UNICODE);

