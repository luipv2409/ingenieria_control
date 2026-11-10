<?php
require_once __DIR__ . '/../config/auth.php';
$titulo = $titulo ?? 'Panel';
$pagina = $pagina ?? '';
$estilos = $estilos ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> · Punata Vacuna Segura</title>
<link rel="stylesheet" href="assets/css/estilo.css">
<?= $estilos ?>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="logo">
      <span class="logo-ico">❄</span>
      <div>
        <strong>PUNATA</strong><br><small>VACUNA SEGURA</small>
      </div>
    </div>
    <nav>
      <?php
      $notifPend = 0;
      if (usuario_logueado()) {
          $notifPend = (int)valor(
              "SELECT COUNT(*) FROM notificacion WHERE id_usuario = ? AND estado <> 'LEIDA'",
              [$_SESSION['usuario_id']]);
      }
      $menu = [
        ['dashboard',    'Panel general',  'ver_panel',           '▣'],
        ['lecturas',     'Lecturas',       'ver_lecturas',        '∿'],
        ['alarmas',      'Alarmas',        'ver_alarmas',         '⚠'],
        ['notificaciones','Notificaciones','ver_alarmas',         '✉', $notifPend],
        ['dispositivos', 'Dispositivos',   'ver_lecturas',        '⚙'],
        ['instituciones','Instituciones',  'ver_panel',           '⛨'],
        ['vacunas',      'Vacunas',        'ver_panel',           '⚕'],
        ['reportes',     'Reportes',       'ver_reportes',        '≡'],
        ['usuarios',     'Usuarios',       'gestionar_usuarios',  '☺'],
        ['auditoria',    'Auditoría',      'auditar',             '⚿'],
      ];
      foreach ($menu as $item) {
          [$id, $txt, $perm, $ico] = $item;
          $badge = $item[4] ?? 0;
          if (!tiene_permiso($perm)) continue;
          $act = ($pagina === $id) ? ' class="activo"' : '';
          $chapa = $badge > 0 ? ' <span class="nav-badge">' . (int)$badge . '</span>' : '';
          echo '<a href="index.php?p=' . $id . '"' . $act . '><span class="ico">' . $ico . '</span> '
             . e($txt) . $chapa . '</a>';
      }
      ?>
    </nav>
    <div class="sesion">
      <small>Sesión:</small>
      <strong><?= e($_SESSION['usuario_nombre'] ?? '') ?></strong>
      <small><?= e(implode(', ', $_SESSION['roles'] ?? [])) ?></small>
      <a class="perfil" href="index.php?p=perfil">Mi perfil</a>
      <a class="salir" href="index.php?p=logout">Cerrar sesión</a>
    </div>
  </aside>
  <main class="contenido">
    <header class="barra">
      <h1><?= e($titulo) ?></h1>
      <div class="estado-sistema">
        <span class="punto"></span> Sistema operativo · <?= date('d/m/Y H:i') ?>
      </div>
    </header>
    <?php if (!empty($error_403)): ?>
      <div class="alerta error"><?= e($error_403) ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['mensaje'])): ?>
      <div class="alerta ok"><?= e($_SESSION['mensaje']) ?></div>
      <?php unset($_SESSION['mensaje']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
      <div class="alerta error"><?= e($_SESSION['error']) ?></div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
