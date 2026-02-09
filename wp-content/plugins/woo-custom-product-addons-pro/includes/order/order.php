<?php


namespace Acowebs\WCPA;

use Exception;
use WC_Order_Factory;
use function wc_get_order_item_meta;
use function wc_update_order_item_meta;

class Order
{
    /**
     * @var false|mixed|string|void
     */
    private $show_price;
    public $orderCurrency = false;
    public function __construct()
    {
        add_action(
            'woocommerce_checkout_create_order_line_item',
            array($this, 'checkout_create_order_line_item'),
            10,
            4
        );
        /** support for RFQ request quote plugin  */
        add_action(
            'rfqtk_woocommerce_checkout_create_order_line_item',
            array($this, 'rfqtk_checkout_create_order_line_item'),
            10,
            4
        );


        add_action('woocommerce_checkout_update_order_meta', array($this, 'checkout_order_processed'), 1, 1);
        /** support for block checkout */
        add_action('woocommerce_store_api_checkout_update_order_meta',
            array($this, 'checkout_order_processed'), 1, 1);


        add_action('woocommerce_checkout_subscription_created', array($this, 'checkout_subscription_created'), 10,
            1); //compatibility with subscription plugin


        add_filter('woocommerce_order_item_display_meta_value', array($this, 'display_meta_value'), 10, 3);

        add_action('woocommerce_after_order_itemmeta', array($this, 'order_item_line_item_html'), 10, 3);


        add_action('woocommerce_order_item_get_formatted_meta_data', array(
            $this,
            'order_item_get_formatted_meta_data',
        ), 10, 2);


        add_filter('woocommerce_display_item_meta', array($this, 'display_item_meta'), 10, 3);

        add_filter('woocommerce_email_attachments', array($this, 'email_attachments'), 10, 3);


        add_filter('woocommerce_order_item_display_meta_key', array($this, 'item_display_meta_key'), 10, 3);
//        apply_filters( 'woocommerce_order_item_display_meta_key', $display_key, $meta, $this ),

        add_filter('woocommerce_order_formatted_line_subtotal', array(
            $this,
            'order_formatted_line_subtotal',
        ), 10, 2);

        add_action('woocommerce_before_save_order_items', array($this, 'before_save_order_items'), 10, 2);
    }


    public function item_display_meta_key($display_key, $meta, $item)
    {

        $meta_map = $item->get_meta('_wcpa_order_meta_key_label_map');

        $data = $meta->get_data();
        if ($meta_map && isset($data['id']) && isset($meta_map[$data['id']])) {
            $display_key = $meta_map[$data['id']];
        }

        return $display_key;
    }


    public function email_attachments($attachments, $email_id, $order)
    {

        if (!in_array($email_id, ['customer_on_hold_order', 'customer_processing_order', 'new_order'])) {
            return $attachments;
        }
        if (!Config::get_config('attach_files_in_emails')) {
            return $attachments;
        }

        // Avoiding errors and problems
        if (!is_a($order, 'WC_Order') || !isset($email_id)) {
            return $attachments;
        }

        $items = $order->get_items();

        foreach ($items as $item) {
            $sections = $item->get_meta('_WCPA_order_meta_data', true); // Replace '_your_meta_key' with your actual meta key
            foreach ($sections as $section) {
                $fields = $section['fields'];
                foreach ($fields as $row) {
                    foreach ($row as $field) {
                        if ($field['type'] == 'file') {
                            foreach ($field['value'] as $file) {
                                $link = $file['file'];
                                $attachments[] = $link;
                            }
                        }
                    }
                }
            }
        }

        return $attachments;
    }

    //TODO to verify
    public function display_item_meta($html, $item, $args)
    {
        $html = str_replace('<strong class="wc-item-meta-label">' . WCPA_EMPTY_LABEL . ':</strong>', '', $html);

        return str_replace(WCPA_EMPTY_LABEL . ':', '', $html);
    }

