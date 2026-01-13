define(['jquery','mage/apply/main','domReady!'], function ($, apply) {
  function reinit() {
    var $el = $('#product-list-container');
    if (!$el.length || $el.data('sf3Init')) return;
    $el.data('sf3Init', true);
    try { apply($el); } catch(e) {
      var blob = $el.attr('data-mage-init');
      if (!blob) return;
      try {
        var cfg = JSON.parse(blob)['Shatchi_SearchFilter3/js/product-listing'];
        if (cfg) require(['Shatchi_SearchFilter3/js/product-listing'], fn => fn(cfg, $el.get(0)));
      } catch(_) {}
    }
  }
  reinit();
  $(document).on('contentUpdated', reinit);
});
