<?php
if (!defined('ABSPATH')) exit;

/**
 * Kingdom Nexus - Navbar Renderer (v8 - Top Position)
 * Logo left, Cart + User right
 * Renders at wp_body_open for proper placement
 */

/**
 * Render the navbar (extracted from inline closure)
 */
function knx_render_navbar() {
    global $post;
    $slug = is_object($post) ? $post->post_name : '';

    // Skip navbar on admin/dashboard pages
    $admin_slugs = [
        'dashboard','basic-dashboard','advanced-dashboard',
        'hubs','edit-hub','edit-hub-items','edit-item-categories',
        'drivers','customers','cities','settings','menus','hub-categories'
    ];
    if (in_array($slug, $admin_slugs, true)) return;

    $session  = knx_get_session();
    $is_logged = $session ? true : false;
    $username  = $session ? $session->username : '';
    $role      = $session ? $session->role : 'guest';
    $role_level = $is_logged ? knx_get_role_level($role) : 0;
    $is_admin = $role_level >= 3;

    // === Assets (con echo) ===
    echo '<link rel="stylesheet" href="' . esc_url(KNX_URL . 'inc/modules/navbar/navbar-style.css?v=' . KNX_VERSION) . '">';
    echo '<script src="' . esc_url(KNX_URL . 'inc/modules/navbar/navbar-script.js?v=' . KNX_VERSION) . '" defer></script>';

    // Drawer del carrito (aislado, sin overlay)
    echo '<link rel="stylesheet" href="' . esc_url(KNX_URL . 'inc/modules/cart/cart-drawer.css?v=' . KNX_VERSION) . '">';
    echo '<script src="' . esc_url(KNX_URL . 'inc/modules/cart/cart-drawer.js?v=' . KNX_VERSION) . '" defer></script>';

    // Detector de ubicación solo en explore
    if ($slug === 'explore-hubs' && !in_array($slug, $admin_slugs, true)) {
      echo '<link rel="stylesheet" href="' . esc_url(KNX_URL . 'inc/modules/home/knx-location-modal.css?v=' . KNX_VERSION) . '">';
      echo '<script src="' . esc_url(KNX_URL . 'inc/modules/home/knx-location-detector.js?v=' . KNX_VERSION) . '" defer></script>';
    }

    if (!function_exists('knx_get_brand_logo')) {
        function knx_get_brand_logo() {
            $upload = wp_upload_dir();
            $custom = $upload['basedir'] . '/our-local-collective-logo.svg';
            if (file_exists($custom)) {
                return $upload['baseurl'] . '/our-local-collective-logo.svg';
            }
            return KNX_URL . 'assets/default-logo.svg';
        }
    }
    ?>

    <!-- Agrega id para scope anti-bleed -->
    <nav class="knx-nav" id="knx-scope">
      <div class="knx-nav__inner">
        <!-- Logo -->
        <a href="<?php echo esc_url(site_url('/')); ?>" class="knx-nav__brand">
          <span class="knx-nav__logo">🛒</span>
          <span class="knx-nav__brand-text">Kingdom Nexus</span>
        </a>

        <?php if ($slug === 'explore-hubs'): ?>
        <div class="knx-nav__center">
          <button class="knx-loc-chip" id="knx-detect-location" type="button" aria-label="Detect location">
            <i class="fas fa-location-dot"></i>
            <span class="knx-loc-chip__text" id="knxLocChipText">Set location</span>
          </button>
        </div>
        <?php endif; ?>

        <!-- Right Side -->
        <div class="knx-nav__actions">
          <!-- Cart Button: ahora es toggle (no navega) -->
          <a href="#" class="knx-nav__cart" id="knxCartToggle" role="button"
             aria-haspopup="dialog" aria-controls="knxCartDrawer" aria-expanded="false">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <span>Cart</span>
            <span class="knx-nav__cart-badge" id="knxCartBadge">0</span>
          </a>

          <?php if ($is_logged): ?>
            <?php if ($is_admin): ?>
              <button class="knx-nav__username-btn" id="knxAdminMenuBtn" aria-label="User menu">
                <span class="knx-nav__username-text"><?php echo esc_html($username); ?></span>
              </button>
            <?php else: ?>
              <button class="knx-nav__username-btn" id="knxAccountToggle" type="button" aria-haspopup="dialog" aria-controls="knxAccountDrawer" aria-expanded="false">
                <span class="knx-nav__username-text"><?php echo esc_html($username); ?></span>
              </button>
            <?php endif; ?>

            <form method="post" class="knx-nav__logout knx-nav__logout--desktop">
              <?php wp_nonce_field('knx_logout_action','knx_logout_nonce'); ?>
              <button type="submit" name="knx_logout" aria-label="Logout">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                  <polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                </svg>
                <span>Logout</span>
              </button>
            </form>
          <?php else: ?>
            <a href="<?php echo esc_url(site_url('/login')); ?>" class="knx-nav__login">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                <polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
              </svg>
              <span>Login</span>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </nav>

    <?php if ($is_admin): ?>
      <!-- ===== Admin Sidebar with Overlay ===== -->
      <div class="knx-admin-overlay" id="knxAdminOverlay"></div>
      <aside class="knx-admin-sidebar" id="knxAdminSidebar">
        <header class="knx-admin-sidebar__header">
          <a href="<?php echo esc_url(site_url('/dashboard')); ?>" class="knx-admin-sidebar__logo">
            <i class="fas fa-home"></i>
          </a>
          <button id="knxAdminClose" class="knx-admin-sidebar__close" aria-label="Close admin menu" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </header>
        <nav class="knx-admin-sidebar__nav">
          <?php
          // Admin menu links - only show if page exists and is published
          $admin_links = [
            ['slug' => 'dashboard', 'icon' => 'chart-line', 'label' => 'Dashboard'],
            ['slug' => 'hubs', 'icon' => 'store', 'label' => 'Hubs'],
            ['slug' => 'menus', 'icon' => 'utensils', 'label' => 'Menus'],
            ['slug' => 'hub-categories', 'icon' => 'list', 'label' => 'Hub Categories'],
            ['slug' => 'drivers', 'icon' => 'car', 'label' => 'Drivers'],
            ['slug' => 'customers', 'icon' => 'users', 'label' => 'Customers'],
            ['slug' => 'cities', 'icon' => 'city', 'label' => 'Cities'],
            ['slug' => 'settings', 'icon' => 'cog', 'label' => 'Settings'],
          ];

          foreach ($admin_links as $link) {
            $page = get_page_by_path($link['slug']);
            if ($page && $page->post_status === 'publish') {
              $url = esc_url(site_url('/' . $link['slug']));
              $icon = esc_attr($link['icon']);
              $label = esc_html($link['label']);
              echo '<a href="' . $url . '" class="knx-admin-sidebar__link">';
              echo '<i class="fas fa-' . $icon . '"></i>';
              echo '<span>' . $label . '</span>';
              echo '</a>';
            }
          }
          ?>
        </nav>
      </aside>
    <?php endif; ?>

    <!-- ===== Cart Drawer (derecha, sin overlay) ===== -->
    <aside class="knx-cart-drawer" id="knxCartDrawer" role="dialog" aria-modal="true" aria-labelledby="knxCartTitle">
      <header class="knx-cart-drawer__header">
        <h3 id="knxCartTitle">Your Cart</h3>
        <button type="button" class="knx-cart-drawer__close" id="knxCartClose" aria-label="Close cart">×</button>
      </header>

      <div class="knx-cart-drawer__body" id="knxCartItems">
        <!-- Items renderizados via JS (solo summary del menú, sin fees) -->
      </div>

      <footer class="knx-cart-drawer__footer">
        <div class="knx-cart-drawer__total">
          <span>Total:</span>
          <strong id="knxCartTotal">$0.00</strong>
        </div>
        <a class="knx-cart-drawer__checkout" href="<?php echo esc_url(site_url('/checkout')); ?>">Checkout</a>
      </footer>
    </aside>
    <?php
}

/**
 * Hook navbar to wp_body_open
 */
add_action('wp_body_open', 'knx_render_navbar', 5);