    public function order_item_line_item_html($item_id, $item, $product)
    {
        $meta_data = $item->get_meta(WCPA_ORDER_META_KEY);

        if (is_array($meta_data) && count($meta_data)) {
            $firstKey = array_key_first($meta_data);
            if (is_string($firstKey)) {
////                include(plugin_dir_path(__FILE__).'meta-line-item.php');
//                $meta = new OrderMetaLineItem($item, $product);
//                $meta->render();

                echo '<div class="wcpa_order_meta" 
                 data-wcpa=\'' . htmlspecialchars(wp_json_encode($meta_data), ENT_QUOTES) . '\'
                   data-itemId="' . $item_id . '" >
                <div class="wcpa_skeleton_loader"></div>
				 <div class="wcpa_skeleton_label"></div>
				 <div class="wcpa_skeleton_field"></div></div>';
            } else {
                include(plugin_dir_path(__FILE__) . 'meta-line-item_v1.php');
            }
        }
    }

    /**
     * To hide showing wcpa meta as default order meta in admin end order details. As we are already showing this data in formatted mode
     */
    public function order_item_get_formatted_meta_data($formatted_meta, $item)
    {
        $count = is_admin()? 0: 1;
        if (Config::get_config('show_meta_in_order') && did_action('woocommerce_before_order_itemmeta') > $count) {
            foreach ($formatted_meta as $meta_id => $v) {
                if ($this->wcpa_meta_by_meta_id($item, $meta_id)) {
                    unset($formatted_meta[$meta_id]);
                }
            }
        }

        return $formatted_meta;
    }

    private function wcpa_meta_by_meta_id($item, $meta_id)
    {
        $meta_data = $item->get_meta(WCPA_ORDER_META_KEY);


        if (is_array($meta_data) && count($meta_data)) {
            $firstKey = array_key_first($meta_data);
            if (is_string($firstKey)) {
                /** version 2 Format - including sections  */
                foreach ($meta_data as $sectionKey => $section) {
                    $form_rules = $section['extra']->form_rules;
                    foreach ($section['fields'] as $rowIndex => $row) {
                        foreach ($row as $colIndex => $field) {
                            if (isset($field['meta_id']) && ($meta_id == $field['meta_id'])) {
                                return ['form_rules' => $form_rules, 'field' => $field];
                            }
                        }
                    }
                }
            } else {
                /** version 1 Format */
                foreach ($meta_data as $v) {
                    if (isset($v['meta_id']) && ($meta_id == $v['meta_id'])) {
                        return $v;
                    }
                }
            }
        } else {
            return false;
        }

        return false;
    }

    public function checkout_order_processed($order_id)
    {
        $order = wc_get_order($order_id);
        $items = $order->get_items();
        if (is_array($items)) {
            foreach ($items as $item_id => $item) {
                $this->update_order_item($item, $order_id);
            }
        }
        if(WC()->session){
           WC()->session->set('wcpa_upload_file_temp',[]);
        }
    }


