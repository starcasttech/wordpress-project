<?php
/**
 * Plugin Name: Starcast Complete API
 * Plugin URI: http://localhost
 * Description: Complete WordPress plugin for Starcast Technologies - handles post types, taxonomies, REST API, bookings, and React frontend integration
 * Version: 2.0.0
 * Author: Starcast Technologies
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class StarcastCompleteAPI {
    
    public function __construct() {
        // Core WordPress integration
        add_action('init', array($this, 'register_custom_post_types'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('rest_api_init', array($this, 'register_rest_fields'));
        add_action('rest_api_init', array($this, 'register_custom_routes'));
        add_action('acf/init', array($this, 'register_acf_fields'));
        
        // Database and email setup
        add_action('init', array($this, 'create_database_tables'));
        add_action('rest_api_init', array($this, 'setup_cors_headers'));
    }
    
    /**
     * Register custom post types with REST API support
     */
    public function register_custom_post_types() {
        
        // Register Fibre Packages Post Type
        $fibre_args = array(
            'label' => 'Fibre Packages',
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true, // Enable native WordPress REST API
            'rest_base' => 'fibre_packages',
            'query_var' => true,
            'rewrite' => array('slug' => 'fibre_packages'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-networking',
            'supports' => array('title', 'editor', 'custom-fields'),
            'labels' => array(
                'name' => 'Fibre Packages',
                'singular_name' => 'Fibre Package',
                'menu_name' => 'Fibre Packages',
                'add_new' => 'Add New Package',
                'add_new_item' => 'Add New Fibre Package',
                'edit_item' => 'Edit Fibre Package',
                'new_item' => 'New Fibre Package',
                'view_item' => 'View Fibre Package',
                'search_items' => 'Search Fibre Packages',
                'not_found' => 'No fibre packages found',
                'not_found_in_trash' => 'No fibre packages found in trash'
            )
        );
        register_post_type('fibre_packages', $fibre_args);
        
        // Register LTE Packages Post Type  
        $lte_args = array(
            'label' => 'LTE Packages',
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true, // Enable native WordPress REST API
            'rest_base' => 'lte_packages',
            'query_var' => true,
            'rewrite' => array('slug' => 'lte_packages'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 6,
            'menu_icon' => 'dashicons-smartphone',
            'supports' => array('title', 'editor', 'custom-fields'),
            'labels' => array(
                'name' => 'LTE Packages',
                'singular_name' => 'LTE Package',
                'menu_name' => 'LTE Packages',
                'add_new' => 'Add New Package',
                'add_new_item' => 'Add New LTE Package',
                'edit_item' => 'Edit LTE Package',
                'new_item' => 'New LTE Package',
                'view_item' => 'View LTE Package',
                'search_items' => 'Search LTE Packages',
                'not_found' => 'No LTE packages found',
                'not_found_in_trash' => 'No LTE packages found in trash'
            )
        );
        register_post_type('lte_packages', $lte_args);
    }
    
    /**
     * Register custom taxonomies with REST API support
     */
    public function register_taxonomies() {
        
        // Register Fibre Provider Taxonomy
        $fibre_provider_args = array(
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest' => true, // Enable native WordPress REST API
            'rest_base' => 'fibre_provider',
            'query_var' => true,
            'rewrite' => array('slug' => 'fibre_provider'),
            'labels' => array(
                'name' => 'Fibre Providers',
                'singular_name' => 'Fibre Provider',
                'search_items' => 'Search Fibre Providers',
                'all_items' => 'All Fibre Providers',
                'parent_item' => 'Parent Fibre Provider',
                'parent_item_colon' => 'Parent Fibre Provider:',
                'edit_item' => 'Edit Fibre Provider',
                'update_item' => 'Update Fibre Provider',
                'add_new_item' => 'Add New Fibre Provider',
                'new_item_name' => 'New Fibre Provider Name',
                'menu_name' => 'Fibre Providers'
            )
        );
        register_taxonomy('fibre_provider', array('fibre_packages'), $fibre_provider_args);
        
        // Register LTE Provider Taxonomy
        $lte_provider_args = array(
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest' => true, // Enable native WordPress REST API
            'rest_base' => 'lte_provider',
            'query_var' => true,
            'rewrite' => array('slug' => 'lte_provider'),
            'labels' => array(
                'name' => 'LTE Providers',
                'singular_name' => 'LTE Provider',
                'search_items' => 'Search LTE Providers',
                'all_items' => 'All LTE Providers',
                'parent_item' => 'Parent LTE Provider',
                'parent_item_colon' => 'Parent LTE Provider:',
                'edit_item' => 'Edit LTE Provider',
                'update_item' => 'Update LTE Provider',
                'add_new_item' => 'Add New LTE Provider',
                'new_item_name' => 'New LTE Provider Name',
                'menu_name' => 'LTE Providers'
            )
        );
        register_taxonomy('lte_provider', array('lte_packages'), $lte_provider_args);
    }
    
    /**
     * Register REST API fields for ACF integration
     */
    public function register_rest_fields() {
        
        // Register ACF fields for fibre packages
        register_rest_field('fibre_packages', 'acf', array(
            'get_callback' => array($this, 'get_acf_fields'),
            'update_callback' => array($this, 'update_acf_fields'),
            'schema' => array(
                'description' => 'ACF fields for fibre packages',
                'type' => 'object'
            )
        ));
        
        // Register ACF fields for LTE packages
        register_rest_field('lte_packages', 'acf', array(
            'get_callback' => array($this, 'get_acf_fields'),
            'update_callback' => array($this, 'update_acf_fields'),
            'schema' => array(
                'description' => 'ACF fields for LTE packages',
                'type' => 'object'
            )
        ));
        
        // Register ACF fields for provider taxonomies
        register_rest_field('fibre_provider', 'acf', array(
            'get_callback' => array($this, 'get_taxonomy_acf_fields'),
            'update_callback' => array($this, 'update_taxonomy_acf_fields'),
            'schema' => array(
                'description' => 'ACF fields for fibre providers',
                'type' => 'object'
            )
        ));
        
        register_rest_field('lte_provider', 'acf', array(
            'get_callback' => array($this, 'get_taxonomy_acf_fields'),
            'update_callback' => array($this, 'update_taxonomy_acf_fields'),
            'schema' => array(
                'description' => 'ACF fields for LTE providers',
                'type' => 'object'
            )
        ));
    }
    
    /**
     * Register custom REST API routes for legacy support and additional functionality
     */
    public function register_custom_routes() {
        
        // Legacy packages endpoints (for backward compatibility)
        register_rest_route('starcast/v1', '/packages/fibre', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_fibre_packages_legacy'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('starcast/v1', '/packages/lte', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_lte_packages_legacy'),
            'permission_callback' => '__return_true'
        ));
        
        // Booking endpoints
        register_rest_route('starcast/v1', '/bookings', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_booking'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('starcast/v1', '/bookings/availability/(?P<date>[0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'check_availability'),
            'permission_callback' => '__return_true'
        ));
        
        // Application submission endpoints
        register_rest_route('starcast/v1', '/fibre-application', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_fibre_application'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('starcast/v1', '/lte-application', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_lte_application'),
            'permission_callback' => '__return_true'
        ));
        
        // Signup endpoints
        register_rest_route('starcast/v1', '/signup', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_signup'),
            'permission_callback' => '__return_true'
        ));
        
        // Contact form endpoint
        register_rest_route('starcast/v1', '/contact', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_contact_email'),
            'permission_callback' => '__return_true'
        ));
        
        // Promo code validation
        register_rest_route('starcast/v1', '/validate-promo', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_promo_code'),
            'permission_callback' => '__return_true'
        ));
    }
    
    // =================
    // ACF FIELD HANDLERS
    // =================
    
    /**
     * Get ACF fields for posts
     */
    public function get_acf_fields($object, $field_name, $request) {
        if (function_exists('get_fields')) {
            return get_fields($object['id']);
        }
        return array();
    }
    
    /**
     * Update ACF fields for posts
     */
    public function update_acf_fields($value, $object, $field_name, $request) {
        if (function_exists('update_field')) {
            foreach ($value as $field_key => $field_value) {
                update_field($field_key, $field_value, $object->ID);
            }
        }
        return true;
    }
    
    /**
     * Get ACF fields for taxonomy terms
     */
    public function get_taxonomy_acf_fields($object, $field_name, $request) {
        if (function_exists('get_fields')) {
            return get_fields($object['taxonomy'] . '_' . $object['id']);
        }
        return array();
    }
    
    /**
     * Update ACF fields for taxonomy terms
     */
    public function update_taxonomy_acf_fields($value, $object, $field_name, $request) {
        if (function_exists('update_field')) {
            foreach ($value as $field_key => $field_value) {
                update_field($field_key, $field_value, $object['taxonomy'] . '_' . $object['id']);
            }
        }
        return true;
    }
    
    // =================
    // LEGACY API ENDPOINTS
    // =================
    
    /**
     * Legacy fibre packages endpoint (maintains backward compatibility)
     */
    public function get_fibre_packages_legacy($request) {
        $packages = get_posts(array(
            'post_type' => 'fibre_packages',
            'numberposts' => -1,
            'post_status' => 'publish'
        ));
        
        $formatted_packages = array();
        
        foreach ($packages as $package) {
            $acf = function_exists('get_fields') ? get_fields($package->ID) : array();
            $meta = get_post_meta($package->ID);
            
            $formatted_packages[] = array(
                'id' => $package->ID,
                'title' => $package->post_title,
                'description' => $package->post_content,
                'excerpt' => $package->post_excerpt,
                'price' => $acf['price'] ?? $meta['monthly_price'][0] ?? $meta['price'][0] ?? '0',
                'download' => $acf['download'] ?? $meta['download_speed'][0] ?? $meta['speed'][0] ?? 'N/A',
                'upload' => $acf['upload'] ?? $meta['upload_speed'][0] ?? $meta['speed'][0] ?? 'N/A',
                'speed' => $acf['download'] ?? $meta['download_speed'][0] ?? $meta['speed'][0] ?? 'N/A',
                'upload_speed' => $acf['upload'] ?? $meta['upload_speed'][0] ?? $meta['speed'][0] ?? 'N/A',
                'data' => 'Unlimited',
                'type' => 'fibre',
                'acf' => $acf,
                'features' => array(
                    'Unlimited Data',
                    'No Throttling',
                    'Free Installation',
                    '24/7 Support'
                ),
                'installation_fee' => $acf['installation_fee'] ?? $meta['installation_fee'][0] ?? '0',
                'contract_period' => $acf['contract_period'] ?? $meta['contract_period'][0] ?? '24 months',
                'router_included' => ($acf['router_included'] ?? $meta['router_included'][0] ?? 'no') === 'yes',
                'availability' => $acf['availability'] ?? $meta['availability'][0] ?? 'Available',
                'created_at' => $package->post_date,
                'updated_at' => $package->post_modified
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $formatted_packages,
            'count' => count($formatted_packages)
        ), 200);
    }
    
    /**
     * Legacy LTE packages endpoint (maintains backward compatibility)
     */
    public function get_lte_packages_legacy($request) {
        $packages = get_posts(array(
            'post_type' => 'lte_packages',
            'numberposts' => -1,
            'post_status' => 'publish'
        ));
        
        $formatted_packages = array();
        
        foreach ($packages as $package) {
            $acf = function_exists('get_fields') ? get_fields($package->ID) : array();
            $meta = get_post_meta($package->ID);
            
            $formatted_packages[] = array(
                'id' => $package->ID,
                'title' => $package->post_title,
                'description' => $package->post_content,
                'excerpt' => $package->post_excerpt,
                'price' => $acf['price'] ?? $meta['monthly_price'][0] ?? $meta['price'][0] ?? '0',
                'data' => $acf['data'] ?? $meta['data_limit'][0] ?? $meta['data'][0] ?? 'Unlimited',
                'speed' => $acf['speed'] ?? $meta['max_speed'][0] ?? $meta['speed'][0] ?? 'Up to 150Mbps',
                'type' => 'lte',
                'network' => $acf['network'] ?? $meta['network'][0] ?? '4G/5G',
                'acf' => $acf,
                'features' => array(
                    'High-Speed Internet',
                    'No Line Rental',
                    'Quick Setup',
                    'Portable'
                ),
                'installation_fee' => $acf['installation_fee'] ?? $meta['installation_fee'][0] ?? '0',
                'contract_period' => $acf['contract_period'] ?? $meta['contract_period'][0] ?? '24 months',
                'router_included' => ($acf['router_included'] ?? $meta['router_included'][0] ?? 'no') === 'yes',
                'sim_included' => ($acf['sim_included'] ?? $meta['sim_included'][0] ?? 'no') === 'yes',
                'availability' => $acf['availability'] ?? $meta['availability'][0] ?? 'Available',
                'created_at' => $package->post_date,
                'updated_at' => $package->post_modified
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => $formatted_packages,
            'count' => count($formatted_packages)
        ), 200);
    }
    
    // =================
    // BOOKING FUNCTIONALITY
    // =================
    
    /**
     * Create booking
     */
    public function create_booking($request) {
        $params = $request->get_json_params();
        
        // Validate required fields
        $required_fields = ['customer_name', 'customer_email', 'customer_phone', 'customer_address', 'package_type', 'booking_date', 'preferred_time'];
        foreach ($required_fields as $field) {
            if (empty($params[$field])) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => "Missing required field: $field"
                ), 400);
            }
        }
        
        global $wpdb;
        
        // Insert booking
        $result = $wpdb->insert(
            $wpdb->prefix . 'starcast_bookings',
            array(
                'customer_name' => sanitize_text_field($params['customer_name']),
                'customer_email' => sanitize_email($params['customer_email']),
                'customer_phone' => sanitize_text_field($params['customer_phone']),
                'customer_address' => sanitize_textarea_field($params['customer_address']),
                'package_id' => isset($params['package_id']) ? intval($params['package_id']) : null,
                'package_type' => sanitize_text_field($params['package_type']),
                'booking_date' => sanitize_text_field($params['booking_date']),
                'preferred_time' => sanitize_text_field($params['preferred_time']),
                'installation_notes' => sanitize_textarea_field($params['installation_notes'] ?? ''),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Failed to create booking'
            ), 500);
        }
        
        $booking_id = $wpdb->insert_id;
        $booking_reference = 'STB-' . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
        
        // Send confirmation email
        $this->send_booking_confirmation($params, $booking_id);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Booking created successfully',
            'data' => array(
                'booking_id' => $booking_id,
                'booking_reference' => $booking_reference,
                'customer_name' => $params['customer_name'],
                'customer_email' => $params['customer_email'],
                'booking_date' => $params['booking_date'],
                'preferred_time' => $params['preferred_time'],
                'status' => 'pending'
            )
        ), 201);
    }
    
    /**
     * Check availability for a date
     */
    public function check_availability($request) {
        $date = $request['date'];
        
        global $wpdb;
        
        $existing_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT preferred_time FROM {$wpdb->prefix}starcast_bookings WHERE booking_date = %s AND status NOT IN ('cancelled')",
            $date
        ));
        
        $all_time_slots = array(
            '08:00-10:00',
            '10:00-12:00',
            '12:00-14:00',
            '14:00-16:00',
            '16:00-18:00'
        );
        
        $booked_slots = array();
        foreach ($existing_bookings as $booking) {
            $booked_slots[] = $booking->preferred_time;
        }
        
        $available_slots = array_diff($all_time_slots, $booked_slots);
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'date' => $date,
                'available_slots' => array_values($available_slots),
                'booked_slots' => $booked_slots,
                'total_slots' => count($all_time_slots),
                'available_count' => count($available_slots)
            )
        ), 200);
    }
    
    // =================
    // APPLICATION SUBMISSION
    // =================
    
    /**
     * Submit fibre application
     */
    public function submit_fibre_application($request) {
        $params = $request->get_params();
        
        // Validate required fields
        $required_fields = ['customer_name', 'customer_email', 'customer_phone', 'customer_address'];
        foreach ($required_fields as $field) {
            if (empty($params[$field])) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => "Missing required field: $field"
                ), 400);
            }
        }
        
        global $wpdb;
        
        // Insert application
        $result = $wpdb->insert(
            $wpdb->prefix . 'starcast_applications',
            array(
                'customer_name' => sanitize_text_field($params['customer_name']),
                'customer_email' => sanitize_email($params['customer_email']),
                'customer_phone' => sanitize_text_field($params['customer_phone']),
                'customer_address' => sanitize_textarea_field($params['customer_address']),
                'package_id' => isset($params['package_id']) ? intval($params['package_id']) : null,
                'package_type' => 'fibre',
                'installation_address' => sanitize_textarea_field($params['installation_address'] ?? $params['customer_address']),
                'preferred_contact_method' => sanitize_text_field($params['preferred_contact_method'] ?? 'email'),
                'promo_code' => sanitize_text_field($params['promo_code'] ?? ''),
                'notes' => sanitize_textarea_field($params['notes'] ?? ''),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to submit application'
            ), 500);
        }
        
        $application_id = $wpdb->insert_id;
        $application_reference = 'STF-' . str_pad($application_id, 6, '0', STR_PAD_LEFT);
        
        // Send confirmation email
        $this->send_application_confirmation($params, $application_id, $application_reference);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Application submitted successfully',
            'data' => array(
                'application_id' => $application_id,
                'application_reference' => $application_reference,
                'customer_name' => $params['customer_name'],
                'customer_email' => $params['customer_email'],
                'status' => 'pending'
            )
        ), 201);
    }
    
    /**
     * Submit LTE application
     */
    public function submit_lte_application($request) {
        $params = $request->get_params();
        
        // Validate required fields
        $required_fields = ['customer_name', 'customer_email', 'customer_phone', 'customer_address'];
        foreach ($required_fields as $field) {
            if (empty($params[$field])) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => "Missing required field: $field"
                ), 400);
            }
        }
        
        global $wpdb;
        
        // Insert application
        $result = $wpdb->insert(
            $wpdb->prefix . 'starcast_applications',
            array(
                'customer_name' => sanitize_text_field($params['customer_name']),
                'customer_email' => sanitize_email($params['customer_email']),
                'customer_phone' => sanitize_text_field($params['customer_phone']),
                'customer_address' => sanitize_textarea_field($params['customer_address']),
                'package_id' => isset($params['package_id']) ? intval($params['package_id']) : null,
                'package_type' => 'lte',
                'installation_address' => sanitize_textarea_field($params['installation_address'] ?? $params['customer_address']),
                'preferred_contact_method' => sanitize_text_field($params['preferred_contact_method'] ?? 'email'),
                'promo_code' => sanitize_text_field($params['promo_code'] ?? ''),
                'notes' => sanitize_textarea_field($params['notes'] ?? ''),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to submit application'
            ), 500);
        }
        
        $application_id = $wpdb->insert_id;
        $application_reference = 'STL-' . str_pad($application_id, 6, '0', STR_PAD_LEFT);
        
        // Send confirmation email
        $this->send_application_confirmation($params, $application_id, $application_reference);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Application submitted successfully',
            'data' => array(
                'application_id' => $application_id,
                'application_reference' => $application_reference,
                'customer_name' => $params['customer_name'],
                'customer_email' => $params['customer_email'],
                'status' => 'pending'
            )
        ), 201);
    }
    
    // =================
    // SIGNUP & CONTACT
    // =================
    
    /**
     * Create signup
     */
    public function create_signup($request) {
        $params = $request->get_json_params();
        
        // Validate required fields
        $required_fields = ['customer_name', 'customer_email', 'customer_phone', 'customer_address', 'package_type'];
        foreach ($required_fields as $field) {
            if (empty($params[$field])) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => "Missing required field: $field"
                ), 400);
            }
        }
        
        global $wpdb;
        
        // Insert signup
        $result = $wpdb->insert(
            $wpdb->prefix . 'starcast_signups',
            array(
                'customer_name' => sanitize_text_field($params['customer_name']),
                'customer_email' => sanitize_email($params['customer_email']),
                'customer_phone' => sanitize_text_field($params['customer_phone']),
                'customer_address' => sanitize_textarea_field($params['customer_address']),
                'package_id' => isset($params['package_id']) ? intval($params['package_id']) : null,
                'package_type' => sanitize_text_field($params['package_type']),
                'installation_address' => sanitize_textarea_field($params['installation_address'] ?? $params['customer_address']),
                'preferred_contact_method' => sanitize_text_field($params['preferred_contact_method'] ?? 'email'),
                'notes' => sanitize_textarea_field($params['notes'] ?? ''),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Failed to create signup'
            ), 500);
        }
        
        $signup_id = $wpdb->insert_id;
        $signup_reference = 'STS-' . str_pad($signup_id, 6, '0', STR_PAD_LEFT);
        
        // Send confirmation email
        $this->send_signup_confirmation($params, $signup_id);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Signup created successfully',
            'data' => array(
                'signup_id' => $signup_id,
                'signup_reference' => $signup_reference,
                'customer_name' => $params['customer_name'],
                'customer_email' => $params['customer_email'],
                'package_type' => $params['package_type'],
                'status' => 'pending'
            )
        ), 201);
    }
    
    /**
     * Send contact email
     */
    public function send_contact_email($request) {
        $params = $request->get_json_params();
        
        // Validate required fields
        $required_fields = ['name', 'email', 'subject', 'message'];
        foreach ($required_fields as $field) {
            if (empty($params[$field])) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => "Missing required field: $field"
                ), 400);
            }
        }
        
        $to = get_option('admin_email');
        $subject = 'New Contact Form Submission - ' . sanitize_text_field($params['subject']);
        $message = sprintf(
            "New contact form submission:\n\nName: %s\nEmail: %s\nPhone: %s\nSubject: %s\nMessage:\n%s",
            sanitize_text_field($params['name']),
            sanitize_email($params['email']),
            sanitize_text_field($params['phone'] ?? 'Not provided'),
            sanitize_text_field($params['subject']),
            sanitize_textarea_field($params['message'])
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . sanitize_text_field($params['name']) . ' <' . sanitize_email($params['email']) . '>'
        );
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        return new WP_REST_Response(array(
            'success' => $sent,
            'message' => $sent ? 'Contact form submitted successfully' : 'Failed to send email'
        ), $sent ? 200 : 500);
    }
    
    /**
     * Validate promo code
     */
    public function validate_promo_code($request) {
        $params = $request->get_json_params();
        $promo_code = sanitize_text_field($params['promo_code'] ?? '');
        $package_id = intval($params['package_id'] ?? 0);
        $package_type = sanitize_text_field($params['package_type'] ?? '');
        
        if (empty($promo_code)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Promo code is required'
            ), 400);
        }
        
        // Simple promo code validation (extend this as needed)
        $valid_promos = array(
            'SAVE20' => array('discount' => 20, 'type' => 'percentage'),
            'NEWCUSTOMER' => array('discount' => 100, 'type' => 'fixed'),
            'FIBER50' => array('discount' => 50, 'type' => 'fixed'),
        );
        
        $promo_upper = strtoupper($promo_code);
        
        if (isset($valid_promos[$promo_upper])) {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Promo code is valid',
                'data' => array(
                    'promo_code' => $promo_code,
                    'discount' => $valid_promos[$promo_upper]['discount'],
                    'type' => $valid_promos[$promo_upper]['type'],
                    'description' => $this->get_promo_description($promo_upper)
                )
            ), 200);
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid promo code'
            ), 400);
        }
    }
    
    private function get_promo_description($promo_code) {
        $descriptions = array(
            'SAVE20' => '20% off for first 3 months',
            'NEWCUSTOMER' => 'R100 off installation fee',
            'FIBER50' => 'R50 off monthly fee for 6 months'
        );
        
        return $descriptions[$promo_code] ?? 'Special discount applied';
    }
    
    
    // =================
    // DATABASE TABLES
    // =================
    
    /**
     * Create database tables for bookings, signups, and applications
     */
    public function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Bookings table
        $bookings_table = $wpdb->prefix . 'starcast_bookings';
        $bookings_sql = "CREATE TABLE $bookings_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(20) NOT NULL,
            customer_address text NOT NULL,
            package_id mediumint(9),
            package_type enum('fibre', 'lte') NOT NULL,
            booking_date date NOT NULL,
            preferred_time varchar(50),
            installation_notes text,
            status enum('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email (customer_email),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        // Signups table
        $signups_table = $wpdb->prefix . 'starcast_signups';
        $signups_sql = "CREATE TABLE $signups_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(20) NOT NULL,
            customer_address text NOT NULL,
            package_id mediumint(9),
            package_type enum('fibre', 'lte') NOT NULL,
            installation_address text,
            preferred_contact_method enum('email', 'phone', 'sms') DEFAULT 'email',
            notes text,
            status enum('pending', 'processing', 'approved', 'rejected') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email (customer_email),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        // Applications table (for fibre/LTE applications)
        $applications_table = $wpdb->prefix . 'starcast_applications';
        $applications_sql = "CREATE TABLE $applications_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(20) NOT NULL,
            customer_address text NOT NULL,
            package_id mediumint(9),
            package_type enum('fibre', 'lte') NOT NULL,
            installation_address text,
            preferred_contact_method enum('email', 'phone', 'sms') DEFAULT 'email',
            promo_code varchar(50),
            notes text,
            status enum('pending', 'processing', 'approved', 'rejected') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email (customer_email),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($bookings_sql);
        dbDelta($signups_sql);
        dbDelta($applications_sql);
    }
    
    // =================
    // EMAIL FUNCTIONS
    // =================
    
    /**
     * Send booking confirmation email
     */
    private function send_booking_confirmation($booking_data, $booking_id) {
        $booking_reference = 'STB-' . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
        
        $to = $booking_data['customer_email'];
        $subject = 'Booking Confirmation - ' . $booking_reference;
        
        $message = sprintf(
            "Dear %s,\n\nYour technician booking has been confirmed!\n\nBooking Details:\n- Reference: %s\n- Date: %s\n- Time: %s\n- Service: %s\n- Address: %s\n\nWe'll contact you within 24 hours to confirm the appointment.\n\nThank you for choosing Starcast Technologies!\n\nBest regards,\nStarcast Technologies Team",
            $booking_data['customer_name'],
            $booking_reference,
            $booking_data['booking_date'],
            $booking_data['preferred_time'],
            strtoupper($booking_data['package_type']),
            $booking_data['customer_address']
        );
        
        wp_mail($to, $subject, $message);
        
        // Send admin notification
        $admin_email = get_option('admin_email');
        $admin_subject = 'New Booking: ' . $booking_reference;
        $admin_message = sprintf(
            "New booking received:\n\nCustomer: %s\nEmail: %s\nPhone: %s\nService: %s\nDate: %s\nTime: %s\nAddress: %s\n\nPlease contact the customer within 24 hours.",
            $booking_data['customer_name'],
            $booking_data['customer_email'],
            $booking_data['customer_phone'],
            strtoupper($booking_data['package_type']),
            $booking_data['booking_date'],
            $booking_data['preferred_time'],
            $booking_data['customer_address']
        );
        
        wp_mail($admin_email, $admin_subject, $admin_message);
    }
    
    /**
     * Send application confirmation email
     */
    private function send_application_confirmation($application_data, $application_id, $application_reference) {
        $to = $application_data['customer_email'];
        $subject = 'Application Confirmation - ' . $application_reference;
        
        $message = sprintf(
            "Dear %s,\n\nThank you for your application!\n\nApplication Details:\n- Reference: %s\n- Service: %s\n- Address: %s\n\nOur team will review your application and contact you within 24-48 hours.\n\nBest regards,\nStarcast Technologies Team",
            $application_data['customer_name'],
            $application_reference,
            strtoupper($application_data['package_type']),
            $application_data['customer_address']
        );
        
        wp_mail($to, $subject, $message);
        
        // Send admin notification
        $admin_email = get_option('admin_email');
        $admin_subject = 'New Application: ' . $application_reference;
        $admin_message = sprintf(
            "New application received:\n\nCustomer: %s\nEmail: %s\nPhone: %s\nService: %s\nAddress: %s\nPromo Code: %s\n\nPlease review and contact the customer.",
            $application_data['customer_name'],
            $application_data['customer_email'],
            $application_data['customer_phone'],
            strtoupper($application_data['package_type']),
            $application_data['customer_address'],
            $application_data['promo_code'] ?? 'None'
        );
        
        wp_mail($admin_email, $admin_subject, $admin_message);
    }
    
    /**
     * Send signup confirmation email
     */
    private function send_signup_confirmation($signup_data, $signup_id) {
        $signup_reference = 'STS-' . str_pad($signup_id, 6, '0', STR_PAD_LEFT);
        
        $to = $signup_data['customer_email'];
        $subject = 'Signup Confirmation - ' . $signup_reference;
        
        $message = sprintf(
            "Dear %s,\n\nThank you for your interest in Starcast Technologies!\n\nYour signup has been received:\n- Reference: %s\n- Service: %s\n- Contact Method: %s\n\nOur team will contact you within 24 hours to discuss your requirements.\n\nBest regards,\nStarcast Technologies Team",
            $signup_data['customer_name'],
            $signup_reference,
            strtoupper($signup_data['package_type']),
            $signup_data['preferred_contact_method']
        );
        
        wp_mail($to, $subject, $message);
    }
    
    // =================
    // CORS HEADERS
    // =================
    
    /**
     * Setup CORS headers for React frontend
     */
    public function setup_cors_headers() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', function($value) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            return $value;
        });
    }
    
    // =================
    // ACF FIELD GROUPS
    // =================
    
    /**
     * Register ACF field groups if ACF is available
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }
        
        // Fibre Package Fields
        acf_add_local_field_group(array(
            'key' => 'group_fibre_package_fields',
            'title' => 'Fibre Package Details',
            'fields' => array(
                array(
                    'key' => 'field_fibre_download',
                    'label' => 'Download Speed',
                    'name' => 'download',
                    'type' => 'text',
                    'instructions' => 'e.g. 100Mbps',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_fibre_upload',
                    'label' => 'Upload Speed',
                    'name' => 'upload',
                    'type' => 'text',
                    'instructions' => 'e.g. 100Mbps',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_fibre_price',
                    'label' => 'Price',
                    'name' => 'price',
                    'type' => 'number',
                    'instructions' => 'Monthly price in ZAR',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_fibre_promo_active',
                    'label' => 'Promo Active',
                    'name' => 'promo_active',
                    'type' => 'true_false',
                    'ui' => 1,
                ),
                array(
                    'key' => 'field_fibre_promo_price',
                    'label' => 'Promo Price',
                    'name' => 'promo_price',
                    'type' => 'number',
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_fibre_promo_active',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_fibre_promo_duration',
                    'label' => 'Promo Duration (months)',
                    'name' => 'promo_duration',
                    'type' => 'number',
                    'default_value' => 1,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_fibre_promo_active',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_fibre_promo_type',
                    'label' => 'Promo Type',
                    'name' => 'promo_type',
                    'type' => 'select',
                    'choices' => array(
                        'general' => 'General',
                        'new_customers_only' => 'New Customers Only',
                        'existing_customers' => 'Existing Customers',
                    ),
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_fibre_promo_active',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_fibre_promo_badge',
                    'label' => 'Promo Badge',
                    'name' => 'promo_badge',
                    'type' => 'select',
                    'choices' => array(
                        'hot-deal' => 'Hot Deal',
                        'limited-time' => 'Limited Time',
                        'best-value' => 'Best Value',
                        'new-customer' => 'New Customer',
                        'special-offer' => 'Special Offer',
                    ),
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_fibre_promo_active',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                ),
                array(
                    'key' => 'field_fibre_promo_text',
                    'label' => 'Promo Text',
                    'name' => 'promo_text',
                    'type' => 'text',
                    'instructions' => 'Custom promo text (optional)',
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_fibre_promo_active',
                                'operator' => '==',
                                'value' => '1',
                            ),
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'fibre_packages',
                    ),
                ),
            ),
        ));
        
        // LTE Package Fields
        acf_add_local_field_group(array(
            'key' => 'group_lte_package_fields',
            'title' => 'LTE Package Details',
            'fields' => array(
                array(
                    'key' => 'field_lte_speed',
                    'label' => 'Speed',
                    'name' => 'speed',
                    'type' => 'text',
                    'instructions' => 'e.g. Up to 150Mbps',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_lte_data',
                    'label' => 'Data Allowance',
                    'name' => 'data',
                    'type' => 'text',
                    'instructions' => 'e.g. 100GB or Unlimited',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_lte_price',
                    'label' => 'Price',
                    'name' => 'price',
                    'type' => 'number',
                    'instructions' => 'Monthly price in ZAR',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_lte_network',
                    'label' => 'Network Type',
                    'name' => 'network',
                    'type' => 'select',
                    'choices' => array(
                        '4G' => '4G',
                        '5G' => '5G',
                        '4G/5G' => '4G/5G'
                    ),
                    'default_value' => '4G/5G',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'lte_packages',
                    ),
                ),
            ),
        ));
        
        // Fibre Provider Fields
        acf_add_local_field_group(array(
            'key' => 'group_fibre_provider_fields',
            'title' => 'Fibre Provider Details',
            'fields' => array(
                array(
                    'key' => 'field_provider_logo',
                    'label' => 'Provider Logo',
                    'name' => 'logo',
                    'type' => 'image',
                    'return_format' => 'url',
                ),
                array(
                    'key' => 'field_provider_description',
                    'label' => 'Description',
                    'name' => 'description',
                    'type' => 'textarea',
                ),
                array(
                    'key' => 'field_provider_website',
                    'label' => 'Website',
                    'name' => 'website',
                    'type' => 'url',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'taxonomy',
                        'operator' => '==',
                        'value' => 'fibre_provider',
                    ),
                ),
            ),
        ));
        
        // LTE Provider Fields
        acf_add_local_field_group(array(
            'key' => 'group_lte_provider_fields',
            'title' => 'LTE Provider Details',
            'fields' => array(
                array(
                    'key' => 'field_lte_provider_logo',
                    'label' => 'Provider Logo',
                    'name' => 'logo',
                    'type' => 'image',
                    'return_format' => 'url',
                ),
                array(
                    'key' => 'field_lte_provider_description',
                    'label' => 'Description',
                    'name' => 'description',
                    'type' => 'textarea',
                ),
                array(
                    'key' => 'field_lte_provider_website',
                    'label' => 'Website',
                    'name' => 'website',
                    'type' => 'url',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'taxonomy',
                        'operator' => '==',
                        'value' => 'lte_provider',
                    ),
                ),
            ),
        ));
    }
}

// Initialize the plugin
new StarcastCompleteAPI();

/**
 * Activation hook to flush rewrite rules and create tables
 */
register_activation_hook(__FILE__, 'starcast_complete_api_activate');
function starcast_complete_api_activate() {
    // Register post types and taxonomies
    $plugin = new StarcastCompleteAPI();
    $plugin->register_custom_post_types();
    $plugin->register_taxonomies();
    $plugin->create_database_tables();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Deactivation hook to flush rewrite rules
 */
register_deactivation_hook(__FILE__, 'starcast_complete_api_deactivate');
function starcast_complete_api_deactivate() {
    flush_rewrite_rules();
}