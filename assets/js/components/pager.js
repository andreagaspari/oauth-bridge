// Pager component: lightweight renderer that uses c-select and c-btn classes
window.renderPager = function(container, { page=1, per_page=25, total=0, onPage, syncUrl=true } = {}){
  container.innerHTML = '';
  // store base title to append page info
  const baseTitle = document.title || document.querySelector('title')?.textContent || '';
  function setTitle(p){
    try{
      if(!p || p <= 1) document.title = baseTitle;
      else document.title = baseTitle + ' — Pagina ' + p;
    }catch(e){}
  }

  // if requested, read page/per_page from URL to initialize
  if(syncUrl){
    try{
      const params = new URLSearchParams(window.location.search);
      const qp = parseInt(params.get('page'),10);
      const qper = parseInt(params.get('per_page'),10);
      if(!isNaN(qp) && qp > 0) page = qp;
      if(!isNaN(qper) && qper > 0) per_page = qper;
    }catch(e){ /* ignore */ }
  }
  // apply initial title
  setTitle(page);
  const totalPages = Math.max(1, Math.ceil(total / per_page));
  const wrapper = document.createElement('div'); wrapper.className = 'c-pager';

  // info text (Elementi A - B su C)
  const info = document.createElement('div'); info.className = 'c-pager__info';
  function updateInfo(){
    if(!total || total <= 0){ info.textContent = 'Elementi 0 su 0'; return; }
    const startItem = Math.min(total, (page-1)*per_page + 1);
    const endItem = Math.min(total, page*per_page);
    info.textContent = `Elementi ${startItem} - ${endItem} su ${total}`;
  }
  updateInfo();

  // page buttons
  const pages = document.createElement('div'); pages.className='c-pager__pages';
  function updateUrl(p, per, mode){
    if(!syncUrl) return;
    try{
      const url = new URL(window.location.href);
      const params = new URLSearchParams(url.search);
      params.set('page', String(p));
      params.set('per_page', String(per));
      url.search = params.toString();
      // update title to include page number
      setTitle(p);
      if(mode === 'replace'){
        window.history.replaceState({}, '', url.toString());
      } else {
        // default: push a new history entry so back/forward navigates pages
        window.history.pushState({}, '', url.toString());
      }
    }catch(e){ /* ignore */ }
  }

  function btn(label, p, disabled){ const b = document.createElement('button'); b.className='c-btn'; b.textContent = label; if(disabled) b.disabled=true; else b.addEventListener('click', ()=>{ updateUrl(p, parseInt(select.value,10), 'push'); if(typeof onPage === 'function') onPage(p, parseInt(select.value,10)); }); return b; }
  pages.appendChild(btn('«', 1, page<=1));
  const start = Math.max(1, page-2); const end = Math.min(totalPages, page+2);
  for(let p=start;p<=end;p++){ const b = btn(p, p, false); if(p===page) b.classList.add('c-btn--primary'); pages.appendChild(b); }
  pages.appendChild(btn('»', totalPages, page>=totalPages));

  // pages (will be placed at the same level as info and per-page select)

  // per-page select
  const perWrap = document.createElement('div'); perWrap.className = 'c-pager__per';
  const select = document.createElement('select');
  select.className = 'c-select';
  // accessibility: ensure form field has a name and id
  select.name = 'per_page';
  select.id = 'pager-perpage-' + Date.now().toString(36) + '-' + Math.floor(Math.random()*1000);
  select.setAttribute('aria-label', 'Elementi per pagina');
  [10,25,50,100].forEach(n => { const o = document.createElement('option'); o.value=n; o.textContent = n+' / pagina'; if(n==per_page) o.selected=true; select.appendChild(o); });
  select.addEventListener('change', ()=>{ const v = parseInt(select.value,10); updateUrl(1, v, 'replace'); if(typeof onPage === 'function') onPage(1, v); });

  // ensure popstate navigations call onPage so back/forward actually changes content
  // remove previous handler if present on the container wrapper
  if(wrapper._pagerPopHandler) window.removeEventListener('popstate', wrapper._pagerPopHandler);
  const popHandler = function(){
    try{
      const params = new URLSearchParams(window.location.search);
      const qp = parseInt(params.get('page'),10) || 1;
      const qper = parseInt(params.get('per_page'),10) || per_page;
      setTitle(qp);
      if(typeof onPage === 'function') onPage(qp, qper);
    }catch(e){}
  };
  wrapper._pagerPopHandler = popHandler;
  window.addEventListener('popstate', popHandler);
  // wrap select so the custom arrow pseudo-element appears
  const selectWrap = document.createElement('div'); selectWrap.className = 'c-select-wrapper'; selectWrap.appendChild(select);
  perWrap.appendChild(selectWrap);
  // append info, pages and per-select as siblings so the parent flex can distribute them
  wrapper.appendChild(info);
  wrapper.appendChild(pages);
  wrapper.appendChild(perWrap);

  // Append to container
  container.appendChild(wrapper);
  return wrapper;
};