    public function update_order_item($item)
    {
        if (!is_object($item)) {
            $item = WC_Order_Factory::get_order_item($item);
        }
        if (!$item) {
            return false;
        }
        $wcpa_meta_data = $item->get_meta(WCPA_ORDER_META_KEY);
        if (!is_array($wcpa_meta_data)) {
            return;
        }
        $quantity = $item->get_quantity();
        $save_price = Config::get_config('show_price_in_order_meta');
        foreach ($wcpa_meta_data as $sectionKey => $section) {
            $form_rules = $section['extra']->form_rules;
            foreach ($section['fields'] as $rowIndex => $row) {
                foreach ($row as $colIndex => $field) {
                    $plain = $this->order_meta_plain($field, $form_rules, $save_price, $quantity);
                    if (isset($field['meta_id']) && is_numeric($field['meta_id'])) {
                        $item->update_meta_data(false, $plain, $field['meta_id']);
                        //* setting key false as it doesnt changed */

                    } else {
                        $item->add_meta_data(
                            'WCPA_id_' . $colIndex . '_' . $rowIndex . '_' . $sectionKey,
                            // why sectionKey at end? section can be contain '_', so splitting will result wrong
                            $plain
                        );
                    }

                }
            }
        }

        $item->save_meta_data();
        $meta_data = $item->get_meta_data();
        $meta_label_map = [];
        foreach ($meta_data as $meta) {
            $data = (object)$meta->get_data();
            if (($index = $this->check_wcpa_meta($data)) !== false) {
                $metaDataItem = &$wcpa_meta_data[$index->sectionKey]['fields'][$index->rowIndex][$index->colIndex];
                $meta_label_map[$data->id] = $metaDataItem['label'];
                $metaKeyAttr = Config::get_config('order_line_item_meta_key');
                if ($metaKeyAttr == 'name') { // label or name or id
                    $metaKey = $metaDataItem['name'];
                } else if ($metaKeyAttr == 'id') {
                    $metaKey = $metaDataItem['elementId'];
                } else {
                    $metaKey = $metaDataItem['label'];
                }
                if (
                    $metaDataItem['type'] == 'hidden' ||
                    !Config::get_config('show_meta_in_order') ||
                    (isset($metaDataItem['form_data']->hideFieldIn_order) && $metaDataItem['form_data']->hideFieldIn_order) ||
                    ($metaDataItem['type'] == 'productGroup' && isset($metaDataItem['form_data']->independent) && $metaDataItem['form_data']->independent)

                ) {
                    $item->update_meta_data('_' . $metaKey, $data->value, $data->id);

                } else {
                    $item->update_meta_data($metaKey, $data->value, $data->id);

                }


                if ($metaDataItem['type'] == 'productGroup' && is_array($metaDataItem['value'])) {
                    foreach ($metaDataItem['value'] as $v) {
                        $p_id = $v['value'];
                        $p_quantity = $v['quantity'];
                        $product = wc_get_product($p_id);
                        if ($product->get_manage_stock()) {
                            $stock_quantity = $product->get_stock_quantity();
                            if (!isset($field['form_data']->independent) || !$field['form_data']->independent) {
                                if (!isset($field['form_data']->independentQuantity) || !$field['form_data']->independentQuantity) {
                                    $p_quantity *= $quantity;
                                }
                                $new_quantity = $stock_quantity - $p_quantity;
                                $product->set_stock_quantity($new_quantity);
                                $product->save();
                            }
                        }
                    }
                }
                $metaDataItem['meta_id'] = $data->id;
            }
        }

        $wcpa_meta_data = apply_filters('wcpa_order_meta_data', $wcpa_meta_data, $item);
        $item->update_meta_data(WCPA_ORDER_META_KEY, $wcpa_meta_data);
        $item->update_meta_data('_wcpa_order_meta_key_label_map', $meta_label_map);
        $item->save_meta_data();
    }

