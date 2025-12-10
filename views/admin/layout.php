<?php
/**
 * Admin layout wrapper
 * Expects: $pageTitle (string), $content (HTML string)
 */
use Immaginificio\OAuthProxyBridge\Core\Assets;
use Immaginificio\OAuthProxyBridge\Core\Auth;
use Immaginificio\OAuthProxyBridge\Core\Svg;

$userName = Auth::currentAdminName() ?? 'Admin';
$version = 'v0.0.01';

?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo htmlspecialchars($pageTitle ?? 'Admin'); ?></title>
  <!-- Favicon (SVG) + PNG fallback -->
  <link rel="icon" href="/assets/imgs/immaginificio.svg" type="image/svg+xml">
  <link rel="alternate icon" href="/assets/imgs/immaginificio.png" type="image/png">
    <?php
    // enqueue core admin stylesheet and common component styles
    if (class_exists(Assets::class)) {
      // palette (centralized variables) must load first
      Assets::enqueueStyle('palette','/assets/css/palette.css');
      // component styles commonly used across admin layout
      Assets::enqueueComponents(['button','icon-button','card','icon','grid']);
      // base admin stylesheet
      Assets::enqueueStyle('admin','/assets/css/admin.css');
      // print enqueued styles
      Assets::printStyles();
    } else {
      echo '<link rel="stylesheet" href="/assets/css/admin.css">';
    }
    ?>
</head>
<body>
  <div class="admin-shell" id="adminShell">
    <header class="admin-header">
      <div class="left">
        <button class="c-btn c-icon-btn hamburger" id="hamburgerBtn" aria-label="Apri menu">
          <?php echo Svg::inline('menu.svg', ['class'=>'hamburger-icon c-icon','aria-hidden'=>'true']); ?>
          <span class="sr-only">Menu</span>
        </button>
        <h1 class="admin-header__title">OAuth 2.0 Pryoxy Bridge Admin</h1>
      </div>
      <div class="right top-actions">
          <span class="welcome-msg">Bentornato, <?php echo htmlspecialchars($userName); ?></span>
        <button class="c-btn" id="logoutBtn" title="Logout">
          <?php echo Svg::inline('logout.svg', ['class'=>'menu-icon c-icon','aria-hidden'=>'true']); ?>
          <span class="label">Logout</span></button>
      </div>
    </header>

    <aside class="admin-sidebar" id="adminSidebar">
      <ul>
        <li><a href="/admin"><?php echo Svg::inline('dashboard.svg', ['class'=>'c-icon','aria-hidden'=>'true']); ?><span class="label">Dashboard</span></a></li>
        <li><a href="/admin/keys/view"><?php echo Svg::inline('keys.svg', ['class'=>'c-icon','aria-hidden'=>'true']); ?><span class="label">Gestione Chiavi</span></a></li>
        <li><a href="/admin/logs/view"><?php echo Svg::inline('logs.svg', ['class'=>'c-icon','aria-hidden'=>'true']); ?><span class="label">Visualizza Logs</span></a></li>
        <li><a href="/admin/users/view"><?php echo Svg::inline('users.svg', ['class'=>'c-icon','aria-hidden'=>'true']); ?><span class="label">Utenti</span></a></li>
      </ul>
      <div class="sidebar-footer">
        <button id="sidebarToggle" class="sidebar-toggle" title="Riduci / Espandi sidebar">
          <?php echo Svg::inline('left.svg', ['class'=>'toggle-icon c-icon','aria-hidden'=>'true']); ?>
          <span class="label">Riduci</span>
        </button>
      </div>
    </aside>

    <main class="admin-main">
      <div class="admin-content">
        <?php echo $content ?? '<div>Pagina corrente</div>'; ?>
      </div>
    </main>

    <footer class="admin-footer">
      <div>OAuth 2.0 Server by Immaginificio</div>
      <div><?php echo htmlspecialchars($version); ?></div>
    </footer>
  </div>

    <?php
    if (class_exists(Assets::class)) {
      $doc = $_SERVER['DOCUMENT_ROOT'] ?? '';
      $hasChart = file_exists(rtrim($doc,'/') . '/assets/js/vendor/chart.min.js');

      if ($hasChart) {
        Assets::enqueueScript('chart','/assets/js/vendor/chart.min.js', [], true);
      }

      // enqueue common component scripts if present
      Assets::enqueueComponents(['toggle-password','icon-button']);

      // page/application scripts
      Assets::enqueueScript('admin','/assets/js/admin.js', [], true);
      // admin-logs is page-specific; only enqueue when present on disk
      if (file_exists((($_SERVER['DOCUMENT_ROOT'] ?? rtrim(getcwd(),'/')) . '/assets/js/admin-logs.js'))) {
        Assets::enqueueScript('admin-logs','/assets/js/admin-logs.js', ['admin'], true);
      }

      // print non-footer scripts first, then footer scripts
      Assets::printScripts(false);
      Assets::printScripts(true);
    } else {
      $doc = $_SERVER['DOCUMENT_ROOT'] ?? '';
      if (file_exists(rtrim($doc,'/') . '/assets/js/vendor/chart.min.js')) {
        echo '<script src="/assets/js/vendor/chart.min.js"></script>';
      }
      echo '<script src="/assets/js/admin.js"></script>';
      if (file_exists(rtrim($doc,'/') . '/assets/js/admin-logs.js')) {
        echo '<script src="/assets/js/admin-logs.js"></script>';
      }
    }
    ?>
</body>
</html>
