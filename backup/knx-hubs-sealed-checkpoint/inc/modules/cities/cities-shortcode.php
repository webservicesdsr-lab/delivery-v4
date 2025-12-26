<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Cities Shortcode (v3.0 Production)
 * ----------------------------------------------------------
 * Shortcode: [knx_cities]
 * ✅ Fully responsive CRUD with REST Add + Toggle
 * ✅ Toast integration
 * ✅ Dynamic prefix support
 * ✅ Pagination + Search
 * ✅ Consistent with hubs v3.8 styling
 * ==========================================================
 */

add_shortcode('knx_cities', function() {
    global $wpdb;

    /** Validate session & roles */
    $session = knx_get_session();
    if (!$session || !in_array($session->role, ['manager', 'super_admin'])) {
        wp_safe_redirect(site_url('/login'));
        exit;
    }

    /** Pagination + search setup */
    $table     = $wpdb->prefix . 'knx_cities';
    $per_page  = 10;
    $page      = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset    = ($page - 1) * $per_page;
    $search    = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    $where = '';
    $params = [];
    if ($search) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where = "WHERE name LIKE %s";
        $params = [$like];
    }

    /** Fetch cities */
    $query = "SELECT * FROM $table $where ORDER BY id DESC LIMIT %d OFFSET %d";
    $prepared = !empty($params)
        ? $wpdb->prepare($query, ...array_merge($params, [$per_page, $offset]))
        : $wpdb->prepare($query, $per_page, $offset);
    $cities = $wpdb->get_results($prepared);

    /** Pagination count */
    $total_query = "SELECT COUNT(*) FROM $table $where";
    $total = !empty($params)
        ? $wpdb->get_var($wpdb->prepare($total_query, ...$params))
        : $wpdb->get_var($total_query);
    $pages = ceil(max(1, $total) / $per_page);

    /** Nonces for REST */
    $nonce_add    = wp_create_nonce('knx_add_city_nonce');
    $nonce_toggle = wp_create_nonce('knx_toggle_city_nonce');

    ob_start(); ?>

    <link rel="stylesheet" href="<?php echo esc_url(KNX_URL . 'inc/modules/cities/cities-style.css'); ?>">

    <div class="knx-cities-wrapper"
         data-api-add="<?php echo esc_url(rest_url('knx/v1/add-city')); ?>"
         data-api-toggle="<?php echo esc_url(rest_url('knx/v1/toggle-city')); ?>"
         data-nonce-add="<?php echo esc_attr($nonce_add); ?>"
         data-nonce-toggle="<?php echo esc_attr($nonce_toggle); ?>">

        <div class="knx-cities-header">
            <h2><i class="fas fa-city"></i> Cities Management</h2>

            <div class="knx-cities-controls">
                <form method="get" class="knx-search-form">
                    <input type="hidden" name="page_id" value="<?php echo get_the_ID(); ?>">
                    <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Search cities...">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
                <button id="knxAddCityBtn" class="knx-add-btn"><i class="fas fa-plus"></i> Add City</button>
            </div>
        </div>

        <table class="knx-cities-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Edit</th>
                    <th>Toggle</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($cities): foreach ($cities as $city): ?>
                    <tr data-id="<?php echo esc_attr($city->id); ?>">
                        <td><?php echo esc_html(stripslashes($city->name)); ?></td>
                        <td>
                            <span class="status-<?php echo $city->status === 'active' ? 'active' : 'inactive'; ?>">
                                <?php echo $city->status === 'active' ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="knx-edit-cell">
                            <a href="<?php echo esc_url(site_url('/edit-city?id=' . $city->id)); ?>" class="knx-edit-link" title="Edit City">
                                <i class="fas fa-pen"></i>
                            </a>
                        </td>
                        <td>
                            <label class="knx-switch">
                                <input type="checkbox" class="knx-toggle-city" <?php checked($city->status, 'active'); ?>>
                                <span class="knx-slider"></span>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4" style="text-align:center;">No cities found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($pages > 1): ?>
            <div class="knx-pagination">
                <?php
                $base_url = remove_query_arg('paged');
                if ($search) $base_url = add_query_arg('search', urlencode($search), $base_url);

                if ($page > 1) echo '<a href="' . esc_url(add_query_arg('paged', $page - 1, $base_url)) . '">&laquo; Prev</a>';
                for ($i = 1; $i <= $pages; $i++) {
                    $active = $i == $page ? 'active' : '';
                    echo '<a href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '" class="' . $active . '">' . $i . '</a>';
                }
                if ($page < $pages) echo '<a href="' . esc_url(add_query_arg('paged', $page + 1, $base_url)) . '">Next &raquo;</a>';
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal: Add City -->
    <div id="knxAddCityModal" class="knx-modal">
        <div class="knx-modal-content">
            <h3>Add City</h3>
            <form id="knxAddCityForm">
                <input type="text" name="name" placeholder="City Name" required>
                <button type="submit" class="knx-btn">Save</button>
                <button type="button" id="knxCloseModal" class="knx-btn-secondary">Cancel</button>
            </form>
        </div>
    </div>

    <script src="<?php echo esc_url(KNX_URL . 'inc/modules/cities/cities-script.js'); ?>"></script>

    <?php
    return ob_get_clean();
});
