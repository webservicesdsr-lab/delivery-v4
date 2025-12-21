<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Payments Admin Settings (Production v1)
 * Path: /inc/modules/admin/payments-settings.php
 * ----------------------------------------------------------
 * - Creates "Nexus Payments" admin menu
 * - Allows entering Stripe Publishable + Secret keys
 * - Stores keys in wp_knx_settings
 * - Only super_admin role can manage settings
 * - Fully modular (NO enqueue, no mixing)
 * ==========================================================
 */

/**
 * Add admin menu page
 */
add_action('admin_menu', 'knx_register_payments_settings_page');

function knx_register_payments_settings_page() {
    // Only allow super_admin
    $session = function_exists('knx_get_session') ? knx_get_session() : false;
    if (!$session || $session->role !== 'super_admin') {
        return;
    }

    add_menu_page(
        'Nexus Payments',
        'Nexus Payments',
        'manage_options',
        'knx-payments-settings',
        'knx_render_payments_settings_page',
        'dashicons-money-alt',
        57
    );
}

/**
 * Render settings page
 */
function knx_render_payments_settings_page() {
    global $wpdb;
    $settings_table = $wpdb->prefix . 'knx_settings';

    // Fetch saved values
    $publishable_key = $wpdb->get_var(
        "SELECT value FROM {$settings_table} WHERE name = 'stripe_publishable_key' LIMIT 1"
    );
    $secret_key = $wpdb->get_var(
        "SELECT value FROM {$settings_table} WHERE name = 'stripe_secret_key' LIMIT 1"
    );

    // Handle form submission
    if (isset($_POST['knx_payments_save'])) {

        // Nonce check
        if (!isset($_POST['knx_payments_nonce']) ||
            !wp_verify_nonce($_POST['knx_payments_nonce'], 'knx_payments_save_action')) {
            wp_die('Security check failed.');
        }

        $pub = sanitize_text_field($_POST['stripe_publishable_key'] ?? '');
        $sec = sanitize_text_field($_POST['stripe_secret_key'] ?? '');

        // Save or update Publishable Key
        if ($wpdb->get_var("SELECT COUNT(*) FROM {$settings_table} WHERE name='stripe_publishable_key'")) {
            $wpdb->update($settings_table,
                ['value' => $pub],
                ['name' => 'stripe_publishable_key'],
                ['%s'],
                ['%s']
            );
        } else {
            $wpdb->insert($settings_table,
                ['name' => 'stripe_publishable_key', 'value' => $pub],
                ['%s', '%s']
            );
        }

        // Save or update Secret Key
        if ($wpdb->get_var("SELECT COUNT(*) FROM {$settings_table} WHERE name='stripe_secret_key'")) {
            $wpdb->update($settings_table,
                ['value' => $sec],
                ['name' => 'stripe_secret_key'],
                ['%s'],
                ['%s']
            );
        } else {
            $wpdb->insert($settings_table,
                ['name' => 'stripe_secret_key', 'value' => $sec],
                ['%s', '%s']
            );
        }

        echo '<div class="updated"><p>Stripe keys updated successfully.</p></div>';

        // Refresh values for form
        $publishable_key = $pub;
        $secret_key      = $sec;
    }

    ?>

    <div class="wrap">
        <h1>Nexus Payments — Stripe Configuration</h1>
        <p>Enter your Stripe API keys. These are stored securely and never exposed in frontend JavaScript.</p>

        <form method="post" style="max-width:600px; margin-top:20px;">

            <?php wp_nonce_field('knx_payments_save_action', 'knx_payments_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label>Stripe Publishable Key</label></th>
                    <td>
                        <input type="text"
                               name="stripe_publishable_key"
                               class="regular-text"
                               value="<?php echo esc_attr($publishable_key); ?>"
                               placeholder="pk_live_XXXX">
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>Stripe Secret Key</label></th>
                    <td>
                        <input type="password"
                               name="stripe_secret_key"
                               class="regular-text"
                               value="<?php echo esc_attr($secret_key); ?>"
                               placeholder="sk_live_XXXX">
                        <p class="description">Hidden for security. Re-enter only if changing.</p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="submit" name="knx_payments_save" class="button button-primary">
                    Save Stripe Settings
                </button>
            </p>
        </form>
    </div>

    <?php
}
