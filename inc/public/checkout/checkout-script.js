/**
 * Checkout Script - Kingdom Nexus (Production)
 * Scoped to #knx-checkout
 * - Simple collapsible for fees explanation
 * - Scaffolding handler for "Continue to secure total"
 */

document.addEventListener("DOMContentLoaded", function () {
  var root = document.getElementById("knx-checkout");
  if (!root) return;

  /* ==========================================================
   * COLLAPSIBLE: FEES EXPLANATION
   * ========================================================== */
  var toggles = root.querySelectorAll(".knx-co-disclaimer-toggle");

  toggles.forEach(function (btn) {
    btn.addEventListener("click", function () {
      var key = btn.getAttribute("data-co-toggle");
      if (!key) return;

      var panel = root.querySelector('.knx-co-disclaimer[data-co-panel="' + key + '"]');
      if (!panel) return;

      var isOpen = panel.classList.contains("is-open");

      if (isOpen) {
        panel.classList.remove("is-open");
        btn.classList.remove("is-open");
      } else {
        panel.classList.add("is-open");
        btn.classList.add("is-open");
      }
    });
  });

  /* ==========================================================
   * PLACE ORDER (SCAFFOLDING ONLY)
   * ========================================================== */
  var placeBtn = root.querySelector("#knxCoPlaceOrderBtn");
  if (placeBtn) {
    placeBtn.addEventListener("click", function () {
      var rawSubtotal = placeBtn.getAttribute("data-co-subtotal") || "0";
      var subtotal = parseFloat(rawSubtotal);

      if (!isFinite(subtotal) || subtotal <= 0) {
        // No items / invalid subtotal – silent fail, just log
        console.warn("KNX Checkout: No valid subtotal to process.");
        return;
      }

      /**
       * Scaffolding only:
       * - At this point we already have a synced cart in DB (knx_carts / knx_cart_items)
       * - In the next iteration we will:
       *   1) Call a secure backend endpoint to:
       *      - Validate hub + coverage + minimum order
       *      - Calculate taxes, fees and delivery using backend rules
       *      - Create a knx_order + knx_order_items
       *   2) Redirect to a live order status page.
       */
      console.log(
        "KNX Checkout scaffolding: Continue to secure total clicked. Subtotal =",
        subtotal
      );
    });
  }
});