    public function order_meta_plain($v, $form_rules, $show_price = true, $quantity = 1, $product = false)
    {
        $field_price_multiplier = 1;
        if (Config::get_config('show_field_price_x_quantity', false)) {
            $field_price_multiplier = $quantity;
        }

        if (
            (isset($form_rules['pric_cal_option_once'])
                && $form_rules['pric_cal_option_once'] === true)
            || (isset($form_rules['pric_use_as_fee']) && $form_rules['pric_use_as_fee'] === true) ||
            (isset($v['is_fee']) && $v['is_fee'] === true)
        ) {
            $field_price_multiplier = 1;
        }
        $metaValue = '';
        $value = $v['value'];
        $fieldType = $v['type'];
        switch ($fieldType) {
            case 'file':
                if (is_array($value)) {
                    /**
                     * Convert files array as string Joining each file name and its URL with a pipe (|)
                     */
                    $metaValue = implode(
                        "\r\n",
                        array_map(
                            function ($a) {
                                return (is_string($a) ? $a : ($a['file_name'] . ' | ' . $a['url']));
                            },
                            $value
                        )
                    );
                    if (isset($v['quantity']) && !empty($v['quantity'])) {
                        $metaValue = $metaValue . '\r\nx ' . $v['quantity'];
                    }
                    if ($v['price'] && $show_price) {
                        $metaValue = $metaValue . '\r\n(' . wcpaPrice($v['price'] * $field_price_multiplier, false, 1) . ')';
                    }
                }
                break;
            case 'image-group':
            case 'productGroup':


                if (is_array($value)) {
                    if ($v['type'] == 'productGroup') {
                        if (isset($value[0]) && is_object($value[0])) {
                            //convert older version productGroup object as array
                            $value = array_map(function ($prdct) {
                                return [
                                    'i' => 0,
                                    'label' => $prdct->get_title(),
                                    'value' => $prdct->get_id(),
                                    'image' => ''
                                ];
                            }, $value);
                        }
                    }


                    if ($v['price'] && $show_price) {
                        $metaValue = implode(
                            "\r\n",
                            array_map(
                                function ($val, $price) use ($field_price_multiplier, $product) {
                                    $qt = '';
                                    if (isset($val['quantity']) && !empty($val['quantity'])) {
                                        $qt .= ' x ' . $val['quantity'];
                                    }
                                    if ($val['i'] === 'other') {
                                        $_return = $val['label'] . $qt . ': ' . $val['value'];
                                    } else {
                                        //    $_return = $val['label'] . $qt . ' | ' . $val['value'] . ' | ' . $val['image'];
                                        $_return = orderMetaValueForDb($val['label'], $val['value'], $qt, $val['image']);
                                    }


                                    if ($price) {
                                        $_return .= ' | (' . wcpaPrice($price * $field_price_multiplier, false, 1) . ')';
                                    }

                                    return $_return;
                                },
                                $value,
                                $v['price']
                            )
                        );
                    } else {
                        $metaValue = implode(
                            "\r\n",
                            array_map(
                                function ($val) {
                                    $qt = '';
                                    if (isset($val['quantity']) && !empty($val['quantity'])) {
                                        $qt .= ' x ' . $val['quantity'];
                                    }

                                    if ($val['i'] === 'other') {
                                        $_return = $val['label'] . $qt . ': ' . $val['value'];
                                    } else {
//                                        $_return = $val['label'] . $qt . ' | ' . $val['value'] . ' | ' . $val['image'];
                                        $_return = orderMetaValueForDb($val['label'], $val['value'], $qt, $val['image']);
                                    }

                                    return $_return;
                                },
                                $value
                            )
                        );
                    }
                }

                break;

            case 'color-group':
                if (is_array($value)) {
                    if ($v['price'] && $show_price) {
                        $metaValue = implode(
                            "\r\n",
                            array_map(
                                function ($val, $price) use ($field_price_multiplier, $product) {
                                    $qt = '';
                                    if (isset($val['quantity']) && !empty($val['quantity'])) {
                                        $qt .= ' x ' . $val['quantity'];
                                    }

                                    if ($val['i'] === 'other') {
                                        $_return = $val['label'] . $qt . ': ' . $val['value'];
                                    } else {
//                                        $_return = $val['label'] . $qt . ' | ' . $val['value'] . ' | ' . $val['color'];
                                        $_return = orderMetaValueForDb($val['label'], $val['value'], $qt, false, $val['color']);
                                    }

                                    if ($price) {
                                        $_return .= ' | (' . wcpaPrice($price * $field_price_multiplier, false, 1) . ')';
                                    }

                                    return $_return;
                                },
                                $value,
                                $v['price']
                            )
                        );
                    } else {
                        $metaValue = implode(
                            "\r\n",
                            array_map(
                                function ($val) {
                                    $qt = '';
                                    if (isset($val['quantity']) && !empty($val['quantity'])) {
                                        $qt .= ' x ' . $val['quantity'];
                                    }
                                    if ($val['i'] === 'other') {
                                        $_return = $val['label'] . $qt . ': ' . $val['value'];
                                    } else {
//                                        $_return = $val['label'] . $qt . ' | ' . $val['value'] . ' | ' . $val['color'];
                                        $_return = orderMetaValueForDb($val['label'], $val['value'], $qt, false, $val['color']);
                                    }

                                    return $_return;
                                },
                                $value
                            )
                        );
                    }
                }


                break;
            case  'placeselector':
                $strings = [
                    'street' => Config::get_config('place_selector_street'),
                    'city' => Config::get_config('place_selector_city'),
                    'state' => Config::get_config('place_selector_state'),
                    'zip' => Config::get_config('place_selector_zip'),
                    'country' => Config::get_config('place_selector_country'),
                    'latitude' => Config::get_config('place_selector_latitude'),
                    'longitude' => Config::get_config('place_selector_longitude'),
                ];
                if (!empty($value['value'])) {
                    $metaValue = $value['value'] . '<br>';
                    if (!empty($value['split']['street_number'])) {
                        $metaValue .= $strings['street'] . ' ' . $value['split']['street_number'] . ' ' . $value['split']['route'] . "\r\n";
                    }
                    if (!empty($value['split']['locality'])) {
                        $metaValue .= $strings['city'] . ' ' . $value['split']['locality'] . "\r\n";
                    }
                    if (!empty($value['split']['administrative_area_level_1'])) {
                        $metaValue .= $strings['state'] . ' ' . $value['split']['administrative_area_level_1'] . "\r\n";
                    }
                    if (!empty($value['split']['postal_code'])) {
                        $metaValue .= $strings['zip'] . ' ' . $value['split']['postal_code'] . "\r\n";
                    }
                    if (!empty($value['split']['country'])) {
                        $metaValue .= $strings['country'] . ' ' . $value['split']['country'] . "\r\n";
                    }
                    if (isset($value['cords']['lat']) && !empty($value['cords']['lat'])) {
                        $metaValue .= $strings['latitude'] . ' ' . $value['cords']['lat'] . "\r\n";
                        $metaValue .= $strings['longitude'] . ' ' . $value['cords']['lng'] . "\r\n";
                    }
                    if ($v['price'] && $show_price) {
                        $metaValue = $metaValue . '\r\n(' . wcpaPrice($v['price'] * $field_price_multiplier, false, 1) . ')';
                    }
                }

                break;

            case 'date':
            case 'datetime-local':

                $meta_custom_date_format = Config::get_config('meta_custom_date_format');
                $format = isset($v['dateFormat']) ? $v['dateFormat'] : false;
                if (is_array($value)) {
                    if (isset($value['start'])) {
                        $metaValue = ($meta_custom_date_format ? formattedDate($value['start'], $format) : $value['start']) .
                            __(' to ', 'woo-custom-product-addons-pro') .
                            ($meta_custom_date_format ? formattedDate($value['end'], $format) : $value['end']);
                    } else {
                        $metaValue = '';
                        foreach ($value as $dt) {
                            $metaValue .= ($meta_custom_date_format ? formattedDate($dt, $format) : $dt) . ', ';
                        }
                        $metaValue = trim($metaValue, ',');
                    }
                } else {
                    $metaValue = ($meta_custom_date_format ? formattedDate($value, $format) : $value);
                }
                if (isset($v['quantity']) && !empty($v['quantity'])) {
                    $metaValue = $metaValue . ' x ' . $v['quantity'];
                }

                if ($v['price'] && $show_price) {
                    $metaValue = $metaValue . ' (' . wcpaPrice($v['price'] * $field_price_multiplier, false, 1) . ')';
                }

                break;
            default:
                if (is_array($value) && in_array($v['type'], ['select', 'radio-group', 'checkbox-group'])) {
                    if ($v['price'] && $show_price) {
                        $metaValue = implode(
                            "\r\n",
                            array_map(
                                function ($val, $price) use ($field_price_multiplier, $product) {
                                    $qt = '';
                                    if (isset($val['quantity']) && !empty($val['quantity'])) {
                                        $qt .= ' x ' . $val['quantity'];
                                    }

                                    if ($val['i'] === 'other') {
                                        $_return = $val['label'] . $qt . ': ' . $val['value'];
                                    } else {
//                                        $_return = $val['label'] . $qt . ' | ' . $val['value'];
//                                        $_return = $val['label'] . $qt; // removed value as many requested not need value in export data
                                        $_return = orderMetaValueForDb($val['label'], $val['value'], $qt);
                                    }

                                    if ($price) {
                                        $_return .= ' | (' . wcpaPrice($price * $field_price_multiplier, false, 1) . ')';
                                    }

                                    return $_return;
                                },
                                $value,
                                $v['price']
                            )
                        );
                    } else {
                        $metaValue = implode(
                            "\r\n",
                            array_map(
                                function ($val) {
                                    $qt = '';
                                    if (isset($val['quantity']) && !empty($val['quantity'])) {
                                        $qt .= ' x ' . $val['quantity'];
                                    }
                                    if ($val['i'] === 'other') {
                                        $_return = $val['label'] . $qt . ': ' . $val['value'];
                                    } else {
//                                        $_return = $val['label'] . $qt . ' | ' . $val['value'];
//                                        $_return = $val['label'] . $qt ; // removed value as many requested not need value in export data
                                        $_return = orderMetaValueForDb($val['label'], $val['value'], $qt);
                                    }

                                    return $_return;
                                },
                                $value
                            )
                        );
                    }
                } else {
                    $metaValue = $value;
                    if (isset($v['quantity']) && !empty($v['quantity'])) {
                        $metaValue = $metaValue . ' x ' . $v['quantity'];
                    }
                    if ($v['price'] && $show_price) {
                        $metaValue .= ' (' . wcpaPrice($v['price'] * $field_price_multiplier, false, 1) . ')';
                    }
                }


                break;
            //TODO check content field
        }

        return $metaValue;
    }

