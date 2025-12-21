jQuery(document).ready(function($) {
    let isEditMode = false;
    
    // Load drivers
    function loadDrivers() {
        const search = $('#knxDriverSearch').val();
        const status = $('#knxStatusFilter').val();
        
        let url = knxDrivers.api;
        const params = [];
        if (search) params.push(`search=${encodeURIComponent(search)}`);
        if (status) params.push(`status=${status}`);
        if (params.length) url += '?' + params.join('&');
        
        $.ajax({
            url: url,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', knxDrivers.nonce);
            },
            success: function(response) {
                if (response.success) {
                    renderDriversTable(response.drivers);
                }
            },
            error: function() {
                $('#knxDriversTable').html('<div class="knx-error">Failed to load drivers</div>');
            }
        });
    }
    
    // Render drivers table
    function renderDriversTable(drivers) {
        if (drivers.length === 0) {
            $('#knxDriversTable').html('<div class="knx-empty">No drivers found</div>');
            return;
        }
        
        let html = '<table class="knx-table"><thead><tr>';
        html += '<th>Username</th><th>Email</th><th>Full Name</th><th>Status</th><th>Registered</th><th>Actions</th>';
        html += '</tr></thead><tbody>';
        
        drivers.forEach(driver => {
            const statusBadge = driver.status === 'active' ? 'knx-badge-success' : 'knx-badge-warning';
            
            html += `<tr>
                <td>${escapeHtml(driver.username)}</td>
                <td>${escapeHtml(driver.email)}</td>
                <td>${escapeHtml(driver.full_name || '-')}</td>
                <td><span class="${statusBadge}">${escapeHtml(driver.status)}</span></td>
                <td>${formatDate(driver.created_at)}</td>
                <td>
                    <button class="knx-btn-icon knx-edit-driver" data-id="${driver.id}" data-driver='${JSON.stringify(driver)}'>✏️</button>
                    <button class="knx-btn-icon knx-delete-driver" data-id="${driver.id}" data-username="${escapeHtml(driver.username)}">🗑️</button>
                </td>
            </tr>`;
        });
        
        html += '</tbody></table>';
        $('#knxDriversTable').html(html);
    }
    
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Open modal for new driver
    $('#knxOpenDriverModal').on('click', function() {
        isEditMode = false;
        $('#knxModalTitle').text('Register Driver');
        $('#knxDriverForm')[0].reset();
        $('#knxDriverId').val('');
        $('#knxUsername').prop('disabled', false);
        $('#knxPasswordGroup label').html('Password <span style="color:red;">*</span>');
        $('#knxPassword').prop('required', true);
        $('#knxStatusGroup').hide();
        $('#knxSaveDriver').text('Register Driver');
        $('#knxDriverModal').fadeIn();
    });
    
    // Edit driver
    $(document).on('click', '.knx-edit-driver', function() {
        isEditMode = true;
        const driver = JSON.parse($(this).attr('data-driver'));
        
        $('#knxModalTitle').text('Edit Driver');
        $('#knxDriverId').val(driver.id);
        $('#knxUsername').val(driver.username).prop('disabled', true);
        $('#knxEmail').val(driver.email);
        $('#knxFullName').val(driver.full_name || '');
        $('#knxPhone').val('');
        $('#knxStatus').val(driver.status);
        $('#knxPassword').val('').prop('required', false);
        $('#knxPasswordGroup label').html('Password <span style="color:#666;">(leave blank to keep current)</span>');
        $('#knxStatusGroup').show();
        $('#knxSaveDriver').text('Update Driver');
        $('#knxDriverModal').fadeIn();
    });
    
    // Close modal
    $('.knx-modal-close, #knxCancelModal').on('click', function() {
        $('#knxDriverModal').fadeOut();
    });
    
    // Save driver
    $('#knxDriverForm').on('submit', function(e) {
        e.preventDefault();
        
        const driverId = $('#knxDriverId').val();
        const data = {
            username: $('#knxUsername').val(),
            email: $('#knxEmail').val(),
            full_name: $('#knxFullName').val(),
            phone: $('#knxPhone').val(),
            password: $('#knxPassword').val()
        };
        
        if (isEditMode) {
            data.status = $('#knxStatus').val();
        }
        
        const method = isEditMode ? 'PUT' : 'POST';
        const url = isEditMode ? `${knxDrivers.api}/${driverId}` : knxDrivers.api;
        
        $.ajax({
            url: url,
            method: method,
            contentType: 'application/json',
            data: JSON.stringify(data),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', knxDrivers.nonce);
                $('#knxSaveDriver').prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    $('#knxDriverModal').fadeOut();
                    loadDrivers();
                    alert(response.message || 'Driver saved successfully');
                } else {
                    alert('Error: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.error || 'Failed to save driver'));
            },
            complete: function() {
                $('#knxSaveDriver').prop('disabled', false).text(isEditMode ? 'Update Driver' : 'Register Driver');
            }
        });
    });
    
    // Delete driver
    $(document).on('click', '.knx-delete-driver', function() {
        const driverId = $(this).data('id');
        const username = $(this).data('username');
        
        if (!confirm(`Delete driver "${username}"? This action cannot be undone.`)) {
            return;
        }
        
        $.ajax({
            url: `${knxDrivers.api}/${driverId}`,
            method: 'DELETE',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', knxDrivers.nonce);
            },
            success: function(response) {
                if (response.success) {
                    loadDrivers();
                    alert(response.message || 'Driver deleted successfully');
                } else {
                    alert('Error: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.error || 'Failed to delete driver'));
            }
        });
    });
    
    // Search and filter
    $('#knxDriverSearch, #knxStatusFilter').on('input change', function() {
        clearTimeout(window.knxSearchTimer);
        window.knxSearchTimer = setTimeout(loadDrivers, 300);
    });
    
    // Initial load
    loadDrivers();
});
