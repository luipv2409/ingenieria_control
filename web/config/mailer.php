<?php
/**
 * Servicio de Notificaciones por Correo Electrónico (Gmail SMTP)
 * PUNATA VACUNA SEGURA
 */

function enviar_email_smtp(string $destinatarioEmail, string $destinatarioNombre, string $asunto, string $cuerpoHtml): bool
{
    $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $smtpPort = (int)(getenv('SMTP_PORT') ?: 465); // Puerto SSL
    $smtpUser = getenv('SMTP_USER') ?: 'ludwinggunnarpv24@gmail.com'; // Cuenta de Google
    $smtpPass = getenv('SMTP_PASS') ?: '';          // Contraseña de aplicación cargada desde entorno
    $smtpFrom = getenv('SMTP_FROM') ?: 'ludwinggunnarpv24@gmail.com';
    $nombreRemitente = 'Punata Vacuna Segura - Alertas';

    $timeout = 10;
    // Conexión SSL directa a smtp.gmail.com
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ]
    ]);

    $socket = @stream_socket_client("ssl://{$smtpHost}:{$smtpPort}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        error_log("Error SMTP Connect: $errstr ($errno)");
        return false;
    }

    $leer = function() use ($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $data;
    };

    $enviar = function(string $cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };

    $res = $leer();
    if (substr($res, 0, 3) !== '220') { fclose($socket); return false; }

    $enviar("EHLO " . gethostname());
    $res = $leer();

    $enviar("AUTH LOGIN");
    $res = $leer();
    if (substr($res, 0, 3) !== '334') { fclose($socket); return false; }

    $enviar(base64_encode($smtpUser));
    $res = $leer();
    if (substr($res, 0, 3) !== '334') { fclose($socket); return false; }

    $enviar(base64_encode(str_replace(' ', '', $smtpPass)));
    $res = $leer();
    if (substr($res, 0, 3) !== '235') {
        error_log("Error SMTP Auth (" . trim($smtpUser) . "): " . trim($res));
        fclose($socket);
        return false;
    }

    $enviar("MAIL FROM: <{$smtpFrom}>");
    $res = $leer();
    if (substr($res, 0, 3) !== '250') { fclose($socket); return false; }

    $enviar("RCPT TO: <{$destinatarioEmail}>");
    $res = $leer();
    if (substr($res, 0, 3) !== '250' && substr($res, 0, 3) !== '251') { fclose($socket); return false; }

    $enviar("DATA");
    $res = $leer();
    if (substr($res, 0, 3) !== '354') { fclose($socket); return false; }

    $cabeceras = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: =?UTF-8?B?" . base64_encode($nombreRemitente) . "?= <{$smtpFrom}>",
        "To: =?UTF-8?B?" . base64_encode($destinatarioNombre) . "?= <{$destinatarioEmail}>",
        "Date: " . date('r'),
        "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=",
        "X-Priority: 1 (Highest)",
        "X-Mailer: PunataVacunaSegura/PHP",
    ];

    $enviar(implode("\r\n", $cabeceras) . "\r\n\r\n" . $cuerpoHtml . "\r\n.");
    $res = $leer();

    $enviar("QUIT");
    fclose($socket);

    return substr($res, 0, 3) === '250';
}

/**
 * Notificar por correo a los usuarios responsables cuando ocurre una alarma
 */