    public function sanitize_values($value, $type) {
        if (is_array($value)) {
            array_walk($value, function(&$a, $b) {
                sanitize_text_field($a);
            }); // using this array_wal method to preserve the keys
            return $value;
        } else if ($type == 'textarea') {
            return sanitize_textarea_field($value);
        } else {
            return sanitize_text_field($value);
        }
    }

    public function before_save_order_items($order_id, $items) {

        if (is_array($items) && isset($items['wcpa_meta'])) {
            $wcpa_meta = $items['wcpa_meta'];
            if (isset($wcpa_meta['value']) && is_array($wcpa_meta['value'])) {
                foreach ($wcpa_meta['value'] as $item_id => $data) {
                    if (!$item = WC_Order_Factory::get_order_item(absint($item_id))) {
                        continue;
                    }

                    $meta_data = $item->get_meta(WCPA_ORDER_META_KEY);

                    foreach ($meta_data as $k => $v) {
                        $meta_id = $meta_data[$k]['meta_id'];
                        if (isset($data[$k])) {
                            $meta_value_temp = array('type' => false, 'value' => false, 'price' => FALSE);

                            $meta_data[$k]['value'] = $this->sanitize_values($data[$k], $v['type']);
                            $meta_value_temp['value'] = $meta_data[$k]['value'];
                            $meta_value_temp['type'] = $v['type'];
                            $meta_value = $this->order_meta_plain_v1($meta_value_temp);

	                        if ( $v['type'] == 'hidden' ||
                                !Config::get_config('show_meta_in_order') ) {
		                        $item->update_meta_data('_' . $v['label'], $meta_value, $meta_id);
	                        } else {
		                        $item->update_meta_data($v['label'], $meta_value, $meta_id);
	                        }

                        } else {
                            $item->delete_meta_data_by_mid($meta_id);
                            unset($meta_data[$k]);
                        }
                    }
                    $item->update_meta_data(WCPA_ORDER_META_KEY, $meta_data);
                    $item->save();
                }
            }
        }
    }

