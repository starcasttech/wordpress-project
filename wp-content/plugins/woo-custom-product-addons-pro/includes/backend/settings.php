<?php

namespace Acowebs\WCPA;


class Settings
{

    static $key;
    static $screen_options_key;
    private $confKeys = [];
    private $values = false;

    public function __construct()
    {
        self::$screen_options_key = 'wcpa_screen_options';

        self::$key      = 'wcpa_settings_key';

        $this->confKeys = [
            /** display section */

            'disp_show_field_price'   => ['boolean', true], /** type,default */
            'disp_hide_options_price' => ['boolean', false], /** type,default */
            'disp_show_section_price' => ['boolean', false],
            // removed this feature as of now, can add in in future versions


            'disp_summ_show_total_price'   => ['boolean', true],
            'disp_summ_show_product_price' => ['boolean', true],
            'disp_summ_show_option_price'  => ['boolean', true],
            'disp_summ_show_fee'           => ['boolean', false],
            'disp_summ_show_discount'      => ['boolean', false],
            'summary_order'                => [
                'array',
                [
                    'option_price',
                    'product_price',
                    'fee',
                    'discount',
                    'total_price'
                ]
            ],


            'show_meta_in_cart'     => ['boolean', true],
            'show_meta_in_checkout' => ['boolean', true],
            'show_meta_in_order'    => ['boolean', true],

            'show_price_in_cart'       => ['boolean', true],
            'show_price_in_checkout'   => ['boolean', true],
            'show_price_in_order'      => ['boolean', true],
            'show_price_in_order_meta' => ['boolean', true],

            'wcpa_hide_option_price_zero' => ['boolean', false],
            'cart_hide_price_zero'        => ['boolean', false],

            /** Content Strings */

            'summary_title'         => ['text', __('', 'woo-custom-product-addons-pro')],
            'options_total_label'   => ['text', __('Options Price', 'woo-custom-product-addons-pro')],
            'options_product_label' => ['text', __('Product Price', 'woo-custom-product-addons-pro')],
            'discount_label'        => ['text', __('Discount', 'woo-custom-product-addons-pro')],
            'total_label'           => ['text', __('Total', 'woo-custom-product-addons-pro')],
            'fee_label'             => ['text', __('Fee', 'woo-custom-product-addons-pro')],

            'field_option_price_format' => ['text', '({price})'],

            'add_to_cart_text'   => ['text', __('Select options', 'woo-custom-product-addons-pro')],
            'price_prefix_label' => ['text', __('', 'woo-custom-product-addons-pro')],

            'file_button_text'           => ['text', __('Choose File', 'woo-custom-product-addons-pro')],
            'file_placeholder'           => ['text', __('{count} Files', 'woo-custom-product-addons-pro')],
            'file_upload_completed'           => ['text', __('Completed', 'woo-custom-product-addons-pro')],
            'file_upload_failed'           => ['text', __('Failed to upload', 'woo-custom-product-addons-pro')],
            'file_droppable_action_text' => ['text', __('Browse', 'woo-custom-product-addons-pro')],
            'file_droppable_desc_text'   => [
                'text', __('or {action} to choose a file', 'woo-custom-product-addons-pro')
            ],
            'file_droppable_text'        => ['text', __('Drag and Drop Files Here', 'woo-custom-product-addons-pro')],

            'place_selector_street'    => ['text', __('Street Address', 'woo-custom-product-addons-pro')],
            'place_selector_city'      => ['text', __('City', 'woo-custom-product-addons-pro')],
            'place_selector_state'     => ['text', __('State', 'woo-custom-product-addons-pro')],
            'place_selector_zip'       => ['text', __('Zip Code', 'woo-custom-product-addons-pro')],
            'place_selector_country'   => ['text', __('Country', 'woo-custom-product-addons-pro')],
            'place_selector_latitude'  => ['text', __('Latitude', 'woo-custom-product-addons-pro')],
            'place_selector_longitude' => ['text', __('Longitude', 'woo-custom-product-addons-pro')],


            'other_text'              => ['text', __('Other', 'woo-custom-product-addons-pro')],
            'clearSelection_text'     => ['text', __('Clear Selection', 'woo-custom-product-addons-pro')],
            'repeater_add_text'       => ['text', __('Add Field', 'woo-custom-product-addons-pro')],
            'repeater_remove_text'    => ['text', __('Remove Field', 'woo-custom-product-addons-pro')],


            /** Validation Strings */
            'wcpa_validation_strings' => [
                'array',
                [
                    'uploadPending'                => [
                        'text', __('Files are being uploaded.', 'woo-custom-product-addons-pro')
                    ],
                    'validNumberError'                => [
                        'text', __('Provide a valid number.', 'woo-custom-product-addons-pro')
                    ],
                    'formError'                    => [
                        'text',
                        __('Fix the errors shown above', 'woo-custom-product-addons-pro')
                    ],
                    'checkCaptcha'                 => [
                        'text',
                        __('Tick the "I\'m not a robot" verification', 'woo-custom-product-addons-pro')
                    ],
                    'validation_requiredError'     => [
                        'text', __('Field is required', 'woo-custom-product-addons-pro')
                    ],
                    'validation_allowedCharsError' => [
                        'text',
                        __('Characters %s is not supported', 'woo-custom-product-addons-pro'), '{characters}'
                    ],
                    'validation_patternError'      => [
                        'text', __('Pattern not matching', 'woo-custom-product-addons-pro'), '{pattern}'
                    ],
                    'validation_minlengthError'    => [
                        'text',
                        __('Minimum  %s characters required', 'woo-custom-product-addons-pro'), '{minLength}'
                    ],
                    'validation_maxlengthError'    => [
                        'text',
                        __('Maximum %s characters allowed', 'woo-custom-product-addons-pro'), '{maxLength}'
                    ],

                    'validation_minValueError' => [
                        'text', __('Minimum value is %s', 'woo-custom-product-addons-pro'), '{minValue}'
                    ],
                    'validation_maxValueError' => [
                        'text', __('Maximum value is %s', 'woo-custom-product-addons-pro'), '{maxValue}'
                    ],

                    'validation_minFieldsError' => [
                        'text', __('Select minimum %s fields', 'woo-custom-product-addons-pro'), '{minOptions}'
                    ],

                    'validation_maxFieldsError' => [
                        'text', __('Select maximum %s fields', 'woo-custom-product-addons-pro'), '{maxOptions}'
                    ],

                    'validation_maxFileCountError' => [
                        'text', __('Maximum %s files allowed', 'woo-custom-product-addons-pro'), '{maxFileCount}'
                    ],
                    'validation_minFileCountError' => [
                        'text', __('Minimum %s files required', 'woo-custom-product-addons-pro'), '{minFileCount}'
                    ],

                    'validation_maxFileSizeError' => [
                        'text',
                        __('Maximum file size should be %s', 'woo-custom-product-addons-pro'), '{maxFileSize}'
                    ],
                    'validation_minFileSizeError' => [
                        'text',
                        __('Minimum file size should be %s', 'woo-custom-product-addons-pro'), '{minFileSize}'
                    ],

                    'validation_fileExtensionError'    => [
                        'text',
                        __('File type is not supported', 'woo-custom-product-addons-pro')
                    ],
                    'validation_quantityRequiredError' => [
                        'text',
                        __('Please enter a valid quantity', 'woo-custom-product-addons-pro')
                    ],
                    'validation_otherFieldError'       => [
                        'text', __('Other value required', 'woo-custom-product-addons-pro')
                    ],
                    'validation_charleftMessage'       => [
                        'text', __('%s characters left', 'woo-custom-product-addons-pro'), '{charLeft}'
                    ],
                    'validEmailError'                  => [
                        'text',
                        __('Provide a valid email address', 'woo-custom-product-addons-pro')
                    ],
                    'validUrlError'                    => [
                        'text', __('Provide a valid URL', 'woo-custom-product-addons-pro')
                    ],
                    'minQuantityError'                 => [
                        'text', __('Minimum quantity required is %s', 'woo-custom-product-addons-pro'), '{minQuantity}'
                    ],
                    'maxQuantityError'                 => [
                        'text', __('Maximum quantity allowed is %s', 'woo-custom-product-addons-pro'), '{maxQuantity}'
                    ],
                    'groupMinError'                    => [
                        'text', __('Requires minimum  %s', 'woo-custom-product-addons-pro'), '{minValue}'
                    ],
                    'groupMaxError'                    => [
                        'text', __('Allowed maximum  %s', 'woo-custom-product-addons-pro'), '{maxValue}'
                    ],
                    'validation_uploadError'    => [
                        'text',
                        __('Failed to upload %s', 'woo-custom-product-addons-pro'), '{fileName}'
                    ],

                ]
            ],

            'product_custom_fields' => ['array', []],

            'wcpa_custom_extensions_choose'  => ['array', []],
            'wcpa_custom_extensions'         => ['array', []],

//			'form_loading_order_by_date'     => [ 'boolean', true ],
//			'use_sumo_selector'              => [ 'boolean', true ],
//            'load_all_scripts'               => ['boolean', false],
//			'wcpa_show_form_json'            => [ 'boolean', false ],
            'wcpa_disable_validation_scroll' => ['boolean', false],
//            'wcpa_show_val_error_box'        => ['boolean', false],
            'hide_empty_data'                => ['boolean', false],
            'google_map_api_key'             => ['text', ''],
            'google_map_countries'           => ['text', ''],
            'recaptcha_site_key'             => ['text', ''],
            'recaptcha_secret_key'           => ['text', ''],
            'recaptcha_v'                    => ['text', 'v2'],
            'captcha_for_all_forms'          => ['boolean', false],


            'change_price_as_quantity' => ['boolean', false],

            'ajax_add_to_cart'         => ['boolean', false],
            'add_to_cart_button_class' => ['text', 'wcpa_add_to_cart_button'],


            'consider_product_tax_conf'      => ['boolean', true],
            'tax_for_addon'      => ['text', 'product_tax'], // product_tax,no_tax,tax_class
            'count_fee_once_in_a_order'      => ['boolean', false],
            'show_fee_in_line_subtotal'      => ['boolean', true],
            'wcpa_apply_coupon_to_fee'       => ['boolean', false],
            'show_assigned_products_in_list' => ['boolean', false],
            'show_field_price_x_quantity'    => ['boolean', false],
            'wcpa_show_form_order'           => ['boolean', false],

            'remove_discount_from_fields' => ['boolean', false],

            'enable_map_to_checkout' => ['boolean', false],

            'enable_cart_item_edit' => ['boolean', false],
            'cart_edit_text'        => ['text', __('[Edit Options]', 'woo-custom-product-addons-pro')],

            'plugin_init_triggers' => ['text', ''],

            'render_hook'                   => ['text', 'woocommerce_before_add_to_cart_button'],
            'render_hook_priority'          => ['text', '10'],
            'render_hook_variable'          => ['text', 'woocommerce_before_add_to_cart_button'],
            'render_hook_variable_priority' => ['text', '10'],

            'discount_show_field_price'     => ['boolean', true],
            'discount_strike_field_price'   => ['boolean', true],
            'discount_strike_summary_price' => ['boolean', true],
            'discount_strike_total_price'   => ['boolean', true],

            'override_cart_meta_template' => ['boolean', true],

            'gallery_update_field' => ['text', 'last_field'], // last_item or last_updated_field
            'update_top_price' => ['boolean', false], // last_item or last_updated_field
            'accordion_open' => ['text', 'first_opened'], // all_opened,all_closed
            'accordion_auto_open' =>  ['boolean', false], // all_opened,all_closed
            'accordion_auto_close' =>  ['boolean', false], // all_opened,all_closed
            'datepicker_disableMobile' => ['boolean', false],
            'radio_unselect_img' => ['boolean', false], // valid for color  group and image groups, and product group
            'append_global_form' => ['text', 'at_start'],// at_end
            'enqueue_cs_js_all_pages' =>  ['boolean', true],
            'responsive_layout' =>  ['boolean', false],
            'wcpa_acd_count' =>  ['text', 0],
            'attach_files_in_emails' =>  ['boolean', false],
            'upload_method' =>  ['text', 'normal'],// tus, normal
            'cloud' =>  ['array', [
                'service'=>['text', 's3'],
                'region'=>['text', ''],
                'key'=>['text', ''],
                'secret'=>['text', ''],
                'bucket'=>['text', ''],
                'directory'=>['text', ''],
            ]],// tus, normal
            'item_meta_format' =>  ['text', '{label} | {value}'] ,// {label} {value}
            'item_meta_format_image' =>  ['text', '{label} | {value} | {image}'] ,// {label} {value} {image}
            'item_meta_format_color' =>  ['text', '{label} | {value} | {color}'] ,// {label} {value} {image}
            'meta_custom_date_format' =>  ['boolean', false] ,// whether to store the date in custom format or  the default Y-m-d H:i
            'separate_cart_items' =>  ['boolean', false] ,//to make each items as separate line item even if the options are same
        ];
    }

