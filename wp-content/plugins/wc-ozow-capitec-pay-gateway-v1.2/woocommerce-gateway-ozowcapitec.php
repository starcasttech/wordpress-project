<?php
/**
 * @wordpress-plugin
 * @Plugin Name: WC Capitec Pay via Ozow Gateway
 * Plugin URI: https://ozow.com/integrations/woo-commerce
 * Description: Receive instant EFT payments from customers using the South African Ozow payments provider using Capitec bank.
 * Author: Ozow
 * Author URI: https://ozow.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Version: 1.2.0
 * Requires at least: 6.2
 * Tested up to: 6.5.4
 * WC tested up to: 8.7.0
 * WC requires at least: 7.2
 * Requires PHP: 7.2 or letter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_GATEWAY_OZOW_CAPITEC_VERSION', '1.2.0' );
define( 'WC_GATEWAY_OZOW_CAPITEC_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_GATEWAY_OZOW_CAPITEC_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );


load_plugin_textdomain( 'wc-ozow-capitec-pay-gateway', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

add_action('plugins_loaded', 'ozow_capitec_pay_init', 0);

/**
 * Initialize the gateway.
 */
function ozow_capitec_pay_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    
    require_once( plugin_basename( 'classes/class-wc-gateway-ozow-capitec-pay.php' ) );
	require_once( plugin_basename( 'includes/capitecpay-wc-gateway-resend-payment-link-function.php' ) );

    add_filter('woocommerce_payment_gateways', 'ozow_capitec_pay_wc_add_gateway_class');
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ozow_capitec_pay_plugin_links' );
}

/**
 * Add the gateway to WooCommerce
 */
function ozow_capitec_pay_wc_add_gateway_class($methods) {
    
    $methods[] = 'WC_Gateway_Ozow_Capitec_Pay';
    return $methods;
}

/**
 * Show action links on the plugin screen.
 *
 * @param mixed $links Plugin Action links.
 *
 * @return array
 */
function ozow_capitec_pay_plugin_links( $links ) {
	$settings_url = add_query_arg(
		array(
			'page' => 'wc-settings',
			'tab' => 'checkout',
			'section' => 'wc_gateway_ozow_capitec_pay',
		),
		admin_url( 'admin.php' )
	);

	$plugin_links = array(
		'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'wc-ozow-capitec-pay-gateway' ) . '</a>',
		'<a href="https://ozow.com/contact">' . esc_html__( 'Support', 'wc-ozow-capitec-pay-gateway' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}


add_action( 'woocommerce_blocks_loaded', 'ozow_capitec_pay_wc_blocks_support' );

function ozow_capitec_pay_wc_blocks_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once dirname( __FILE__ ) . '/includes/class-ozow-capitec-pay-wc-gateway-blocks-support.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_Gateway_Ozow_Capitec_Pay_Blocks_Support );
			}
		);
	}
}


/**
 * Make it compatible with Woocommerce features.
 *
 * List of features:
 * - custom_order_tables
 *
 * @since 1.6.1 Rename function
 * @return void
 */
function ozow_capitec_pay_wc_declare_feature_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__
		);
	}
}
add_action( 'before_woocommerce_init', 'ozow_capitec_pay_wc_declare_feature_compatibility' );

?>
