// Toast notifications: basic API
var _toast_container = (function(){
  let c = document.querySelector('.c-toast-container');
  if(!c){ c = document.createElement('div'); c.className='c-toast-container'; document.body.appendChild(c); }
  return c;
})();

window.showToast = function(message, { type='default', timeout=3500 } = {}){
  const el = document.createElement('div');
  el.className = 'c-toast' + (type && type!=='default' ? ' c-toast--'+type : '');
  el.textContent = message;
  // ensure toast is not focusable and clicks do not propagate to underlying UI
  el.tabIndex = -1;
  el.setAttribute('role','status');
  el.addEventListener('click', function(ev){ ev.stopPropagation(); });
  _toast_container.appendChild(el);
  // small delay to trigger transition
  requestAnimationFrame(()=> el.classList.add('is-visible'));
  setTimeout(()=>{
    el.classList.remove('is-visible');
    setTimeout(()=> el.remove(), 220);
  }, timeout);
  return el;
};