    /**
     * Get Screen Options
     * @since 5.0.0
     */
    public static function get_screen_options()
    {
        return get_option(self::$screen_options_key, false);
    }

    /**
     * Update Screen Options
     * @since 5.0.0
     */
    public function update_screen_options($options)
    {
        $settings = get_option(self::$screen_options_key, false);

        if ($settings === $options) {
            return true;
        }

        return update_option(self::$screen_options_key, $options, true);
    }

    /**
     * Get Gloval Seetings
     * @since 5.0.0
     */
    public function get_settings($isBackend = false)
    {
        if ($this->values !== false) {
            return $this->values;
        }
        $this->values = [];
        $settings     = get_option(self::$key);

        foreach ($this->confKeys as $key => $val) {
            list($type, $default) = $val;

            if ($type == 'array') {
                if ($key == 'wcpa_custom_extensions' &&
                    isset($settings[$key]) && ! empty($settings[$key]) &&
                    array_key_first($settings[$key]) !== 0
                ) {
                    /** convert old method assosiative array to normal index based array */
                    $value = $settings[$key];
                    foreach ($value as $k => $v) {
                        $this->values[$key][] = ['ext' => $k, 'mime' => $v];
                    }

                    continue;
                }
                if ($key == 'wcpa_custom_extensions_choose' &&
                    isset($settings[$key]) && ! empty($settings[$key]) &&
                    array_key_first($settings[$key]) !== 0
                ) {
                    /** convert old method assosiative array to normal index based array */
                    $value = $settings[$key];
                    foreach ($value as $k => $v) {
                        $this->values[$key][] = $k;
                    }

                    continue;
                }


                if ($key == 'product_custom_fields') {
                    $value              = isset($settings[$key]) ? $settings[$key] : $default;
                    $this->values[$key] = array_values($value); // make normal index based array

                    continue;
                }
                if ($key == 'summary_order') {
                    $value = (isset($settings[$key]) ? $settings[$key] : $default);  // +$default for merging missing item

                    $commonElements = array_intersect($value, $default);

                    $this->values[$key] = array_values(array_unique(array_merge($commonElements, $default)));


                    continue;
                }

                if (empty($default)) {
                    $value              = isset($settings[$key]) ? $settings[$key] : $default;
                    $this->values[$key] = $value;
                } else {
                    foreach ($default as $_key => $_val) {
                        list($_type, $_default) = $_val;
                        $value = isset($settings[$key][$_key]) ? $settings[$key][$_key] : $_default;
                        if (isset($_val[2]) && $isBackend) {
                            $value = str_replace("%s", $_val[2], $value);
                        }
                        if ($_type == 'boolean') {
                            $value = metaToBoolean($value);
                        }
                        $this->values[$key][$_key] = $value;
                    }
                }
            } else {

                $value = isset($settings[$key]) ? $settings[$key] : $default;
                if ($type == 'boolean') {
                    $value = metaToBoolean($value);
                }
                if($key==='file_droppable_desc_text' ){
                    $value  = str_replace('%s','{action}',$value);
                }
                $this->values[$key] = $value;
            }
        }

        if ($isBackend) {
            array_push($this->values['wcpa_custom_extensions'], ['ext' => '', 'mime' => '']);
            array_push($this->values['product_custom_fields'], ['name' => '', 'value' => '']);
            /** adding an empty field to show the add section in backend */
        }

        return $this->values;
    }

