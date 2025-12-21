<?php
if (!defined('ABSPATH')) exit;

/**
 * Software Fees Settings Shortcode
 * [knx_fees_settings]
 */
add_shortcode('knx_fees_settings', 'knx_fees_settings_render');

function knx_fees_settings_render() {
    $session = knx_get_session();
    
    // Only super_admin
    if ($session->role !== 'super_admin') {
        return '<div class="knx-error">Access denied. Super admin role required.</div>';
    }
    
    ob_start();
    ?>
    <link rel="stylesheet" href="<?php echo esc_url(KNX_URL . 'inc/modules/admin/fees-settings.css'); ?>">
    
    <div class="knx-fees-settings-wrapper">
        <div class="knx-fees-header">
            <h2>Software Fees & Rates</h2>
            <p class="knx-subtitle">Configure platform fees, delivery rates, and other charges</p>
        </div>
        
        <div id="knxFeesLoader" class="knx-loader">Loading settings...</div>
        
        <form id="knxFeesForm" style="display:none;">
            <div class="knx-settings-section">
                <h3>Platform Fees</h3>
                
                <div class="knx-form-row">
                    <div class="knx-form-group">
                        <label>Platform Fee (%)</label>
                        <input type="number" id="knxPlatformFeePercent" step="0.01" min="0" max="100" />
                        <small>Percentage fee applied to each order</small>
                    </div>
                    
                    <div class="knx-form-group">
                        <label>Platform Fixed Fee ($)</label>
                        <input type="number" id="knxPlatformFeeFixed" step="0.01" min="0" />
                        <small>Fixed fee added to each order</small>
                    </div>
                </div>
            </div>
            
            <div class="knx-settings-section">
                <h3>Delivery Fees</h3>
                
                <div class="knx-form-row">
                    <div class="knx-form-group">
                        <label>Base Delivery Fee ($)</label>
                        <input type="number" id="knxDeliveryBaseFee" step="0.01" min="0" />
                        <small>Minimum delivery charge</small>
                    </div>
                    
                    <div class="knx-form-group">
                        <label>Per Kilometer Rate ($)</label>
                        <input type="number" id="knxDeliveryPerKm" step="0.01" min="0" />
                        <small>Additional charge per kilometer</small>
                    </div>
                </div>
            </div>
            
            <div class="knx-settings-section">
                <h3>Additional Charges</h3>
                
                <div class="knx-form-row">
                    <div class="knx-form-group">
                        <label>Service Fee (%)</label>
                        <input type="number" id="knxServiceFeePercent" step="0.01" min="0" max="100" />
                        <small>Service charge percentage</small>
                    </div>
                    
                    <div class="knx-form-group">
                        <label>Tax Rate (%)</label>
                        <input type="number" id="knxTaxRate" step="0.01" min="0" max="100" />
                        <small>Sales tax or VAT rate</small>
                    </div>
                </div>
            </div>
            
            <div class="knx-settings-section">
                <h3>Order Minimums</h3>
                
                <div class="knx-form-row">
                    <div class="knx-form-group">
                        <label>Minimum Order Amount ($)</label>
                        <input type="number" id="knxMinimumOrderAmount" step="0.01" min="0" />
                        <small>Minimum subtotal required to place order</small>
                    </div>
                </div>
            </div>
            
            <div class="knx-form-actions">
                <button type="submit" class="knx-btn-primary" id="knxSaveFees">Save Settings</button>
            </div>
        </form>
    </div>
    
    <script>
    var knxFees = {
        api: '<?php echo esc_url(rest_url('knx/v1/settings/fees')); ?>',
        nonce: '<?php echo wp_create_nonce('knx_settings_nonce'); ?>'
    };
    </script>
    <script src="<?php echo esc_url(KNX_URL . 'inc/modules/admin/fees-settings.js'); ?>"></script>
    <?php
    return ob_get_clean();
}