    public function order_meta_plain_v1($v) {
        if (is_array($v['value'])) {

            return implode(', ', $v['value']);
        } else {

            return $v['value'];
        }
    }

    private function check_wcpa_meta($meta)
    {
        preg_match("/WCPA_id_(.*)/", $meta->key, $matches);
        if ($matches && count($matches)) {
            $pattern = "/([0-9]+)_([0-9]+)_(.*)/";
            preg_match($pattern, $matches[1], $index);
            if (count($index) == 4) {
                return (object)[
                    'sectionKey' => $index[3],
                    'rowIndex' => $index[2],
                    'colIndex' => $index[1]
                ];
            }

            return false;
        } else {
            return false;
        }
    }

    /**
     * Prepare addon values as plain text, it can be stored as order line item meta
     * This data can be utilized even if WCPA plugin is inActive
     * Also 3rd party plugins might be using this data, even it is not compatible with product addon, this raw data will be accessible
     */
    //TODO handle version 1 Data
    public function checkout_subscription_created($subscription)
    {
        $items = $subscription->get_items();
        $order_id = $subscription->get_id();
        if (is_array($items)) {
            foreach ($items as $item_id => $item) {
                $this->update_order_item($item, $order_id);
            }
        }
    }

    public function checkout_create_order_line_item($item, $cart_item_key, $values, $order)
    {
        if (empty($values[WCPA_CART_ITEM_KEY])) {
            return;
        }


        $item->add_meta_data(WCPA_ORDER_META_KEY, $values[WCPA_CART_ITEM_KEY]);
        if(isset($values['wcpaDiscountUnit']) && !empty($values['wcpaDiscountUnit']) && $values['wcpaDiscountUnit']!==1){
            $item->add_meta_data('_wcpaDiscountUnit', $values['wcpaDiscountUnit']);
        }

    }

