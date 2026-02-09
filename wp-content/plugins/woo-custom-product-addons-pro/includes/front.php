<?php

namespace Acowebs\WCPA;


class Front
{

    public $assets_url;
    public $version;
    public $token;
    public $wcpaProducts = [];
    public $translate_keys = [
        'label',
        'placeholder',
        'description',
        'value',
        'tooltip',
        'repeater_section_label',
        'repeater_field_label',
        'repeater_add_label',
        'repeater_remove_label',
        'quantity_label',
        'check_value',

        'requiredError',
        'validEmailError',
        'validUrlError',
        'minFieldsError',
        'maxFieldsError',
        'groupMinError',
        'groupMaxError',
        'otherFieldError',
        'quantityRequiredError',
        'allowedCharsError',
        'patternError',
        'maxlengthError',
        'minlengthError',
        'minValueError',
        'maxValueError',
        'minQuantityError',
        'maxQuantityError',
        'maxFileCountError',
        'minFileCountError',
        'maxFileSizeError',
        'minFileSizeError',
        'fileExtensionError',

    ];
    public function __construct()
    {
        $this->assets_url = WCPA_ASSETS_URL;
        $this->version = WCPA_VERSION;
        $this->token = WCPA_TOKEN;

        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles'], 10);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 99);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts'], 10);

        add_filter('woocommerce_product_add_to_cart_text', array($this, 'add_to_cart_text'), 10, 2);
        add_filter('woocommerce_loop_add_to_cart_args', array($this, 'add_to_cart_args'), 10, 2);
        add_filter('woocommerce_product_supports', array($this, 'product_supports'), 10, 3);
        add_filter('woocommerce_product_add_to_cart_url', array($this, 'add_to_cart_url'), 20, 2);
        add_action('woocommerce_get_price_html', array($this, 'get_price_html'), 10, 2);
        add_action('woocommerce_available_variation', array($this, 'variation_info'), 10, 3);

        add_filter('post_class', array($this, 'product_class'), 10, 3);


