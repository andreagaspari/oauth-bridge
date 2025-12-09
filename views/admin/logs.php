<?php
/**
 * Logs view - shows recent logs
 */
use Immaginificio\OAuthProxyBridge\Core\Assets;
use Immaginificio\OAuthProxyBridge\Core\Auth;
use Immaginificio\OAuthProxyBridge\Core\Svg;

if (!Auth::check()) {
  header('Location: /admin/login');
  exit;
}

// enqueue components (css + pager) and page script
  if (class_exists(Assets::class)) {
    // include pager so the pagination control component is available on this page
    // 'filters' aggregates modal/select/autocomplete/date-range and related assets
    Assets::enqueueComponents(['card', 'grid', 'field', 'input-group', 'icon', 'select', 'pager', 'modal', 'toast', 'autocomplete', 'date-range', 'filters']);
    // enqueue page script (requesting defer attribute as before)
    Assets::enqueueScript('admin-logs','/assets/js/admin-logs.js', [], true, ['defer' => true]);
  }

ob_start();
?>
  
  <div class="c-card">
    <div class="c-card__header">
      <h2 class="c-card__title">Logs</h2>
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
            <label class="c-field__label">Sorgente</label>
            <input id="filterSource" class="c-field__control" type="text" placeholder="Cerca sorgente (nome utente, chiave, email...)" />
            <div id="filterSourceList" class="c-field__hint c-autocomplete-list"></div>
          </div>
          <div style="width:180px;position:relative;">
            <label class="c-field__label">Provider</label>
            <div class="c-select-wrapper">
              <select id="filterProvider" class="c-select">
                <option value="">Tutti</option>
              </select>
            </div>
          </div>
          <div style="width:180px;">
            <label class="c-field__label">Indirizzo IP</label>
            <input id="filterIp" class="c-field__control" type="text" placeholder="Es. 127.0.0.1 o 127.*.*.*" />
            <div id="filterIpList" class="c-field__hint c-autocomplete-list"></div>
          </div>
            <div style="min-width:220px;display:flex;gap:8px;align-items:end;">
            <div class="c-date-range" style="display:flex;gap:8px;align-items:end;">
              <div style="width:160px;">
                <label class="c-field__label">Intervallo</label>
                <div class="c-select-wrapper">
                  <select id="filterPreset" class="c-select">
                    <option value="all">Tutto</option>
                    <option value="">Personalizzato</option>
                    <option value="today">Oggi</option>
                    <option value="yesterday">Ieri</option>
                    <option value="7">Ultimi 7 giorni</option>
                    <option value="30">Ultimi 30 giorni</option>
                    <option value="90">Ultimi 3 mesi</option>
                  </select>
                </div>
              </div>
              <div class="c-date-range__custom">
                <label class="c-field__label">Dal</label>
                <input id="filterDateFrom" class="c-field__control" type="date" />
              </div>
              <div class="c-date-range__custom">
                <label class="c-field__label">Al</label>
                <input id="filterDateTo" class="c-field__control" type="date" />
              </div>
            </div>
          </div>
          <div style="margin-left:auto;">
            <button id="applyFilters" class="c-btn c-btn--primary">Applica</button>
            <button id="clearFilters" class="c-btn">Azzera</button>
          </div>
        </div>
      </div>

      <div id="logsGrid" class="c-grid c-grid--logs" style="width:100%" aria-live="polite"></div>
    </div>

    <div class="c-card__footer">
      <div id="pagination-controls" class="w-100"></div>
    </div>
  </div>

<?php
$content = ob_get_clean();
$pageTitle = 'Logs';
require __DIR__ . '/layout.php';