    public function get_license()
    {
        $key = get_option('wcpa_activation_license_key', '');

        if ( ! empty($key)) {
            $license = sanitize_text_field($key);

            // data to send in our API request
            $api_params = array(
                'edd_action' => 'check_license',
                'license'    => $license,
                'item_id'    => WCPA_ITEM_ID,
                'item_name'  => rawurlencode(WCPA_PLUGIN_NAME), // the name of our product in EDD
                'url'        => home_url(),
            );

            // Call the custom API.
            $response = wp_remote_post(
                WCPA_STORE_URL,
                array(
                    'timeout'   => 15,
                    'sslverify' => false,
                    'body'      => $api_params,
                )
            );

            // make sure the response came back okay
            if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
                if (is_wp_error($response)) {
                    $message = $response->get_error_message();
                } else {
                    $message = __('An error occurred, please try again.');
                }
            } else {
                $license_data = json_decode(wp_remote_retrieve_body($response));

                if (false === $license_data->success) {
                    switch ($license_data->error) {
                        case 'expired':
                            $message = sprintf(
                            /* translators: the license key expiration date */
                                __('Your license key expired on %s.', 'edd-sample-plugin'),
                                date_i18n(get_option('date_format'),
                                    strtotime($license_data->expires, current_time('timestamp')))
                            );
                            break;

                        case 'disabled':
                        case 'revoked':
                            $message = __('Your license key has been disabled.', 'edd-sample-plugin');
                            break;

                        case 'missing':
                            $message = __('Invalid license.', 'edd-sample-plugin');
                            break;

                        case 'invalid':
                        case 'site_inactive':
                            $message = __('Your license is not active for this URL.', 'edd-sample-plugin');
                            break;

                        case 'item_name_mismatch':
                            /* translators: the plugin name */
                            $message = sprintf(__('This appears to be an invalid license key for %s.',
                                'edd-sample-plugin'), WCPA_PLUGIN_NAME);
                            break;

                        case 'no_activations_left':
                            $message = __('Your license key has reached its activation limit.', 'edd-sample-plugin');
                            break;

                        default:
                            $message = __('An error occurred, please try again.', 'edd-sample-plugin');
                            break;
                    }
                }
            }

            // Check if anything passed on a message constituting a failure
            if ( ! empty($message)) {
                return ['status' => false, 'message' => $message];
            }

            // $license_data->license will be either "valid" or "invalid"
            if ('site_inactive' === $license_data->license || 'inactive' === $license_data->license) {
                // update_option( 'wcpa_activation_license_url', '' );
                update_option('wcpa_activation_license_status', 'inactive');
            }
        }

