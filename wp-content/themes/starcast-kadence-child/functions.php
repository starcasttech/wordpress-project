<?php
/**
 * Starcast Pro - Kadence Child Theme
 * Professional ISP theme with advanced features
 *
 * @package Starcast_Pro
 * @version 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('STARCAST_VERSION', '2.0.0');
define('STARCAST_THEME_DIR', get_stylesheet_directory());
define('STARCAST_THEME_URI', get_stylesheet_directory_uri());

/**
 * Enqueue parent and child theme styles
 */
function starcast_enqueue_styles() {
    // Parent theme
    wp_enqueue_style('kadence-parent-style', get_template_directory_uri() . '/style.css');

    // Child theme
    wp_enqueue_style('starcast-pro-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('kadence-parent-style'),
        STARCAST_VERSION
    );

    // Google Fonts - Inter
    wp_enqueue_style('starcast-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap',
        array(),
        null
    );

    // Custom JavaScript
    wp_enqueue_script('starcast-pro-js',
        get_stylesheet_directory_uri() . '/assets/js/starcast.js',
        array('jquery'),
        STARCAST_VERSION,
        true
    );

    // Localize script for AJAX
    wp_localize_script('starcast-pro-js', 'starcastData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('starcast_nonce'),
        'siteUrl' => home_url(),
    ));
}
add_action('wp_enqueue_scripts', 'starcast_enqueue_styles');

/**
 * Enqueue Google Maps for the coverage checker.
 */
function starcast_enqueue_google_maps() {
    if (is_admin()) {
        return;
    }

    wp_enqueue_script(
        'starcast-google-maps',
        'https://maps.googleapis.com/maps/api/js?key=AIzaSyB7RDjR0beuN5o7Pb6cy98R8MY8cjiRRZA&callback=starcastInitCoverageMap',
        array('starcast-pro-js'),
        null,
        true
    );
}
add_action('wp_enqueue_scripts', 'starcast_enqueue_google_maps', 20);

/**
 * Add utility navigation bar
 */
function starcast_utility_bar() {
    ?>
    <div class="starcast-utility-bar">
        <div class="container">
            <ul class="starcast-utility-links">
                <li><a href="<?php echo home_url('/coverage-check'); ?>">Coverage Checker</a></li>
                <li><a href="<?php echo home_url('/help'); ?>">Help Centre</a></li>
                <li><a href="<?php echo home_url('/contact'); ?>">Contact Us</a></li>
            </ul>
            <ul class="starcast-utility-links">
                <?php if (is_user_logged_in()) : ?>
                    <li><a href="<?php echo home_url('/my-account'); ?>">My Account</a></li>
                    <li><a href="<?php echo wp_logout_url(home_url()); ?>">Logout</a></li>
                <?php else : ?>
                    <li><a href="<?php echo home_url('/my-account'); ?>">Log In</a></li>
                    <li><a href="<?php echo home_url('/register'); ?>">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php
}
add_action('kadence_before_header', 'starcast_utility_bar');

/**
 * Custom package display shortcode with filters
 * Usage: [starcast_packages type="fibre" featured="yes"]
 */
