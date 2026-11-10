/* ===== VENDOR DATA INTERCEPTOR (v2) ===== */
(function() {
  'use strict';
  
  window.__INVOICE_CACHE = {};
  
  var origFetch = window.fetch;
  window.fetch = function() {
    var url = arguments[0];
    if (typeof url === 'string') {
      return origFetch.apply(this, arguments).then(function(response) {
        var cloned = response.clone();
        cloned.json().then(function(data) {
          if (data && data.status === 'success' && Array.isArray(data.data)) {
            data.data.forEach(function(item) {
              var key = 'id_' + item.id;
              window.__INVOICE_CACHE[key] = item;
              if (item.booking_ref) {
                window.__INVOICE_CACHE['ref_' + item.booking_ref] = item;
              }
              if (item.voucher_no) {
                window.__INVOICE_CACHE['ref_' + item.voucher_no] = item;
              }
            });
          }
        }).catch(function() {});
        return response;
      });
    }
    return origFetch.apply(this, arguments);
  };
})();
