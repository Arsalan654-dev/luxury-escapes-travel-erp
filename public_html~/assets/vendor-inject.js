/* ===== VENDOR SECTIONS INJECTOR (v4) ===== */
(function() {
  'use strict';

  var NAVY = '#0a1f44';
  var GOLD = '#c9a84c';

  function getReactFiber(el) {
    var key = Object.keys(el).find(function(k) {
      return k.startsWith('__reactFiber$') || k.startsWith('__reactInternalInstance$');
    });
    return key ? el[key] : null;
  }

  function findVendorDataFromFiber(fiber) {
    var current = fiber;
    for (var i = 0; i < 80 && current; i++) {
      if (current.memoizedProps) {
        var p = current.memoizedProps;
        var keys = Object.keys(p);
        for (var k = 0; k < keys.length; k++) {
          var val = p[keys[k]];
          if (val && typeof val === 'object' && !Array.isArray(val)) {
            if (val.vendor_address || val.vendor_bank_name || val.vendor_bank_account_no) {
              return val;
            }
          }
        }
      }
      current = current.return;
    }
    return null;
  }

  function buildAddressSection(data) {
    return '<div style="border:2px solid ' + GOLD + ';border-radius:10px;padding:24px;margin:0 0 16px;background:linear-gradient(135deg,#fffdf7 0%,#f8f6f0 100%);">' +
      '<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">' +
      '<div style="width:4px;height:28px;background:' + NAVY + ';border-radius:2px;"></div>' +
      '<span style="font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:' + NAVY + ';">Supplier Address</span>' +
      '<div style="flex:1;height:2px;background:linear-gradient(90deg,' + GOLD + ' 0%,transparent 100%);"></div>' +
      '</div>' +
      '<div style="font-size:16px;color:#212529;line-height:1.8;font-weight:500;padding-left:14px;">' +
      data.vendor_address.replace(/\n/g, '<br>') +
      '</div></div>';
  }

  function buildAccountSection(data) {
    var grid = '';
    if (data.vendor_bank_name) {
      grid += '<div style="padding:12px 16px;background:#f8f9fa;border-radius:8px;">' +
        '<div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#6c757d;font-weight:700;margin-bottom:4px;">Bank Name</div>' +
        '<div style="font-size:16px;color:#212529;font-weight:600;">' + data.vendor_bank_name + '</div></div>';
    }
    if (data.vendor_bank_account_name) {
      grid += '<div style="padding:12px 16px;background:#f8f9fa;border-radius:8px;">' +
        '<div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#6c757d;font-weight:700;margin-bottom:4px;">Account Name</div>' +
        '<div style="font-size:16px;color:#212529;font-weight:600;">' + data.vendor_bank_account_name + '</div></div>';
    }
    if (data.vendor_bank_account_no) {
      grid += '<div style="padding:12px 16px;background:#f8f9fa;border-radius:8px;">' +
        '<div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#6c757d;font-weight:700;margin-bottom:4px;">Account Number</div>' +
        '<div style="font-size:18px;color:' + NAVY + ';font-weight:700;letter-spacing:1px;">' + data.vendor_bank_account_no + '</div></div>';
    }
    if (data.vendor_bank_iban) {
      grid += '<div style="padding:12px 16px;background:#f8f9fa;border-radius:8px;">' +
        '<div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#6c757d;font-weight:700;margin-bottom:4px;">IBAN</div>' +
        '<div style="font-size:16px;color:#212529;font-weight:600;letter-spacing:0.5px;">' + data.vendor_bank_iban + '</div></div>';
    }
    if (data.vendor_bank_swift) {
      grid += '<div style="padding:12px 16px;background:#f8f9fa;border-radius:8px;">' +
        '<div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#6c757d;font-weight:700;margin-bottom:4px;">SWIFT Code</div>' +
        '<div style="font-size:16px;color:#212529;font-weight:600;letter-spacing:1px;">' + data.vendor_bank_swift + '</div></div>';
    }
    if (!grid) return '';
    return '<div style="border:2px solid ' + NAVY + ';border-radius:10px;padding:24px;background:linear-gradient(135deg,' + NAVY + '08 0%,#fff 100%);">' +
      '<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">' +
      '<div style="width:4px;height:28px;background:' + GOLD + ';border-radius:2px;"></div>' +
      '<span style="font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:' + NAVY + ';">Bank Account Details</span>' +
      '<div style="flex:1;height:2px;background:linear-gradient(90deg,' + NAVY + ' 0%,transparent 100%);"></div>' +
      '</div>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">' + grid + '</div></div>';
  }

  function processCards() {
    var cards = document.querySelectorAll('.printable-voucher-card');
    cards.forEach(function(card) {
      if (card.getAttribute('data-vendor-injected')) return;

      var footer = card.querySelector('.footer-strip');
      if (!footer || !footer.parentElement) return;

      var vendorData = null;

      // METHOD 1: React Fiber deep search
      var fiber = getReactFiber(card);
      if (fiber) {
        vendorData = findVendorDataFromFiber(fiber);
      }

      // METHOD 2: Fetch interceptor cache - match by visible text
      if (!vendorData || !vendorData.vendor_address) {
        var cache = window.__INVOICE_CACHE || {};
        var keys = Object.keys(cache);
        var cardText = card.textContent || '';

        for (var i = 0; i < keys.length; i++) {
          var item = cache[keys[i]];
          if (!item || !item.vendor_address) continue;

          // Try matching by vendor_name visible in card
          if (item.vendor_name && cardText.indexOf(item.vendor_name) >= 0) {
            vendorData = item;
            break;
          }
          // Try matching by booking_ref
          if (item.booking_ref && cardText.indexOf(item.booking_ref) >= 0) {
            vendorData = item;
            break;
          }
          // Try matching by voucher_no
          if (item.voucher_no && cardText.indexOf(item.voucher_no) >= 0) {
            vendorData = item;
            break;
          }
          // Try matching by hotel_name
          if (item.hotel_name && cardText.indexOf(item.hotel_name) >= 0) {
            vendorData = item;
            break;
          }
        }
      }

      if (!vendorData) return;
      if (!vendorData.vendor_address && !vendorData.vendor_bank_name && !vendorData.vendor_bank_account_no) return;

      var parent = footer.parentElement;

      if (vendorData.vendor_address) {
        var addrDiv = document.createElement('div');
        addrDiv.className = 'px-6 pb-2 injected-address-section';
        addrDiv.innerHTML = buildAddressSection(vendorData);
        parent.insertBefore(addrDiv, footer);
      }

      if (vendorData.vendor_bank_name || vendorData.vendor_bank_account_no) {
        var acctDiv = document.createElement('div');
        acctDiv.className = 'px-6 pb-2 injected-account-section';
        acctDiv.innerHTML = buildAccountSection(vendorData);
        parent.insertBefore(acctDiv, footer);
      }

      card.setAttribute('data-vendor-injected', 'true');
    });
  }

  setInterval(processCards, 800);
  processCards();
})();
