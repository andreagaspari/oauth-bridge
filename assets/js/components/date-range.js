// Date range component: toggles visibility of date inputs based on preset select
document.addEventListener('DOMContentLoaded', function(){
  function initDateRange(){
    const preset = document.getElementById('filterPreset');
    const from = document.getElementById('filterDateFrom');
    const to = document.getElementById('filterDateTo');
    if(!preset || !from || !to) return;
    const datesContainer = from.closest('.c-date-range') || null;
    function update(){
      const v = preset.value;
      // show date inputs only when value is empty string (Personalizzato)
      if(datesContainer){
        if(v === ''){
          datesContainer.classList.add('c-date-range--custom');
        } else {
          datesContainer.classList.remove('c-date-range--custom');
        }
      }
    }
    // initial state
    update();
    preset.addEventListener('change', update);
  }
  initDateRange();
});
