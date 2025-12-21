/**
 * ==========================================================
 * Kingdom Nexus - Checkout Script (Production v3)
 * ----------------------------------------------------------
 * Responsibilities:
 * - Handle disclaimer collapse ("How are taxes/fees calculated?")
 * - Ultra-clean animation without interfering with payment flow
 * - No external libraries, no jQuery
 * - Immune to Hello Elementor hovers
 *
 * DOES NOT calculate fees, totals or expose formulas.
 * ==========================================================
 */

document.addEventListener("DOMContentLoaded", () => {
    // ---------------------------------------------
    // Collapse toggle handler
    // ---------------------------------------------
    const toggles = document.querySelectorAll(
        "#knx-checkout .knx-co-disclaimer-toggle"
    );

    toggles.forEach((btn) => {
        btn.addEventListener("click", () => {
            const key = btn.dataset.coToggle;
            if (!key) return;

            const panel = document.querySelector(
                `#knx-checkout .knx-co-disclaimer[data-co-panel="${key}"]`
            );
            if (!panel) return;

            const isOpen = panel.classList.contains("is-open");

            // Close all other panels (optional but clean UX)
            document
                .querySelectorAll("#knx-checkout .knx-co-disclaimer")
                .forEach((p) => {
                    if (p !== panel) p.classList.remove("is-open");
                });

            // Toggle the clicked one
            if (isOpen) {
                panel.classList.remove("is-open");
                btn.classList.remove("is-open");
            } else {
                panel.classList.add("is-open");
                btn.classList.add("is-open");
            }
        });
    });

    // ---------------------------------------------
    // Smooth scroll to top when something major occurs
    // (Used by checkout-payment-flow.js)
    // ---------------------------------------------
    window.knxSmoothScrollTop = function () {
        window.scrollTo({ top: 0, behavior: "smooth" });
    };
});
