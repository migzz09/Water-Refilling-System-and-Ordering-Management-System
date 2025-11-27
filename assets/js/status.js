/**
 * WaterWorld - status Page Scripts
 */

// preserve tab selection when linking with ?tab=table
(function(){
  const params = new URLSearchParams(window.location.search);
  const tab = params.get('tab');
  if(tab){
    const mapping = {
      'batch_status':'#batch',
      'delivery_status':'#delivery',
      'payment_status':'#payment'
    };
    const selector = mapping[tab];
    if(selector){
      const tabEl = document.querySelector('a[href="'+selector+'"]');
      if(tabEl){
        const bs = bootstrap.Tab.getOrCreateInstance(tabEl);
        bs.show();
      }
    }
  }
})();