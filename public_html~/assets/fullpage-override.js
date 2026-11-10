/* ===== FULL-PAGE A4 INVOICE FIX (v17) ===== */
(function() {
  'use strict';

  var css = `
    /* ===== SCREEN MODAL: full page ===== */
    @media screen {
      .print-modal-container {
        padding: 0 !important;
        align-items: stretch !important;
        justify-content: stretch !important;
      }
      
      .print-backdrop,
      .voucher-print-backdrop {
        display: none !important;
      }
      
      .printable-voucher-card,
      .printable-transport-voucher,
      .printable-visa-voucher,
      .hotel-voucher-print-shell {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        max-width: 100vw !important;
        max-height: 100vh !important;
        border-radius: 0 !important;
        margin: 0 !important;
        z-index: 999999 !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
        background: #ffffff !important;
      }
      
      .printable-voucher-card > div:first-child,
      .printable-transport-voucher > div:first-child,
      .printable-visa-voucher > div:first-child,
      .hotel-voucher-print-shell > div:first-child {
        flex: 1 1 auto !important;
        overflow-y: auto !important;
        min-height: 0 !important;
      }
    }

    /* ===== BROWSER PRINT: full A4 page ===== */
    @media print {
      @page {
        size: A4 portrait !important;
        margin: 0 !important;
        padding: 0 !important;
      }
      
      body {
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
      
      /* Hide backdrops only */
      .print-backdrop,
      .voucher-print-backdrop {
        display: none !important;
      }
      
      /* The card: full page flex column */
      .printable-voucher-card,
      .printable-transport-voucher,
      .printable-visa-voucher,
      .hotel-voucher-print-shell {
        position: relative !important;
        top: auto !important;
        left: auto !important;
        right: auto !important;
        bottom: auto !important;
        width: 100% !important;
        max-width: 100% !important;
        min-height: 297mm !important;
        height: auto !important;
        max-height: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        border: none !important;
        overflow: visible !important;
        background: #ffffff !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: flex-start !important;
        page-break-inside: auto !important;
      }
      
      /* Content wrapper: fills space */
      .printable-voucher-card > div:first-child,
      .printable-transport-voucher > div:first-child,
      .printable-visa-voucher > div:first-child,
      .hotel-voucher-print-shell > div:first-child {
        flex: 1 !important;
        overflow: visible !important;
        height: auto !important;
        max-height: none !important;
        display: flex !important;
        flex-direction: column !important;
      }
      
      /* Last content child: expand to fill remaining space */
      .printable-voucher-card > div:first-child > *:last-child,
      .printable-transport-voucher > div:first-child > *:last-child,
      .printable-visa-voucher > div:first-child > *:last-child,
      .hotel-voucher-print-shell > div:first-child > *:last-child {
        flex: 1 !important;
      }
      
      /* Every content child: uniform spacing */
      .printable-voucher-card > div:first-child > *,
      .printable-transport-voucher > div:first-child > *,
      .printable-visa-voucher > div:first-child > *,
      .hotel-voucher-print-shell > div:first-child > * {
        flex-shrink: 0 !important;
      }
      
      /* Footer: stays at bottom of card */
      .footer-strip {
        flex-shrink: 0 !important;
        margin-top: auto !important;
        background: #ffffff !important;
        border-top: 1px solid #eee !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
      
      /* Hide modal header bar only */
      .print\:hidden {
        display: none !important;
      }
      
      /* ===== TABLES ===== */
      table {
        width: 100% !important;
        border-collapse: collapse !important;
      }
      
      th, td {
        padding: 12px 14px !important;
      }
      
      /* ===== CARDS ===== */
      .border {
        padding: 16px !important;
        margin-bottom: 12px !important;
      }
      
      .space-y-4 > * + * {
        margin-top: 12px !important;
      }
      
      .mb-4 { margin-bottom: 12px !important; }
      .mb-2 { margin-bottom: 8px !important; }
      
      /* ===== TEXT SIZES ===== */
      .text-\[6px\] { font-size: 9px !important; }
      .text-\[7px\] { font-size: 10px !important; }
      .text-\[8px\] { font-size: 11px !important; }
      .text-\[9px\] { font-size: 12px !important; }
      .text-\[10px\] { font-size: 13px !important; }
      .text-\[11px\] { font-size: 14px !important; }
      .text-xs { font-size: 13px !important; }
      .text-sm { font-size: 15px !important; }
      .text-base { font-size: 17px !important; }
      .text-lg { font-size: 20px !important; }
      .text-xl { font-size: 24px !important; }
      
      /* Grid */
      .grid > div { padding: 14px !important; }
      .gap-2 { gap: 10px !important; }
      .gap-3 { gap: 12px !important; }
      
      h3 { font-size: 16px !important; }
      
      .px-6 { padding-left: 20px !important; padding-right: 20px !important; }
      .px-8 { padding-left: 20px !important; padding-right: 20px !important; }
      .py-3 { padding-top: 12px !important; padding-bottom: 12px !important; }
      .pb-2 { padding-bottom: 14px !important; }
      
      /* Injected vendor sections */
      .injected-address-section,
      .injected-account-section {
        padding: 0 20px 14px !important;
      }
    }
  `;
  
  var style = document.createElement('style');
  style.setAttribute('type', 'text/css');
  style.setAttribute('data-injected', 'fullpage-override-v17');
  style.textContent = css;
  document.head.appendChild(style);
})();