//        add_filter('woocommerce_paypal_payments_product_supports_payment_request_button',
//            array($this, 'show_checkout_button'), 10, 2);

        add_filter('wc_stripe_hide_payment_request_on_product_page', array($this, 'remove_checkout_button'), 10, 2);

        add_filter('wcpay_payment_request_is_product_supported', array($this, 'show_checkout_button'), 10, 2);


        add_action('woocommerce_single_product_summary', array($this, 'show_warnings'), 30);



        //TODO test
        add_filter('woocommerce_email_format_string', array($this, 'email_format_string'), 2, 10);

        // other plugin compatibility codes
        add_filter('woo_discount_rules_has_price_override', array(
            $this,
            'woo_discount_rules_has_price_override',
        ), 10, 2);
        // for https://wordpress.org/plugins/woo-discount-rules/

        new Comp();
        new Discounts();
        new Currency();
        new Cron();
        new Render();
        new Process();
        new Cart();
        new Order();
        /**
         * Render Init: => before cart action hooks and display hook change
         *
         * load forms from product
         *
         * call form render
         *
         * Inside render:
         *   form outer with product data
         *   prepare product data
         *
         *   print form outer
         *
         *   Cart edit data
         *
         *   foreach data.
         *     section open <div section, section id> <h3> section title , optional
         *      foreach section.fields <div row
         *        foreach row  <div col,
         *
         *   print form outer close
         */
    }




    public function woo_discount_rules_has_price_override($status, $product)
    {
        if ($product) {
            $status = $this->is_wcpa_product($product->get_id());
        }

        return $status;
    }

    /**
     * Check a product has form assigned
     *
     * @param $product_id
     *
     * @return bool
     */
    public function is_wcpa_product($product_id)
    {
        if (!$this->wcpaProducts) {
            $form = new Form();
            $this->wcpaProducts = $form->get_wcpaProducts();
        }

        return in_array($product_id, $this->wcpaProducts['full']);
    }

    /**
     * Provide tags to use in email  templates
     * {wcpa_id_<element_id>}
     */
    public function email_format_string($string, $obj)
    {
        // $email

        if (is_string($string) && preg_match_all('/\{(\s)*?wcpa_id_([^}]*)}/', $string, $matches)) {
            if (isset($obj->id)) {
                $order = $obj->object;
                if($order){
                    foreach ($matches[2] as $k => $match) {
                        $value = valueByOrder($order->get_id(), $match, 'elementId', false);
                        if(is_array($value)) {
                            $temp = [];
                            foreach($value as $val) {
                                if(is_array($val)) {
                                    $temp[] = $val['label'];
                                } else {
                                    $temp[] = $val;
                                }
                            }
                            $value = implode("  ",$temp);
                        }
                        if($value!==false){
                            $string = str_replace('{wcpa_id_' . $match . '}', $value, $string);
                        }else{
                            $string = str_replace('{wcpa_id_' . $match . '}', '', $string);
                        }
                    }
                }
            }
        }

        return $string;
    }

    /**
     * Show the reasons to admin  why wcpa  fields  are not rendered
     */
    public function show_warnings()
    {
        global $product;
        if (!$product->is_purchasable() && ($product->is_type(['simple', 'variable']))) {
            $product_id = $product->get_id();
            // check if admin user
            if (current_user_can('manage_options') && $this->is_wcpa_product($product_id)) {
                echo '<p style="color:red">' . __('WCPA fields will show only if product has set price',
                        'woo-custom-product-addons-pro') . '</p>';
            }
        }
    }

    /**
     * Disable or remove direct payment buttons(Paypal/Stripe) from product detail page
     *
     * @param $allow
     * @param $product
     *
     * @return false
     * @since 5.0.0
     */
    public function remove_checkout_button($allow, $product)
    {

        if (!$allow) {
            if (is_object($product)) {
                if (method_exists($product, 'get_id')) {
                    $id = $product->get_id();
                } else if (isset($product->ID)) {
                    $id = $product->ID;
                } else {
                    $id = 0;
                }
            } else {
                $id = $product;
            }

            if ($this->is_wcpa_product($id)) {
                return true;
            }
        }

        return $allow;
    }

    public function show_checkout_button($allow, $product)
    {

        if ($allow) {
            if (is_object($product)) {
                if (method_exists($product, 'get_id')) {
                    $id = $product->get_id();
                } else if (isset($product->ID)) {
                    $id = $product->ID;
                } else {
                    $id = 0;
                }
            } else {
                $id = $product;
            }

            if ($this->is_wcpa_product($id)) {
                return false;
            }
        }

        return $allow;
    }

    /**
     * while loading variation data in product detail page , by default it will be missing stock_status and stock_quantity
     * We need to append that information as well, so that we can use it for conditiona logics
     *
     * @param $infos
     * @param $object
     * @param $variation
     *
     * @return mixed
     * @since 5.0.0
     */
    public function variation_info($infos, $object, $variation)
    {
        if (!isset($infos['stock_status'])) {
            $infos['stock_status'] = $variation->get_stock_status('edit');
        }
        if (!isset($infos['stock_quantity'])) {
            $infos['stock_quantity'] = $variation->get_stock_quantity('edit');
        }

        return $infos;
    }

    /**
     * @param $price
     * @param $product
     *
     * @return string
     */
    public function get_price_html($price, $product)
    {
        $label = Config::get_config('price_prefix_label');

        if (trim($label) !== '') {
            if ($this->is_wcpa_product($product->get_id())) {
                $price = $label . ' ' . $price;
            }
        }

        return $price;
    }

    /**
     * return permalink for wcpa product,
     * If direct Purchasable product, return  the original $url
     *
     * @param $url
     * @param $product
     *
     * @return mixed
     */
    public function add_to_cart_url($url, $product)
    {
        $product_id = $product->get_id();
        if ($this->is_wcpa_product($product_id) && !$this->is_direct_purchasable_product($product_id) && !$product->is_type('external')) {
            return $product->get_permalink();
        } else {
            return $url;
        }
    }

    public function is_direct_purchasable_product($product_id)
    {
        if (!$this->wcpaProducts) {
            $form = new Form();
            $this->wcpaProducts = $form->get_wcpaProducts();
        }

        return in_array($product_id, $this->wcpaProducts['direct_purchasable']);
    }

    public function product_class($classes = array(), $class = false, $product_id = false)
    {
        if ($product_id && $this->is_wcpa_product($product_id)) {
            $classes[] = 'wcpa_has_options';
        }

        return $classes;
    }

    public function add_to_cart_text($text, $product)
    {
        $product_id = $product->get_id();

        if ($this->is_wcpa_product($product_id) && $product->is_in_stock() && !$this->is_direct_purchasable_product($product_id)) {
            $text = Config::get_config('add_to_cart_text', 'Select options', true);
        }

        return $text;
    }

    /**
     * Remove ajax add to cart feature for wcpa products.
     *
     * @param $support
     * @param $feature
     * @param $product
     *
     * @return bool
     */
    public function product_supports($support, $feature, $product)
    {
        $product_id = $product->get_id();
        if ($feature == 'ajax_add_to_cart' && $this->is_wcpa_product($product_id) && !$this->is_direct_purchasable_product($product_id)) {
            $support = false;
        }

        return $support;
    }

    public function add_to_cart_args($args, $product)
    {
        $product_id = $product->get_id();

        if ($this->is_wcpa_product($product_id) && $product->is_in_stock() && !$this->is_direct_purchasable_product($product_id)) {
            $class = Config::get_config('add_to_cart_button_class');
            if (isset($args['class'])) {
                $args['class'] .= ' ' . $class;
            }
        }

        return $args;
    }

    public function enqueue_styles()
    {
        $design = Config::get_config('active_design', false);


        if ($design === false || !isset($design['active'])) {
            wp_register_style($this->token . '-frontend', esc_url($this->assets_url) . 'css/base.css', array(),
                $this->version);
        } else {
            if ($design['cssCode'] && !empty($design['cssCode'])) {
                add_action('wp_head', function () use ($design) {
                    echo '<style>' . $design['cssCode'] . '</style>';
                }, 10);
            }
            wp_register_style($this->token . '-frontend',
                esc_url($this->assets_url) . 'css/' . $design['active']['style'] . '.css', array(), $this->version);
        }
        if (Config::get_config('enqueue_cs_js_all_pages') || is_product()) {
            wp_enqueue_style($this->token . '-frontend');
        }

    }

    /**
     * Load frontend Javascript.
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public function admin_enqueue_scripts()
    {
        $this->registerFrontScripts(true);
        $scripts = [
            'file',
            'datepicker',
            'select'
        ];
        foreach ($scripts as $tag) {
            //   wp_enqueue_script($this->token . '-' . $tag);
        }

//        wp_enqueue_script($this->token . '-front'); moved to admin.php
//        wp_enqueue_script($this->token . '-googlemapplace');


    }

    public function registerFrontScripts($isAdmin = false)
    {
        wp_enqueue_script('wp-hooks');

        $google_map_api = Config::get_config('google_map_api_key', '');
        $reCAPTCHA_site_key = Config::get_config('recaptcha_site_key', '');
        $recaptcha_v = Config::get_config('recaptcha_v'); //v2(Checkbox) and v3, no v2 invisible providing

//        if (current_user_can('manage_options')) {
//            wp_register_script($this->token.'-admin-front', esc_url($this->assets_url).'js/backend/front.js', array(),
//                $this->version,
//                true);
//            wp_enqueue_script($this->token.'-admin-front');
//        }

        $upload_method = activeUploadMethod();
        if ($upload_method == 'cloud') {
            wp_register_script($this->token . '-file', esc_url($this->assets_url) . 'js/file-s3.js', array(), $this->version,
                true);
        } else if ($upload_method == 'tus') {
            wp_register_script($this->token . '-file', esc_url($this->assets_url) . 'js/file-tus.js', array(), $this->version,
                true);
        } else {
            wp_register_script($this->token . '-file', esc_url($this->assets_url) . 'js/file.js', array(), $this->version,
                true);
        }


        wp_register_script($this->token . '-datepicker', esc_url($this->assets_url) . 'js/datepicker.js', array(),
            $this->version, true);
        wp_register_script($this->token . '-color', esc_url($this->assets_url) . 'js/color.js', array(),
            $this->version, true);
        wp_register_script($this->token . '-select', esc_url($this->assets_url) . 'js/select.js', array(), $this->version,
            true);
        wp_register_script($this->token . '-front', esc_url($this->assets_url) . 'js/front-end.js', array('wp-hooks'),
            $this->version, true);
        wp_register_script($this->token . '-productGallery', esc_url($this->assets_url) . 'js/product-gallery.js',
            array('wp-hooks'), $this->version, true);


        wp_register_script($this->token . '-googlemapplace',
            'https://maps.googleapis.com/maps/api/js?key=' . $google_map_api . '&libraries=places&callback=window.wcpaMapInit', array(), $this->version,
            true);
        if ($recaptcha_v == 'v3') {
            wp_register_script($this->token . '-recaptcha',
                'https://www.google.com/recaptcha/api.js?render=' . $reCAPTCHA_site_key, array($this->token . '-front'),
                $this->version);
        } else {
            wp_register_script($this->token . '-recaptcha',
                'https://www.google.com/recaptcha/api.js?onload=wcpaCaptchaTrigger&render=explicit',
                array($this->token . '-front'), $this->version);
        }


        $_validation_messages = Config::get_config('wcpa_validation_strings');
        $validation_messages = [];
        /** remove validation_ prefix in keys */
        foreach ($_validation_messages as $key => $v) {
            $validation_messages[str_replace('validation_', '', $key)] = $v;
        }


        $wcpa_global_vars = array(
            'api_nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : null,
            'root' => rest_url($this->token . '/front/'),
            'assets_url' => $this->assets_url,
            'date_format' => __(get_option('date_format'), 'woo-custom-product-addons-pro'),
            'time_format' => __(get_option('time_format'), 'woo-custom-product-addons-pro'),
            'validation_messages' => $validation_messages,
            'google_map_api' => $google_map_api,
            'reCAPTCHA_site_key' => $reCAPTCHA_site_key,
            'recaptcha_v' => $recaptcha_v,
            'ajax_add_to_cart' => Config::get_config('ajax_add_to_cart'),
            'summary_order' => Config::get_config('summary_order'),

            'change_price_as_quantity' => Config::get_config('change_price_as_quantity'),
            'show_field_price_x_quantity' => Config::get_config('show_field_price_x_quantity'),
            'disable_validation_scroll' => Config::get_config('wcpa_disable_validation_scroll'),
            'gallery_update_field' => Config::get_config('gallery_update_field'),
            'update_top_price' => Config::get_config('update_top_price'),
            'datepicker_disableMobile' => Config::get_config('datepicker_disableMobile'),
            'radio_unselect_img' => Config::get_config('radio_unselect_img'),

            'strings' => array(
                'place_selector_street' => Config::get_config('place_selector_street'),
                'place_selector_city' => Config::get_config('place_selector_city'),
                'place_selector_state' => Config::get_config('place_selector_state'),
                'place_selector_zip' => Config::get_config('place_selector_zip'),
                'place_selector_country' => Config::get_config('place_selector_country'),
                'place_selector_latitude' => Config::get_config('place_selector_latitude'),
                'place_selector_longitude' => Config::get_config('place_selector_longitude'),
                'file_button_text' => Config::get_config('file_button_text'),
                'file_placeholder' => Config::get_config('file_placeholder'),
                'file_droppable_action_text' => Config::get_config('file_droppable_action_text'),
                'file_droppable_desc_text' => Config::get_config('file_droppable_desc_text'),
                'file_upload_completed' => Config::get_config('file_upload_completed'),
                'file_upload_failed' => Config::get_config('file_upload_failed'),
                'other' => Config::get_config('other_text'),
                'clearSelection' => Config::get_config('clearSelection_text'),
                'repeater_add' => Config::get_config('repeater_add_text'),
                'repeater_remove' => Config::get_config('repeater_remove_text'),
                'file_droppable_text' => Config::get_config('file_droppable_text'),
                'to' => __(' to ', 'woo-custom-product-addons-pro'),
            )
        );

        $wcpa_global_vars['i18n_view_cart'] = esc_attr__('View cart', 'woocommerce');

        $wcpa_global_vars['options_price_format'] = Config::get_config('field_option_price_format');
        $wcpa_global_vars['wc_price_format'] = get_woocommerce_price_format();
        $wcpa_global_vars['hide_option_price_zero'] = Config::get_config('wcpa_hide_option_price_zero');

        $wcpa_global_vars['discount_show_field_price'] = Config::get_config('discount_show_field_price');
        $wcpa_global_vars['discount_strike_field_price'] = Config::get_config('discount_strike_field_price');
        $wcpa_global_vars['discount_strike_summary_price'] = Config::get_config('discount_strike_summary_price');
        $wcpa_global_vars['discount_strike_total_price'] = Config::get_config('discount_strike_total_price');
        $wcpa_global_vars['responsive_layout'] = Config::get_config('responsive_layout');
        $wcpa_global_vars['product_price_parent_selector'] = apply_filters('wcpa_product_price_parent_selector', '.summary');


        $wcpa_global_vars['accordion_open'] = Config::get_config('accordion_open');
        $wcpa_global_vars['accordion_auto_open'] = Config::get_config('accordion_auto_open');
        $wcpa_global_vars['accordion_auto_close'] = Config::get_config('accordion_auto_close');

        $wcpa_global_vars['woo_price_suffix'] = get_option('woocommerce_price_display_suffix');
        $wcpa_global_vars['prices_include_tax'] = wc_prices_include_tax();
        $wcpa_global_vars['isAdmin'] = current_user_can('manage_options');

        $transKey = apply_filters('wcpa_attribute_translations_key', 'gt_translate_keys');
        if ($transKey != '' && $transKey !== false) {
            $wcpa_global_vars['strings'][$transKey] = array_keys($wcpa_global_vars['strings']);
            $wcpa_global_vars['validation_messages'][$transKey] = array_keys($wcpa_global_vars['validation_messages']);
        }

        if ($isAdmin) {
            $wcpa_global_vars['cart_url'] = null;
            $wcpa_global_vars['is_cart'] = false;
        } else {
            $wcpa_global_vars['cart_url'] = apply_filters('woocommerce_add_to_cart_redirect',
                wc_get_cart_url(), null);
            $wcpa_global_vars['is_cart'] = is_cart();
        }

        $user_roles[] = 'guest';
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $roles = ( array )$user->roles;
            $user_roles = $roles;
        }
        $wcpa_global_vars['user_roles'] = $user_roles;

        $mapRestriction = Config::get_config('google_map_countries');
        if (!empty($mapRestriction)) {
            $mapRestriction = preg_split("/[\s,]+/", trim($mapRestriction, ','));
            $wcpa_global_vars['google_map_countries'] = $mapRestriction;
        } else {
            $wcpa_global_vars['google_map_countries'] = '';
        }


        $init_triggers = Config::get_config('wcpa_init_triggers', []); // used for hooks
        if (!is_array($init_triggers) && !empty($init_triggers)) {
            $init_triggers = [$init_triggers];
        }

        $init_triggers2 = Config::get_config('plugin_init_triggers');
        if (!empty($init_triggers2)) {
            $init_triggers2 = explode(',', $init_triggers2);
        } else {
            $init_triggers2 = [];
        }
        $wcpa_global_vars['init_triggers'] = array_unique(array_merge($init_triggers, $init_triggers2, array(
            'wcpt_product_modal_ready',
            'qv_loader_stop',
            'quick_view_pro:load',
            'elementor/popup/show',
            'xt_wooqv-product-loaded',
            'woodmart-quick-view-displayed',
            'porto_init_countdown',
            'woopack.quickview.ajaxload',
//            'acoqvw_quickview_loaded',
            'quick-view-displayed',
            'update_lazyload',
            'riode_load',
            'yith_infs_added_elem',
            'jet-popup/show-event/after-show',
            'etheme_quick_view_content_loaded',
//            'awcpt_wcpa_init',
            'wc_backbone_modal_loaded' // barn2 restaurant booking

        )));


