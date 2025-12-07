// Filters modal: clone filters into a responsive modal on small screens
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const openBtn = document.getElementById('openFiltersBtn');
    const filters = document.getElementById('logsFilters');
    if(!openBtn || !filters) return;

    // Show the open button only on small screens via JS fallback
    const mq = window.matchMedia('(max-width:900px)');
    function syncButton(){
      if(mq.matches){
        openBtn.parentElement.style.display = 'block';
        filters.classList.add('filters-hidden');
      } else {
        openBtn.parentElement.style.display = 'none';
        filters.classList.remove('filters-hidden');
      }
    }
    mq.addListener(syncButton);
    syncButton();

    let overlay = null;
    let placeholder = null;
    let originalParent = null;
    // We'll prefer reusing the global `Modal` component when available.
    function createOverlay(){
      // noop: Modal.open will create its own DOM. Keep function for parity.
    }

    function openOverlay(){
      // prepare placeholder to restore position later
      if(!placeholder){
        originalParent = filters.parentElement;
        placeholder = document.createElement('div');
        placeholder.className = 'filters-placeholder';
        placeholder.style.display = 'none';
        originalParent.insertBefore(placeholder, filters);
      }

      // remove hidden class so modal will show it
      filters.classList.remove('filters-hidden');

      // If a standard Modal component exists, reuse it so we have X, backdrop and ESC handling
      if(window.Modal && typeof window.Modal.open === 'function'){
        // use Modal.open and supply two actions: Azzera, Applica
        window.Modal.open(filters, {
          title: 'Filtri',
          actions: [
            { label: 'Azzera', className: 'c-btn', onClick: function(){ const origClear = document.getElementById('clearFilters'); if(origClear) origClear.click(); } },
            { label: 'Applica', className: 'c-btn c-btn--primary', onClick: function(){ const origApply = document.getElementById('applyFilters'); if(origApply) origApply.click(); window.Modal.close && window.Modal.close(); } }
          ]
        });

        // observe modal root for close (when `is-open` class removed) to restore original DOM
        const root = document.querySelector('.c-modal');
        if(root){
          const mo = new MutationObserver(function(mutations){
            mutations.forEach(m=>{
              if(m.attributeName === 'class'){
                if(!root.classList.contains('is-open')){
                  // modal closed — restore filters
                  if(placeholder && originalParent){
                    originalParent.insertBefore(filters, placeholder);
                    placeholder.remove(); placeholder = null; originalParent = null;
                    // if on small screens, hide the inline filters again
                    if(mq.matches){ filters.classList.add('filters-hidden'); }
                  }
                  mo.disconnect();
                }
              }
            });
          });
          mo.observe(root, { attributes: true, attributeFilter: ['class'] });
        }
        return;
      }

      // Fallback: if no Modal component, create simple overlay (legacy path)
      if(!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'filters-modal-overlay';
        overlay.setAttribute('role','dialog');
        overlay.setAttribute('aria-modal','true');
        const container = document.createElement('div');
        container.className = 'filters-modal';
        const body = document.createElement('div'); body.className = 'filters-modal__body';
        const footer = document.createElement('div'); footer.className = 'filters-modal__footer';
        const clearBtn = document.createElement('button'); clearBtn.className='c-btn'; clearBtn.textContent='Azzera'; clearBtn.addEventListener('click', ()=>{ document.getElementById('clearFilters')?.click(); });
        const applyBtn = document.createElement('button'); applyBtn.className='c-btn c-btn--primary'; applyBtn.textContent='Applica'; applyBtn.addEventListener('click', ()=>{ document.getElementById('applyFilters')?.click(); closeOverlay(); });
        footer.appendChild(clearBtn); footer.appendChild(applyBtn);
        container.appendChild(body); container.appendChild(footer);
        overlay.appendChild(container);
        document.body.appendChild(overlay);
      }
      // move filters into fallback overlay
      const bodyEl = overlay.querySelector('.filters-modal__body'); bodyEl.innerHTML=''; bodyEl.appendChild(filters);
      overlay.style.display = 'flex';
      document.documentElement.classList.add('filters-modal-open');
    }

    function closeOverlay(){
      if(!overlay) return;
      // move filters back to original place if placeholder exists
      if(placeholder && originalParent){
        originalParent.insertBefore(filters, placeholder);
        placeholder.remove();
        placeholder = null;
        originalParent = null;
        // if on small screens, hide the inline filters again
        if(mq.matches){ filters.classList.add('filters-hidden'); }
      }
      overlay.style.display = 'none';
      document.documentElement.classList.remove('filters-modal-open');
      openBtn.focus();
    }

    // expose a small API so other components can control the modal
    window.FiltersModal = {
      open: openOverlay,
      close: closeOverlay,
      apply: function(){ const origApply = document.getElementById('applyFilters'); if(origApply) origApply.click(); closeOverlay(); },
      clear: function(){ const origClear = document.getElementById('clearFilters'); if(origClear) origClear.click(); }
    };

    openBtn.addEventListener('click', function(){ openOverlay(); });
  });
})();
