<?php
/**
 * Admin login view
 * Minimal HTML form that posts to /admin/login
 * @since 0.0.1
 */
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Login</title>
  <?php
  use Immaginificio\OAuthProxyBridge\Core\Assets;

  if (class_exists(Assets::class)) {
    // enqueue all required components
    Assets::enqueueStyle('palette','/assets/css/palette.css');
    Assets::enqueueComponents(['button','icon-button','card','field','input-group','grid','icon','toggle-password','alert']);
    Assets::enqueueStyle('admin','/assets/css/admin.css');
    Assets::printStyles();

    Assets::enqueueScript('admin-login','/assets/js/admin-login.js', [], true, ['defer' => true]);
  }
  ?>
</head>
<body>
  <div class="login-shell">
    <div class="login-card c-card" role="region" aria-label="Login amministrazione">
      <div class="c-card__header mb-2g">
        <h1 class="c-card__title w-100">OAuth 2.0 Server</h1>
        <h2 class="c-card__subtitle w-100">Pannello di amministrazione</h2>
      </div>

      <div class="c-card__body">
        <form id="loginForm" >
          <div class="c-field">
            <label class="c-field__label" for="emailInput">Email</label>
            <input id="emailInput" class="c-field__control" type="email" name="email" required />
          </div>

          <div class="c-field">
            <label class="c-field__label" for="passwordInput">Password</label>
            <div class="c-input-group">
              <input id="passwordInput" class="c-field__control" type="password" name="password" required />
              <div class="c-input-addon">
                <button type="button" id="togglePwd" class="c-icon-btn" data-toggle="password" data-target="#passwordInput" aria-label="Mostra password">
                  <?php
                  use Immaginificio\OAuthProxyBridge\Core\Svg;
                  // include both states, JS will toggle visibility via .is-open class
                  echo Svg::inline('eye-closed.svg', ['class' => 'svg-eye svg-eye--closed']);
                  echo Svg::inline('eye-open.svg', ['class' => 'svg-eye svg-eye--open']);
                  ?>
                </button>
              </div>
            </div>
          </div>

          <div class="login-actions" style="text-align:center;margin-top:12px">
              <button type="submit" class="c-btn c-btn--primary">
              <?php echo Svg::inline('login.svg', ['class'=>'c-icon','aria-hidden'=>'true']); ?>
              Accedi
            </button>
          </div>
        </form>
        <div id="message" aria-live="polite"></div>
      </div>
    </div>
  </div>

  <?php
  if (class_exists(Assets::class)) {
    // scripts were enqueued above via enqueueComponents; just print footer scripts
    Assets::printScripts(true);
  } else {
    echo '<script src="/assets/js/components/toggle-password.js"></script>';
    echo '<script src="/assets/js/admin-login.js"></script>';
  }
  ?>
</body>
</html>
