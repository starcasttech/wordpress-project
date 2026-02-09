<?php
/**
 * @wordpress-plugin
 * @Plugin Name: Ozow Gateway for WooCommerce
 * Plugin URI: https://ozow.com/integrations/woo-commerce
 * Description: Receive instant EFT payments from customers using the South African Ozow payments provider.
 * Author: Ozow
 * Author URI: https://ozow.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Version: 1.3.2
 * Requires at least: 6.6
 * Tested up to: 6.8.2
 * WC tested up to: 10.1.2
 * WC requires at least: 7.2
 * Requires PHP: 7.4 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_GATEWAY_OZOW_VERSION', '1.3.2' ); // WRCS: DEFINED_VERSION.
define( 'WC_GATEWAY_OZOW_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_GATEWAY_OZOW_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

load_plugin_textdomain( 'wc-ozow-gateway', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

add_action('plugins_loaded', 'ozowpay_init', 0);

/**
 * Initialize the gateway.
 */
function ozowpay_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    
    require_once( plugin_basename( 'classes/class-wc-gateway-ozow.php' ) );
	require_once( plugin_basename( 'includes/ozowpay-wc-gateway-resend-payment-link-function.php' ) );

    add_filter('woocommerce_payment_gateways', 'ozowpay_wc_add_gateway_class');
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ozowpay_plugin_links' );
add_filter( 'woocommerce_valid_order_statuses_for_payment', 'add_on_hold_to_valid_order_statuses', 10, 2 );
	add_filter('option_woocommerce_ozow_settings', 'ozowpay_wc_decode_gateway_settings');
}

/**
 * Add the gateway to WooCommerce
 */
function ozowpay_wc_add_gateway_class($methods) {
    
    $methods[] = 'WC_Gateway_Ozow';
    return $methods;
}

/**
 * Show action links on the plugin screen.
 *
 * @param mixed $links Plugin Action links.
 *
 * @return array
 */
function ozowpay_plugin_links( $links ) {
	$settings_url = add_query_arg(
		array(
			'page' => 'wc-settings',
			'tab' => 'checkout',
			'section' => 'wc_gateway_ozow',
		),
		admin_url( 'admin.php' )
	);

	$plugin_links = array(
		'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'wc-ozow-gateway' ) . '</a>',
		'<a href="https://ozow.com/contact">' . esc_html__( 'Support', 'wc-ozow-gateway' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}


add_action( 'woocommerce_blocks_loaded', 'ozowpay_wc_blocks_support' );

function ozowpay_wc_blocks_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once dirname( __FILE__ ) . '/includes/class-ozowpay-wc-gateway-blocks-support.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_Gateway_Ozow_Blocks_Support );
			}
		);
	}
}

/**
 * Make it compatible with Woocommerce features.
 *
 * List of features:
 * - custom_order_tables
 * - product_block_editor
 *
 * @return void
 */
function ozowpay_wc_declare_feature_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__
		);
	}
}
add_action( 'before_woocommerce_init', 'ozowpay_wc_declare_feature_compatibility' );

/**
 * Include 'on-hold' in valid payment statuses.
 *
 * Adds 'on-hold' to WooCommerce's list of order statuses that require payment.
 *
 * @param array $valid_order_statuses Existing valid payment statuses.
 * @param WC_Order $order The order being checked.
 * 
 * @return array Updated list of valid payment statuses.
 */
function add_on_hold_to_valid_order_statuses( $valid_order_statuses, $order ) {
    // Add 'on-hold' status to the list of valid statuses
    $valid_order_statuses[] = 'on-hold';
    return $valid_order_statuses;
}

/**
 * Decodes HTML entities in the 'private_key' and 'api_key' fields of the gateway settings array.
 *
 * This filter ensures that any HTML entities present in the 'private_key' or 'api_key'
 * fields are properly decoded before being used by the gateway.
 *
 * @param array $value The gateway settings array.
 * @return array The modified gateway settings array with decoded keys.
 */
function ozowpay_wc_decode_gateway_settings( $value ) {
    if ( isset( $value['private_key'] ) ) {
        $value['private_key'] = html_entity_decode( $value['private_key'] );
    }
    if ( isset( $value['api_key'] ) ) {
        $value['api_key'] = html_entity_decode( $value['api_key'] );
    }
    return $value;
}
?>
