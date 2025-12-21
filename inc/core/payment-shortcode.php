<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - PAYMENT PAGE (Production)
 * Shortcode: [knx_payment]
 * ----------------------------------------------------------
 * - Receives ?token=preorder_token
 * - Validates user session
 * - Requests secure totals from backend
 * - Displays Subtotal, Fees, Taxes, Total
 * - Shows Stripe “Pay Now”
 * ==========================================================
 */

add_shortcode('knx_payment', 'knx_render_payment_page');

function knx_render_payment_page() {

    // ------------------------------------------------------
    // REQUIRE LOGGED-IN NEXUS SESSION (TASK 2: Checkout guard)
    // ------------------------------------------------------
    $session = knx_guard_checkout_page('customer');

    // ------------------------------------------------------
    // VERIFY PREORDER TOKEN
    // ------------------------------------------------------
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

    if ($token === '') {
        return "<div id='knx-payment'><p>Missing payment token.</p></div>";
    }

    // ------------------------------------------------------
    // PAGE OUTPUT
    // ------------------------------------------------------
    ob_start();
    ?>

<!-- CSS -->
<link rel="stylesheet"
      href="<?php echo esc_url(KNX_URL . 'inc/public/payment/payment-style.css?v=' . KNX_VERSION); ?>">

<div id="knx-payment"
     data-preorder-token="<?php echo esc_attr($token); ?>">

    <header class="knx-pay-header">
        <h1>Secure Payment</h1>
        <p>Review your final total and pay securely.</p>
    </header>

    <div class="knx-pay-layout">

        <!-- SUMMARY CARD -->
        <section class="knx-pay-card" id="knxPaySummaryCard">
            <h2 class="knx-pay-title">Order Total</h2>

            <div class="knx-pay-summary">
                <div class="knx-pay-row">
                    <span>Items subtotal</span>
                    <strong id="knxPaySubtotal">$0.00</strong>
                </div>

                <div class="knx-pay-row">
                    <span>Delivery fee</span>
                    <strong id="knxPayDelivery">$0.00</strong>
                </div>

                <div class="knx-pay-row">
                    <span>Service fee</span>
                    <strong id="knxPayService">$0.00</strong>
                </div>

                <div class="knx-pay-row">
                    <span>Taxes</span>
                    <strong id="knxPayTaxes">$0.00</strong>
                </div>

                <div class="knx-pay-divider"></div>

                <div class="knx-pay-row knx-pay-total">
                    <span>Total</span>
                    <strong id="knxPayTotal">$0.00</strong>
                </div>
            </div>

            <button id="knxStripePayBtn"
                    class="knx-pay-btn-primary"
                    data-preorder-token="<?php echo esc_attr($token); ?>">
                Pay Now
            </button>

            <p class="knx-pay-safe">
                Secured by Stripe. No card data is stored in our servers.
            </p>
        </section>

        <!-- OPTIONAL SIDEBAR: Details, delivery notes, etc. -->
        <aside class="knx-pay-sidebar">
            <div class="knx-pay-card">
                <h2 class="knx-pay-title">Details</h2>
                <p>You will receive email confirmation once payment is complete.</p>
            </div>
        </aside>

    </div>
</div>

<!-- JS -->
<script src="<?php echo esc_url(KNX_URL . 'inc/public/payment/payment-script.js?v=' . KNX_VERSION); ?>"></script>

<?php
    return ob_get_clean();
}
