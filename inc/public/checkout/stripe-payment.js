/**
 * ==========================================================
 * Kingdom Nexus - Stripe Payment Handler (Production)
 * ----------------------------------------------------------
 * - Renders Stripe Elements (Card / Apple Pay / Google Pay)
 * - Confirms card payment with PaymentIntent
 * - Sends payment_intent_id + cart_id → place-order API
 * - Redirects to order confirmation
 * ==========================================================
 */

document.addEventListener("DOMContentLoaded", async function () {
    const wrapper = document.getElementById("knx-stripe-payment");
    if (!wrapper) return;

    const publicKey = wrapper.dataset.publicKey;
    const clientSecret = wrapper.dataset.clientSecret;
    const cartId = parseInt(wrapper.dataset.cartId || "0", 10);

    if (!publicKey || !clientSecret || !cartId) {
        console.error("Stripe Payment: Missing required attributes.");
        return;
    }

    // ==========================================================
    // 1) INIT STRIPE
    // ==========================================================
    const stripe = Stripe(publicKey);
    const elements = stripe.elements({
        appearance: {
            theme: "stripe",
            labels: "floating",
        },
    });

    // CARD ELEMENT
    const card = elements.create("card", {
        hidePostalCode: true,
    });
    card.mount("#knx-card-element");

    // Elements for UI feedback
    const btn = document.getElementById("knxStripePayBtn");
    const errorBox = document.getElementById("knxStripeError");

    function showError(msg) {
        if (!errorBox) return;
        errorBox.textContent = msg;
        errorBox.style.display = "block";
    }

    function setLoading(state) {
        if (!btn) return;
        btn.disabled = state;
        btn.textContent = state ? "Processing..." : "Pay Now";
    }

    // ==========================================================
    // 2) CLICK HANDLER
    // ==========================================================
    btn.addEventListener("click", async function () {
        setLoading(true);
        errorBox.style.display = "none";

        // ------------------------------------------------------
        // 2A) Confirm card payment
        // ------------------------------------------------------
        let result;
        try {
            result = await stripe.confirmCardPayment(clientSecret, {
                payment_method: {
                    card: card
                }
            });
        } catch (err) {
            showError("Unable to process payment. Try again.");
            setLoading(false);
            return;
        }

        if (result.error) {
            showError(result.error.message || "Payment failed.");
            setLoading(false);
            return;
        }

        const intent = result.paymentIntent;
        if (!intent || !intent.id) {
            showError("Payment verification failed.");
            setLoading(false);
            return;
        }

        // ==========================================================
        // 3) SEND TO API: PLACE ORDER
        // ==========================================================
        try {
            const res = await fetch("/wp-json/knx/v1/checkout/place-order", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    cart_id: cartId,
                    payment_intent_id: intent.id
                })
            });

            const data = await res.json();

            if (!data.success) {
                showError(data.message || "Order could not be created.");
                setLoading(false);
                return;
            }

            // ==========================================================
            // 4) SUCCESS — REDIRECT
            // ==========================================================
            if (data.next_step_url) {
                window.location.href = data.next_step_url;
                return;
            }

            // fallback
            window.location.href = "/order-confirmation?order_id=" + data.order_id;

        } catch (err) {
            showError("A network error occurred. Please try again.");
            setLoading(false);
        }
    });
});
