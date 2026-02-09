<?php
/**
 * Plugin Name: Starcast Content Display
 * Description: Theme-independent content display for Starcast ISP platform - Fibre packages, LTE packages, and custom functionality
 * Version: 1.0.0
 * Author: Starcast Technologies
 */

if (!defined('ABSPATH')) exit;

class Starcast_Content_Display {

    public function __construct() {
        // Register shortcodes
        add_shortcode('starcast_fibre_packages', [$this, 'display_fibre_packages']);
        add_shortcode('starcast_fibre_carousel', [$this, 'display_fibre_carousel']);
        add_shortcode('starcast_lte_packages', [$this, 'display_lte_packages']);
        add_shortcode('starcast_lte_carousel', [$this, 'display_lte_carousel']);
        add_shortcode('starcast_package_selection', [$this, 'display_package_selection']);
        add_shortcode('starcast_fibre_signup', [$this, 'display_fibre_signup']);
        add_shortcode('starcast_lte_signup', [$this, 'display_lte_signup']);
        add_shortcode('starcast_router_selection', [$this, 'display_router_selection']);
        add_shortcode('starcast_booking_form', [$this, 'display_booking_form']);

        // Enqueue styles and scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Enqueue CSS and JS for the shortcodes
     */
    public function enqueue_assets() {
        $post = get_post();
        $post_content = $post ? $post->post_content : '';
        $has_fibre_packages = has_shortcode($post_content, 'starcast_fibre_packages');
        $has_fibre_carousel = has_shortcode($post_content, 'starcast_fibre_carousel');
        $has_lte_packages = has_shortcode($post_content, 'starcast_lte_packages');
        $has_lte_carousel = has_shortcode($post_content, 'starcast_lte_carousel');
        $has_package_selection = has_shortcode($post_content, 'starcast_package_selection');

        if ($has_fibre_packages || $has_lte_packages || $has_package_selection) {

            wp_enqueue_style('starcast-packages', plugin_dir_url(__FILE__) . 'assets/starcast-packages.css', [], '1.0.0');
            wp_enqueue_script('starcast-packages', plugin_dir_url(__FILE__) . 'assets/starcast-packages.js', ['jquery'], '1.0.0', true);
        }

        if ($has_fibre_carousel) {
            wp_enqueue_style('starcast-fibre-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800;900&family=Roboto:wght@300;400;500;600;700&display=swap', [], '1.0.0');
            wp_enqueue_style('starcast-fibre-carousel', plugin_dir_url(__FILE__) . 'assets/fibre-carousel.css', [], '1.0.0');
            wp_enqueue_script('starcast-fibre-carousel', plugin_dir_url(__FILE__) . 'assets/fibre-carousel.js', [], '1.0.0', true);

            $providers_csv = $this->get_fibre_carousel_providers_from_content($post_content);
            $provider_data = $this->get_fibre_provider_data($providers_csv);
            wp_localize_script('starcast-fibre-carousel', 'starcastFibreData', [
                'providers' => $provider_data,
                'signupUrl' => home_url('/signup/')
            ]);
        }

        if ($has_lte_carousel) {
            wp_enqueue_style('starcast-lte-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800;900&display=swap', [], '1.0.0');
            wp_enqueue_style('starcast-lte-carousel', plugin_dir_url(__FILE__) . 'assets/lte-carousel.css', [], '1.0.0');
            wp_enqueue_script('starcast-lte-carousel', plugin_dir_url(__FILE__) . 'assets/lte-carousel.js', [], '1.0.0', true);

            $provider_data = $this->get_lte_provider_data();
            wp_localize_script('starcast-lte-carousel', 'starcastLteData', [
                'providers' => $provider_data,
                'signupUrl' => home_url('/router-selection/')
            ]);
        }
    }

    /**
     * Display Fibre Packages
     */
    public function display_fibre_packages($atts) {
        $atts = shortcode_atts([
            'providers' => 'openserve,octotel,frogfoot,vuma,metrofibre', // Default major providers
            'show_filters' => 'yes',
            'columns' => '3'
        ], $atts);

        // Get providers
        $allowed_providers = array_map('trim', explode(',', $atts['providers']));
        $providers = get_terms([
            'taxonomy' => 'fibre_provider',
            'hide_empty' => false,
        ]);

        $provider_data = [];

        foreach ($providers as $provider) {
            $provider_slug_lower = strtolower($provider->slug);
            $provider_name_lower = strtolower($provider->name);

            // Check if this provider is allowed
            $is_allowed = false;
            $priority = 999;

            foreach ($allowed_providers as $index => $allowed) {
                if ($provider_slug_lower === strtolower($allowed) ||
                    $provider_name_lower === strtolower($allowed) ||
                    strpos($provider_slug_lower, strtolower($allowed)) !== false ||
                    strpos($provider_name_lower, strtolower($allowed)) !== false) {
                    $is_allowed = true;
                    $priority = $index;
                    break;
                }
            }

            if (!$is_allowed) continue;

            // Get packages for this provider
            $posts = get_posts([
                'post_type' => 'fibre_packages',
                'numberposts' => -1,
                'tax_query' => [[
                    'taxonomy' => 'fibre_provider',
                    'field' => 'slug',
                    'terms' => $provider->slug,
                ]]
            ]);

            $logo = function_exists('get_field') ? get_field('logo', $provider) : null;

            $packages = [];
            foreach ($posts as $post) {
                $price = get_field('price', $post->ID) ?: get_post_meta($post->ID, 'price', true);
                $download = get_field('download', $post->ID) ?: get_post_meta($post->ID, 'download', true);
                $upload = get_field('upload', $post->ID) ?: get_post_meta($post->ID, 'upload', true);

                $packages[] = [
                    'id' => $post->ID,
                    'title' => get_the_title($post),
                    'price' => $price ? intval($price) : 0,
                    'download' => $download ?: 'N/A',
                    'upload' => $upload ?: 'N/A',
                    'provider' => $provider->name,
                    'download_speed' => intval(preg_replace('/[^0-9]/', '', $download)),
                ];
            }

            // Sort packages by download speed
            usort($packages, function($a, $b) {
                return $a['download_speed'] - $b['download_speed'];
            });

            if (!empty($packages)) {
                $provider_data[] = [
                    'name' => $provider->name,
                    'slug' => $provider->slug,
                    'logo' => $logo,
                    'packages' => $packages,
                    'priority' => $priority
                ];
            }
        }

        // Sort providers by priority
        usort($provider_data, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        // Generate HTML output
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/fibre-packages.php';
        return ob_get_clean();
    }

    /**
     * Display Fibre Carousel (theme-independent)
     */
    public function display_fibre_carousel($atts) {
        $atts = shortcode_atts([
            'providers' => 'openserve,octotel,frogfoot,vuma,metrofibre'
        ], $atts);

        $provider_data = $this->get_fibre_provider_data($atts['providers']);

        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/fibre-carousel.php';
        return ob_get_clean();
    }

    /**
     * Display LTE Packages
     */
    public function display_lte_packages($atts) {
        $atts = shortcode_atts([
            'show_filters' => 'yes',
            'columns' => '3'
        ], $atts);

        // Get LTE packages
        $packages = get_posts([
            'post_type' => 'lte_packages',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        $package_data = [];
        foreach ($packages as $post) {
            $price = get_field('price', $post->ID) ?: get_post_meta($post->ID, 'price', true);
            $data = get_field('data', $post->ID) ?: get_post_meta($post->ID, 'data', true);
            $data_limit = get_field('data_limit', $post->ID) ?: get_post_meta($post->ID, 'data_limit', true) ?: $data;
            $speed = get_field('speed', $post->ID) ?: get_post_meta($post->ID, 'speed', true) ?: 'Variable';
            $validity = get_field('validity', $post->ID) ?: get_post_meta($post->ID, 'validity', true) ?: '30 days';

            $package_data[] = [
                'id' => $post->ID,
                'title' => get_the_title($post),
                'price' => $price ?: 0,
                'data_limit' => $data_limit ?: 'N/A',
                'speed' => $speed,
                'validity' => $validity,
            ];
        }

        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/lte-packages.php';
        return ob_get_clean();
    }

    /**
     * Display LTE Carousel (theme-independent)
     */
    public function display_lte_carousel($atts) {
        $atts = shortcode_atts([
            'providers' => 'vodacom,mtn,telkom'
        ], $atts);

        $provider_data = $this->get_lte_provider_data($atts['providers']);

        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/lte-carousel.php';
        return ob_get_clean();
    }

    /**
     * Display Package Selection (used in LTE-5G page)
     */
    public function display_package_selection($atts) {
        $atts = shortcode_atts([
            'type' => 'lte' // lte or fibre
        ], $atts);

        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/package-selection.php';
        return ob_get_clean();
    }

    public function display_fibre_signup($atts) {
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/fibre-signup.php';
        return ob_get_clean();
    }

    public function display_lte_signup($atts) {
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/lte-signup.php';
        return ob_get_clean();
    }

    public function display_router_selection($atts) {
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/router-selection.php';
        return ob_get_clean();
    }

    public function display_booking_form($atts) {
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/booking-form.php';
        return ob_get_clean();
    }

    private function get_fibre_provider_data($providers_csv = null) {
        $allowed_providers = $providers_csv ? array_map('trim', explode(',', $providers_csv)) : ['openserve', 'octotel', 'frogfoot', 'vuma', 'metrofibre'];
        $providers = get_terms([
            'taxonomy' => 'fibre_provider',
            'hide_empty' => false,
        ]);

        $provider_data = [];

        foreach ($providers as $provider) {
            $provider_slug_lower = strtolower($provider->slug);
            $provider_name_lower = strtolower($provider->name);

            // Check if this provider is allowed
            $is_allowed = false;
            $priority = 999;

            foreach ($allowed_providers as $index => $allowed) {
                $allowed_lower = strtolower($allowed);
                if ($provider_slug_lower === $allowed_lower ||
                    $provider_name_lower === $allowed_lower ||
                    strpos($provider_slug_lower, $allowed_lower) !== false ||
                    strpos($provider_name_lower, $allowed_lower) !== false) {
                    $is_allowed = true;
                    $priority = $index;
                    break;
                }
            }

            if (!$is_allowed) {
                continue;
            }

            // Get packages for this provider
            $posts = get_posts([
                'post_type' => 'fibre_packages',
                'numberposts' => -1,
                'tax_query' => [[
                    'taxonomy' => 'fibre_provider',
                    'field' => 'slug',
                    'terms' => $provider->slug,
                ]]
            ]);

            $logo = function_exists('get_field') ? get_field('logo', $provider) : null;
            if (is_array($logo) && isset($logo['url'])) {
                $logo = $logo['url'];
            }

            $packages = [];
            foreach ($posts as $post) {
                $price = get_field('price', $post->ID) ?: get_post_meta($post->ID, 'price', true);
                $download = get_field('download', $post->ID) ?: get_post_meta($post->ID, 'download', true);
                $upload = get_field('upload', $post->ID) ?: get_post_meta($post->ID, 'upload', true);

                $packages[] = [
                    'id' => $post->ID,
                    'title' => get_the_title($post),
                    'price' => $price ? intval($price) : 0,
                    'download' => $download ?: 'N/A',
                    'upload' => $upload ?: 'N/A',
                    'provider' => $provider->name,
                    'download_speed' => intval(preg_replace('/[^0-9]/', '', $download)),
                    'has_promo' => is_promo_active($post->ID),
                    'promo_price' => get_promo_price($post->ID),
                    'effective_price' => get_effective_price($post->ID),
                    'promo_savings' => get_promo_savings($post->ID),
                    'promo_display_text' => get_promo_display_text($post->ID),
                    'promo_badge_html' => get_promo_badge_html($post->ID),
                    'promo_duration' => get_promo_duration($post->ID),
                    'promo_type' => get_promo_type($post->ID),
                    'promo_text' => get_promo_text($post->ID),
                ];
            }

            // Sort packages by download speed
            usort($packages, function($a, $b) {
                return $a['download_speed'] - $b['download_speed'];
            });

            if (!empty($packages)) {
                $provider_data[] = [
                    'name' => $provider->name,
                    'slug' => $provider->slug,
                    'logo' => $logo,
                    'packages' => $packages,
                    'priority' => $priority
                ];
            }
        }

        // Sort providers by priority
        usort($provider_data, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return $provider_data;
    }

    private function get_lte_provider_data($providers_csv = null) {
        $allowed_providers = $providers_csv ? array_map('trim', explode(',', $providers_csv)) : ['vodacom', 'mtn', 'telkom'];

        $all_packages = [];
        $providers = get_terms([
            'taxonomy' => 'lte_provider',
            'hide_empty' => false,
        ]);

        foreach ($providers as $provider) {
            $posts = get_posts([
                'post_type' => 'lte_packages',
                'numberposts' => -1,
                'orderby' => 'meta_value_num',
                'meta_key' => 'display_order',
                'order' => 'ASC',
                'tax_query' => [[
                    'taxonomy' => 'lte_provider',
                    'field' => 'slug',
                    'terms' => $provider->slug,
                ]]
            ]);

            foreach ($posts as $post) {
                $price = get_field('price', $post->ID) ?: get_post_meta($post->ID, 'price', true);
                if (!$price) $price = 0;

                $speed = get_field('speed', $post->ID) ?: get_post_meta($post->ID, 'speed', true);
                if (!$speed) $speed = '';

                $data = get_field('data', $post->ID) ?: get_post_meta($post->ID, 'data', true);
                if (!$data) $data = '';

                $aup = get_field('aup', $post->ID) ?: get_post_meta($post->ID, 'aup', true);
                if (!$aup) $aup = '';

                $throttle = get_field('throttle', $post->ID) ?: get_post_meta($post->ID, 'throttle', true);
                if (!$throttle) $throttle = '';

                $package_type = get_post_meta($post->ID, 'package_type', true);
                if (!$package_type) {
                    $title_lower = strtolower(get_the_title($post));
                    if (strpos($title_lower, 'mobile') !== false) {
                        $package_type = 'mobile-data';
                    } elseif (strpos($title_lower, '5g') !== false) {
                        $package_type = 'fixed-5g';
                    } else {
                        $package_type = 'fixed-lte';
                    }
                }

                $all_packages[] = array(
                    'id' => $post->ID,
                    'name' => get_the_title($post),
                    'provider' => $provider->name,
                    'provider_slug' => $provider->slug,
                    'price' => intval($price),
                    'type' => $package_type,
                    'speed' => $speed,
                    'data' => $data,
                    'aup' => $aup,
                    'throttle' => $throttle
                );
            }
        }

        $grouped = [];
        $provider_names = $allowed_providers;

        foreach ($provider_names as $prov_name) {
            $packages = [];
            foreach ($all_packages as $pkg) {
                if (strtolower($pkg['provider']) === strtolower($prov_name)) {
                    $packages[] = $pkg;
                }
            }

            usort($packages, function($a, $b) {
                $a_is_mobile = stripos($a['name'], 'mobile') !== false;
                $b_is_mobile = stripos($b['name'], 'mobile') !== false;

                if ($a_is_mobile && !$b_is_mobile) {
                    return 1;
                }
                if (!$a_is_mobile && $b_is_mobile) {
                    return -1;
                }

                if ($a_is_mobile == $b_is_mobile) {
                    $a_is_5g = stripos($a['name'], '5g') !== false;
                    $b_is_5g = stripos($b['name'], '5g') !== false;

                    if (!$a_is_mobile && !$b_is_mobile) {
                        if ($a_is_5g && !$b_is_5g) return -1;
                        if (!$a_is_5g && $b_is_5g) return 1;
                    }

                    return $a['price'] - $b['price'];
                }

                return 0;
            });

            if (!empty($packages)) {
                $slug = strtolower(str_replace(' ', '-', $prov_name));
                $term = get_term_by('slug', $slug, 'lte_provider');
                $logo = '';
                if ($term) {
                    $logo = function_exists('get_field') ? get_field('logo', $term) : '';
                    if (!$logo) {
                        $logo = get_term_meta($term->term_id, 'logo', true);
                    }
                }

                if (is_array($logo) && isset($logo['url'])) {
                    $logo = $logo['url'];
                }

                $grouped[] = [
                    'name' => $prov_name,
                    'slug' => $slug,
                    'logo' => $logo ?: '',
                    'packages' => $packages
                ];
            }
        }

        return $grouped;
    }

    private function get_fibre_carousel_providers_from_content($post_content) {
        if (!$post_content) {
            return null;
        }

        $pattern = '/\\[starcast_fibre_carousel\\b([^\\]]*)\\]/i';
        if (!preg_match($pattern, $post_content, $matches)) {
            return null;
        }

        $atts = shortcode_parse_atts($matches[1]);
        if (is_array($atts) && !empty($atts['providers'])) {
            return $atts['providers'];
        }

        return null;
    }
}

// Initialize the plugin
new Starcast_Content_Display();

add_action('after_setup_theme', 'starcast_register_promo_functions', 20);

function starcast_register_promo_functions() {
    if (!function_exists('get_global_promo_settings')) {
        function get_global_promo_settings() {
            return get_option('ppm_promo_settings', array(
                'master_toggle' => 'enabled',
                'campaign_name' => '',
                'start_date' => '',
                'end_date' => '',
                'default_duration' => '2',
                'default_badge_text' => 'PROMO'
            ));
        }
    }

    if (!function_exists('are_promos_globally_enabled')) {
        function are_promos_globally_enabled() {
            $settings = get_global_promo_settings();

            // Check master toggle
            if ($settings['master_toggle'] !== 'enabled') {
                return false;
            }

            // Check date range if set
            if (!empty($settings['start_date']) && !empty($settings['end_date'])) {
                $now = current_time('Y-m-d');
                $start = $settings['start_date'];
                $end = $settings['end_date'];

                if ($now < $start || $now > $end) {
                    return false;
                }
            }

            return true;
        }
    }

    if (!function_exists('is_promo_active')) {
        function is_promo_active($package_id = null, $package_data = null) {
            // Check global promo status first
            if (!are_promos_globally_enabled()) {
                return false;
            }

            // If package_id is provided, get from post meta
            if ($package_id) {
                $promo_active = get_post_meta($package_id, 'promo_active', true);
                return $promo_active === 'yes' || $promo_active === true;
            }

            // If package_data is provided (for JSON packages), check the array
            if ($package_data && is_array($package_data)) {
                return isset($package_data['promo_active']) && $package_data['promo_active'] === true;
            }

            return false;
        }
    }

    if (!function_exists('get_promo_price')) {
        function get_promo_price($package_id = null, $package_data = null) {
            if (!is_promo_active($package_id, $package_data)) {
                return null;
            }

            // If package_id is provided, get from post meta
            if ($package_id) {
                $promo_price = get_post_meta($package_id, 'promo_price', true);
                return $promo_price ? intval($promo_price) : null;
            }

            // If package_data is provided (for JSON packages), check the array
            if ($package_data && is_array($package_data)) {
                return isset($package_data['promo_price']) ? intval($package_data['promo_price']) : null;
            }

            return null;
        }
    }

    if (!function_exists('get_regular_price')) {
        function get_regular_price($package_id = null, $package_data = null) {
            // If package_id is provided, get from post meta
            if ($package_id) {
                $price = get_field('price', $package_id);
                if (!$price) $price = get_post_meta($package_id, 'price', true);
                return $price ? intval($price) : 0;
            }

            // If package_data is provided (for JSON packages), check the array
            if ($package_data && is_array($package_data)) {
                return isset($package_data['price']) ? intval($package_data['price']) : 0;
            }

            return 0;
        }
    }

    if (!function_exists('get_effective_price')) {
        function get_effective_price($package_id = null, $package_data = null) {
            $promo_price = get_promo_price($package_id, $package_data);
            if ($promo_price !== null) {
                return $promo_price;
            }

            return get_regular_price($package_id, $package_data);
        }
    }

    if (!function_exists('get_promo_savings')) {
        function get_promo_savings($package_id = null, $package_data = null) {
            $regular_price = get_regular_price($package_id, $package_data);
            $promo_price = get_promo_price($package_id, $package_data);

            if ($promo_price === null) {
                return 0;
            }

            return $regular_price - $promo_price;
        }
    }

    if (!function_exists('get_promo_duration')) {
        function get_promo_duration($package_id = null, $package_data = null) {
            // If package_id is provided, get from post meta
            if ($package_id) {
                $duration = get_post_meta($package_id, 'promo_duration', true);
                return $duration ? intval($duration) : 2;
            }

            // If package_data is provided (for JSON packages), check the array
            if ($package_data && is_array($package_data)) {
                return isset($package_data['promo_duration']) ? intval($package_data['promo_duration']) : 2;
            }

            return 2;
        }
    }

    if (!function_exists('get_promo_badge_text')) {
        function get_promo_badge_text($package_id = null, $package_data = null) {
            // If package_id is provided, get from post meta
            if ($package_id) {
                $badge_text = get_post_meta($package_id, 'promo_badge_text', true);
                return $badge_text ?: 'PROMO';
            }

            // If package_data is provided (for JSON packages), check the array
            if ($package_data && is_array($package_data)) {
                return isset($package_data['promo_badge_text']) ? $package_data['promo_badge_text'] : 'PROMO';
            }

            return 'PROMO';
        }
    }

    if (!function_exists('get_promo_text')) {
        function get_promo_text($package_id = null, $package_data = null) {
            // If package_id is provided, get from post meta
            if ($package_id) {
                return get_post_meta($package_id, 'promo_text', true) ?: '';
            }

            // If package_data is provided (for JSON packages), check the array
            if ($package_data && is_array($package_data)) {
                return isset($package_data['promo_text']) ? $package_data['promo_text'] : '';
            }

            return '';
        }
    }

    if (!function_exists('get_promo_type')) {
        function get_promo_type($package_id = null, $package_data = null) {
            // If package_id is provided, get from post meta
            if ($package_id) {
                $type = get_post_meta($package_id, 'promo_type', true);
                return $type ?: 'general';
            }

            // If package_data is provided (for JSON packages), check the array
            if ($package_data && is_array($package_data)) {
                return isset($package_data['promo_type']) ? $package_data['promo_type'] : 'general';
            }

            return 'general';
        }
    }

    if (!function_exists('get_promo_display_text')) {
        function get_promo_display_text($package_id = null, $package_data = null) {
            if (!is_promo_active($package_id, $package_data)) {
                return '';
            }

            $regular_price = get_regular_price($package_id, $package_data);
            $promo_price = get_promo_price($package_id, $package_data);
            $duration = get_promo_duration($package_id, $package_data);
            $savings = get_promo_savings($package_id, $package_data);
            $custom_text = get_promo_text($package_id, $package_data);

            if ($custom_text) {
                return $custom_text;
            }

            // Generate default text
            $month_text = $duration == 1 ? 'month' : 'months';
            return "R{$promo_price} for first {$duration} {$month_text}, then R{$regular_price} - Save R{$savings}!";
        }
    }

    if (!function_exists('get_promo_badge_html')) {
        function get_promo_badge_html($package_id = null, $package_data = null) {
            if (!is_promo_active($package_id, $package_data)) {
                return '';
            }

            $badge_text = get_promo_badge_text($package_id, $package_data);

            return '<span class="promo-badge promo-badge-promo">' . $badge_text . '</span>';
        }
    }
}

add_action('after_setup_theme', 'starcast_register_google_places_functions', 20);

function starcast_register_google_places_functions() {
    if (!function_exists('starcast_get_google_maps_api_key')) {
        function starcast_get_google_maps_api_key() {
            // Securely retrieve the API key from wp-config.php
            // Returns an empty string if not defined, preventing key exposure
            return defined('STARCAST_GOOGLE_MAPS_API_KEY') ? STARCAST_GOOGLE_MAPS_API_KEY : '';
        }
    }

    if (!function_exists('starcast_enqueue_google_maps_api')) {
        function starcast_enqueue_google_maps_api($callback_function = 'initGooglePlaces') {
            static $enqueued = false;

            if (!$enqueued) {
                $api_key = starcast_get_google_maps_api_key();

                // Only enqueue the script if the API key is available
                if (empty($api_key)) {
                    // Log an admin-facing error if the key is missing
                    if (is_admin()) {
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-error"><p><strong>Starcast Notice:</strong> Google Maps API key is not configured. Please define STARCAST_GOOGLE_MAPS_API_KEY in your wp-config.php file.</p></div>';
                        });
                    }
                    // Log a console error for developers
                    error_log('Starcast Notice: Google Maps API key is missing. The script will not be enqueued.');
                    return;
                }

                $maps_api_url = "https://maps.googleapis.com/maps/api/js?key={$api_key}&libraries=places&callback={$callback_function}";

                wp_enqueue_script(
                    'google-maps-api',
                    $maps_api_url,
                    array(),
                    '1.0',
                    true
                );

                $enqueued = true;
            }
        }
    }

    if (!function_exists('starcast_google_places_script')) {
        function starcast_google_places_script($page_identifier, $input_id, $callback_name = null) {
            $callback_name = $callback_name ?: "initGooglePlaces{$page_identifier}";
            $api_key = starcast_get_google_maps_api_key();
            $debug = defined('STARCAST_GOOGLE_MAPS_DEBUG') && STARCAST_GOOGLE_MAPS_DEBUG;

            // If API key is missing, output a degradation script and return
            if (empty($api_key)) {
                echo "
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const addressInput = document.getElementById('{$input_id}');
                    if (addressInput) {
                        addressInput.placeholder = 'Address functionality disabled. Missing API key.';
                        addressInput.disabled = true;
                        console.warn('[{$page_identifier}] Google Maps API key is not defined. Address autocomplete is disabled.');
                    }
                });
                </script>";
                return;
            }

            echo "
            <script async defer src=\"https://maps.googleapis.com/maps/api/js?key={$api_key}&libraries=places&callback={$callback_name}\"></script>
            <script>
            // Google Maps API Error Handler
            window.gm_authFailure = function() {
                console.error('[{$page_identifier}] Google Maps API authentication failed. Check your API key and billing.');
                const addressInput = document.getElementById('{$input_id}');
                if (addressInput) {
                    addressInput.placeholder = 'Google Maps authentication failed. Enter address manually.';
                    addressInput.style.borderColor = '#ff6b6b';
                }
            };

            function {$callback_name}() {
                const addressInput = document.getElementById('{$input_id}');

                if (!addressInput) {
                    console.error('[{$page_identifier}] Address input field #{$input_id} not found.');
                    return;
                }

                // Check if Google Maps API is loaded
                if (typeof google === 'undefined') {
                    console.error('[{$page_identifier}] Google Maps API not loaded. Check your internet connection and API key.');
                    addressInput.placeholder = 'Google Maps API not loaded. Enter address manually.';
                    return;
                }

                if (typeof google.maps === 'undefined') {
                    console.error('[{$page_identifier}] Google Maps core not loaded.');
                    addressInput.placeholder = 'Google Maps core not loaded. Enter address manually.';
                    return;
                }

                if (typeof google.maps.places === 'undefined') {
                    console.error('[{$page_identifier}] Google Places library not loaded. Check API key permissions.');
                    addressInput.placeholder = 'Google Places not loaded. Enter address manually.';
                    return;
                }

                try {
                    const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                        componentRestrictions: { country: 'za' },
                        fields: ['formatted_address', 'geometry', 'address_components'],
                        types: ['address']
                    });

                    // Set bounds to South Africa
                    const southAfricaBounds = new google.maps.LatLngBounds(
                        new google.maps.LatLng(-34.8, 16.3), // Southwest corner
                        new google.maps.LatLng(-22.1, 32.9)  // Northeast corner
                    );
                    autocomplete.setBounds(southAfricaBounds);

                    // Update placeholder
                    addressInput.placeholder = 'Start typing your address...';
                    addressInput.style.borderColor = ''; // Reset any error styling

                    // Add place selection listener
                    autocomplete.addListener('place_changed', function() {
                        const place = autocomplete.getPlace();
                        " . ($debug ? "console.log('[{$page_identifier}] Place object:', place);" : "") . "

                        if (place && place.formatted_address) {
                            addressInput.value = place.formatted_address;
                            addressInput.style.borderColor = '#28a745'; // Success green

                            // Dispatch custom event for other scripts to listen to
                            const event = new CustomEvent('starcast_address_selected', {
                                detail: { place: place, page: '{$page_identifier}' }
                            });
                            document.dispatchEvent(event);

                            console.log('[{$page_identifier}] Address selected:', place.formatted_address);
                        } else {
                            console.warn('[{$page_identifier}] Place changed, but no formatted_address found.', place);
                            if (place && place.name) {
                                addressInput.value = place.name;
                            }
                        }
                    });

                    // Add error handling for geocoder failures
                    autocomplete.addListener('error', function(error) {
                        console.error('[{$page_identifier}] Google Places error:', error);
                        addressInput.placeholder = 'Address lookup error. Enter manually.';
                    });

                    console.log('[{$page_identifier}] Google Places Autocomplete initialized successfully for #{$input_id}.');

                } catch (e) {
                    console.error('[{$page_identifier}] Error initializing Google Places Autocomplete:', e);
                    addressInput.placeholder = 'Address autocomplete error. Enter manually.';
                    addressInput.style.borderColor = '#ff6b6b'; // Error red

                    // Log additional debug info if enabled
                    " . ($debug ? "console.error('[{$page_identifier}] Debug info:', { apiKey: '{$api_key}', googloaded: typeof google !== 'undefined', mapsLoaded: typeof google !== 'undefined' && typeof google.maps !== 'undefined', placesLoaded: typeof google !== 'undefined' && typeof google.maps !== 'undefined' && typeof google.maps.places !== 'undefined' });" : "") . "
                }
            }

            // Fallback: Try to initialize after a delay if callback doesn't fire
            setTimeout(function() {
                if (typeof {$callback_name} === 'function' && document.getElementById('{$input_id}') && document.getElementById('{$input_id}').placeholder === 'Start typing your address...') {
                    // Already initialized successfully
                    return;
                }
                if (typeof google !== 'undefined' && typeof google.maps !== 'undefined' && typeof google.maps.places !== 'undefined') {
                    {$callback_name}();
                }
            }, 3000);
            </script>";
        }
    }
}
