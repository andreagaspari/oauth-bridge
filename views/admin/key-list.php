<?php
  /**
   * Key list view - fetches keys from API and shows table
   */
  use Immaginificio\OAuthProxyBridge\Core\Assets;
  use Immaginificio\OAuthProxyBridge\Core\Auth;
  use Immaginificio\OAuthProxyBridge\Core\Svg;
  use Immaginificio\OAuthProxyBridge\Services\ServiceManager;

  if (!Auth::check()) { header('Location: /admin/login'); exit; }
  
  // enqueue components (css) + admin-keys script
    if (class_exists(Assets::class)) {
    // include modal/toast/alert and filters components so we reuse the same filter UI/modal as logs
    Assets::enqueueComponents(['card','grid','field','input-group','icon','select','autocomplete','filters','modal','button','badge','toast','alert','pager']);
    Assets::enqueueScript('admin-keys','/assets/js/admin-keys.js', [], true, ['defer' => true]);
  }

  ob_start();
  ?>

    <div class="c-card">
      <div class="c-card__header">
        <h2 class="c-card__title">Chiavi</h2>

        <button id="openCreateKeyBtn" class="c-btn c-btn--primary" aria-label="Crea chiave">
            <?php echo Svg::inline('plus.svg', ['class' => 'c-icon', 'aria-hidden' => 'true']); ?>
            <span class="label">Aggiungi</span>
        </button>

        <!-- hidden template for key create/edit form -->
        <div id="createKeyFormTemplate" style="display:none">
          <?php
            // available providers from ServiceManager
            $svc = new ServiceManager();
            $availableProviders = $svc->availableProviders();
          ?>
          <form id="createKeyFormModal" style="display:flex;flex-direction:column;gap:12px;align-items:stretch">
            <div class="c-field"><label class="c-field__label">Nome chiave</label><input type="text" name="name" placeholder="Es. Sito XYZ" class="c-field__control" /></div>
            <div class="c-field"><label class="c-field__label">URL del sito</label><input type="text" name="site_url" placeholder="*.esempio.it/*" required class="c-field__control" />
              <div class="c-field__hint">Puoi usare wildcard, es.: <code>*.esempio.it/*</code></div>
            </div>
            <div class="c-field"><label class="c-field__label">Descrizione</label><textarea name="description" placeholder="Descrizione (opzionale)" rows="4" maxlength="255" class="c-field__control"></textarea></div>
            <?php if (!empty($availableProviders)): ?>
              <div class="c-field">
                <label class="c-field__label">Provider abilitati</label>
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                  <?php foreach($availableProviders as $p): ?>
                    <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="providers[]" value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>" /> <?php echo htmlspecialchars(ucfirst($p)); ?></label>
                  <?php endforeach; ?>
                </div>
                <div class="c-field__hint">Seleziona i provider che questa chiave può usare.</div>
              </div>
            <?php endif; ?>
            <div class="c-field"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="active" checked /> Attiva</label></div>
            <button type="submit" class="c-btn c-btn--primary">Salva</button>
          </form>
        </div>
      </div>
      <div class="c-card__body">
        <div class="filters-modal-bar" style="margin-bottom:8px;display:none;">
          <button id="openFiltersBtn" class="c-btn c-icon-btn filters-open-btn" type="button">
            <?php echo Svg::inline('filter.svg', ['class' => 'c-icon', 'aria-hidden' => 'true']); ?>
            <span class="label">Filtri</span>
          </button>
        </div>
        <div id="logsFilters" style="margin-bottom:12px;">
          <div class="c-field" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">
            <div style="flex:1;min-width:180px;position:relative;">
              <label class="c-field__label">Nome o sito</label>
              <input id="filterQ" class="c-field__control" type="text" placeholder="Cerca per nome o URL sito" />
              <div id="filterQList" class="c-field__hint c-autocomplete-list"></div>
            </div>
            <div style="width:180px;position:relative;">
              <label class="c-field__label">Servizi</label>
              <div class="c-select-wrapper">
                <select id="filterProvider" class="c-select">
                  <option value="">Tutti</option>
                  <?php if (!empty($availableProviders)): foreach($availableProviders as $p): ?>
                    <option value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>"><?php echo htmlspecialchars(ucfirst($p)); ?></option>
                  <?php endforeach; endif; ?>
                </select>
              </div>
            </div>
            <div style="width:140px;">
              <label class="c-field__label">Attiva</label>
              <div class="c-select-wrapper">
                <select id="filterActive" class="c-select">
                  <option value="">Tutte</option>
                  <option value="1">Sì</option>
                  <option value="0">No</option>
                </select>
              </div>
            </div>
            <div style="margin-left:auto;">
              <button id="applyKeyFilters" class="c-btn c-btn--primary" id="applyFilters">Applica</button>
              <button id="clearKeyFilters" class="c-btn" id="clearFilters">Azzera</button>
            </div>
          </div>
        </div>
          <div id="keysGrid" class="c-grid c-grid--keys" style="width:100%;margin-top:12px" aria-live="polite"></div>
        </div>
      <div class="c-card__footer">
        <div id="pagination-controls" class="w-100"></div>
      </div>
    </div>

  <?php
  $content = ob_get_clean();
  $pageTitle = 'Gestione Chiavi';
  require __DIR__ . '/layout.php';
