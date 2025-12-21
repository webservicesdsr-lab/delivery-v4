/**
 * ==========================================================
 * Kingdom Nexus - SECURE TOTAL SCRIPT (Production v1)
 * ----------------------------------------------------------
 * Handles:
 *  - Confirm & Continue button
 *  - Calls backend to compute totals & create Stripe intent
 *  - Loads Stripe.js safely
 *  - Redirects user to Stripe’s payment page
 *
 * Requirements:
 *  - payments-api.php must return:
 *      { success: true, client_secret, publishable_key }
 * ==========================================================
 */

document.addEventListener("DOMContentLoaded", () => {
    const btn = document.getElementById("knxSecureTotalConfirmBtn");
    const wrapper = document.getElementById("knx-secure-total");

    if (!btn || !wrapper) return;

    const cartId = parseInt(btn.dataset.cartId || "0", 10);
    if (!cartId) return;

    // ------------------------------------------------------
    // UI HELPERS
    // ------------------------------------------------------
    function setLoading(state) {
        if (state) {
            btn.disabled = true;
            btn.textContent = "Processing…";
            btn.classList.add("knx-st-btn-loading");
        } else {
            btn.disabled = false;
            btn.textContent = "Confirm & Continue";
            btn.classList.remove("knx-st-btn-loading");
        }
    }

    function showError(msg) {
        let old = document.querySelector(".knx-st-error");
        if (old) old.remove();

        const div = document.createElement("div");
        div.className = "knx-st-error";
        div.textContent = msg;

        wrapper.prepend(div);

        window.scrollTo({ top: 0, behavior: "smooth" });
        setTimeout(() => div.remove(), 7000);
    }

    // ------------------------------------------------------
    // MAIN ACTION
    // ------------------------------------------------------
    btn.addEventListener("click", function () {
        setLoading(true);

        fetch("/wp-json/knx/v1/payments/create-intent", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ cart_id: cartId }),
        })
            .then((res) => res.json())
            .then(async (data) => {
                if (!data || !data.success) {
                    showError(data?.message || "Unable to continue.");
                    return;
                }

                const clientSecret  = data.client_secret;
                const publishableKey = data.publishable_key;

                if (!clientSecret || !publishableKey) {
                    showError("Payment initialization failed.");
                    return;
                }

                // ------------------------------------------------------
                // LOAD STRIPE JS DYNAMICALLY
                // ------------------------------------------------------
                const stripeJs = await loadStripeScript();
                if (!stripeJs) {
                    showError("Stripe failed to load. Please retry.");
                    return;
                }

                const stripe = Stripe(publishableKey);

                // ------------------------------------------------------
                // REDIRECT TO STRIPE PAYMENT PAGE
                // ------------------------------------------------------
                const { error } = await stripe.confirmPayment({
                    clientSecret: clientSecret,
                    confirmParams: {
                        return_url: window.location.origin + "/order-status",
                    },
                });

                if (error) {
                    showError(error.message || "Stripe error occurred.");
                }
            })
            .catch(() => {
                showError("Network error. Please retry.");
            })
            .finally(() => {
                setLoading(false);
            });
    });

    // ------------------------------------------------------
    // DYNAMIC SCRIPT LOADER
    // ------------------------------------------------------
    function loadStripeScript() {
        return new Promise((resolve) => {
            if (window.Stripe) return resolve(true);

            const s = document.createElement("script");
            s.src = "https://js.stripe.com/v3/";
            s.onload = () => resolve(true);
            s.onerror = () => resolve(false);
            document.body.appendChild(s);
        });
    }
});
