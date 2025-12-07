// Modal component: minimal API
var Modal = (function(){
  let escHandler = null;

  function ensureRoot(){
    let root = document.querySelector('.c-modal');
    if(!root){
      root = document.createElement('div'); root.className='c-modal';
      root.innerHTML = '<div class="c-modal__backdrop"></div>'+
        '<div class="c-modal__panel">'+
          '<div class="c-modal__header"><div class="c-modal__title"></div><button class="c-modal__close" aria-label="Chiudi">✕</button></div>'+
          '<div class="c-modal__body"></div>'+
          '<div class="c-modal__footer"></div>'+
        '</div>';
      document.body.appendChild(root);
      root.querySelector('.c-modal__close').addEventListener('click', function(){ close(); });
      root.querySelector('.c-modal__backdrop').addEventListener('click', function(){ close(); });
    }
    return root;
  }

  function open(content, opts = {}){
    const root = ensureRoot();
    const titleEl = root.querySelector('.c-modal__title');
    const body = root.querySelector('.c-modal__body');
    const footer = root.querySelector('.c-modal__footer');

    // set title from opts or from dataset.title on element
    const title = opts.title || (content && content.dataset && content.dataset.title) || '';
    titleEl.textContent = title;

    // fill body
    body.innerHTML = '';
    if(typeof content === 'string') body.innerHTML = content;
    else if(content instanceof Element) body.appendChild(content);
    else if(content && content.nodeType) body.appendChild(content);

    // clear footer and render actions (if provided) or build default Cancel+Save
    footer.innerHTML = '';
    // Hide common inline action buttons to avoid duplicates in the modal footer.
    // This covers both buttons inside forms and standalone page controls (like apply/clear).
    const form = body.querySelector('form');
    // selectors of elements we want to hide when the modal is open
    const inlineSelectors = ['#applyFilters', '#clearFilters', 'button[data-modal-hide-on-open]'];
    try {
      // hide submit controls inside a form (existing behaviour)
      if (form) {
        const candidates = Array.from(form.querySelectorAll('button, input'));
        candidates.forEach(el => {
          const tag = el.tagName.toLowerCase();
          const type = (el.getAttribute('type') || '').toLowerCase();
          const isSubmitInput = (tag === 'input' && type === 'submit');
          const isSubmitButton = (tag === 'button' && (type === '' || type === 'submit'));
          if (isSubmitInput || isSubmitButton) {
            el.style.display = 'none';
            el.setAttribute('data-modal-hidden-submit', '1');
          }
        });
      }

      // hide any well-known inline filter buttons or elements explicitly marked
      inlineSelectors.forEach(sel => {
        const found = Array.from(body.querySelectorAll(sel));
        found.forEach(el => {
          el.style.display = 'none';
          el.setAttribute('data-modal-hidden-submit', '1');
        });
      });
    } catch (e) {
      // ignore DOM issues
    }

    if (opts.actions && Array.isArray(opts.actions)) {
      renderActions(footer, opts.actions);
    } else {
      if (form) {
        const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'c-btn'; cancel.textContent = 'Annulla';
        cancel.addEventListener('click', () => close());
        footer.appendChild(cancel);

        const save = document.createElement('button'); save.type = 'button'; save.className = 'c-btn c-btn--primary'; save.textContent = 'Salva';
        save.addEventListener('click', () => {
          if (typeof form.requestSubmit === 'function') form.requestSubmit(); else form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        });
        footer.appendChild(save);
      }
    }

    // add ESC handler — prefer closing any visible autocomplete first
    escHandler = function(e){
      if(e.key !== 'Escape') return;
      try {
        const active = document.activeElement;
        // find any visible autocomplete list: element with class .c-autocomplete-list and visible in layout
        const lists = Array.from(document.querySelectorAll('.c-autocomplete-list'));
        const visibleList = lists.find(el => {
          try {
            const rects = el.getClientRects();
            return rects && rects.length > 0 && window.getComputedStyle(el).display !== 'none' && el.offsetParent !== null;
          } catch (e) { return false; }
        });
        if (visibleList && active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA')) {
          // If focus is inside an input and an autocomplete is visible, let the autocomplete handle ESC
          return;
        }
      } catch (err) {
        // ignore errors and proceed to close
      }
      close();
    };
    document.addEventListener('keydown', escHandler);

    root.classList.add('is-open');
    return root;
  }

  function renderActions(container, actions){
    actions.forEach(a=>{
      const btn = document.createElement('button'); btn.type = a.type || 'button'; btn.className = a.className || 'c-btn'; btn.textContent = a.label || 'Azione';
      if(typeof a.onClick === 'function') btn.addEventListener('click', a.onClick);
      container.appendChild(btn);
    });
  }

  function close(){
    const root = document.querySelector('.c-modal');
    if(!root) return;
    root.classList.remove('is-open');
    // restore any hidden in-form submit controls that we previously hid
    try {
      const hidden = Array.from(root.querySelectorAll('[data-modal-hidden-submit]'));
      hidden.forEach(el => {
        // remove inline display style we set
        el.style.removeProperty('display');
        el.removeAttribute('data-modal-hidden-submit');
      });
    } catch (e) {
      // ignore
    }
    // remove esc handler
    if(escHandler){ document.removeEventListener('keydown', escHandler); escHandler = null; }
  }

  // convenience confirm dialog: message string, onYes callback
  function confirm(message, onYes){
    const content = document.createElement('div');
    const p = document.createElement('p'); p.textContent = message; content.appendChild(p);
    open(content, { title: 'Conferma', actions: [
      { label: 'Annulla', className: 'c-btn', onClick: function(){ close(); } },
      { label: 'Conferma', className: 'c-btn c-btn--primary', onClick: function(){ try{ if(typeof onYes === 'function') onYes(); } finally { close(); } } }
    ]});
  }

  return { open, close, confirm };
})();

// expose globally for non-module usage
window.Modal = Modal;
