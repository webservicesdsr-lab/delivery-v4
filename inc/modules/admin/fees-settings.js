jQuery(document).ready(function($) {
    
    // Load current settings
    function loadSettings() {
        $.ajax({
            url: knxFees.api,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', knxFees.nonce);
            },
            success: function(response) {
                if (response.success) {
                    populateForm(response.settings);
                    $('#knxFeesLoader').hide();
                    $('#knxFeesForm').fadeIn();
                }
            },
            error: function() {
                $('#knxFeesLoader').html('<div class="knx-error">Failed to load settings</div>');
            }
        });
    }
    
    // Populate form with current values
    function populateForm(settings) {
        $('#knxPlatformFeePercent').val(settings.platform_fee_percent || 0);
        $('#knxPlatformFeeFixed').val(settings.platform_fee_fixed || 0);
        $('#knxDeliveryBaseFee').val(settings.delivery_base_fee || 0);
        $('#knxDeliveryPerKm').val(settings.delivery_per_km || 0);
        $('#knxServiceFeePercent').val(settings.service_fee_percent || 0);
        $('#knxTaxRate').val(settings.tax_rate || 0);
        $('#knxMinimumOrderAmount').val(settings.minimum_order_amount || 0);
    }
    
    // Save settings
    $('#knxFeesForm').on('submit', function(e) {
        e.preventDefault();
        
        const data = {
            platform_fee_percent: parseFloat($('#knxPlatformFeePercent').val()) || 0,
            platform_fee_fixed: parseFloat($('#knxPlatformFeeFixed').val()) || 0,
            delivery_base_fee: parseFloat($('#knxDeliveryBaseFee').val()) || 0,
            delivery_per_km: parseFloat($('#knxDeliveryPerKm').val()) || 0,
            service_fee_percent: parseFloat($('#knxServiceFeePercent').val()) || 0,
            tax_rate: parseFloat($('#knxTaxRate').val()) || 0,
            minimum_order_amount: parseFloat($('#knxMinimumOrderAmount').val()) || 0
        };
        
        $.ajax({
            url: knxFees.api,
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify(data),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', knxFees.nonce);
                $('#knxSaveFees').prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    alert('Settings saved successfully');
                } else {
                    alert('Error: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.error || 'Failed to save settings'));
            },
            complete: function() {
                $('#knxSaveFees').prop('disabled', false).text('Save Settings');
            }
        });
    });
    
    // Initial load
    loadSettings();
});
