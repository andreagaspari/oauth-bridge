(function(window){
  window.Components = window.Components || {};

  // Attach toggle behavior to a given toggle button and input selector
  // Usage: Components.togglePassword('#togglePwd','#passwordInput')
  window.Components.togglePassword = function(toggleSelector, inputSelector){
    const toggle = document.querySelector(toggleSelector);
    const input = document.querySelector(inputSelector);
    if(!toggle || !input) return;

    // avoid double-initialization on same element
    if (toggle.__pw_api) return toggle.__pw_api;

    function setClosed(){
      toggle.classList.remove('is-open');
      toggle.setAttribute('aria-label','Mostra password');
      input.type = 'password';
    }
    function setOpen(){
      toggle.classList.add('is-open');
      toggle.setAttribute('aria-label','Nascondi password');
      input.type = 'text';
    }

    // default closed
    setClosed();
    toggle.addEventListener('click', function(e){
      e.preventDefault();
      if(input.type === 'password') setOpen(); else setClosed();
      input.focus();
    });

    // expose programmatic API
      const api = {
        open: setOpen,
        close: setClosed,
        toggle: function() { if (input.type === 'password') setOpen(); else setClosed(); }
      };
    // attach API marker to the element to prevent duplicate listeners
    try{ toggle.__pw_api = api; }catch(e){}
    return api;
  };

  // auto-init for dataset attributes
  function initAuto() {
    document.querySelectorAll('[data-toggle="password"]').forEach(el=>{
      const inputSel = el.getAttribute('data-target') || 'input[type=password]';
      try{ window.Components.togglePassword('#'+el.id, inputSel); }catch(e){/* ignore init errors */}
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAuto);
  } else {
    // DOM already parsed, initialize immediately
    initAuto();
  }

})(window);
