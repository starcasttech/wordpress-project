<?php

namespace Acowebs\WCPA;

if (!defined('ABSPATH')) {
    exit;
}

class Comp
{

    public function __construct()
    {
        /** Barn2 restaurant booking */
        add_filter('wc_restaurant_ordering_modal_data', array($this, 'restaurant_ordering_modal_data'), 10, 2);

        /** Klavio abandoned cart */
        add_filter('kl_checkout_item', array($this, 'kl_checkout_item'), 10, 2);
        add_action('kl_cart_rebuild_complete', array($this, 'kl_cart_rebuild_complete'), 10, 1);
        add_filter('kl_started_checkout', array($this, 'kl_started_checkout'), 10, 2);

//        $event_data = apply_filters( 'kl_started_checkout', $event_data, $cart );

    }

    public function kl_started_checkout($event_data, $cart)
    {

//        $event_data['$extra']['CartRebuildKey'] = base64_encode( json_encode( $wck_cart ) );
        if (!$cart || !isset($event_data['$extra']['CartRebuildKey'])) {
            return $event_data;
        }
        if (method_exists($cart, 'get_cart')) {
            $cart_contents = $cart->get_cart();
        } else {
            $cart_contents = $cart->cart_contents;
        }
        $Products = (array)json_decode(base64_decode($event_data['$extra']['CartRebuildKey']));

        $hasWcpa = false;
        foreach ($cart_contents as $key => $value) {
            if (isset($value[WCPA_CART_ITEM_KEY]) && isset($Products['normal_products']->{$key})) {
//                $item[WCPA_CART_ITEM_KEY] = $value[WCPA_CART_ITEM_KEY];
                /** extract name and value from wcpa_data**/
                $Products['normal_products']->{$key}->wcpa_data = wcpaDataToKeyValue($value[WCPA_CART_ITEM_KEY]);
                $hasWcpa = true;
            }
        }
        if ($hasWcpa) {
            $event_data['$extra']['CartRebuildKey'] = base64_encode(json_encode($Products));
        }

        return $event_data;
    }

    public function kl_cart_rebuild_complete($kl_cart)
    {

        if (isset($kl_cart['normal_products'])) {
            if (method_exists(WC()->cart, 'get_cart')) {
                $cart_contents = WC()->cart->get_cart_contents();
            } else {
                $cart_contents = WC()->cart->cart_contents;
            }

            $normal_products = $kl_cart['normal_products'];
            $normal_products = array_values($normal_products);
            $i = 0;

            foreach ($cart_contents as $key => $cart_item) {
                if (isset($normal_products[$i]) && isset($normal_products[$i]['wcpa_data']) && !empty($normal_products[$i]['wcpa_data'])) {
                    WC()->cart->remove_cart_item($key);
                }
                $i++;
            }
            foreach ($normal_products as $product) {
                if (isset($product['wcpa_data']) && !empty($product['wcpa_data'])) {
                    $_get = $_GET;
                    $_GET = array_merge($_GET, $product['wcpa_data']);
                    $cart_item_data = apply_filters('wcpa_add_cart_item_data', [], $product['product_id'], $product['variation_id'], $product['quantity'], true);
                    WC()->cart->add_to_cart($product['product_id'], $product['quantity'], $product['variation_id'], $product['variation'], $cart_item_data);
                    $_GET = $_get;
                }
            }


        }

    }

    public function kl_checkout_item($item, $product)
    {

        if (!WC()->cart) {
            return $item;
        }
        if (method_exists(WC()->cart, 'get_cart')) {
            $cart_contents = WC()->cart->get_cart();
        } else {
            $cart_contents = WC()->cart->cart_contents;
        }
        foreach ($cart_contents as $key => $value) {
            if ($value['data'] == $product && isset($value[WCPA_CART_ITEM_KEY])) {
                $item[WCPA_CART_ITEM_KEY] = $value[WCPA_CART_ITEM_KEY];
            }
        }


        return $item;
    }

    public function restaurant_ordering_modal_data($data, $product)
    {

        if (array_key_exists('options', $data)) {
            $data['options'] = $data['options'] . apply_filters('wcpa_render_form', '', $product);
        }

        return $data;
    }
}