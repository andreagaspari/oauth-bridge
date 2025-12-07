// Admin UI helper: sidebar collapse, hamburger toggle, persist state
(function(){
  const shell = document.getElementById('adminShell');
  const sidebar = document.getElementById('adminSidebar');
  const hamburger = document.getElementById('hamburgerBtn');

  function applyState(){
    const collapsed = localStorage.getItem('admin.sidebarCollapsed') === '1';
    if(collapsed) shell.classList.add('sidebar-collapsed'); else shell.classList.remove('sidebar-collapsed');
  }

  // Toggle collapse when clicking on sidebar icons area (desktop) or hamburger (mobile)
  sidebar?.addEventListener('dblclick', function(){
    const collapsed = shell.classList.toggle('sidebar-collapsed');
    localStorage.setItem('admin.sidebarCollapsed', collapsed? '1':'0');
  });

  hamburger?.addEventListener('click', function(e){
    // on mobile, toggle visibility
    shell.classList.toggle('show-sidebar');
  });

  // Allow simple single-click on header left area to collapse on desktop
  document.querySelector('.admin-header .left')?.addEventListener('click', function(e){
    if(window.matchMedia('(min-width:900px)').matches){
      const collapsed = shell.classList.toggle('sidebar-collapsed');
      localStorage.setItem('admin.sidebarCollapsed', collapsed? '1':'0');
    }
  });

  // Logout/profile handlers
  document.getElementById('logoutBtn')?.addEventListener('click', async function(){
    await fetch('/admin/logout', { method:'POST', credentials:'same-origin' });
    window.location.href = '/admin/login';
  });
  document.getElementById('profileBtn')?.addEventListener('click', function(){
    alert('Profilo (non ancora implementato)');
  });

  // Sidebar footer toggle button (shows chevron and label)
  const sidebarToggle = document.getElementById('sidebarToggle');
  function updateSidebarToggle(){
    const collapsed = shell.classList.contains('sidebar-collapsed');
    if(!sidebarToggle) return;
    const icon = sidebarToggle.querySelector('.toggle-icon');
    const label = sidebarToggle.querySelector('.label');
    if(collapsed){
      if(icon) icon.classList.add('rotated');
      if(label) label.textContent = '';
      sidebarToggle.setAttribute('title','Apri sidebar');
    } else {
      if(icon) icon.classList.remove('rotated');
      if(label) label.textContent = 'Riduci';
      sidebarToggle.setAttribute('title','Riduci sidebar');
    }
  }
  sidebarToggle?.addEventListener('click', function(e){
    const collapsed = shell.classList.toggle('sidebar-collapsed');
    localStorage.setItem('admin.sidebarCollapsed', collapsed? '1':'0');
    updateSidebarToggle();
  });
  // init: apply persisted state first, then update the toggle UI
  applyState();
  updateSidebarToggle();
})();
