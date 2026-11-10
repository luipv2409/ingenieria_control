<?php
/**
 * Pantalla de recuperación de contraseña por correo electrónico
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recuperar Contraseña · Punata Vacuna Segura</title>
<link rel="stylesheet" href="assets/css/estilo.css">
<style>
  body { margin: 0; background: #f0f4f8; }
  .login-pantalla { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
  .login-caja { background: white; max-width: 420px; width: 100%; padding: 32px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
  .login-caja h1 { font-size: 20px; text-align: center; margin: 12px 0 6px; color: #1e3a8a; }
  .sub { font-size: 13px; color: #64748b; text-align: center; margin-bottom: 24px; }
  .campo { margin-bottom: 16px; }
  .campo label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
  .campo input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
  .boton { width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 14px; }
  .boton:hover { background: #1d4ed8; }
  .enlaces { text-align: center; margin-top: 18px; font-size: 13px; }
  .enlaces a { color: #2563eb; text-decoration: none; }
  .enlaces a:hover { text-decoration: underline; }
  .alerta { padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
  .alerta.ok { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
  .alerta.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
</style>
</head>
<body>
<div class="login-pantalla">
  <div class="login-caja">
    <div style="text-align:center;font-size:38px;color:#2e75b6">❄</div>
    <h1>Recuperar Contraseña</h1>
    <div class="sub">Ingrese su correo electrónico registrado para enviarle una clave provisional de acceso.</div>

    <?php if (!empty($mensaje)): ?>
      <div class="alerta ok"><?= e($mensaje) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alerta error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (empty($mensaje)): ?>
      <form method="post" action="index.php?p=recuperar">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="campo">
          <label>Correo electrónico institucional o personal</label>
          <input type="email" name="correo" required autofocus placeholder="usuario@correo.com"
                 value="<?= e($_POST['correo'] ?? '') ?>">
        </div>
        <button class="boton" type="submit">Enviar Contraseña Provisional</button>
      </form>
    <?php endif; ?>

    <div class="enlaces">
      <a href="index.php?p=login">← Volver al inicio de sesión</a>
    </div>
  </div>
</div>
</body>
</html>

