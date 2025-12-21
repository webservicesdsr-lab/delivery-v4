/**
 * ==========================================================
 * Kingdom Nexus - Checkout Payment Flow (Production)
 * Controls the "Continue to secure total" button
 * and sends cart data to backend pre-validation API.
 *
 * Future backend endpoint:
 *   POST /wp-json/knx/v1/checkout/pre-validate
 *
 * This file ONLY handles:
 * - Button click
 * - Collecting comment + cart_id + subtotal
 * - Visual feedback (loading / error)
 * - Redirect based on backend response
 * ==========================================================
 */

document.addEventListener("DOMContentLoaded", function () {
    const btn = document.getElementById("knxCoPlaceOrderBtn");
    if (!btn) return;

    const wrapper = document.getElementById("knx-checkout");
    if (!wrapper) return;

    // Extract cart ID + subtotal
    const cartId = parseInt(btn.dataset.coCartId || "0", 10);
    const subtotal = parseFloat(btn.dataset.coSubtotal || "0");

    // COMMENT FIELD
    let commentField = document.getElementById("knxCheckoutComment");

    // Disable button while sending
    function setLoadingState(isLoading) {
        if (isLoading) {
            btn.disabled = true;
            btn.textContent = "Calculating total...";
            btn.classList.add("knx-btn-loading");
        } else {
            btn.disabled = false;
            btn.textContent = "Continue to secure total";
            btn.classList.remove("knx-btn-loading");
        }
    }

    // Create error banner
    function showError(msg) {
        let old = document.querySelector(".knx-checkout-error");
        if (old) old.remove();

        let div = document.createElement("div");
        div.className = "knx-checkout-error";
        div.textContent = msg;

        wrapper.prepend(div);
        window.scrollTo({ top: 0, behavior: "smooth" });

        setTimeout(() => div.remove(), 6000);
    }

    // MAIN CLICK HANDLER
    btn.addEventListener("click", function () {
        if (!cartId || subtotal <= 0) {
            showError("Your cart is empty or expired.");
            return;
        }

        const comment = commentField ? commentField.value.trim() : "";

        const payload = {
            cart_id: cartId,
            subtotal: subtotal,
            comment: comment,
        };

        setLoadingState(true);

        fetch("/wp-json/knx/v1/checkout/pre-validate", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            credentials: "same-origin",
            body: JSON.stringify(payload),
        })
            .then((res) => res.json())
            .then((data) => {
                if (!data || !data.success) {
                    let msg = data?.error || "Unable to continue. Please retry.";
                    showError(msg);
                    return;
                }

                // Backend must respond with next_step_url or next_step_token
                if (data.next_step_url) {
                    window.location.href = data.next_step_url;
                    return;
                }

                // TEMPORARY redirect for testing
                window.location.href = "/secure-total-test?cart_id=" + cartId;
            })
            .catch(() => {
                showError("A network error occurred. Please try again.");
            })
            .finally(() => {
                setLoadingState(false);
            });
    });
});
