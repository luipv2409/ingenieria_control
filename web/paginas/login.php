<?php $estilos = '<style>body{margin:0}</style>'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingreso · Punata Vacuna Segura</title>
<link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
<div class="login-pantalla">
  <form class="login-caja" method="post" action="index.php?p=login">
    <div style="text-align:center;font-size:38px;color:#2e75b6">❄</div>
    <h1>PUNATA VACUNA SEGURA</h1>
    <div class="sub">Sistema inteligente de monitoreo y control de la cadena de frío</div>

    <?php if (!empty($error)): ?>
      <div class="alerta error"><?= e($error) ?></div>
    <?php endif; ?>

    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="campo">
      <label>Correo electrónico</label>
      <input type="email" name="correo" required autofocus placeholder="usuario@correo.com"
             value="<?= e($_POST['correo'] ?? '') ?>">
    </div>
    <div class="campo">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
        <label style="margin:0;">Contraseña</label>
        <a href="index.php?p=recuperar" style="font-size:12px; color:#2563eb; text-decoration:none;">¿Olvidaste tu contraseña?</a>
      </div>
      <input type="password" name="password" required placeholder="••••••••">
    </div>
    <button class="boton" type="submit">Ingresar al panel</button>

    <div class="ayuda">
      <strong>Cuenta Administrador:</strong><br>
      ludwinggunnarpv24@gmail.com · admin123 <em>(Administrador)</em><br>
    </div>
  </form>
</div>
</body>
</html>
