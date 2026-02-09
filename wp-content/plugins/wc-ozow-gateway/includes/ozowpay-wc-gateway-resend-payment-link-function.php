<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'current_screen', 'ozowpay_add_custom_order_actions_to_edit_order_page' );

function ozowpay_add_custom_order_actions_to_edit_order_page( $current_screen ) {
    if ((isset($_GET['post']) || (isset($_GET['page']) && $_GET['page'] == "wc-orders")) && isset( $_GET['action'] ) && $_GET['action'] === 'edit' ) {
		add_filter( 'woocommerce_order_actions', 'ozowpay_add_custom_resend_payment_link_order_action_button' );

	}
}

function ozowpay_add_custom_resend_payment_link_order_action_button( $actions ) {
	
    if(isset($_GET['id'])){
        $order_id = $_REQUEST['id'];
    }else if(isset($_GET['post'])){
        $order_id = $_REQUEST['post'];
    }else{
        $order_id = NULL;
    }

	$order = wc_get_order($order_id);

    $payment_method_name = $order->get_payment_method();

	$customer_phone = $order->get_billing_phone();
	$customer_email_address = $order->get_billing_email();
	
    $payment_gateway_id = 'ozow';
    $payment_gateways = WC_Payment_Gateways::instance();
    $payment_gateway = $payment_gateways->payment_gateways()[$payment_gateway_id];

    $allow_resend_payment_link = $payment_gateway->get_allow_resend_payment_link()  === 'yes' ? 'true' : 'false';

	if ( $order && $payment_method_name == 'ozow') {
		$order_status = $order->get_status(); // Get the order status
        	$payment_method =  $order->get_payment_method();

		if ( in_array( $order_status, array('failed','cancelled' ) ) && $allow_resend_payment_link == 'true' && $payment_method == "ozow") {
			if(!empty($customer_phone)){
				$actions['ozowpay_resend_payment_link_to_phone'] = __( 'Resend Payment Link To Phone', 'wc-ozow-gateway' );
			}
			if(!empty($customer_email_address)){
				$actions['ozowpay_resend_payment_link_to_email_address'] = __( 'Resend Payment Link To Email', 'wc-ozow-gateway' );
			}
		}
	}

    return $actions;
}


add_action( 'woocommerce_order_action_ozowpay_resend_payment_link_to_phone', 'ozowpay_resend_payment_link_to_phone_order_action_callback' );
add_action( 'woocommerce_order_action_ozowpay_resend_payment_link_to_email_address', 'ozow_resend_payment_link_to_email_address_order_action_callback' );

function ozowpay_send_payment_link($order, $address, $correspondenceType) {
    // Retrieve necessary payment gateway information
    $payment_gateway_id = 'ozow';
    $payment_gateways = WC_Payment_Gateways::instance();
    $payment_gateway = $payment_gateways->payment_gateways()[$payment_gateway_id];

    // Construct API request arguments
    $site_code = $payment_gateway->get_site_code();
    $private_key = $payment_gateway->get_private_key();
    $api_key = $payment_gateway->get_api_key();
    $country_code = $order->get_billing_country();
    $currency_code = $order->get_currency();
    $amount = $order->get_total();
    $transaction_reference = $order->get_id();
    $bank_reference = $order->get_id();
    $order_id = $order->get_id();
    $order_key = $order->get_order_key();
    $optional_5 = 'WP-' . $GLOBALS['wp_version'] . ' PHP-' . phpversion() . ' Plugin-V1.3.2 WC-' . WC_VERSION;
    $customer_first_name = $order->get_billing_first_name();
    $customer_last_name = $order->get_billing_last_name();
    $customer_phone = $order->get_billing_phone();
    $customer_email = $order->get_billing_email();
    $customer_name = $customer_first_name . ' ' . $customer_last_name;

    $response_url = add_query_arg('wc-api', 'WC_Gateway_Ozow', home_url('/'));
    $cancel_url = $response_url;
    $success_url = $response_url;
    $error_url = $response_url;
    $notify_url = add_query_arg('notify', '1', $response_url);
    $is_test = $payment_gateway->get_is_test_mode() === 'yes' ? 'true' : 'false';

    $args = array(
        'SiteCode' => $site_code,
        'CountryCode' => empty($country_code) ? 'ZA' : $country_code,
        'CurrencyCode' => $currency_code,
        'Amount' => $amount,
        'TransactionReference' => $order_id,
        'BankReference' => $order_id,
        'Optional1' => $order_key,
        'Optional5' => $optional_5,
        'Customer' => $customer_name,
        'CancelUrl' => $cancel_url,
        'ErrorUrl' => $error_url,
        'SuccessUrl' => $success_url,
        'NotifyUrl' => $notify_url,
        'IsTest' => $is_test
    );

    $string_to_hash = strtolower(implode('', $args) . $private_key);
    $args['HashCheck'] = hash("sha512", $string_to_hash);

    $args['CustomerCellphoneNumber'] = $customer_phone;
    
    $customArray = array(
        'requestFields' => $args,
        'correspondenceType' => $correspondenceType,
        'address' => $address
    );

    // Prepare API request
    $api_args = array(
        'method' => 'POST',
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'apiKey' => $api_key,
        ),
        'body' => json_encode($customArray),
        'timeout' => 30,
    );

    // Send API request
    $response = wp_remote_post('https://api.ozow.com/PostAndSendPaymentRequest', $api_args);

    // Handle API response
    $redirect_url = admin_url('post.php?post=' . $order_id . '&action=edit&type=apierror&ozow_message=Resend Payment Link Action Status: Something went wrong! Please try again later.');

    if (!is_wp_error($response)) {
        $response_code = wp_remote_retrieve_response_code($response);
        $responseObj = json_decode($response['body']);

        if (!empty($responseObj->errorMessage)) {
            $redirect_url = admin_url('post.php?post=' . $order_id . '&action=edit&type=resperror&ozow_message=Resend Payment Link Action Status: ' . $responseObj->errorMessage);
        } else {
            $redirect_url = admin_url('post.php?post=' . $order_id . '&action=edit&type=respsuccess&ozow_message=Resend Payment Link Action Status: Payment Link Successfully Sent!');
            $order->add_order_note(__('Resend Payment Link Action Status: Payment Link Successfully Sent!', 'wc-ozow-gateway'));
        }
    }

    // Redirect back to the order edit page
    wp_redirect($redirect_url);
    exit;
}

// Function to resend payment link to phone
function ozowpay_resend_payment_link_to_phone_order_action_callback($order) {
    $customer_phone = $order->get_billing_phone();
    ozowpay_send_payment_link($order, $customer_phone, 1);
}

// Function to resend payment link to email
function ozow_resend_payment_link_to_email_address_order_action_callback($order) {
    $customer_email = $order->get_billing_email();
    ozowpay_send_payment_link($order, $customer_email, 2);
}


function ozowpay_custom_admin_notices() {
	if(isset($_GET['type']) && isset($_REQUEST['ozow_message'])) {
		if($_GET['type'] === 'apierror'){
			$message_type = 'error';
			$message_text = $_REQUEST['ozow_message'];
		}
		if($_GET['type'] === 'resperror'){
			$message_type = 'error';
			$message_text = $_REQUEST['ozow_message'];
		}
		if($_GET['type'] === 'respsuccess'){
			$message_type = 'success';
			$message_text = $_REQUEST['ozow_message'];
		}
		echo '<div class="notice notice-' . esc_attr($message_type) . ' is-dismissible"><p>' . esc_html($message_text) . '</p></div>';
	}
}

add_action( 'admin_notices', 'ozowpay_custom_admin_notices' );

?>
