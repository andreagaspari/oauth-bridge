// Login page JS
document.addEventListener('DOMContentLoaded', function(){
  const form = document.getElementById('loginForm');
  const msg = document.getElementById('message');
  const pwd = document.getElementById('passwordInput');
  const toggle = document.getElementById('togglePwd');
  // use component API if available
  if (window.Components && toggle && pwd) {
    window.Components.togglePassword('#togglePwd', '#passwordInput');
  }
  form?.addEventListener('submit', async function(e){
    e.preventDefault();
    const f = new FormData(e.target);
    const body = { email: f.get('email'), password: f.get('password') };
    const res = await fetch('/admin/login', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body), credentials: 'same-origin' });
    const data = await res.json();
    if(res.ok && data.ok){ window.location.href = '/admin'; }
    else {
      if(!msg) return;
      const text = data.message || data.error || 'Credenziali errate';
      // prefer using the Alert component if available
      if(typeof window.renderAlert === 'function'){
        msg.innerHTML = '';
        window.renderAlert(msg, { message: text, type: 'danger' });
      } else {
        msg.textContent = text;
        msg.style.display = 'block';
        msg.classList.add('c-field__hint');
      }
    }
  });
});
