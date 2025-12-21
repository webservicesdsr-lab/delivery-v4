/**
 * ==========================================================
 * Kingdom Nexus - Checkout Payment Flow (Production v2)
 *
 * Controls the "Continue to secure total" button and runs:
 * 1) /wp-json/knx/v1/checkout/prevalidate
 * 2) /wp-json/knx/v1/checkout/secure-total
 *
 * Flow:
 * - Reads session_token from:
 *     a) #knx-checkout[data-session-token], or
 *     b) knx_cart_token cookie (fallback)
 * - Step 1: Validates cart, hub status and subtotal integrity
 * - Step 2: Computes a secure total (backend fees, no formulas exposed)
 * - On success: stores secure total in sessionStorage and redirects
 *   to /secure-total (you will later create that page/shortcode).
 * ==========================================================
 */

document.addEventListener("DOMContentLoaded", function () {
  const wrapper = document.getElementById("knx-checkout");
  const btn = document.getElementById("knxCoPlaceOrderBtn");

  if (!wrapper || !btn) return;

  // Cart ID is only used for basic sanity and debug labels
  const cartId = parseInt(wrapper.dataset.cartId || "0", 10);

  /**
   * Read knx_cart_token cookie if needed.
   */
  function getCookie(name) {
    const match = document.cookie.match(
      new RegExp("(?:^|; )" + name.replace(/([$?*|{}\]\\\/+^])/g, "\\$1") + "=([^;]*)")
    );
    return match ? decodeURIComponent(match[1]) : null;
  }

  /**
   * Resolve session_token from:
   * 1) data-session-token attribute
   * 2) knx_cart_token cookie (fallback)
   */
  function resolveSessionToken() {
    let token = wrapper.dataset.sessionToken || "";
    if (!token) {
      const fromCookie = getCookie("knx_cart_token");
      if (fromCookie) {
        token = fromCookie;
      }
    }
    return token.trim();
  }

  /**
   * Button loading / idle state.
   */
  function setLoadingState(isLoading, label) {
    if (isLoading) {
      btn.disabled = true;
      btn.textContent = label || "Securing your total...";
      btn.classList.add("knx-btn-loading");
    } else {
      btn.disabled = false;
      btn.textContent = "Continue to secure total";
      btn.classList.remove("knx-btn-loading");
    }
  }

  /**
   * Generic error banner at top of checkout.
   */
  function showError(msg) {
    const existing = document.querySelector(".knx-checkout-error");
    if (existing) existing.remove();

    const div = document.createElement("div");
    div.className = "knx-checkout-error";
    div.textContent = msg || "Something went wrong. Please try again.";

    wrapper.prepend(div);
    window.scrollTo({ top: 0, behavior: "smooth" });

    setTimeout(() => {
      if (div.parentNode) div.parentNode.removeChild(div);
    }, 6000);
  }

  /**
   * Call /checkout/prevalidate
   */
  async function callPrevalidate(sessionToken) {
    const res = await fetch("/wp-json/knx/v1/checkout/prevalidate", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      credentials: "same-origin",
      body: JSON.stringify({
        session_token: sessionToken
      })
    });

    const data = await res.json().catch(() => null);
    return { ok: res.ok, data };
  }

  /**
   * Call /checkout/secure-total
   */
  async function callSecureTotal(sessionToken) {
    const res = await fetch("/wp-json/knx/v1/checkout/secure-total", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      credentials: "same-origin",
      body: JSON.stringify({
        session_token: sessionToken
      })
    });

    const data = await res.json().catch(() => null);
    return { ok: res.ok, data };
  }

  /**
   * Main click handler: two-step backend flow.
   */
  btn.addEventListener("click", async function () {
    const sessionToken = resolveSessionToken();

    if (!sessionToken) {
      showError("Your cart session has expired. Please rebuild your order.");
      return;
    }

    if (!cartId) {
      // Not fatal, but likely means no active cart in DB
      showError("Your cart is empty or expired. Please add items again.");
      return;
    }

    // Step 1: Pre-validate cart and hub status
    try {
      setLoadingState(true, "Verifying your cart...");

      const pre = await callPrevalidate(sessionToken);
      if (!pre.ok || !pre.data || pre.data.success === false) {
        const msg =
          (pre.data && (pre.data.message || pre.data.error)) ||
          "We could not validate your cart. Please rebuild your order.";
        showError(msg);
        setLoadingState(false);
        return;
      }

      // Step 2: Secure total (fees + final total)
      setLoadingState(true, "Securing your total...");

      const sec = await callSecureTotal(sessionToken);
      if (!sec.ok || !sec.data || sec.data.success === false) {
        const msg =
          (sec.data && (sec.data.message || sec.data.error)) ||
          "We could not secure your total. Please try again.";
        showError(msg);
        setLoadingState(false);
        return;
      }

      const payload = sec.data;

      // Store secure total in sessionStorage for the next screen
      try {
        const store = {
          cart_id: payload.cart_id || null,
          hub_id: payload.hub_id || null,
          hub_name: payload.hub_name || "",
          currency: payload.currency || "usd",
          breakdown: payload.breakdown || {},
          estimated_total: payload.estimated_total || 0
        };
        sessionStorage.setItem("knx_secure_total", JSON.stringify(store));
      } catch (e) {
        // If storage fails, we still redirect; backend remains source of truth
      }

      // Final step: redirect to secure total review page.
      // You will later create a WP page with slug /secure-total
      // and a shortcode (e.g. [knx_secure_total]) to read sessionStorage
      // and show the final breakdown + payment button.
      setLoadingState(true, "Redirecting to secure review...");
      window.location.href = "/secure-total";

    } catch (err) {
      showError("A network error occurred. Please try again.");
      setLoadingState(false);
    }
  });
});