    public function rfqtk_checkout_create_order_line_item($item, $cart_item_key, $values, $order)
    {
        if (empty($values[WCPA_CART_ITEM_KEY])) {
            return;
        }


        $item->add_meta_data(WCPA_ORDER_META_KEY, $values[WCPA_CART_ITEM_KEY]);
        if(isset($values['wcpaDiscountUnit']) && !empty($values['wcpaDiscountUnit']) && $values['wcpaDiscountUnit']!==1){
            $item->add_meta_data('_wcpaDiscountUnit', $values['wcpaDiscountUnit']);
        }
        $item->save();
    }

    /**
     * Display   formatted meta value
     *
     * @param $display_value
     * @param null $meta
     * @param null $item
     *
     * @return mixed|void
     */
    public function display_meta_value($display_value, $meta = null, $item = null)
    {
        if ($item != null && $meta !== null) {
            $wcpa_data = $this->wcpa_meta_by_meta_id($item, $meta->id);
        } else {
            $wcpa_data = false;
        }


        $out_display_value = $display_value;
        if ($wcpa_data) {

            if($this->orderCurrency === false) {
                $order_id = $item->get_order_id();
                $order = wc_get_order($order_id);
                if ($order) {
                    // Get the currency for the order
                    $this->orderCurrency = $order->get_currency();
                }
            }
            if (isset($wcpa_data['form_rules'])) {
                $form_rules = $wcpa_data['form_rules'];
                $field = $wcpa_data['field'];
            } else {
                $form_rules = isset($wcpa_data['form_data']->form_rules) ? $wcpa_data['form_data']->form_rules : [];
                $field = $wcpa_data;
            }
            $this->show_price = Config::get_config('show_price_in_order');
            $quantity = $item->get_quantity();

            if ($this->show_price == false) {// dont compare with === , $show_price will be 1 for true and 0 for false
                /** if it need to hide the price in order, generate a plain field without price */
                $meta->value = $display_value = $this->order_meta_plain($field, $form_rules, false, $quantity);
            }


            $quantityMultiplier = 1;
            if (Config::get_config('show_field_price_x_quantity')) {
                $quantityMultiplier = $quantity;
            }

            if ((isset($form_rules['pric_cal_option_once']) &&
                    $form_rules['pric_cal_option_once'] === true) ||
                (isset($form_rules['pric_use_as_fee']) &&
                    $form_rules['pric_use_as_fee'] === true) ||
                (isset($field['is_fee']) && $field['is_fee'] === true)
            ) {
                $quantityMultiplier = 1;
            }

            $discountUnit = 1;

            if (!isset($form_rules['exclude_from_discount']) || !$form_rules['exclude_from_discount']) {
                $discountUnit = $item->get_meta('_wcpaDiscountUnit');
                if(!$discountUnit){
                    $discountUnit=1;
                }
                 $discountUnit = floatval($discountUnit);
            }

            //TODO check currency and taxrate
            $metaDisplay = new MetaDisplay(false, $this->show_price, $quantityMultiplier,1,$discountUnit,$this->orderCurrency);
            $out_display_value = $metaDisplay->display($field, $form_rules);

            /** removed below code as '$display_value' contains price value it display price twice */
//            if (in_array(
//                $field['type'],
//                [
//                    'date',
//                    'datetime-local',
//                    'content',
//                    'textarea',
//                    'color',
//                    'file',
//                    'image-group',
//                    'color-group',
//                    'placeselector',
//
//                ]
//            )) {
//                $out_display_value = $metaDisplay->display($field, $form_rules);
//            } else {
//                $out_display_value = $metaDisplay->display($field, $form_rules, $display_value);
//            }
        }

        return $out_display_value;
    }

