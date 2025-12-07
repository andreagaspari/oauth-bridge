// Simple Alert helper: can be used to render inline alerts
window.renderAlert = function(container, { title = '', message = '', type = 'default' } = {}){
  const el = document.createElement('div');
  el.className = 'c-alert' + (type? ' c-alert--'+type : '');
  if(title) el.innerHTML = '<div class="c-alert__title">'+title+'</div>';
  const body = document.createElement('div'); body.className='c-alert__body'; body.textContent = message;
  el.appendChild(body);
  container.appendChild(el);
  return el;
};
