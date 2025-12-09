// Users page JS
document.addEventListener('DOMContentLoaded', function(){
  const perPageOptions = [10,25,50,100];
  // initialize state from URL if present so reload returns to same page
  let state = (function(){
    const s = { page: 1, per_page: 25 };
    try{
      const params = new URLSearchParams(window.location.search);
      const qp = parseInt(params.get('page'),10);
      const qper = parseInt(params.get('per_page'),10);
      if(!isNaN(qp) && qp>0) s.page = qp;
      if(!isNaN(qper) && qper>0) s.per_page = qper;
    }catch(e){}
    return s;
  })();

  // pagination is rendered via the global renderPager component when available
  function renderPagination(meta){
    const container = document.getElementById('pagination-controls'); if(!container) return;
    if (typeof window.renderPager === 'function') {
      window.renderPager(container, {
        page: state.page,
        per_page: state.per_page,
        total: meta.total || 0,
        onPage: (p, per) => { state.page = p; state.per_page = per; fetchUsers(); }
      });
      return;
    }
    // fallback: do nothing
    container.innerHTML = '';
  }

  async function fetchUsers(){
    const q = new URLSearchParams({ page: state.page, per_page: state.per_page });
    const res = await fetch('/admin/users?'+q.toString(), { credentials: 'same-origin' });
    const data = await res.json();
    const container = document.getElementById('usersGrid');
    if(!container) return; container.innerHTML = '';

    const headers = ['ID','Email','Nome','Admin','Creato','Azioni'];
    // create a header row
    const headerRow = document.createElement('div'); headerRow.className = 'c-grid__row c-grid__header-row';
    headers.forEach(h=>{ const el = document.createElement('div'); el.className='c-grid__header'; el.textContent = h; headerRow.appendChild(el); });
    container.appendChild(headerRow);

    (data.data||[]).forEach(u=>{
        const values = [u.id, u.email, u.name||'', u.is_admin? 'Sì':'No', u.created_at, ''];
        const row = document.createElement('div'); row.className = 'c-grid__row';
        values.forEach((v, idx)=>{
          const cell = document.createElement('div'); cell.className='c-grid__cell';
          // add data-label for mobile stacked layout
          cell.setAttribute('data-label', headers[idx] || '');
          if(idx<5) { 
            cell.textContent = v; }
          else {
            // actions cell
            cell.classList.add('c-grid__cell--actions');

            if (data.current_user_id && data.current_user_id === u.id){
              const btn = document.createElement('button'); btn.textContent='Modifica'; btn.className='c-btn c-btn--secondary'; btn.addEventListener('click', ()=> editProfile(u)); cell.appendChild(btn);
            } else {
              const editBtn = document.createElement('button'); editBtn.textContent='Modifica'; editBtn.className='c-btn c-btn--secondary'; editBtn.addEventListener('click', ()=> editProfile(u)); cell.appendChild(editBtn);
              const del = document.createElement('button'); del.textContent='Elimina'; del.setAttribute('aria-label','Elimina utente '+u.id); del.className='c-btn c-btn--danger'; del.addEventListener('click', ()=> deleteUser(u.id)); cell.appendChild(del);
            }
          }
          row.appendChild(cell);
        });
        container.appendChild(row);
    });
    renderPagination(data.meta||{ total:0, page:state.page, per_page:state.per_page, total_pages:1 });
  }

  async function deleteUser(id){
    // use modal confirm when available
    if (window.Modal && typeof window.Modal.confirm === 'function'){
      window.Modal.confirm('Eliminare utente '+id+'?', async function(){
        const res = await fetch('/admin/users/'+id+'/delete', { method:'POST', credentials:'same-origin' });
        if(res.ok) { window.showToast && window.showToast('Eliminato', { type: 'success' }); fetchUsers(); }
        else { window.showToast && window.showToast('Eliminazione fallita', { type: 'danger' }); }
      });
      return;
    }
    if(!confirm('Eliminare utente '+id+'?')) return;
    const res = await fetch('/admin/users/'+id+'/delete', { method:'POST', credentials:'same-origin' });
    if(res.ok) { alert('Eliminato'); fetchUsers(); }
    else { alert('Eliminazione fallita'); }
  }

  function editProfile(user){
    // open modal with prefilled form for editing
    openUserForm(user);
  }

  // open create/edit user modal; if `user` provided, switch to edit mode
  function openUserForm(user){
    const tmpl = document.getElementById('createUserFormTemplate'); if(!tmpl) return;
    const node = tmpl.firstElementChild.cloneNode(true);
    // fill values if editing
    if(user){ node.querySelector('[name="email"]').value = user.email || ''; node.querySelector('[name="name"]').value = user.name || ''; node.querySelector('[name="is_admin"]').checked = !!user.is_admin; }
    // attach submit handler (ensure idempotent by using once)
    node.addEventListener('submit', async function(e){
      e.preventDefault();
      const form = e.target;
      // HTML5 validity check (stop if invalid)
      if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
        // show native validation UI
        if (typeof form.reportValidity === 'function') form.reportValidity();
        return;
      }

      // validate password confirmation if present
      if (form.password && form.password.value) {
        if (form.password_confirm && form.password_confirm.value !== form.password.value) {
          window.showToast && window.showToast('Le password non corrispondono', { type: 'danger' });
          return;
        }
      }

      const payload = { email: form.email.value, name: form.name.value };
      if(form.password && form.password.value) payload.password = form.password.value;
      payload.is_admin = form.is_admin.checked ? 1 : 0;
      try{
        let url = '/admin/users';
        if(user && user.id) url = '/admin/users/'+user.id;
        const res = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload), credentials:'same-origin' });
        if(res.ok){ window.showToast && window.showToast(user ? 'Utente aggiornato' : 'Utente creato', { type:'success' }); fetchUsers(); window.Modal && window.Modal.close(); }
        else { const j = await res.json(); window.showToast && window.showToast('Errore: '+(j.error||'sconosciuto'), { type:'danger' }); }
      }catch(err){ window.showToast && window.showToast('Errore di rete', { type:'danger' }); }
    }, { once: true });
    const title = user && user.id ? 'Modifica utente' : 'Crea utente';
    window.Modal && window.Modal.open(node, { title });

    // After modal is open, initialize password toggles for inputs inside the modal
    try {
      const modalBody = document.querySelector('.c-modal__body');
      if (modalBody) {
        const formInModal = modalBody.querySelector('form');
        if (formInModal) {
          ['password','password_confirm'].forEach(name => {
            const input = formInModal.querySelector('[name="' + name + '"]');
            if (!input) return;
            // ensure unique id
            const uid = 'uid' + Date.now() + Math.floor(Math.random()*1000);
            const inputId = name + '_' + uid;
            input.id = inputId;
            // find addon container if present
            let addon = input.parentNode.querySelector('.c-input-addon');
            if (!addon) {
              // fallback: create an absolute addon and append after input
              addon = document.createElement('div'); addon.className = 'c-input-addon'; input.parentNode.appendChild(addon);
            }
            // try to find an existing toggle button rendered server-side
            let btn = addon.querySelector('.js-toggle-password');
            if (btn) {
              // set attributes expected by the toggle component
              btn.setAttribute('data-target', '#' + inputId);
              btn.id = 'toggle_' + inputId;
              btn.setAttribute('aria-label', 'Mostra password');
            } else {
              // fallback: create a minimal toggle button (no SVG markup duplication)
              btn = document.createElement('button'); btn.type = 'button'; btn.className = 'c-btn c-icon-btn'; btn.setAttribute('data-toggle', 'password'); btn.setAttribute('data-target', '#' + inputId); btn.setAttribute('aria-label', 'Mostra password'); btn.id = 'toggle_' + inputId;
              // append an empty placeholder icon; CSS will handle display
              const span = document.createElement('span'); span.className = 'c-icon'; span.textContent = '👁'; btn.appendChild(span);
              addon.appendChild(btn);
            }
            // initialize component
            try { if (window.Components && typeof window.Components.togglePassword === 'function') { window.Components.togglePassword('#' + btn.id, '#' + inputId); } } catch(e) {}
            // add HTML5 custom validity handling so checkValidity() accounts for password confirmation
            try {
              const formEl = formInModal;
              const pwdEl = formEl.querySelector('[name="password"]');
              const confirmEl = formEl.querySelector('[name="password_confirm"]');
              if (pwdEl && confirmEl) {
                const validate = function(){
                  try {
                    if (pwdEl.value && pwdEl.value.length > 0) {
                      confirmEl.required = true;
                      if (confirmEl.value !== pwdEl.value) {
                        confirmEl.setCustomValidity('Le password non corrispondono');
                      } else {
                        confirmEl.setCustomValidity('');
                      }
                    } else {
                      confirmEl.required = false;
                      confirmEl.setCustomValidity('');
                    }
                  } catch (e) { }
                };
                pwdEl.addEventListener('input', validate);
                confirmEl.addEventListener('input', validate);
                // initial validation run
                validate();
              }
            } catch (e) {}
          });
        }
      }
    } catch (e) {}
  }

  document.getElementById('openCreateUserBtn')?.addEventListener('click', function(){ openUserForm(); });

  // expose helper for inline use
  window.editProfile = editProfile;
  window.deleteUser = deleteUser;
  window.fetchUsers = fetchUsers;

  fetchUsers();
});
