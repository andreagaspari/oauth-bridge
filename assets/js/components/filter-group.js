// Filter group component: manages group behaviour and delegates modal actions
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const root = document.getElementById('logsFilters');
    if(!root) return;

    // wrap root in a container if not already
    if(!root.classList.contains('c-filter-group')) root.classList.add('c-filter-group');

    // create compact toolbar only on small screens (mobile). Avoid showing duplicate toggle
    // if the page already includes an `#openFiltersBtn` (preferred template button).
    var toolbar = null;
    try{
      var mobileOpenExisting = document.getElementById('openFiltersBtn');
      if(window.matchMedia && window.matchMedia('(max-width:720px)').matches && !mobileOpenExisting){
        toolbar = document.createElement('div');
        toolbar.className = 'c-filter-group__toolbar';
        toolbar.innerHTML = '<button type="button" class="c-btn c-btn--secondary c-filter-group__toggle" aria-expanded="false">Mostra filtri</button>';
        root.parentElement.insertBefore(toolbar, root);
      }
    }catch(e){ /* silent fallback: do not create toolbar if matchMedia fails */ }

    const toggle = toolbar ? toolbar.querySelector('.c-filter-group__toggle') : null;
    if(toggle){
      toggle.addEventListener('click', function(){
        const visible = root.style.display !== 'none' && root.style.display !== '';
        if(visible){ root.style.display = 'none'; toggle.setAttribute('aria-expanded','false'); toggle.textContent = 'Mostra filtri'; }
        else { root.style.display = ''; toggle.setAttribute('aria-expanded','true'); toggle.textContent = 'Nascondi filtri'; }
      });
    }

    // expose API on DOM element for other scripts
    root.filterGroup = {
      openInModal: function(){ if(window.FiltersModal && typeof window.FiltersModal.open === 'function') window.FiltersModal.open(); },
      closeModal: function(){ if(window.FiltersModal && typeof window.FiltersModal.close === 'function') window.FiltersModal.close(); },
      apply: function(){ if(window.FiltersModal && typeof window.FiltersModal.apply === 'function') window.FiltersModal.apply(); },
      clear: function(){ if(window.FiltersModal && typeof window.FiltersModal.clear === 'function') window.FiltersModal.clear(); }
    };

    // wire a small mobile button if exists (openFiltersBtn)
    const mobileOpen = document.getElementById('openFiltersBtn');
    if(mobileOpen){ mobileOpen.addEventListener('click', function(){ root.filterGroup.openInModal(); }); }

  });
})();
