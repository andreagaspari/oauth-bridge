// Logs page JS
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

  function renderPagination(meta){
    const container = document.getElementById('pagination-controls'); if(!container) return;
    if (typeof window.renderPager === 'function'){
      window.renderPager(container, {
        page: state.page,
        per_page: state.per_page,
        total: meta.total || 0,
        onPage: (p, per) => { state.page = p; state.per_page = per; loadLogs(); }
      });
      return;
    }
    // fallback: simple pagination omitted
  }

  // provider normalization map (display label -> provider value)
  const providerMap = {
    'auth':'auth',
    'user':'user',
    'site_key':'site_key',
    'callback':'callback',
    'google':'google'
  };

  // helper to set URL query params without adding history (replaceState)
  function replaceUrlParams(params){
    const u = new URL(window.location.href);
    // clear existing filter params first
    ['source','provider','ip','date_from','date_to','page','per_page'].forEach(k=>u.searchParams.delete(k));
    Object.keys(params).forEach(k=>{
      if(params[k] !== null && params[k] !== undefined && params[k] !== '') u.searchParams.set(k, params[k]);
    });
    window.history.replaceState({}, document.title, u.toString());
  }

  // Suggestions: fetch from server endpoints
  async function fetchSuggestions(type, q){
    try{
      const url = '/admin/logs/suggestions?type=' + encodeURIComponent(type) + '&q=' + encodeURIComponent(q || '');
      const res = await fetch(url, { credentials: 'same-origin', headers:{'Accept':'application/json'} });
      const data = await res.json();
      return data.data || [];
    }catch(e){ return []; }
  }

  // Build provider select options
  function initProviderSelect(){
    const sel = document.getElementById('filterProvider'); if(!sel) return;
    // If the select already has options (server-populated, e.g. on keys page showing real services), do not overwrite
    try{ if(sel.options && sel.options.length > 1) return; }catch(e){}
    Object.keys(providerMap).forEach(k=>{
      const opt = document.createElement('option'); opt.value = providerMap[k]; opt.textContent = k; sel.appendChild(opt);
    });
  }

  // Autocomplete basic UI for source and ip
  function attachAutocomplete(inputId, listId, type){
    const inp = document.getElementById(inputId); const lst = document.getElementById(listId);
    if(!inp || !lst) return;
    let timeout = null;
    let suggestions = [];
    let highlighted = -1;
    // use component class for styling; JS only controls visibility and top positioning
    lst.classList.add('c-autocomplete-list');
    lst.classList.remove('visible');

    function clearHighlights(){
      const kids = lst.querySelectorAll('.c-autocomplete-item');
      kids.forEach(k=>{ k.classList.remove('c-autocomplete-item--highlighted'); k.setAttribute('aria-selected','false'); });
      highlighted = -1;
    }
    function updateHighlight(idx){
      const kids = lst.querySelectorAll('.c-autocomplete-item');
      if(kids.length===0) return;
      if(highlighted >=0 && highlighted < kids.length){ kids[highlighted].classList.remove('c-autocomplete-item--highlighted'); kids[highlighted].setAttribute('aria-selected','false'); }
      highlighted = idx;
      if(highlighted < 0) highlighted = 0;
      if(highlighted >= kids.length) highlighted = kids.length -1;
      const el = kids[highlighted];
      el.classList.add('c-autocomplete-item--highlighted');
      el.setAttribute('aria-selected','true');
      el.scrollIntoView({block:'nearest'});
    }

    inp.addEventListener('input', function(){
      const v = inp.value.trim();
      if(timeout) clearTimeout(timeout);
      if(v.length < 1){ lst.classList.remove('visible'); return; }
      timeout = setTimeout(async ()=>{
        let items = [];
        if(v.length >= 3){ items = await fetchSuggestions(type, v) || []; }
        // Only show 'Sconosciuta' when the user explicitly types something starting with 'sco'
        // Filter it out otherwise (server may return it for broader matches).
        if(type === 'source' && !/^sco/i.test(v)){
          items = (items || []).filter(i => i !== 'Sconosciuta');
        } else if(/^sco/i.test(v)){
          if(!items.includes('Sconosciuta')) items.unshift('Sconosciuta');
        }
        suggestions = items.slice();
        lst.innerHTML = '';
        if(!suggestions || suggestions.length===0){ lst.classList.remove('visible'); return; }
        suggestions.forEach((it, idx)=>{
          const d = document.createElement('div'); d.className='c-field__hint c-autocomplete-item'; d.textContent = it;
          d.tabIndex = 0;
          d.setAttribute('role','option');
          d.addEventListener('click', ()=>{ inp.value = it; lst.classList.remove('visible'); inp.focus(); });
          d.addEventListener('keydown', (ev)=>{ if(ev.key === 'Enter'){ ev.preventDefault(); inp.value = it; lst.classList.remove('visible'); inp.focus(); } });
          d.addEventListener('mouseenter', ()=>{ clearHighlights(); updateHighlight(idx); });
          d.addEventListener('mouseleave', ()=>{ d.classList.remove('c-autocomplete-item--highlighted'); d.setAttribute('aria-selected','false'); highlighted = -1; });
          lst.appendChild(d);
        });
        // position update in case layout changed
        lst.style.top = (inp.offsetTop + inp.offsetHeight) + 'px';
        lst.classList.add('visible');
        clearHighlights();
      }, 200);
    });

    // close on outside click
    document.addEventListener('click', function(e){ if(!lst.contains(e.target) && e.target !== inp){ lst.classList.remove('visible'); } });

    // keyboard handling: Arrow nav, Enter selects highlighted or accepts typed value, Escape closes
    inp.addEventListener('keydown', function(e){
      if(e.key === 'ArrowDown' && lst.classList.contains('visible')){
        e.preventDefault();
        const kids = lst.querySelectorAll('.c-autocomplete-item');
        if(kids.length===0) return;
        if(highlighted < kids.length -1) updateHighlight(highlighted + 1); else updateHighlight(0);
      } else if(e.key === 'ArrowUp' && lst.classList.contains('visible')){
        e.preventDefault();
        const kids = lst.querySelectorAll('.c-autocomplete-item');
        if(kids.length===0) return;
        if(highlighted > 0) updateHighlight(highlighted - 1); else updateHighlight(kids.length -1);
      } else if(e.key === 'Enter' && lst.classList.contains('visible')){
        e.preventDefault();
        const kids = lst.querySelectorAll('.c-autocomplete-item');
        if(highlighted >=0 && kids[highlighted]){
          inp.value = kids[highlighted].textContent;
        } else {
          // accept the typed value even if it's not among suggestions
        }
        lst.classList.remove('visible');
      } else if(e.key === 'Escape'){
        lst.classList.remove('visible');
      }
    });
  }

  function formatDateInput(date){
    // date is Date object -> return yyyy-mm-dd for input[type=date]
    const yyyy = date.getFullYear(); const mm = String(date.getMonth()+1).padStart(2,'0'); const dd = String(date.getDate()).padStart(2,'0');
    return `${yyyy}-${mm}-${dd}`;
  }

  function applyPreset(value){
    const from = document.getElementById('filterDateFrom'); const to = document.getElementById('filterDateTo');
    const now = new Date();
    let start = null; let end = null;
    if(value === 'today'){ start = new Date(now); end = new Date(now); }
    else if(value === 'yesterday'){ start = new Date(now); start.setDate(now.getDate()-1); end = new Date(start); }
    else if(value === '7'){ start = new Date(now); start.setDate(now.getDate()-6); end = new Date(now); }
    else if(value === '30'){ start = new Date(now); start.setDate(now.getDate()-29); end = new Date(now); }
    else if(value === '90'){ start = new Date(now); start.setDate(now.getDate()-89); end = new Date(now); }
    if(start && end){ from.value = formatDateInput(start); to.value = formatDateInput(end); }
  }


  async function loadLogs(){
    const params = { page: state.page, per_page: state.per_page };
    // include filters from inputs if present
    const src = document.getElementById('filterSource');
    const prov = document.getElementById('filterProvider');
    const ipi = document.getElementById('filterIp');
    const df = document.getElementById('filterDateFrom');
    const dt = document.getElementById('filterDateTo');
    if(src && src.value) params.source = src.value.trim();
    if(prov && prov.value) params.provider = prov.value;
    if(ipi && ipi.value) params.ip = ipi.value.trim();
    if(df && df.value) params.date_from = df.value;
    if(dt && dt.value) params.date_to = dt.value;

    // update URL without adding history
    replaceUrlParams(params);

    const q = new URLSearchParams(params);
    const res = await fetch('/admin/logs?'+q.toString(), { headers: {'Accept':'application/json'}, credentials: 'same-origin' });
    const data = await res.json();
    
    const container = document.getElementById('logsGrid');
    if(!container) return;
    container.innerHTML = '';

    // headers
    const headers = ['Sorgente','Provider','Azione','Payload','IP','Data e Ora'];
    const headerRow = document.createElement('div'); headerRow.className = 'c-grid__row c-grid__header-row';
    headers.forEach(h=>{ const el = document.createElement('div'); el.className='c-grid__header'; el.textContent = h; headerRow.appendChild(el); });
    container.appendChild(headerRow);

    (data.data||[]).forEach(r=>{
      const payload = r.payload ? (typeof r.payload === 'string' ? r.payload : JSON.stringify(r.payload)) : '';
      const source = r.source || r.site_name || r.site_url || '';
      const provider = r.provider || '';
      const action = r.action || '';
      const created = r.created_at || r.created || r.createdAt || '';
      const ip = r.ip || r.ip_address || (r.payload && r.payload._ip) || '';

      // normalize provider display
      const provDisplay = providerMap[provider] || provider || '';
      const cells = [source, provDisplay, action, payload, ip, created];
      const row = document.createElement('div'); row.className = 'c-grid__row';
      cells.forEach((c, idx)=>{
        const cell = document.createElement('div');
        cell.className = 'c-grid__cell' + (idx===3 ? ' c-grid__cell--truncate' : '');
        // add data-label for mobile stacked layout
        cell.setAttribute('data-label', headers[idx] || '');
        // render text-safely
        if(idx === 3){
          // Payload column: show truncated preview in cell, open modal on click to view full payload
          const preview = document.createElement('div');
          preview.className = 'c-log-payload-preview';
          const text = String(c || '');
          // show preview (keep truncation via CSS)
          preview.textContent = text;
          preview.title = text;
          preview.style.cursor = 'pointer';
          preview.addEventListener('click', function(e){
            e.preventDefault();
            // try to pretty-print JSON if possible
            let formatted = text;
            try{ const parsed = JSON.parse(text); formatted = JSON.stringify(parsed, null, 2); } catch(e) { /* not JSON */ }
            const wrapper = document.createElement('div');
            const pre = document.createElement('pre'); pre.className = 'c-log-payload__pre'; pre.textContent = formatted;
            wrapper.appendChild(pre);
            // actions: copy + close
            const actions = [
              { label: 'Copia', className: 'c-btn', onClick: function(){
                try{
                  if(navigator.clipboard) navigator.clipboard.writeText(text).then(()=>{ window.showToast && window.showToast('Copiato', {type:'success'}); }).catch(()=>{ window.showToast && window.showToast('Copia fallita', {type:'danger'}); });
                }catch(e){ window.showToast && window.showToast('Copia fallita', {type:'danger'}); }
              } },
              { label: 'Chiudi', className: 'c-btn c-btn--primary', onClick: function(){ window.Modal && window.Modal.close(); } }
            ];
            window.Modal && window.Modal.open(wrapper, { title: 'Payload', actions: actions });
          });
          cell.appendChild(preview);
        } else {
          cell.textContent = String(c || '');
        }
        row.appendChild(cell);
      });
      container.appendChild(row);
    });

    renderPagination(data.meta||{ total:0, page:state.page, per_page:state.per_page, total_pages:1 });
  }

  window.loadLogs = loadLogs;
  // initialize UI elements
  initProviderSelect();
  attachAutocomplete('filterSource','filterSourceList','source');
  attachAutocomplete('filterIp','filterIpList','ip');
  document.getElementById('filterPreset')?.addEventListener('change', function(e){ applyPreset(e.target.value); });
  document.getElementById('applyFilters')?.addEventListener('click', function(e){ e.preventDefault(); state.page = 1; loadLogs(); });
  document.getElementById('clearFilters')?.addEventListener('click', function(e){ e.preventDefault();
    // reset all filter inputs, including preset select
    ['filterSource','filterProvider','filterIp','filterDateFrom','filterDateTo'].forEach(id=>{ const el=document.getElementById(id); if(el) el.value=''; });
    const preset = document.getElementById('filterPreset'); if(preset) { preset.value = 'all'; preset.dispatchEvent(new Event('change')); }
    state.page = 1; loadLogs();
  });

  // populate inputs from URL if present
  (function(){
    try{
      const params = new URLSearchParams(window.location.search);
      const s = params.get('source'); const p = params.get('provider'); const i = params.get('ip');
      const df = params.get('date_from'); const dt = params.get('date_to');
      if(s) document.getElementById('filterSource').value = s;
      if(p) document.getElementById('filterProvider').value = p;
      if(i) document.getElementById('filterIp').value = i;
      if(df) document.getElementById('filterDateFrom').value = df;
      if(dt) document.getElementById('filterDateTo').value = dt;
      const qp = parseInt(params.get('page'),10); const qper = parseInt(params.get('per_page'),10);
      if(!isNaN(qp) && qp>0) state.page = qp; if(!isNaN(qper) && qper>0) state.per_page = qper;
    }catch(e){}
  })();

  loadLogs();
});
