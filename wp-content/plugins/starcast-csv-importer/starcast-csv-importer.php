<?php
/**
 * Plugin Name: Starcast CSV Importer
 * Plugin URI: http://localhost
 * Description: Import and update Fibre and LTE packages from CSV files without deleting existing products
 * Version: 1.0.0
 * Author: Starcast
 * License: GPL v2 or later
 * Text Domain: starcast-csv-importer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('STARCAST_CSV_VERSION', '1.0.0');
define('STARCAST_CSV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STARCAST_CSV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('STARCAST_CSV_PLUGIN_FILE', __FILE__);

// Include required files
require_once STARCAST_CSV_PLUGIN_DIR . 'includes/class-csv-processor.php';
require_once STARCAST_CSV_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once STARCAST_CSV_PLUGIN_DIR . 'includes/class-ajax-handler.php';
require_once STARCAST_CSV_PLUGIN_DIR . 'includes/class-import-history.php';

/**
 * Main plugin class
 */
class Starcast_CSV_Importer {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize plugin hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(STARCAST_CSV_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(STARCAST_CSV_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Initialize components
        add_action('init', array($this, 'init'));
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        }
        
        // AJAX handlers
        add_action('wp_ajax_starcast_csv_upload', array('Starcast_CSV_Ajax_Handler', 'handle_upload'));
        add_action('wp_ajax_starcast_csv_process_batch', array('Starcast_CSV_Ajax_Handler', 'process_batch'));
        add_action('wp_ajax_starcast_csv_get_columns', array('Starcast_CSV_Ajax_Handler', 'get_columns'));
        add_action('wp_ajax_starcast_csv_save_mapping', array('Starcast_CSV_Ajax_Handler', 'save_mapping'));
        add_action('wp_ajax_starcast_csv_get_history', array('Starcast_CSV_Ajax_Handler', 'get_history'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $csv_dir = $upload_dir['basedir'] . '/starcast-csv-imports';
        
        if (!file_exists($csv_dir)) {
            wp_mkdir_p($csv_dir);
        }
        
        // Create .htaccess to protect CSV files
        $htaccess_file = $csv_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // Create database table for import history
        Starcast_CSV_Import_History::create_table();
        
        // Set default options
        $default_options = array(
            'batch_size' => 50,
            'timeout_prevention' => true,
            'create_missing_providers' => true,
            'update_existing' => true,
            'skip_duplicates' => false,
            'log_imports' => true
        );
        
        foreach ($default_options as $key => $value) {
            if (get_option('starcast_csv_' . $key) === false) {
                update_option('starcast_csv_' . $key, $value);
            }
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up scheduled events if any
        wp_clear_scheduled_hook('starcast_csv_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if required post types exist
        if (!post_type_exists('fibre_packages') || !post_type_exists('lte_packages')) {
            add_action('admin_notices', array($this, 'show_post_type_notice'));
        }
        
        // Schedule cleanup task
        if (!wp_next_scheduled('starcast_csv_cleanup')) {
            wp_schedule_event(time(), 'daily', 'starcast_csv_cleanup');
        }
        
        add_action('starcast_csv_cleanup', array($this, 'cleanup_old_files'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('CSV Importer', 'starcast-csv-importer'),
            __('CSV Importer', 'starcast-csv-importer'),
            'manage_options',
            'starcast-csv-importer',
            array('Starcast_CSV_Admin_Page', 'render_page'),
            'dashicons-upload',
            30
        );
        
        add_submenu_page(
            'starcast-csv-importer',
            __('Import History', 'starcast-csv-importer'),
            __('Import History', 'starcast-csv-importer'),
            'manage_options',
            'starcast-csv-history',
            array('Starcast_CSV_Admin_Page', 'render_history_page')
        );
        
        add_submenu_page(
            'starcast-csv-importer',
            __('Settings', 'starcast-csv-importer'),
            __('Settings', 'starcast-csv-importer'),
            'manage_options',
            'starcast-csv-settings',
            array('Starcast_CSV_Admin_Page', 'render_settings_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'starcast-csv') === false) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'starcast-csv-admin',
            STARCAST_CSV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            STARCAST_CSV_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'starcast-csv-admin',
            STARCAST_CSV_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            STARCAST_CSV_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('starcast-csv-admin', 'starcast_csv', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('starcast_csv_nonce'),
            'strings' => array(
                'uploading' => __('Uploading...', 'starcast-csv-importer'),
                'processing' => __('Processing...', 'starcast-csv-importer'),
                'complete' => __('Import Complete!', 'starcast-csv-importer'),
                'error' => __('An error occurred', 'starcast-csv-importer'),
                'confirm_import' => __('Are you sure you want to start the import?', 'starcast-csv-importer'),
                'batch_progress' => __('Processing batch %d of %d', 'starcast-csv-importer')
            )
        ));
    }
    
    /**
     * Show notice if post types don't exist
     */
    public function show_post_type_notice() {
        ?>
        <div class="notice notice-warning">
            <p><?php _e('Starcast CSV Importer: Required post types (fibre_packages or lte_packages) are not registered. Please ensure they are active.', 'starcast-csv-importer'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Clean up old CSV files
     */
    public function cleanup_old_files() {
        $upload_dir = wp_upload_dir();
        $csv_dir = $upload_dir['basedir'] . '/starcast-csv-imports';
        
        if (!is_dir($csv_dir)) {
            return;
        }
        
        // Delete files older than 7 days
        $files = glob($csv_dir . '/*.csv');
        $now = time();
        $days = 7;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Get plugin option
     */
    public static function get_option($key, $default = false) {
        return get_option('starcast_csv_' . $key, $default);
    }
    
    /**
     * Update plugin option
     */
    public static function update_option($key, $value) {
        return update_option('starcast_csv_' . $key, $value);
    }
}

// Initialize plugin
add_action('plugins_loaded', array('Starcast_CSV_Importer', 'get_instance'));

/**
 * Helper function to get plugin instance
 */
function starcast_csv_importer() {
    return Starcast_CSV_Importer::get_instance();
}