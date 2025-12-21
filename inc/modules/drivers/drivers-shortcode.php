<?php
if (!defined('ABSPATH')) exit;

/**
 * Drivers Management Shortcode
 * [knx_drivers]
 */
add_shortcode('knx_drivers', 'knx_render_drivers');

function knx_render_drivers() {
    $session = knx_get_session();
    
    // Only super_admin and manager
    if (!in_array($session->role ?? '', ['super_admin', 'manager'], true)) {
        return '<div class="knx-error">Access denied. Admin privileges required.</div>';
    }
    
    ob_start();
    ?>
    <link rel="stylesheet" href="<?php echo esc_url(KNX_URL . 'inc/modules/drivers/drivers.css'); ?>">
    
    <div class="knx-drivers-wrapper">
        <div class="knx-drivers-header">
            <h2>Drivers Management</h2>
            <button class="knx-btn-primary" id="knxOpenDriverModal">
                <span>+</span> Register Driver
            </button>
        </div>
        
        <div class="knx-drivers-filters">
            <input type="text" id="knxDriverSearch" placeholder="Search drivers..." />
            <select id="knxStatusFilter">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        
        <div id="knxDriversTable">
            <div class="knx-loader">Loading drivers...</div>
        </div>
        
        <!-- Driver Modal -->
        <div id="knxDriverModal" class="knx-modal" style="display:none;">
            <div class="knx-modal-content">
                <span class="knx-modal-close">&times;</span>
                <h3 id="knxModalTitle">Register Driver</h3>
                
                <form id="knxDriverForm">
                    <input type="hidden" id="knxDriverId" />
                    
                    <div class="knx-form-group">
                        <label>Username *</label>
                        <input type="text" id="knxUsername" required />
                    </div>
                    
                    <div class="knx-form-group">
                        <label>Email *</label>
                        <input type="email" id="knxEmail" required />
                    </div>
                    
                    <div class="knx-form-group">
                        <label>Full Name</label>
                        <input type="text" id="knxFullName" />
                    </div>
                    
                    <div class="knx-form-group">
                        <label>Phone</label>
                        <input type="tel" id="knxPhone" />
                    </div>
                    
                    <div class="knx-form-group" id="knxPasswordGroup">
                        <label>Password <span id="knxPasswordLabel">*</span></label>
                        <input type="password" id="knxPassword" />
                    </div>
                    
                    <div class="knx-form-group" id="knxStatusGroup" style="display:none;">
                        <label>Status</label>
                        <select id="knxStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="knx-form-actions">
                        <button type="button" class="knx-btn-secondary" id="knxCancelModal">Cancel</button>
                        <button type="submit" class="knx-btn-primary" id="knxSaveDriver">Register Driver</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    var knxDrivers = {
        api: '<?php echo esc_url(rest_url('knx/v1/drivers')); ?>',
        nonce: '<?php echo wp_create_nonce('knx_drivers_nonce'); ?>'
    };
    </script>
    <script src="<?php echo esc_url(KNX_URL . 'inc/modules/drivers/drivers.js'); ?>"></script>
    <?php
    return ob_get_clean();
}