function starcast_packages_shortcode($atts) {
    $atts = shortcode_atts(array(
        'type' => 'fibre', // fibre or lte
        'featured' => 'no',
        'limit' => -1,
        'provider' => '',
    ), $atts);

    ob_start();
    ?>

    <div class="starcast-packages-section">

        <!-- Filters -->
        <div class="starcast-filters">
            <div class="starcast-filter-row">
                <div class="starcast-filter-group">
                    <label for="starcast-filter-provider">Filter by Provider</label>
                    <select id="starcast-filter-provider" class="starcast-filter-select">
                        <option value="">All Providers</option>
                        <?php
                        $providers = get_terms(array(
                            'taxonomy' => 'fibre_provider',
                            'hide_empty' => true,
                        ));
                        foreach ($providers as $provider) {
                            echo '<option value="' . esc_attr($provider->slug) . '">' . esc_html($provider->name) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="starcast-filter-group">
                    <label for="starcast-filter-speed">Filter by Speed</label>
                    <select id="starcast-filter-speed" class="starcast-filter-select">
                        <option value="">All Speeds</option>
                        <option value="0-50">Up to 50 Mbps</option>
                        <option value="50-100">50-100 Mbps</option>
                        <option value="100-200">100-200 Mbps</option>
                        <option value="200-500">200-500 Mbps</option>
                        <option value="500+">500+ Mbps</option>
                    </select>
                </div>

                <div class="starcast-filter-group">
                    <label for="starcast-filter-sort">Sort by</label>
                    <select id="starcast-filter-sort" class="starcast-filter-select">
                        <option value="price-asc">Price: Low to High</option>
                        <option value="price-desc">Price: High to Low</option>
                        <option value="speed-desc">Speed: High to Low</option>
                        <option value="speed-asc">Speed: Low to High</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Packages Grid -->
        <div class="starcast-packages-grid" id="starcast-packages-container">
            <?php
            $args = array(
                'post_type' => 'fibre_packages',
                'posts_per_page' => $atts['limit'],
                'orderby' => 'meta_value_num',
                'meta_key' => 'price',
                'order' => 'ASC',
            );

            if (!empty($atts['provider'])) {
                $args['tax_query'] = array(
                    array(
                        'taxonomy' => 'fibre_provider',
                        'field' => 'slug',
                        'terms' => $atts['provider'],
                    ),
                );
            }

            $packages = new WP_Query($args);

            if ($packages->have_posts()) :
                while ($packages->have_posts()) : $packages->the_post();
                    starcast_render_package_card(get_the_ID());
                endwhile;
                wp_reset_postdata();
            else :
                echo '<p class="starcast-no-results">No packages found. Please adjust your filters.</p>';
            endif;
            ?>
        </div>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode('starcast_packages', 'starcast_packages_shortcode');

/**
 * Render a single package card
 */
function starcast_render_package_card($package_id) {
    $speed = get_post_meta($package_id, 'download_speed', true);
    $upload_speed = get_post_meta($package_id, 'upload_speed', true);
    $price = get_post_meta($package_id, 'price', true);
    $is_uncapped = get_post_meta($package_id, 'uncapped', true);
    $provider_terms = get_the_terms($package_id, 'fibre_provider');
    $provider = $provider_terms ? $provider_terms[0]->name : 'Starcast';

    // Determine badge
    $badge = '';
    $badge_class = '';
    if (has_term('featured', 'package_category', $package_id)) {
        $badge = 'RECOMMENDED';
        $badge_class = 'starcast-badge-recommended';
    } elseif (has_term('hot-deal', 'package_category', $package_id)) {
        $badge = 'HOT DEAL';
        $badge_class = 'starcast-badge-hot';
    } elseif (get_post_meta($package_id, 'is_special', true)) {
        $badge = 'BEST VALUE';
        $badge_class = 'starcast-badge-deal';
    }

    ?>
    <div class="starcast-package-card starcast-animate-fade-in-up"
         data-speed="<?php echo esc_attr($speed); ?>"
         data-price="<?php echo esc_attr($price); ?>"
         data-provider="<?php echo esc_attr(strtolower($provider)); ?>">

        <?php if ($badge) : ?>
            <div class="starcast-package-badge <?php echo esc_attr($badge_class); ?>">
                <?php echo esc_html($badge); ?>
            </div>
        <?php endif; ?>

        <div class="starcast-package-header">
            <div class="starcast-package-speed">
                <?php echo esc_html($speed); ?><?php echo $upload_speed ? '/' . esc_html($upload_speed) : ''; ?> Mbps
            </div>
            <div class="starcast-package-name">
                <?php echo esc_html(get_the_title($package_id)); ?>
            </div>
            <div class="starcast-package-provider">
                <?php echo esc_html($provider); ?>
            </div>
            <div class="starcast-package-price">
                R<?php echo number_format($price, 2); ?> <small>/month</small>
            </div>
        </div>

        <div class="starcast-package-body">
            <ul class="starcast-package-features">
                <?php if ($is_uncapped) : ?>
                    <li>Uncapped Data</li>
                <?php endif; ?>
                <li>No Fair Usage Policy</li>
                <li>24/7 Customer Support</li>
                <li>Fast Installation (7 Days)</li>
                <li>No Contract Required</li>
                <li>Local Support Team</li>
            </ul>

            <a href="<?php echo home_url('/signup?package_id=' . $package_id); ?>"
               class="starcast-btn starcast-btn-primary"
               style="width: 100%; text-align: center;">
                Get This Package
            </a>
        </div>
    </div>
    <?php
}

/**
 * Coverage checker shortcode
 * Usage: [starcast_coverage_checker]
 */
function starcast_coverage_checker_shortcode() {
    ob_start();
    ?>
    <div class="starcast-coverage-checker">
        <div class="starcast-coverage-card">
            <h2>Check Coverage in Your Area</h2>
            <div id="starcast-coverage-map" style="width: 100%; height: 220px; border-radius: var(--starcast-radius-lg); overflow: hidden; margin: 1rem 0;"></div>
            <p style="text-align: center; color: var(--starcast-gray-600);">
                Enter your address to see available fibre and LTE packages
            </p>

            <div class="starcast-search-box">
                <input type="text"
                       id="starcast-address-input"
                       class="starcast-search-input"
                       placeholder="Enter your street address...">
                <button class="starcast-btn starcast-btn-primary" onclick="starcastCheckCoverage()">
                    Check Availability
                </button>
            </div>

            <div id="starcast-coverage-results" style="margin-top: 2rem;"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('starcast_coverage_checker', 'starcast_coverage_checker_shortcode');

/**
 * AJAX handler for coverage checking
 */
function starcast_check_coverage_ajax() {
    check_ajax_referer('starcast_nonce', 'nonce');

    $address = sanitize_text_field($_POST['address']);

    // Dummy response: always available.
    $response = array(
        'success' => true,
        'message' => 'Product is available at your location.',
        'packages' => array(
            array('type' => 'fibre', 'count' => 15),
            array('type' => 'lte', 'count' => 8),
        ),
    );

    wp_send_json($response);
}
add_action('wp_ajax_starcast_check_coverage', 'starcast_check_coverage_ajax');
add_action('wp_ajax_nopriv_starcast_check_coverage', 'starcast_check_coverage_ajax');

/**
 * Add custom body classes
 */
function starcast_body_classes($classes) {
    $classes[] = 'starcast-pro';

    if (is_page_template('template-fibre-packages.php')) {
        $classes[] = 'starcast-packages-page';
    }

    return $classes;
}
add_filter('body_class', 'starcast_body_classes');

/**
 * Customize WooCommerce
 */
function starcast_woocommerce_support() {
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}
add_action('after_setup_theme', 'starcast_woocommerce_support');

/**
 * Trust bar shortcode
 */
function starcast_trust_bar_shortcode() {
    ob_start();
    ?>
    <div class="starcast-trust-bar">
        <div class="starcast-trust-items">
            <div class="starcast-trust-item">
                <span class="starcast-trust-icon">✓</span>
                <span>7 Day Installation</span>
            </div>
            <div class="starcast-trust-item">
                <span class="starcast-trust-icon">✓</span>
                <span>24/7 Support</span>
            </div>
            <div class="starcast-trust-item">
                <span class="starcast-trust-icon">✓</span>
                <span>100% Local Service</span>
            </div>
            <div class="starcast-trust-item">
                <span class="starcast-trust-icon">✓</span>
                <span>No Contract</span>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('starcast_trust_bar', 'starcast_trust_bar_shortcode');

/**
 * Hero section shortcode
 */
function starcast_hero_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => 'Connect to the Future',
        'accent' => 'with Starcast Technologies',
        'subtitle' => 'Lightning-fast fibre and LTE internet solutions nationwide',
    ), $atts);

    ob_start();
    ?>
    <div class="starcast-hero">
        <div class="starcast-hero-content">
            <h1>
                <?php echo esc_html($atts['title']); ?>
                <span class="starcast-hero-accent"><?php echo esc_html($atts['accent']); ?></span>
            </h1>
            <p><?php echo esc_html($atts['subtitle']); ?></p>
            <div class="starcast-hero-cta">
                <a href="<?php echo home_url('/fibre'); ?>" class="starcast-btn starcast-btn-primary">
                    View Fibre Packages
                </a>
                <a href="<?php echo home_url('/lte-5g'); ?>" class="starcast-btn starcast-btn-secondary">
                    View LTE Options
                </a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('starcast_hero', 'starcast_hero_shortcode');

/**
 * Load SVG sprite icons
 */
function starcast_load_svg_sprites() {
    $svg_path = get_stylesheet_directory() . '/assets/icons.svg';
    if (file_exists($svg_path)) {
        include $svg_path;
    }
}
add_action('wp_footer', 'starcast_load_svg_sprites', 9999);

/**
 * Replace footer HTML content with custom site footer text.
 */
function starcast_filter_footer_html_content($content) {
    return '&copy; 2023 Starcast Technologies<br>Reg No 2023/770423/07';
}
add_filter('theme_mod_footer_html_content', 'starcast_filter_footer_html_content');
