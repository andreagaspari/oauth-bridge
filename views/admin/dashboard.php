<?php
/**
 * Admin dashboard view
 * Shows links to manage keys and view logs
 */
use Immaginificio\OAuthProxyBridge\Core\Assets;
use Immaginificio\OAuthProxyBridge\Core\Auth;

if (!Auth::check()) {
    header('Location: /admin/login');
    exit;
}
// enqueue card component via batch helper
if (class_exists(Assets::class)) {
  Assets::enqueueComponents(['card']);
}

// Render content into layout
ob_start();
?>
  <div class="c-card" style="text-align:center">
    <div style="font-size:18px;font-weight:600">Dashboard</div>
    <p>Benvenuto, <?php echo htmlspecialchars(Auth::currentAdminName() ?? 'admin'); ?>!</p>
  </div>
<?php
$content = ob_get_clean();
$pageTitle = 'Dashboard';
require __DIR__ . '/layout.php';