//	    $wcpa_global_vars['today'] = [
//		    'unixDays' => floor(current_time('timestamp') / (60 * 60 * 24)),
//		    'unixSeconds' => current_time('timestamp'),
//	    ]; /** can be wrong for cached pages */

        $wcpa_global_vars['wc_currency_symbol'] = get_woocommerce_currency_symbol('');

        /** some symbols like dirham, it get converted to unicode, so it failing when find price form product.price_html */
        $wcpa_global_vars['wc_currency_symbol_raw'] = str_replace('&#x', 'wcpaUni', get_woocommerce_currency_symbol('')); //

        $wcpa_global_vars['wc_thousand_sep'] = wc_get_price_thousand_separator();
        $wcpa_global_vars['wc_price_decimals'] = wc_get_price_decimals();
//        $wcpa_global_vars['wc_decimal_sep'] = wc_get_price_decimal_separator();
        $wcpa_global_vars['price_format'] = get_woocommerce_price_format();
        $wcpa_global_vars['wc_decimal_sep'] = wc_get_price_decimal_separator();
        $wcpa_global_vars['wc_currency_pos'] = get_option('woocommerce_currency_pos');
        $wcpa_global_vars['mc_unit'] = Currency::getConUnit();
        $wcpa_global_vars['addons'] = addonsList();
//        $upload_method = Config::get_config('upload_method');
//        if ($upload_method == 'tus') {
//            $tus = new Tus();
//            if (!$tus->isActive()) {
//                $upload_method = 'normal';
//            }
//        }
//        if ($upload_method == 'aws') {
//            $aws = new S3();
//            if (!$aws->isActive()) {
//                $upload_method = 'normal';
//            }
//        }
        $wcpa_global_vars['upload_method'] = $upload_method;
//        wp_enqueue_script('wc-add-to-cart-variation');
        wp_localize_script($this->token . '-front', $this->token . '_front', $wcpa_global_vars);

//        wp_enqueue_script($this->token . '-googlemapplace');
//        wp_enqueue_script($this->token . '-datepicker');
//        wp_enqueue_script($this->token . '-select');
//        wp_enqueue_script($this->token . '-file');
//		wp_enqueue_script( $this->token . '-front' );
//        wp_enqueue_script($this->token . '-productGallery');

    }

    public function enqueue_scripts()
    {
        $this->registerFrontScripts();
    }


}