function notificar_alarma_por_email(PDO $pdo, int $idAlarma, string $tipoAlarma, string $nivel, string $descripcion, string $codigoDispositivo, string $fecha): int
{
    // Obtener usuarios con permiso de responder_alarmas
    $st = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.apellido, u.correo
        FROM usuario u
        WHERE u.estado = 1
          AND u.correo IS NOT NULL AND u.correo <> ''
          AND EXISTS (
            SELECT 1 FROM usuario_rol ur
            JOIN rol_permiso rp ON rp.id_rol = ur.id_rol
            JOIN permiso p      ON p.id_permiso = rp.id_permiso
            WHERE ur.id_usuario = u.id_usuario AND p.nombre = 'responder_alarmas')");
    $st->execute();
    $destinatarios = $st->fetchAll();

    if (empty($destinatarios)) return 0;

    $colorBorde = ($nivel === 'CRITICA') ? '#dc2626' : '#d97706';
    $badgeFondo = ($nivel === 'CRITICA') ? '#fee2e2' : '#fef3c7';
    $badgeTexto = ($nivel === 'CRITICA') ? '#991b1b' : '#92400e';
    $asunto = "[$nivel] Alerta en Cadena de Frío: " . str_replace('_', ' ', $tipoAlarma) . " en $codigoDispositivo";

    $cuerpoHtml = '
    <!DOCTYPE html>
    <html lang="es">
    <head><meta charset="UTF-8"></head>
    <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background:#f1f5f9; margin:0; padding:20px;">
      <div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08); border-top:6px solid ' . $colorBorde . ';">
        <div style="padding:24px 28px;">
          <div style="font-size:12px; font-weight:bold; color:#64748b; letter-spacing:1px; text-transform:uppercase;">Sistema de Monitoreo · Punata Vacuna Segura</div>
          <h2 style="margin:8px 0 16px; color:#1e293b; font-size:20px;">' . htmlspecialchars($asunto) . '</h2>
          
          <div style="background:' . $badgeFondo . '; color:' . $badgeTexto . '; padding:8px 12px; border-radius:6px; font-weight:bold; font-size:13px; display:inline-block; margin-bottom:16px;">
            NIVEL: ' . htmlspecialchars($nivel) . '
          </div>

          <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:14px;">
            <tr style="border-bottom:1px solid #e2e8f0;">
              <td style="padding:8px 0; color:#64748b; width:130px;">Dispositivo:</td>
              <td style="padding:8px 0; font-weight:600; color:#0f172a;">' . htmlspecialchars($codigoDispositivo) . '</td>
            </tr>
            <tr style="border-bottom:1px solid #e2e8f0;">
              <td style="padding:8px 0; color:#64748b;">Tipo de Alarma:</td>
              <td style="padding:8px 0; font-weight:600; color:#0f172a;">' . htmlspecialchars(str_replace('_', ' ', $tipoAlarma)) . '</td>
            </tr>
            <tr style="border-bottom:1px solid #e2e8f0;">
              <td style="padding:8px 0; color:#64748b;">Fecha y Hora:</td>
              <td style="padding:8px 0; font-weight:600; color:#0f172a;">' . htmlspecialchars($fecha) . '</td>
            </tr>
            <tr>
              <td style="padding:8px 0; color:#64748b; vertical-align:top;">Detalle:</td>
              <td style="padding:8px 0; color:#334155;">' . htmlspecialchars($descripcion) . '</td>
            </tr>
          </table>

          <div style="background:#eff6ff; border-left:4px solid #3b82f6; padding:12px; font-size:13px; color:#1e40af; border-radius:4px; margin-bottom:20px;">
            Por favor, tome las acciones correspondientes según el protocolo de contingencia de la red de salud para salvaguardar las vacunas.
          </div>

          <p style="font-size:12px; color:#94a3b8; margin:0; text-align:center;">
            Este es un mensaje automático generado por el sistema de monitoreo en tiempo real con ESP32-S3.
          </p>
        </div>
      </div>
    </body>
    </html>';

    $enviados = 0;
    foreach ($destinatarios as $u) {
        $nombreCompleto = trim($u['nombre'] . ' ' . $u['apellido']);
        $ok = enviar_email_smtp($u['correo'], $nombreCompleto, $asunto, $cuerpoHtml);
        
        $estado = $ok ? 'ENTREGADA' : 'FALLIDA';
        $ins = $pdo->prepare("INSERT INTO notificacion (id_alarma, id_usuario, fecha_envio, medio, estado) VALUES (?, ?, NOW(), 'CORREO', ?)");
        $ins->execute([(int)$idAlarma, (int)$u['id_usuario'], $estado]);

        if ($ok) $enviados++;
    }

    return $enviados;
}

