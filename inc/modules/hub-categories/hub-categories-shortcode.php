<?php
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * Kingdom Nexus - Hub Categories Shortcode (v1)
 * ----------------------------------------------------------
 * Shortcode: [knx_hub_categories]
 * - Minimal admin CRUD identical to Cities: Add + Toggle
 * - Uses existing Cities CSS for compactness
 * ==========================================================
 */

add_shortcode('knx_hub_categories', function() {
    knx_set_admin_context();
    
    global $wpdb;

    // Auth check
    $session = knx_get_session();
    if (!$session || !in_array($session->role, ['manager', 'super_admin'])) {
        wp_safe_redirect(site_url('/login'));
        exit;
    }

    $table     = $wpdb->prefix . 'knx_hub_categories';
    $per_page  = 10;
    $page      = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset    = ($page - 1) * $per_page;
    $search    = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    $where  = '';
    $params = [];
    if ($search) {
        $like   = '%' . $wpdb->esc_like($search) . '%';
        $where  = "WHERE name LIKE %s";
        $params = [$like];
    }

    $query = "SELECT * FROM $table $where ORDER BY sort_order ASC, id DESC LIMIT %d OFFSET %d";
    $prepared = !empty($params)
        ? $wpdb->prepare($query, ...array_merge($params, [$per_page, $offset]))
        : $wpdb->prepare($query, $per_page, $offset);
    $rows = $wpdb->get_results($prepared);

    $total_query = "SELECT COUNT(*) FROM $table $where";
    $total = !empty($params)
        ? $wpdb->get_var($wpdb->prepare($total_query, ...$params))
        : $wpdb->get_var($total_query);
    $pages = ceil(max(1, $total) / $per_page);

    $nonce_add    = wp_create_nonce('knx_add_hub_category_nonce');
    $nonce_toggle = wp_create_nonce('knx_toggle_hub_category_nonce');

    ob_start(); ?>

    <!-- Reuse cities CSS for layout -->
    <link rel="stylesheet" href="<?php echo esc_url(KNX_URL . 'inc/modules/cities/cities-style.css'); ?>">

    <div class="knx-cities-wrapper"
         data-api-add="<?php echo esc_url(rest_url('knx/v1/add-hub-category')); ?>"
         data-api-toggle="<?php echo esc_url(rest_url('knx/v1/toggle-hub-category')); ?>"
         data-nonce-add="<?php echo esc_attr($nonce_add); ?>"
         data-nonce-toggle="<?php echo esc_attr($nonce_toggle); ?>">

        <div class="knx-cities-header">
            <h2><i class="fas fa-tags"></i> Hub Categories</h2>

            <div class="knx-cities-controls">
                <form method="get" class="knx-search-form">
                    <input type="hidden" name="page_id" value="<?php echo get_the_ID(); ?>">
                    <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Search categories...">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
                <button id="knxAddHubCategoryBtn" class="knx-add-btn"><i class="fas fa-plus"></i> Add Category</button>
            </div>
        </div>

        <table class="knx-cities-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Toggle</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows): foreach ($rows as $cat): ?>
                    <tr data-id="<?php echo esc_attr($cat->id); ?>">
                        <td><?php echo esc_html(stripslashes($cat->name)); ?></td>
                        <td>
                            <span class="status-<?php echo $cat->status === 'active' ? 'active' : 'inactive'; ?>">
                                <?php echo $cat->status === 'active' ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <label class="knx-switch">
                                <input type="checkbox" class="knx-toggle-hub-category" <?php checked($cat->status, 'active'); ?>>
                                <span class="knx-slider"></span>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="3" style="text-align:center;">No categories found.</td></tr>
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

    <!-- Modal: Add Hub Category -->
    <div id="knxAddHubCategoryModal" class="knx-modal">
        <div class="knx-modal-content">
            <h3>Add Category</h3>
            <form id="knxAddHubCategoryForm">
                <input type="text" name="name" placeholder="Category Name" required>
                <button type="submit" class="knx-btn">Save</button>
                <button type="button" id="knxCloseHubCategoryModal" class="knx-btn-secondary">Cancel</button>
            </form>
        </div>
    </div>

    <script src="<?php echo esc_url(KNX_URL . 'inc/modules/hub-categories/hub-categories-script.js'); ?>"></script>

    <?php
    return ob_get_clean();
});
