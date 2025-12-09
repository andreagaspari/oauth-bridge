// Keys page JS
document.addEventListener('DOMContentLoaded', function(){
  const perPageOptions = [10,25,50,100];
  // initialize state from URL if present so refresh keeps current page
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

  // svg inline cache helper (used to inline icons into dynamically created buttons)
  if(window.__inlineSvgCache === undefined) window.__inlineSvgCache = {};
  async function ensureInlineSvg(path, target){
    if(window.__inlineSvgCache[path]){ target.innerHTML = window.__inlineSvgCache[path]; return; }
    try{
      const r = await fetch(path);
      if(!r.ok) return;
      const txt = await r.text();
      window.__inlineSvgCache[path] = txt;
      target.innerHTML = txt;
    }catch(e){ /* ignore */ }
  }

  // simple suggestions fetch for keys (name/site)
  async function fetchKeySuggestions(q){
    try{
      const url = '/admin/keys/suggestions?q=' + encodeURIComponent(q || '');
      const res = await fetch(url, { credentials: 'same-origin', headers:{'Accept':'application/json'} });
      const data = await res.json();
      return data.data || [];
    }catch(e){ return []; }
  }

  // Validate site_url on client: accept full http(s) URLs or wildcard host patterns like "*.example.it" optionally with a path
  function isValidSiteUrl(site){
    if(!site) return false;
    site = site.trim();
    if(site.length === 0 || site.length > 255) return false;
    // full URL
    try{ new URL(site); return true; }catch(e){}
    // disallow scheme-like strings with ://
    if(site.indexOf('://') !== -1) return false;
    // pattern: optional '*.' then labels and TLD, optional path
    const re = /^(\*\.)?([a-z0-9-]+\.)+[a-z]{2,}(\/.*)?$/i;
    return re.test(site);
  }

  // lightweight autocomplete copy of the logs one, simplified for keys (single input)
  function attachAutocompleteKey(inputId, listId){
    const inp = document.getElementById(inputId); const lst = document.getElementById(listId);
    if(!inp || !lst) return;
    let timeout = null; let highlighted = -1;
    lst.classList.add('c-autocomplete-list'); lst.classList.remove('visible');

    function clearHighlights(){ const kids = lst.querySelectorAll('.c-autocomplete-item'); kids.forEach(k=>{ k.classList.remove('c-autocomplete-item--highlighted'); k.setAttribute('aria-selected','false'); }); highlighted = -1; }
    function updateHighlight(idx){ const kids = lst.querySelectorAll('.c-autocomplete-item'); if(kids.length===0) return; if(highlighted>=0 && highlighted<kids.length){ kids[highlighted].classList.remove('c-autocomplete-item--highlighted'); kids[highlighted].setAttribute('aria-selected','false'); } highlighted = idx; if(highlighted<0) highlighted = 0; if(highlighted>=kids.length) highlighted = kids.length-1; const el = kids[highlighted]; el.classList.add('c-autocomplete-item--highlighted'); el.setAttribute('aria-selected','true'); el.scrollIntoView({block:'nearest'}); }

    inp.addEventListener('input', function(){ const v = inp.value.trim(); if(timeout) clearTimeout(timeout); if(v.length < 1){ lst.classList.remove('visible'); return; } timeout = setTimeout(async ()=>{ const items = await fetchKeySuggestions(v); lst.innerHTML = ''; if(!items || items.length===0){ lst.classList.remove('visible'); return; } items.forEach((it, idx)=>{ const d = document.createElement('div'); d.className='c-field__hint c-autocomplete-item'; d.textContent = it; d.tabIndex = 0; d.setAttribute('role','option'); d.addEventListener('click', ()=>{ inp.value = it; lst.classList.remove('visible'); inp.focus(); }); d.addEventListener('keydown', (ev)=>{ if(ev.key === 'Enter'){ ev.preventDefault(); inp.value = it; lst.classList.remove('visible'); inp.focus(); } }); d.addEventListener('mouseenter', ()=>{ clearHighlights(); updateHighlight(idx); }); d.addEventListener('mouseleave', ()=>{ d.classList.remove('c-autocomplete-item--highlighted'); d.setAttribute('aria-selected','false'); highlighted = -1; }); lst.appendChild(d); }); lst.style.top = (inp.offsetTop + inp.offsetHeight) + 'px'; lst.classList.add('visible'); clearHighlights(); }, 180); });

    document.addEventListener('click', function(e){ if(!lst.contains(e.target) && e.target !== inp){ lst.classList.remove('visible'); } });
    inp.addEventListener('keydown', function(e){ if(e.key === 'ArrowDown' && lst.classList.contains('visible')){ e.preventDefault(); const kids = lst.querySelectorAll('.c-autocomplete-item'); if(kids.length===0) return; if(highlighted < kids.length -1) updateHighlight(highlighted + 1); else updateHighlight(0); } else if(e.key === 'ArrowUp' && lst.classList.contains('visible')){ e.preventDefault(); const kids = lst.querySelectorAll('.c-autocomplete-item'); if(kids.length===0) return; if(highlighted > 0) updateHighlight(highlighted - 1); else updateHighlight(kids.length -1); } else if(e.key === 'Enter' && lst.classList.contains('visible')){ e.preventDefault(); const kids = lst.querySelectorAll('.c-autocomplete-item'); if(highlighted >=0 && kids[highlighted]){ inp.value = kids[highlighted].textContent; } lst.classList.remove('visible'); } else if(e.key === 'Escape'){ lst.classList.remove('visible'); } });
  }

  // helper to replace URL params for keys filters
  function replaceKeyUrlParams(params){ const u = new URL(window.location.href); ['q','active','page','per_page'].forEach(k=>u.searchParams.delete(k)); Object.keys(params).forEach(k=>{ if(params[k] !== null && params[k] !== undefined && params[k] !== '') u.searchParams.set(k, params[k]); }); window.history.replaceState({}, document.title, u.toString()); }

  function renderPagination(meta){
    const container = document.getElementById('pagination-controls'); if(!container) return;
    if (typeof window.renderPager === 'function'){
      window.renderPager(container, {
        page: state.page,
        per_page: state.per_page,
        total: meta.total || 0,
        onPage: (p, per) => { state.page = p; state.per_page = per; loadKeys(); }
      });
      return;
    }
    // fallback: simple pagination
    container.innerHTML = '';
    const select = document.createElement('select'); select.className = 'c-select c-select--inline';
    perPageOptions.forEach(n=>{ const o=document.createElement('option'); o.value=n; o.textContent=n+' / pagina'; if(n==state.per_page) o.selected=true; select.appendChild(o); });
    select.addEventListener('change', ()=>{ state.per_page = parseInt(select.value,10); state.page=1; loadKeys(); });
    container.appendChild(select);
    const total = meta.total||0; const totalPages = meta.total_pages||1;
    function btn(label,p,disabled){ const b=document.createElement('button'); b.textContent=label; b.className='c-btn'; b.style.marginRight='6px'; if(disabled) b.disabled=true; else b.addEventListener('click', ()=>{ state.page=p; loadKeys(); }); return b; }
    container.appendChild(btn('«',1,state.page<=1));
    const start=Math.max(1,state.page-2); const end=Math.min(totalPages,state.page+2);
    for(let p=start;p<=end;p++){ const b=btn(p,p,false); if(p===state.page) b.classList.add('c-btn--primary'); container.appendChild(b); }
    container.appendChild(btn('»',totalPages,state.page>=totalPages));
  }

  async function loadKeys(){
    // collect filters
    const paramsObj = { page: state.page, per_page: state.per_page };
    const fq = document.getElementById('filterQ'); const fa = document.getElementById('filterActive');
    const fp = document.getElementById('filterProvider');
    if(fq && fq.value) paramsObj.q = fq.value.trim();
    if(fa && fa.value !== undefined && fa.value !== '') paramsObj.active = fa.value;
    if(fp && fp.value !== undefined && fp.value !== '') paramsObj.provider = fp.value;
    // update URL
    replaceKeyUrlParams(paramsObj);
    const q = new URLSearchParams(paramsObj);
    const res = await fetch('/admin/keys?'+q.toString(), { headers: {'Accept':'application/json'}, credentials: 'same-origin' });
    const data = await res.json();
    const container = document.getElementById('keysGrid');
    if(!container) return; container.innerHTML = '';

    const headers = ['Nome','Sito','Descrizione','Servizi','Attiva','Azioni'];
    const headerRow = document.createElement('div'); headerRow.className = 'c-grid__row c-grid__header-row';
    headers.forEach(h=>{ const el = document.createElement('div'); el.className='c-grid__header'; el.textContent = h; headerRow.appendChild(el); });
    container.appendChild(headerRow);

    (data.data||[]).forEach(k=>{
      const values = [k.name||k.site_url||'', k.site_url||'', k.description||'', k.providers||[], k.active, ''];
      const row = document.createElement('div'); row.className = 'c-grid__row';
      values.forEach((v, idx)=>{
        const cell = document.createElement('div'); cell.className='c-grid__cell';
        // add data-label for mobile stacked layout
        cell.setAttribute('data-label', headers[idx] || '');
        if(idx === 3){
          // Providers column: render comma-separated or badges
          const provs = Array.isArray(v) ? v : (v ? (Array.isArray(k.providers) ? k.providers : JSON.parse(k.providers)) : []);
          if(!provs || provs.length === 0){ cell.textContent = '-'; }
          else {
            // render provider badges
            provs.forEach(p=>{
              const name = (p || '').toString();
              const span = document.createElement('span');
              const safe = name.toLowerCase().replace(/[^a-z0-9_-]/g,'-');
              span.className = 'c-badge c-badge--small ' + (('c-badge--' + safe));
              span.setAttribute('data-provider', safe);
              span.textContent = name.charAt(0).toUpperCase() + name.slice(1);
              cell.appendChild(span);
            });
          }
        } else if(idx === 4){
          // Active column: show check (active) or rotated plus (inactive)
          const wrap = document.createElement('span'); wrap.className = 'status-icon';
          const iconWrap = document.createElement('span'); iconWrap.className = 'status-icon__svg';
          if(k.active){ iconWrap.classList.add('status-icon--active'); ensureInlineSvg('/assets/imgs/check.svg', iconWrap); }
          else { iconWrap.classList.add('status-icon--inactive'); ensureInlineSvg('/assets/imgs/plus.svg', iconWrap); }
          wrap.appendChild(iconWrap);
          cell.appendChild(wrap);
        } else if(idx<3) { cell.textContent = v; }
        else if(idx === 5){
            // Actions: Show, Revoke/Activate, Regenerate, Delete
            // Show key (inline input + copy icon)
            const showBtn = document.createElement('button'); showBtn.className='c-btn c-icon-btn c-btn--primary'; showBtn.title='Mostra chiave';
            showBtn.setAttribute('aria-label', 'Mostra chiave');
            // inline the eye-open svg instead of a text label for a compact icon button
            ensureInlineSvg('/assets/imgs/eye-open.svg', showBtn);
            showBtn.addEventListener('click', async function(){
              try{
                const res = await fetch('/admin/keys/'+k.id+'/show', { credentials: 'same-origin' });
                if(!res.ok) throw res;
                const j = await res.json();
                // create input-group: input (readonly) + icon button on right
                const wrapper = document.createElement('div'); wrapper.className = 'c-field';
                const label = document.createElement('label'); label.className='c-field__label'; label.textContent = 'Chiave API';
                const ig = document.createElement('div'); ig.className = 'c-input-group';
                const input = document.createElement('input'); input.className = 'c-field__control'; input.type = 'text'; input.readOnly = true; input.value = j.api_key;
                input.style.fontFamily = 'monospace';
                // icon button (copy) - we'll inline svg if possible (use top-level ensureInlineSvg)
                const addon = document.createElement('div'); addon.className = 'c-input-addon';
                const copyBtn = document.createElement('button'); copyBtn.type = 'button'; copyBtn.className = 'c-btn c-icon-btn'; copyBtn.setAttribute('aria-label','Copia chiave');
                ensureInlineSvg('/assets/imgs/copy.svg', copyBtn);
                copyBtn.addEventListener('click', function(){
                  if(navigator.clipboard){ navigator.clipboard.writeText(input.value).then(()=>{ window.showToast && window.showToast('Copiato', {type:'success'}); }).catch(()=>{ window.showToast && window.showToast('Copia fallita', {type:'danger'}); });
                  } else { try{ const ta = document.createElement('textarea'); ta.value = input.value; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); window.showToast && window.showToast('Copiato', {type:'success'}); }catch(e){ window.showToast && window.showToast('Copia fallita', {type:'danger'}); } }
                });
                addon.appendChild(copyBtn);
                ig.appendChild(input); ig.appendChild(addon);
                wrapper.appendChild(label); wrapper.appendChild(ig);
                // ensure input has no forced white bg; rely on c-field__control for styling
                window.Modal && window.Modal.open(wrapper, { title: 'Chiave API' });
                // auto-select on open
                setTimeout(()=>{ try{ input.select(); }catch(e){} }, 50);
              }catch(err){ window.showToast && window.showToast('Errore recupero chiave', {type:'danger'}); }
            });
            cell.appendChild(showBtn);

            // Edit button
            const editBtn = document.createElement('button'); editBtn.className='c-btn c-btn--secondary'; editBtn.textContent='Modifica';
            editBtn.addEventListener('click', function(){
              // build edit form prefilled with values
              const wrapper = document.createElement('form'); wrapper.style.display='flex'; wrapper.style.flexDirection='column'; wrapper.style.gap='12px';
              // name
              const fName = document.createElement('div'); fName.className='c-field';
              const lName = document.createElement('label'); lName.className='c-field__label'; lName.textContent='Nome chiave';
              const iName = document.createElement('input'); iName.type='text'; iName.name='name'; iName.className='c-field__control'; iName.value = k.name || '';
              fName.appendChild(lName); fName.appendChild(iName);
              // site_url
              const fSite = document.createElement('div'); fSite.className='c-field';
              const lSite = document.createElement('label'); lSite.className='c-field__label'; lSite.textContent='URL del sito';
              const iSite = document.createElement('input'); iSite.type='text'; iSite.name='site_url'; iSite.className='c-field__control'; iSite.value = k.site_url || '';
              const hint = document.createElement('div'); hint.className='c-field__hint'; hint.innerHTML = 'Puoi usare wildcard, es.: <code>*.esempio.it/*</code>';
              fSite.appendChild(lSite); fSite.appendChild(iSite); fSite.appendChild(hint);
              // description
              const fDesc = document.createElement('div'); fDesc.className='c-field';
              const lDesc = document.createElement('label'); lDesc.className='c-field__label'; lDesc.textContent='Descrizione';
              const tDesc = document.createElement('textarea'); tDesc.name='description'; tDesc.className='c-field__control'; tDesc.rows=4; tDesc.value = k.description || '';
              fDesc.appendChild(lDesc); fDesc.appendChild(tDesc);
              // attach character counter for description textarea (same behaviour as create form)
              try{
                const max = parseInt(tDesc.getAttribute('maxlength') || '255', 10);
                const helper = document.createElement('div'); helper.className = 'c-field__helper';
                helper.innerHTML = '<small class="c-field__hint">Lunghezza: <span class="js-char-count">0</span>/' + max + '</small>';
                tDesc.insertAdjacentElement('afterend', helper);
                const updateDescCount = function(){ const cnt = helper.querySelector('.js-char-count'); if(cnt) cnt.textContent = tDesc.value.length; };
                tDesc.addEventListener('input', updateDescCount);
                updateDescCount();
              }catch(e){ /* ignore */ }
              // providers (checkboxes) if available on page (create form had them). Try to reuse availableProviders from DOM if present.
              const provSection = document.createElement('div'); provSection.className='c-field';
              const provLabel = document.createElement('label'); provLabel.className='c-field__label'; provLabel.textContent='Provider abilitati'; provSection.appendChild(provLabel);
              const provWrap = document.createElement('div'); provWrap.style.display='flex'; provWrap.style.flexWrap='wrap'; provWrap.style.gap='8px';
              // attempt to extract available providers from create form template if present
              const tmpl = document.getElementById('createKeyFormTemplate');
              let avail = [];
              if(tmpl){ const inputs = tmpl.querySelectorAll('input[name="providers[]"]'); if(inputs && inputs.length){ inputs.forEach(inp=>{ if(!avail.includes(inp.value)) avail.push(inp.value); }); } }
              // fallback: if k.providers present and non-empty, use those as avail list
              if(avail.length===0 && Array.isArray(k.providers) && k.providers.length>0){ avail = k.providers; }
              if(avail.length>0){
                avail.forEach(p=>{
                  const lab = document.createElement('label'); lab.style.display='flex'; lab.style.alignItems='center'; lab.style.gap='6px';
                  const cb = document.createElement('input'); cb.type='checkbox'; cb.name='providers[]'; cb.value = p; if(Array.isArray(k.providers) && k.providers.indexOf(p)!==-1) cb.checked = true;
                  lab.appendChild(cb); lab.appendChild(document.createTextNode(p.charAt(0).toUpperCase()+p.slice(1)));
                  provWrap.appendChild(lab);
                });
                provSection.appendChild(provWrap);
              }
              // active
              const fActive = document.createElement('div'); fActive.className='c-field';
              const labActive = document.createElement('label'); labActive.style.display='flex'; labActive.style.alignItems='center'; labActive.style.gap='8px';
              const cbActive = document.createElement('input'); cbActive.type='checkbox'; cbActive.name='active'; cbActive.checked = !!k.active;
              labActive.appendChild(cbActive); labActive.appendChild(document.createTextNode('Attiva'));
              fActive.appendChild(labActive);
              // submit
              const submit = document.createElement('button'); submit.type='submit'; submit.className='c-btn c-btn--primary'; submit.textContent = 'Salva';
              wrapper.appendChild(fName); wrapper.appendChild(fSite); wrapper.appendChild(fDesc); if(avail.length>0) wrapper.appendChild(provSection); wrapper.appendChild(fActive); wrapper.appendChild(submit);

              wrapper.addEventListener('submit', async function(ev){
                ev.preventDefault();
                const fd = new FormData(wrapper);
                // client-side validate site_url before sending
                const siteVal = (fd.get('site_url') || '').toString();
                if(!isValidSiteUrl(siteVal)){
                  window.showToast && window.showToast('URL sito non valido', {type:'danger'});
                  return;
                }
                const providers = fd.getAll('providers[]');
                const payload = { name: fd.get('name'), site_url: fd.get('site_url'), description: fd.get('description'), active: fd.get('active') ? 1 : 0, providers: providers && providers.length ? providers : [] };
                try{ const res = await fetch('/admin/keys/'+k.id, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload), credentials:'same-origin' }); if(res.ok){ window.showToast && window.showToast('Chiave aggiornata', {type:'success'}); window.Modal && window.Modal.close(); loadKeys(); } else { const j = await res.json(); window.showToast && window.showToast('Errore: '+(j.error||'sconosciuto'), {type:'danger'}); } }catch(e){ window.showToast && window.showToast('Errore rete', {type:'danger'}); }
              });

              window.Modal && window.Modal.open(wrapper, { title: 'Modifica chiave' });
            });
            cell.appendChild(editBtn);

            // Revoke (if active) or Rigenera (if inactive) — single toggle button
            const toggleBtn = document.createElement('button');
            if(k.active){
              toggleBtn.className = 'c-btn c-btn--warning';
              toggleBtn.textContent = 'Revoca';
              toggleBtn.addEventListener('click', async function(){
                try{
                  const res = await fetch('/admin/keys/'+k.id+'/revoke', { method:'POST', credentials:'same-origin' });
                  if(res.ok){ window.showToast && window.showToast('Chiave revocata', {type:'success'}); loadKeys(); }
                  else { window.showToast && window.showToast('Revoca fallita', {type:'danger'}); }
                }catch(e){ window.showToast && window.showToast('Errore di rete', {type:'danger'}); }
              });
            } else {
              toggleBtn.className = 'c-btn c-btn--success';
              toggleBtn.textContent = 'Rigenera';
              toggleBtn.addEventListener('click', async function(){
                try{
                  const res = await fetch('/admin/keys/'+k.id+'/regenerate', { method:'POST', credentials:'same-origin' });
                  if(!res.ok) { window.showToast && window.showToast('Rigenerazione fallita', {type:'danger'}); return; }
                  const j = await res.json();
                  const wrapper = document.createElement('div'); wrapper.className = 'c-field';
                  const label = document.createElement('label'); label.className='c-field__label'; label.textContent = 'Nuova chiave';
                  const ig = document.createElement('div'); ig.className = 'c-input-group';
                  const input = document.createElement('input'); input.className = 'c-field__control'; input.type = 'text'; input.readOnly = true; input.value = j.api_key;
                  input.style.fontFamily = 'monospace';
                  const addon = document.createElement('div'); addon.className = 'c-input-addon';
                  const copyBtn = document.createElement('button'); copyBtn.type='button'; copyBtn.className='c-btn c-icon-btn'; copyBtn.setAttribute('aria-label','Copia chiave e chiudi');
                  ensureInlineSvg('/assets/imgs/copy.svg', copyBtn);
                  copyBtn.addEventListener('click', function(){
                    if(navigator.clipboard){ navigator.clipboard.writeText(input.value).then(()=>{ window.showToast && window.showToast('Copiato', {type:'success'}); window.Modal && window.Modal.close(); }).catch(()=>{ window.showToast && window.showToast('Copia fallita', {type:'danger'}); });
                    } else { try{ const ta = document.createElement('textarea'); ta.value = input.value; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); window.showToast && window.showToast('Copiato', {type:'success'}); window.Modal && window.Modal.close(); }catch(e){ window.showToast && window.showToast('Copia fallita', {type:'danger'}); } }
                  });
                  addon.appendChild(copyBtn);
                  ig.appendChild(input); ig.appendChild(addon);
                  wrapper.appendChild(label); wrapper.appendChild(ig);
                  window.Modal && window.Modal.open(wrapper, { title: 'Nuova chiave generata' });
                  // auto-select the generated key for quick copy
                  setTimeout(()=>{ try{ input.select(); }catch(e){} }, 50);
                  loadKeys();
                }catch(e){ window.showToast && window.showToast('Errore di rete', {type:'danger'}); }
              });
            }

            // attach toggle into actions
            cell.appendChild(toggleBtn);

            // Delete (kept)
            const del = document.createElement('button'); del.textContent='Elimina'; del.setAttribute('aria-label','Elimina chiave '+k.id); del.className='c-btn c-btn--danger';
            del.addEventListener('click', async function(){
              if (window.Modal && typeof window.Modal.confirm === 'function'){
                window.Modal.confirm('Eliminare chiave '+k.id+'?', async function(){ await fetch('/admin/keys/'+k.id+'/delete', { method:'POST', credentials:'same-origin' }); loadKeys(); });
              } else {
                if (!confirm('Eliminare chiave '+k.id+'?')) return; await fetch('/admin/keys/'+k.id+'/delete', { method:'POST', credentials:'same-origin' }); loadKeys();
              }
            });
            cell.appendChild(del);

                // mark this cell as actions so mobile CSS can hide the label
              cell.classList.add('c-grid__cell--actions');
        }
        row.appendChild(cell);
      });
      container.appendChild(row);
    });
    renderPagination(data.meta||{ total:0, page:state.page, per_page:state.per_page, total_pages:1 });
  }

  // initialize filters UI and events
  attachAutocompleteKey('filterQ','filterQList');
  document.getElementById('applyKeyFilters')?.addEventListener('click', function(e){ e.preventDefault(); state.page = 1; loadKeys(); });
  document.getElementById('clearKeyFilters')?.addEventListener('click', function(e){ e.preventDefault(); const fq = document.getElementById('filterQ'); const fa = document.getElementById('filterActive'); const fp = document.getElementById('filterProvider'); if(fq) fq.value=''; if(fa) fa.value=''; if(fp) fp.value=''; state.page = 1; loadKeys(); });

  // populate filters from URL if present
  (function(){ try{ const params = new URLSearchParams(window.location.search); const q = params.get('q'); const a = params.get('active'); const p = params.get('provider'); if(q) document.getElementById('filterQ').value = q; if(a !== null) document.getElementById('filterActive').value = a; if(p !== null && document.getElementById('filterProvider')) document.getElementById('filterProvider').value = p; const qp = parseInt(params.get('page'),10); const qper = parseInt(params.get('per_page'),10); if(!isNaN(qp) && qp>0) state.page = qp; if(!isNaN(qper) && qper>0) state.per_page = qper; }catch(e){} })();

  // create key via modal form
  document.getElementById('openCreateKeyBtn')?.addEventListener('click', function(){
    const tmpl = document.getElementById('createKeyFormTemplate'); if(!tmpl) return;
    const node = tmpl.firstElementChild.cloneNode(true);
    // attach character counter for description textarea (if present)
    const desc = node.querySelector('textarea[name="description"]');
    if(desc){
      const max = parseInt(desc.getAttribute('maxlength') || '255', 10);
      const helper = document.createElement('div'); helper.className = 'c-field__helper';
      helper.innerHTML = '<small class="c-field__hint">Lunghezza: <span class="js-char-count">0</span>/' + max + '</small>';
      desc.insertAdjacentElement('afterend', helper);
      const update = function(){ const cnt = helper.querySelector('.js-char-count'); if(cnt) cnt.textContent = desc.value.length; };
      desc.addEventListener('input', update);
      update();
    }

    node.addEventListener('submit', async function(e){
      e.preventDefault();
      const fd = new FormData(node);
      const siteVal = (fd.get('site_url') || '').toString();
      if(!isValidSiteUrl(siteVal)){
        window.showToast && window.showToast('URL sito non valido', {type:'danger'});
        return;
      }
      const providers = fd.getAll('providers[]');
      const payload = {
        site_url: fd.get('site_url'),
        name: fd.get('name'),
        description: fd.get('description'),
        active: fd.get('active') ? 1 : 0,
        providers: providers && providers.length ? providers : []
      };
      try{
        const res = await fetch('/admin/keys', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload), credentials: 'same-origin' });
        if(res.ok){ window.showToast && window.showToast('Chiave creata', { type:'success' }); loadKeys(); window.Modal && window.Modal.close(); }
        else { const j = await res.json(); window.showToast && window.showToast('Errore: '+(j.error||'sconosciuto'), { type:'danger' }); }
      }catch(err){ window.showToast && window.showToast('Errore di rete', { type:'danger' }); }
    });
    window.Modal && window.Modal.open(node, { title: 'Crea chiave' });
  });

  loadKeys();
});
