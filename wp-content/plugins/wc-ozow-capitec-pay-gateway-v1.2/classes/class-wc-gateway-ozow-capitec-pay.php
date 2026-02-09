<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Ozow_Capitec_Pay extends WC_Payment_Gateway {
    /**
	 * Constructor
	 *
	 */
    public function __construct() {
        
        $this->id = 'ozowcapitec';
        $this->icon = plugin_dir_url(plugin_dir_path(__FILE__)) . 'ozow-capitec.png';
        $this->method_title = 'Capitec Pay via Ozow Secure Payments';
        $this->has_fields = false;
        $this->description = $this->get_option('description');
        $this->form_url = 'https://pay.ozow.com/';
        $this->token_url = 'https://api.ozow.com/token';
        $this->refund_url = 'https://api.ozow.com/secure/refunds/submit';
        $this->supports = ['products', 'refunds', 'tokenization', 'add_payment_method'];
        $this->capitecBankID = "913999FA-3A32-4E3D-82F0-A1DF7E9E4F7B";

        // Setup available countries.
        $this->available_countries = array('ZA');

        // Setup available currency codes.
        $this->available_currencies = array('ZAR');
        $this->init_settings();
        $this->ozow_capitec_pay_init_form_fields();
        $this->title = $this->get_option('title');
        $this->response_url = add_query_arg('wc-api', 'WC_Gateway_Ozow_Capitec_Pay', home_url('/'));
        $this->cancel_url = $this->response_url;
        $this->success_url = $this->response_url;
        $this->error_url = $this->response_url;
        $this->notify_url = add_query_arg('notify', '1', $this->response_url);
        $this->is_test_mode = $this->get_option('is_test_mode');
        $this->enabled = $this->get_option('enabled');
        $this->site_code = $this->get_option('site_code');
        $this->private_key = $this->get_option('private_key');
        $this->api_key = $this->get_option('api_key');
        $this->allow_resend_payment_link = $this->get_option('allow_resend_payment_link');

        if ($this->is_test_mode) {
            $this->form_fields['is_test_mode']['description'].= ' <strong>' . __('You will not receive payments in test mode, DO NOT HONOUR ANY ORDERS PAID WHILE TEST MODE IS ON.', 'wc-ozow-capitec-pay-gateway') . '</strong>';
        }
        
        add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_gateway_ozow_capitec_pay', array($this, 'ozow_capitec_pay_process_response'));
        
        if (!$this->ozow_capitec_pay_is_valid_for_use()) {
            $this->enabled = false;
        }
    }

    /**
     * Comparing woocommerce current currency with ZAR
     */
    function ozow_capitec_pay_is_valid_for_use() {
        return get_woocommerce_currency() == 'ZAR';
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    function ozow_capitec_pay_init_form_fields() {
        $this->form_fields = array('title' => array('title' => __('Title', 'wc-ozow-capitec-pay-gateway'), 'type' => 'text', 'description' => __('This is the title that the user will see at checkout', 'wc-ozow-capitec-pay-gateway'), 'default' => __('Capitec Pay via Ozow', 'wc-ozow-capitec-pay-gateway')), 'description' => array('title' => __('Description', 'wc-ozow-capitec-pay-gateway'), 'type' => 'textarea', 'description' => __('This controls the description which the user sees during checkout.', 'wc-ozow-capitec-pay-gateway'), 'default' => __("Pay with Capitec Pay via Ozow Secure Payments, (Your order status will be updated immediately after successful payment).", 'wc-ozow-capitec-pay-gateway')), 'enabled' => array('title' => __('Enable/Disable', 'wc-ozow-capitec-pay-gateway'), 'type' => 'checkbox', 'label' => __('Enable Ozow for payment processing', 'wc-ozow-capitec-pay-gateway'), 'default' => 'yes'), 'site_code' => array('title' => __('Site Code', 'wc-ozow-capitec-pay-gateway'), 'type' => 'text', 'default' => ''), 'private_key' => array('title' => __('Private Key', 'wc-ozow-capitec-pay-gateway'), 'type' => 'text', 'default' => ''), 'api_key' => array('title' => __('API Key', 'wc-ozow-capitec-pay-gateway'), 'type' => 'text', 'default' => ''), 'is_test_mode' => array('title' => __('Test Mode', 'wc-ozow-capitec-pay-gateway'), 'type' => 'checkbox', 'label' => __('Enable Ozow Test Mode', 'wc-ozow-capitec-pay-gateway'), 'description' => '', 'default' => 'no'),'allow_resend_payment_link' =>  array('title' => __('Resend Payment Link', 'wc-ozow-gateway'), 'type' => 'checkbox', 'label' => __('Allow to Resend Payment Link', 'wc-ozow-gateway'), 'description' => 'This feature enables admin users to conveniently resend payment links directly from the order action drop-down menu within the order detail view.', 'default' => 'no'));
    }
    
    /**
	 * Admin Panel Options
	 */
    function admin_options() {
        if ($this->ozow_capitec_pay_is_valid_for_use()) {
?>
            <h2><?php esc_html_e('Capitec Pay via Ozow', 'wc-ozow-capitec-pay-gateway'); ?></h2>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table> <?php
        } else {
            echo esc_html__('Gateway Disabled', 'wc-ozow-capitec-pay-gateway') . '<div class="inline error"><p><strong>' . esc_html__('Ozow currently only supports ZAR as a currency.', 'wc-ozow-capitec-pay-gateway') . '</strong></p></div>';
        }
    }

    /**
	 * Prepare post fields
	 */
    function ozow_capitec_pay_get_post_fields($order_id) {
        $order = new WC_Order($order_id);
        $order_total = $order->get_total();
        $currency_code = get_woocommerce_currency();
        $country_code = $order->billing_country;
        $site_code = $this->site_code;
        $order_key = $order->order_key;
        $private_key = $this->private_key;
        $plugin_name = "WC";
        $pluginVersion = "Capitec V1.2";
        $wordpressVersion = 'WP-' . $this->ozow_capitec_pay_get_wordepress_version();
        $phpVersion = 'PHP-' . phpversion();
        $wooCommerceVersion = "WC-" . WC_VERSION;
        $amount = number_format($order_total, 2, '.', '');
        $is_test = ($this->is_test_mode == 'yes') ? 'true' : 'false';
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        $customer_phone = $order->get_billing_phone();
        $display_name = $first_name . " " . $last_name;
        $args = array('SiteCode' => $site_code, 'CountryCode' => empty($country_code) ? 'ZA' : $country_code, 'CurrencyCode' => $currency_code, 'Amount' => $amount, 'TransactionReference' => $order_id, 'BankReference' => $order_id, 'Optional1' => $order_key, 'Optional5' => $wordpressVersion . ' ' . $phpVersion . ' ' . $pluginVersion . ' ' . $wooCommerceVersion, 'Customer' => $display_name, 'CancelUrl' => $this->cancel_url, 'ErrorUrl' => $this->error_url, 'SuccessUrl' => $this->success_url, 'NotifyUrl' => $this->notify_url, 'IsTest' => $is_test, 'SelectedBankId' => $this->capitecBankID);
        $string_to_hash = strtolower(implode('', $args) . $private_key);
        $args['HashCheck'] = hash("sha512", $string_to_hash);
        $args['CustomerCellphoneNumber'] = $customer_phone;
        return $args;
    }

    /**
	 * Current wordpress version
	 */
    private function ozow_capitec_pay_get_wordepress_version() {
        global $wp_version;
        return $wp_version;
    }

    /**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
    function process_payment($order_id) {
        $args = $this->ozow_capitec_pay_get_post_fields($order_id);
        $post_args = http_build_query($args, '', '&');

        return array('result' => 'success', 'redirect' => $this->form_url . '?' . $post_args);
    }

    /**
	 * Verify transaction using api call
	 */
    /**
	 * Verify transaction using api call
	 */
    function ozow_capitec_pay_api_verify_transaction($status, $amount, $transaction_reference, $transaction_id, $is_test) {
        $site_code = $this->site_code;
        $api_key = $this->api_key;
        $url = "https://api.ozow.com/GetTransaction?siteCode=$site_code&transactionId=$transaction_id&isTest=$is_test";

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Accept' => 'application/json',
                "ApiKey" => $api_key
            )
        ));

        $transaction = json_decode(wp_remote_retrieve_body($response) , true);
        if (floatval($transaction['amount']) !== floatval($amount) || $transaction['status'] !== $status || $transaction['transactionReference'] !== $transaction_reference) {
            $logger = wc_get_logger();
            $logger->critical("Verification mismatch: Notification { amount: $amount, status: $status, transactionReference: $transaction_reference } vs API  Notification { amount: $transaction->amount, status: $transaction->status, transactionReference: $transaction->transactionReference }", array('source' => $this->id));
            return false;
        }
        return true;
    }

    /**
	 * Process payment response
	 */
    function ozow_capitec_pay_process_response() {
        global $woocommerce;
        if (isset($_REQUEST['refund']) && $_REQUEST['refund'] == 1) {
            $refund_id = sanitize_text_field($_REQUEST['RefundId']);
            $transaction_id = sanitize_text_field($_REQUEST['TransactionId']);
            $currency_code = sanitize_text_field($_REQUEST['CurrencyCode']);
            $amount = sanitize_text_field($_REQUEST['Amount']);
            $status = sanitize_text_field($_REQUEST['Status']);
            $bank_name = sanitize_text_field($_REQUEST['BankName']);
            $account_number = sanitize_text_field($_REQUEST['AccountNumber']);
            $status_message = sanitize_text_field($_REQUEST['StatusMessage']);
            $hash = sanitize_text_field($_REQUEST['Hash']);
            $string_to_hash = strtolower($refund_id . $transaction_id . $currency_code . $amount . $status . $bank_name . $account_number . $status_message . $this->private_key);
            $hash_check = hash("sha512", $string_to_hash);
            if ($hash == $hash_check) {
                $order = wc_get_orders(array('transaction_id' => $transaction_id, 'limit' => 1, 'return' => 'ids',));
                if (is_array($order) && count($order) === 1) {
                    $order_id = $order[0];
                    $order = new WC_Order($order_id);
                    if (strtolower($status) !== null) {
                        $order->add_order_note(sprintf(__('Refund ID : %1$s <br> Refund Amount : R%2$s <br>Refund Status: %3$s<br>', 'wc-ozow-capitec-pay-gateway'), esc_html($refund_id), floatval($amount), esc_html($status)));
                    }
                }
            }
            exit();
        }
        $site_code = sanitize_text_field($_REQUEST['SiteCode']);
        $site_code = $this->site_code;
        $transaction_id = sanitize_text_field($_REQUEST['TransactionId']);
        $transaction_reference = sanitize_text_field($_REQUEST['TransactionReference']);
        $amount = sanitize_text_field($_REQUEST['Amount']);
        $status = sanitize_text_field($_REQUEST['Status']);
        $display_name = sanitize_text_field($_REQUEST['Customer']);
        $optional_1 = sanitize_text_field($_REQUEST['Optional1']);
        $optional_2 = sanitize_text_field($_REQUEST['Optional2']);
        $optional_3 = sanitize_text_field($_REQUEST['Optional3']);
        $optional_4 = sanitize_text_field($_REQUEST['Optional4']);
        $optional_5 = sanitize_text_field($_REQUEST['Optional5']);
        $currency_code = sanitize_text_field($_REQUEST['CurrencyCode']);
        $is_test = sanitize_text_field($_REQUEST['IsTest']);
        $status_message = sanitize_text_field($_REQUEST['StatusMessage']);
        $hash = sanitize_text_field($_REQUEST['Hash']);
        $notify = sanitize_text_field($_REQUEST['notify']);

        if (empty($is_test)) {
            $is_test = 'false';
        }

        $string_to_hash = strtolower($site_code . $transaction_id . $transaction_reference . $amount . $status . $optional_1 . $optional_2 . $optional_3 . $optional_4 . $optional_5 . $currency_code . $is_test . $status_message . $this->private_key);
        $hash_check = hash("sha512", $string_to_hash);
        $order = new WC_Order($transaction_reference);

        if ($order == null) {
            $error = sprintf(__('Order reference %s is invalid', 'wc-ozow-capitec-pay-gateway'), $transaction_reference);
            wc_add_notice($error, 'error');
            die($error);
        }

        if ($order->needs_payment()) {
            if ($hash != $hash_check) {
                $error = __('Your payment could not be processed. The response returned from Capitec Pay via Ozow is invalid.', 'wc-ozow-capitec-pay-gateway');
                $order->update_status('on-hold', $error);
                wc_add_notice($error, 'error');
            } else if (floatval($amount) != floatval($order->get_total())) {
                $error = sprintf(__('Amount returned from Capitec Pay via Ozow (%1s) does not match order total (%2s) .', 'wc-ozow-capitec-pay-gateway'), $amount, $order->get_total());
                $order->update_status('on-hold', $error);
                wc_add_notice($error, 'error');
            } else if (strtolower($status) == 'cancelled' || strtolower($status) == 'error') {
                $order->update_status('failed', $status_message);
            } else if (strtolower($status) == "complete") {
                if (!$this->ozow_capitec_pay_api_verify_transaction($status, $amount, $transaction_reference, $transaction_id, $is_test)) {
                    $order->update_status('on-hold', __('Your payment could not be processed. We were unable to verify the status of your payment.', 'wc-ozow-capitec-pay-gateway'));
                    wc_add_notice($error, 'error');
                } else if (strtolower($is_test) == 'true') {
                    wc_add_notice("The response received indicates that payment was successful however payment was made in test mode.", 'error');
                    $order->update_status('on-hold', "The response received indicates that payment was successful however payment was made in test mode. Capitec Pay via Ozow Request ID ($transaction_id)");
                } else {
                    $order->payment_complete();
                    $order->set_transaction_id($transaction_id);
                    $order->save();
                }
            } else {
                $order->update_status('on-hold', "Unknown status received from Capitec Pay via Ozow ($status) for transaction $transaction_id");
                wc_add_notice("There was a problem processing your payment, please contact us.", 'error');
            }
        }

        $thankyou_url = add_query_arg('utm_nooverride', '1', $this->get_return_url($order));

        if ($notify == 1) {
            exit();
        }
        
        wp_redirect($thankyou_url);
        exit();
    }

    /**
	 * Process refund.
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund.
	 * a passed in amount.
	 *
	 * @param  int        $order_id Order ID.
	 * @param  float|null $amount Refund amount.
	 * @param  string     $reason Refund reason.
	 * @return boolean True or false based on success, or a WP_Error object.
	 */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);

        if (!$order) {
            return false;
        }

        $payment_gateway_id = 'ozowcapitec';
        $payment_gateways = WC_Payment_Gateways::instance();
        $payment_gateway = $payment_gateways->payment_gateways() [$payment_gateway_id];
        $order_currency = $order->get_currency();
        $api_key = $payment_gateway->api_key;
        $private_key = $payment_gateway->private_key;
        $site_code = $payment_gateway->site_code;
        $transaction_id = $order->get_transaction_id();
        $token_url = $payment_gateway->token_url;
        $refund_url = $payment_gateway->refund_url;
        $response_url = add_query_arg('wc-api', 'WC_Gateway_Ozow_Capitec_Pay', home_url('/'));
        $amount = number_format($amount, 2, '.', '');

        if ($reason) {
            if (strlen($reason) > 500) {
                $reason = function_exists('mb_substr') ? mb_substr($reason, 0, 450) : substr($reason, 0, 450);
                $reason = $reason . '... [See WooCommerce order page for full text.]';
            }
        }

        $token = $this->ozow_capitec_pay_get_access_token($token_url, $site_code, $api_key);

        if ($token) {
            $data = [];
            $data['transactionId'] = $transaction_id;
            $data['amount'] = $amount;
            $data['refundReason'] = $reason;
            $data['notifyUrl'] = add_query_arg('refund', '1', $response_url);
            $string_to_hash = strtolower(implode('', $data) . $privateKey);
            $data['HashCheck'] = hash("sha512", $string_to_hash);
            $post_data[] = $data;
            $refund = $this->ozow_capitec_pay_create_refund($refund_url, $post_data, $token);
            if ($refund) {
                $order->add_order_note(sprintf(__('Refund Initiated R%1$s - Refund ID: %2$s - Reason: %3$s', 'wc-ozow-capitec-pay-gateway'), floatval($amount), esc_html($refund), esc_html($reason)));
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
	 * Get merchant access token.
	 *
	 * @param  string     $token_url Token URL.
     * @param  string     $site_code Site Code.
     * @param  string     $api_key API Key.
	 */
    private function ozow_capitec_pay_get_access_token($token_url, $site_code, $api_key) {

        $api_args = array(
            'method'    => 'POST',
            'headers'   => array(
                "ApiKey"       => $api_key,
                "Accept"       => "application/json",
                "Content-Type" => "application/x-www-form-urlencoded",
            ),
            'body'      => http_build_query(array(
                'grant_type' => 'password',
                'SiteCode'   => $site_code,
            )),
            'timeout'   => 30,
        );
        
        $response = wp_remote_post($token_url, $api_args);

        $result = json_decode(wp_remote_retrieve_body($response));

        if (!is_null($result) && is_object($result)) {
            if (!empty($result->error)) {
                return false;
            } else {
                return $result->access_token;
            }
        } else {
            return false;
        }
    }

    /**
	 * Create Refund.
	 *
	 * @param  string     $refund_url Token URL.
     * @param  array     $post_data post data.
     * @param  string     $token token.
	 */
    private function ozow_capitec_pay_create_refund($refund_url, $post_data, $token) {
        
        $api_args = array(
            'method'  => 'POST',
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => "Bearer $token",
            ),
            'body'    => json_encode($post_data),
            'timeout' => 30,
        );
        

        $response = wp_remote_post($refund_url, $api_args);

        $result = json_decode(wp_remote_retrieve_body($response))[0];

        if (!is_null($result) && is_object($result)) {
            $errors = $result->errors;
            if ($errors === null) {
                return $result->refundId;
            } else {
                return false;
            }
        }
    }
}
