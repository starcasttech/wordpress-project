<?php
/*
 * Plugin Name: Woocommerce Custom Product Addons (5.x.x)
 * Version: 5.2.2
 * Plugin URI: https://acowebs.com
 * Description: Woocommerce Product add-on plugin. Add custom fields to your Woocommerce product page. With an easy-to-use Custom Form Builder, now you can add extra product options quickly.
 * Author URI: https://acowebs.com
 * Author: Acowebs
 * Requires at least: 5.9
 * Tested up to: 6.7
 * Text Domain: woo-custom-product-addons-pro
 * WC requires at least: 3.3.0
 * WC tested up to: 9.6
 * Requires PHP: 7.2
 * Requires Plugins: woocommerce
 */

/**
 *
 * WCPA:WooCommerce Custom Product Addons
 */


use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined('ABSPATH') || exit;
if (defined('WCPA_VERSION')) {//to check free version already running
    add_action('admin_notices', function () {
        ?>
        <div class="error">
            <p>It is found that free version of this plugin <strong> Woocommerce Custom Product Addons</strong> is
                running on this site.
                Please deactivate or remove the same in order to work this plugin properly </p>
        </div>
        <?php
    });
} else {
    if (!defined('WCPA_FILE')) {
        define('WCPA_FILE', __FILE__);
    }


    define('WCPA_VERSION', '5.2.2');
    define('WCPA_PLUGIN_NAME', 'Woocommerce Custom Product Addons');

    define('WCPA_TOKEN', 'wcpa');
    define('WCPA_PATH', plugin_dir_path(WCPA_FILE));
    define('WCPA_URL', plugins_url('/', WCPA_FILE));

    define('WCPA_ASSETS_PATH', WCPA_URL . 'assets/');
    define('WCPA_ASSETS_URL', WCPA_URL . 'assets/');

    define('WCPA_PRODUCT_META_KEY', '_wcpa_product_meta');

    define('WCPA_ORDER_META_KEY', '_WCPA_order_meta_data');

    define('WCPA_PRODUCTS_TRANSIENT_KEY', 'wcpa_products_transient_ver_3');
    define('WCPA_ITEM_ID', 167);
    define('WCPA_STORE_URL', 'https://api.acowebs.com');

    define('WCPA_EMPTY_LABEL', 'wcpa_empty_label');

    define('WCPA_CART_ITEM_KEY', 'wcpa_data');

    if (!defined('WCPA_UPLOAD_DIR')) {
        define('WCPA_UPLOAD_DIR', 'wcpa_uploads');
    }
    if (!defined('WCPA_UPLOAD_CUSTOM_BASE_DIR')) {
        define('WCPA_UPLOAD_CUSTOM_BASE_DIR', false);
    }


    add_action('plugins_loaded', 'wcpa_load_plugin_textdomain');

    if (!version_compare(PHP_VERSION, '7.0', '>=')) {
        add_action('admin_notices', 'wcpa_fail_php_version');
    } elseif (!version_compare(get_bloginfo('version'), '5.0', '>=')) {
        add_action('admin_notices', 'wcpa_fail_wp_version');
    } else {
        require WCPA_PATH . 'includes/helper.php';
        require WCPA_PATH . 'includes/main.php';
    }

// Declare compatibility with custom order tables for WooCommerce.
    add_action(
        'before_woocommerce_init',
        function () {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__,
                    true);
            }
        }
    );



    /**
     * Load Plugin textdomain.
     *
     * Load gettext translate for Plugin text domain.
     *
     * @return void
     * @since 1.0.0
     *
     */
    function wcpa_load_plugin_textdomain()
    {

        load_plugin_textdomain('woo-custom-product-addons-pro');
    }

    /**
     * Plugin admin notice for minimum PHP version.
     *
     * Warning when the site doesn't have the minimum required PHP version.
     *
     * @return void
     * @since 5.0.0
     *
     */
    function wcpa_fail_php_version()
    {
        /* translators: %s: PHP version. */
        $message = sprintf(esc_html__(WCPA_PLUGIN_NAME . ' requires PHP version %s+, plugin is currently NOT RUNNING.',
            'woo-custom-product-addons-pro'), '7.0');
        $html_message = sprintf('<div class="error">%s</div>', wpautop($message));
        echo wp_kses_post($html_message);
    }

    /**
     * Plugin admin notice for minimum WordPress version.
     *
     * Warning when the site doesn't have the minimum required WordPress version.
     *
     * @return void
     * @since 5.0.0
     *
     */
    function wcpa_fail_wp_version()
    {
        /* translators: %s: WordPress version. */
        $message = sprintf(esc_html__(WCPA_PLUGIN_NAME . ' requires WordPress version %s+. Because you are using an earlier version, the plugin is currently NOT RUNNING.',
            'woo-custom-product-addons-pro'), '5.2');
        $html_message = sprintf('<div class="error">%s</div>', wpautop($message));
        echo wp_kses_post($html_message);
    }

    /**
     *  Giving compatablity with backward,
     * @return string
     */
    function wcpa_is_wcpa_product($product_id)
    {
        return Acowebs\WCPA\has_form($product_id);
    }

    /**
     * get wcpa value from order,
     * It can be retrieved by field name or field elementId
     * return false no occurrence
     * @param $order_id
     * @param $key field name or elementId
     * @param string $type whether it is name or elementId
     * @param false $returnAll do return all items as array or return first occurrence
     * @return array|false|mixed
     */
    function wcpa_get_order_value($order_id, $key, $type = 'name', $returnAll = true)
    {
        return Acowebs\WCPA\valueByOrder($order_id, $key, $type, $returnAll);
    }
}