        $url     = get_option('wcpa_activation_license_url') ? base64_decode(get_option('wcpa_activation_license_url')) : '';
        $expires = get_option('wcpa_activation_license_expires', '');
        $status  = get_option('wcpa_activation_license_status', 'Inactive');

        return ['status' => $status, 'licenseKey' => $key, 'licenseUrl' => $url, 'licenseExpires' => $expires];
    }

    public function save_settings($data)
    {
        if ($data) {
            $settings = get_option(self::$key);
            if ( ! is_array($settings)) {
                $settings = [];
            }
            foreach ($this->confKeys as $key => $val) {
                if ('field_option_price_format' == $key) {
                    if ((strpos($data['field_option_price_format'], 'price') === false)) {
                        /** ensure this field has included price tag , otherwise it can cause issues, so omit*/
                        continue;
                    }
                }
                if (in_array($key,['item_meta_format','item_meta_format_image','item_meta_format_color'])) {
                    if ((strpos($data[$key], '{label}') === false) && (strpos($data[$key], '{value}') === false)) {
                        /** ensure this field has included price tag , otherwise it can cause issues, so omit*/
                        continue;
                    }
                }




                list($type, $default) = $val;
                if ('summary_order' == $key) {
                    $settings[$key] = array_map(function ($v) {
                        return sanitize_text_field($v);
                    }, $data[$key]);
                } elseif ($type == 'array') {
                    if (empty($default)) {

                        $settings[$key] = array_map(function ($v) {
                            return $this->sanitize_settings('text', $v);
                        }, $data[$key]);
                    } else {
                        foreach ($default as $_key => $_val) {
                            list($_type, $_default) = $_val;
                            if (isset($data[$key][$_key])) {
                                $settings[$key][$_key] = $this->sanitize_settings($_type, $data[$key][$_key]);
                            } else {
                                $settings[$key][$_key] = $_default;
                            }
                        }
                    }
                } else {
                    if (isset($data[$key])) {
                        $settings[$key] = $this->sanitize_settings($type, $data[$key]);
                    } else {
                        $settings[$key] = $default;
                    }
                }
            }

            if (isset($data['product_custom_fields']) && is_array($data['product_custom_fields'])) {
                $settings['product_custom_fields'] = array_map(function ($cf) {
                    if ($cf == false || trim($cf['name']) == '') {
                        return false;
                    }

                    return array(
                        'name'  => sanitize_key(trim($cf['name'])),
                        'value' => stripslashes(sanitize_text_field($cf['value'])),
                    );
                }, $data['product_custom_fields']);

                $settings['product_custom_fields'] = array_filter($settings['product_custom_fields'], function ($v) {
                    return $v !== false;
                });
            }
            if (isset($data['wcpa_custom_extensions']) && is_array($data['wcpa_custom_extensions'])) {
                $settings['wcpa_custom_extensions'] = array_map(function ($cf) {
                    if ($cf == false || trim($cf['ext']) == '') {
                        return false;
                    }

                    return array(
                        'ext'  => sanitize_key(trim($cf['ext'])),
                        'mime' => stripslashes(sanitize_text_field($cf['mime'])),
                    );
                }, $data['wcpa_custom_extensions']);

                $settings['wcpa_custom_extensions'] = array_filter($settings['wcpa_custom_extensions'], function ($v) {
                    return $v !== false;
                });
            }


//            if (isset($data['product_custom_fields'])) {
//                $custom_fields_name = $_POST['product_custom_field_name'];
//                $custom_fields_value = $_POST['product_custom_field_value'];
//                $_current_fields = isset($settings['product_custom_fields']) ? $settings['product_custom_fields'] : false;
//                $current_fields = array();
//                $count = 0;
//                if (is_array($_current_fields) && !empty($_current_fields)) {
//                    foreach ($_current_fields as $key => $val) {
//                        $field_value = isset($custom_fields_value[$key]) ? trim($custom_fields_value[$key]) : 0;
//                        if (isset($custom_fields_name[$key]) && !empty($custom_fields_name[$key])) {
//                            $count++;
//                            $current_fields['cf_' . $count] = array(
//                                'name' => sanitize_key(trim($custom_fields_name[$key])),
//                                'value' => $field_value,
//                            );
//                            unset($custom_fields_name[$key]);
//                        }
//                    }
//                }
//
//                if (is_array($custom_fields_name)) {
//                    foreach ($custom_fields_name as $key => $val) {
//                        $count++;
//                        $field_value = isset($custom_fields_value[$key]) ? trim($custom_fields_value[$key]) : 0;
//                        if (!empty($val)) {
//                            $current_fields['cf_' . $count] = array(
//                                'name' => sanitize_key(trim($val)),
//                                'value' => $field_value,
//                            );
//                        }
//
//                    }
//                }
//
//                $settings['product_custom_fields'] = $current_fields;
//
//            }

//            if (isset($_POST['wcpa_extension_name'])) {
//                $extensions = [];
//                $extension_name = $_POST['wcpa_extension_name'];
//                $extension_mime = $_POST['wcpa_extension_mime'];
//                if ($extension_name) {
//                    foreach ($extension_name as $key => $ext) {
//                        if (isset($ext) && !empty($ext) && isset($extension_mime[$key])) {
//                            $extensions[$ext] = $extension_mime[$key];
//                        }
//                    }
//                }
//                $settings['wcpa_custom_extensions'] = $extensions;
//            } else {
//                $settings['wcpa_custom_extensions'] = [];
//            }
//
//            if (isset($_POST['wcpa_custom_extension_choose'])) {
//                $extensions = [];
//                $extension_choosed = $_POST['wcpa_custom_extension_choose'];
//
//                $wcpa_mimetypes = [];
//                require 'mimetypes.php';
//
//                $wcpa_mimetypes = apply_filters('wcpa_custom_mime_types', $wcpa_mimetypes);
//
//                if ($extension_choosed) {
//                    foreach ($extension_choosed as $ext) {
//                        if ($ext && isset($wcpa_mimetypes[$ext])) {
//                            $extensions[$ext] = $wcpa_mimetypes[$ext];
//                        }
//                    }
//                }
//                $settings['wcpa_custom_extensions_choose'] = $extensions;
//            } else {
//                $settings['wcpa_custom_extensions_choose'] = [];
//            }

            update_option(self::$key, $settings);
        }

        delete_transient('wcpa_settings_'.WCPA_VERSION);
        refreshCaches();

        return true;
    }

    public function sanitize_settings($type, $val)
    {


        if ($type == 'text') {
            $val = str_ireplace(
                [
                    '{characters}',
                    '{pattern}',
                    '{minLength}',
                    '{maxLength}',
                    '{minValue}',
                    '{maxValue}',
                    '{minField}',
                    '{maxField}',
                    '{minOptions}',
                    '{maxOptions}',
                    '{maxFileCount}',
                    '{minFileCount}',
                    '{maxFileSize}',
                    '{minFileSize}',
                    '{fileExtensions}',
                    '{charLeft}',
                    '{minQuantity}',
                    '{maxQuantity}',
                    '{fileName}'
                ],
                '%s', $val);
            if(is_array($val)){
               return  $val;
            }
            return stripslashes(sanitize_text_field($val));
        } elseif ($type == 'boolean') {
            return metaToBoolean($val);
        }
    }

    public function save_license($post_data)
    {
        $key = $post_data['licenseKey'];

        return $this->activate_license($key);
    }

    function activate_license($key)
    {
        // listen for our activate button to be clicked
        if (empty($key)) {
            return ['status' => false];
        }

        // retrieve the license from the database

        $license = sanitize_text_field($key);

        if ( ! $license) {
            return ['status' => false];
        }

        // data to send in our API request
        $api_params = array(
            'edd_action'  => 'activate_license',
            'license'     => $license,
            'item_id'     => WCPA_ITEM_ID,
            'item_name'   => rawurlencode(WCPA_PLUGIN_NAME), // the name of our product in EDD
            'url'         => home_url(),
            'environment' => function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production',
        );

        // Call the custom API.
        $response = wp_remote_post(
            WCPA_STORE_URL,
            array(
                'timeout'   => 15,
                'sslverify' => false,
                'body'      => $api_params,
            )
        );
        // make sure the response came back okay
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            if (is_wp_error($response)) {
                $message = $response->get_error_message();
            } else {
                $message = __('An error occurred, please try again.');
            }
        } else {
            $license_data = json_decode(wp_remote_retrieve_body($response));

            if (false === $license_data->success) {
                switch ($license_data->error) {
                    case 'expired':
                        $message = sprintf(
                        /* translators: the license key expiration date */
                            __('Your license key expired on %s.', 'edd-sample-plugin'),
                            date_i18n(get_option('date_format'),
                                strtotime($license_data->expires, current_time('timestamp')))
                        );
                        break;

                    case 'disabled':
                    case 'revoked':
                        $message = __('Your license key has been disabled.', 'edd-sample-plugin');
                        break;

                    case 'missing':
                        $message = __('Invalid license.', 'edd-sample-plugin');
                        break;

                    case 'invalid':
                    case 'site_inactive':
                        $message = __('Your license is not active for this URL.', 'edd-sample-plugin');
                        break;

                    case 'item_name_mismatch':
                        /* translators: the plugin name */
                        $message = sprintf(__('This appears to be an invalid license key for %s.', 'edd-sample-plugin'),
                            WCPA_PLUGIN_NAME);
                        break;

                    case 'no_activations_left':
                        $message = __('Your license key has reached its activation limit.', 'edd-sample-plugin');
                        break;

                    default:
                        $message = __('An error occurred, please try again.', 'edd-sample-plugin');
                        break;
                }
            }
        }

        // Check if anything passed on a message constituting a failure
        if ( ! empty($message)) {
            return ['status' => false, 'message' => $message];
        }

        // $license_data->license will be either "valid" or "invalid"
        if ('valid' === $license_data->license) {
            $license_url = base64_encode(home_url());
            update_option('wcpa_activation_license_key', $license);
            update_option('wcpa_activation_license_url', $license_url);
            update_option('wcpa_activation_license_expires', $license_data->expires);
            refreshCaches();
        }
        update_option('wcpa_activation_license_status', $license_data->license);

        return ['status' => true, 'licenseStatus' => $license_data->license, 'licenseUrl' => home_url()];
    }

    public function deactivate_license($key)
    {
        if (empty($key)) {
            return ['status' => false];
        }

        $license = sanitize_text_field($key);

        if ( ! $license) {
            return ['status' => false];
        }

        $old = get_option('wcpa_activation_license_key');
        if ($old && $old != $license) {
            delete_option('wcpa_activation_license_status'); // new license has been entered, so must reactivate
        }

        update_option('wcpa_activation_license_key', $license);

        // data to send in our API request
        $api_params = array(
            'edd_action' => 'deactivate_license',
            'license'    => $license,
            'item_id'    => WCPA_ITEM_ID, // The ID of the item in EDD
            'url'        => home_url(),
        );
        // Call the custom API.
        $response = wp_remote_post(WCPA_STORE_URL,
            array('timeout' => 15, 'sslverify' => false, 'body' => $api_params));

        // make sure the response came back okay
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            if (is_wp_error($response)) {
                $message = $response->get_error_message();
            } else {
                $message = __('An error occurred, please try again.');
            }
        } else {
            $license_data = json_decode(wp_remote_retrieve_body($response));

            if (false === $license_data->success) {
                switch ($license_data->error) {
                    case 'expired':
                        $message = sprintf(
                        /* translators: the license key expiration date */
                            __('Your license key expired on %s.', 'edd-sample-plugin'),
                            date_i18n(get_option('date_format'),
                                strtotime($license_data->expires, current_time('timestamp')))
                        );
                        break;

                    case 'disabled':
                    case 'revoked':
                        $message = __('Your license key has been disabled.', 'edd-sample-plugin');
                        break;

                    case 'missing':
                        $message = __('Invalid license.', 'edd-sample-plugin');
                        break;

                    case 'invalid':
                    case 'site_inactive':
                        $message = __('Your license is not active for this URL.', 'edd-sample-plugin');
                        break;

                    case 'item_name_mismatch':
                        /* translators: the plugin name */
                        $message = sprintf(__('This appears to be an invalid license key for %s.', 'edd-sample-plugin'),
                            WCPA_PLUGIN_NAME);
                        break;

                    case 'no_activations_left':
                        $message = __('Your license key has reached its activation limit.', 'edd-sample-plugin');
                        break;

                    default:
                        $message = __('An error occurred, please try again.', 'edd-sample-plugin');
                        break;
                }
            }
        }
        // do_action('wcpa_license_updated');

        // Check if anything passed on a message constituting a failure
        // if (!empty($message)) {
        //     $base_url = admin_url('options-general.php?page=wcpa_settings');
        //     $redirect = add_query_arg(array('sl_activation' => 'false', 'message' => urlencode($message)), $base_url);
        //     wp_redirect($redirect);
        //     exit();
        // }
        // $license_data->license will be either "valid" or "invalid"
        // update_option('wcpa_activation_license_status', $license_data->license);
        // wp_redirect(admin_url('options-general.php?page=wcpa_settings'));
        // exit();

        // Check if anything passed on a message constituting a failure

        if ( ! empty($message)) {
            return ['status' => false, 'message' => $message];
        }

        // $license_data->license will be either "valid" or "invalid"

        if ('deactivated' === $license_data->license) {
//            update_option('wcpa_activation_license_key', $license);
            update_option('wcpa_activation_license_url', '');
            update_option('wcpa_activation_license_expires', '');
        }
        update_option('wcpa_activation_license_status', $license_data->license);

        return ['status' => true];
    }

    public function reset_key()
    {
        update_option('wcpa_activation_license_key', '');
        update_option('wcpa_activation_license_url', '');
        update_option('wcpa_activation_license_expires', '');
        return ['status' => true];
    }
}