    public function saveOrderMeta($itemId, $data)
    {

        try {

            if (is_array($data)) {

                foreach ($data as $sectionKey => $section) {
                    $data[$sectionKey]['extra'] = (object)$data[$sectionKey]['extra'];
                }
                wc_update_order_item_meta($itemId, WCPA_ORDER_META_KEY, $data);

//                /** delete existing metas */
//                $wcpa_meta_data = \wc_get_order_item_meta($itemId, WCPA_ORDER_META_KEY);
//
//                foreach ($wcpa_meta_data as $sectionKey => $section) {
//                    foreach ($section['fields'] as $rowIndex => $row) {
//                        foreach ($row as $colIndex => $field) {
//                            if (isset($field->meta_id) && is_numeric($field->meta_id)) {
//
//                            }
//                        }
//                    }
//                }

                $this->update_order_item($itemId);
                return true;
            }


        } catch (Exception $e) {
            return false;
        }

    }

    public function getOrderMeta($itemId)
    {
        $meta_data = wc_get_order_item_meta($itemId, WCPA_ORDER_META_KEY);
        if (is_array($meta_data) && count($meta_data)) {
            return $meta_data;
        }
        return false;
    }

    public function order_formatted_line_subtotal($subtotal, $item)
    {
        $meta_data = $item->get_meta(WCPA_ORDER_META_KEY);

        if (is_array($meta_data) && count($meta_data)) {
            $firstKey = array_key_first($meta_data);
            if (is_string($firstKey)) {
                $fees = array();

                foreach ($meta_data as $sectionKey => $section) {
                    $extra = $section['extra'];
                    foreach ($section['fields'] as $rowIndex => $row) {
                        foreach ($row as $colIndex => $v) {
                            $price = 0.0;
                            if (isset($v['price'])) {
                                if ((isset($extra->form_rules['pric_cal_option_once']) && $extra->form_rules['pric_cal_option_once'] === true) ||
                                    (isset($extra->form_rules['pric_use_as_fee']) && $extra->form_rules['pric_use_as_fee'])) {
                                    if (is_array($v['price'])) {
                                        foreach ($v['price'] as $p) {
                                            $price += $p;
                                        }
                                    } else if ($v['price']) {
                                        $price += $v['price'];
                                    }
                                    if (!isset($fees[$extra->form_id])) {
                                        $fees[$extra->form_id] = ['price' => 0.0, 'label' => ''];
                                    }

                                    $fees[$extra->form_id]['price'] += $price;
                                    $fees[$extra->form_id]['label'] = $extra->form_rules['fee_label'];
                                } else if ((isset($v['is_fee']) && $v['is_fee'] === true)) {
                                    if (is_array($v['price'])) {
                                        foreach ($v['price'] as $p) {
                                            $price += $p;
                                        }
                                    } else if ($v['price']) {
                                        $price += $v['price'];
                                    }

                                    $elem_id = sanitize_key($extra->form_id . '_' . $v['form_data']->elementId);
                                    if (!isset($fees[$elem_id])) {
                                        $fees[$elem_id] = ['price' => 0.0, 'label' => ''];
                                    }
                                    $fees[$elem_id]['price'] += $price;
                                    $fees[$elem_id]['label'] = $v['label'];
                                    $fees[$elem_id]['label'] = Cart::get_fee_label($v);
                                }
                            }
                        }
                    }
                }

                $items = '';
                if (!empty($fees)) {
                    foreach ($fees as $fee) {
                        if ($fee['price'] > 0) {
                            $price = $fee['price']; //wcpa_get_price_cart($item->get_product(), $fee['price']);

                            $items .= '<br>' . wc_price($price) . '<small class="woocommerce-Price-taxLabel tax_label">(' . $fee['label'] . ')</small>';
                        }
                    }
                    $subtotal .= $items;
                }
            }
        }

        return $subtotal;
    }

}