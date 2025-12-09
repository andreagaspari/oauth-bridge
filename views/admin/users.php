<?php
// Minimal admin users UI (rendered inside layout)
use Immaginificio\OAuthProxyBridge\Core\Assets;
use Immaginificio\OAuthProxyBridge\Core\Auth;
use Immaginificio\OAuthProxyBridge\Core\Svg;

if (!Auth::check()) { header('Location: /admin/login'); exit; }

// enqueue components: UI css + admin-users script
    if (class_exists(Assets::class)) {
    // include modal, toast and alert so forms and confirmations use the shared components
    Assets::enqueueComponents(['button','icon-button','card','field','grid','input-group','icon','select','modal','toast','alert','pager']);
    Assets::enqueueScript('admin-users','/assets/js/admin-users.js', [], true, ['defer' => true]);
}

ob_start();
?>
    <div class="c-card">
        <div class="c-card__header">
            <h2 class="c-card__title">Utenti</h2>
            
            <button id="openCreateUserBtn" class="c-btn c-btn--primary" aria-label="Crea utente">
                <?php echo Svg::inline('plus.svg', ['class' => 'c-icon', 'aria-hidden' => 'true']); ?>
                <span class="label">Aggiungi</span>
            </button>
            
            <!-- hidden template for the create/edit user form; cloned into modal when needed -->
            <div id="createUserFormTemplate" style="display:none">
                <form id="createUserFormModal">
                    <div class="c-field"><label class="c-field__label">Nome</label><input class="c-field__control" name="name" type="text"></div>
                    <div class="c-field"><label class="c-field__label">Email</label><input class="c-field__control" name="email" type="email" required></div>
                    <div class="c-field">
                        <label class="c-field__label">Password</label>
                        <div class="c-input-group">
                            <input class="c-field__control" name="password" type="password">
                            <div class="c-input-addon">
                                <button type="button" class="c-btn c-icon-btn js-toggle-password" aria-label="Mostra password">
                                    <?php /* SVGs will be inlined via the button content to match other views; JS will only set ids/targets */ ?>
                                    <?php echo Svg::inline('eye-open.svg', ['class' => 'svg-eye svg-eye--open']); ?>
                                    <?php echo Svg::inline('eye-closed.svg', ['class' => 'svg-eye svg-eye--closed']); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="c-field">
                        <label class="c-field__label">Conferma Password</label>
                        <div class="c-input-group">
                            <input class="c-field__control" name="password_confirm" type="password">
                            <div class="c-input-addon">
                                <button type="button" class="c-btn c-icon-btn js-toggle-password" aria-label="Mostra password">
                                    <?php echo Svg::inline('eye-open.svg', ['class' => 'svg-eye svg-eye--open']); ?>
                                    <?php echo Svg::inline('eye-closed.svg', ['class' => 'svg-eye svg-eye--closed']); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="c-field"><label class="c-field__label"><input name="is_admin" type="checkbox"> Amministratore</label></div>
                    <div style="margin-top:8px"><button class="c-btn c-btn--primary" type="submit">Salva</button></div>
                </form>
            </div>
        </div>

        <div class="c-card__body">
            <div id="usersGrid" class="c-grid c-grid--users" aria-live="polite"></div>
        </div>
        <div class="c-card__footer">
            <div id="pagination-controls" class="w-100"></div>
        </div>
    </div>

<?php
$content = ob_get_clean();
$pageTitle = 'Utenti';
require __DIR__ . '/layout.php';
