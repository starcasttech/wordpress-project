<?php

namespace Acowebs\WCPA;

use AF_C_S_P_Price;

if (!defined('ABSPATH')) {
    exit;
}

class Discounts
{

    public function __construct()
    {
//        $calculate_from_price = $product_price = apply_filters('advanced_woo_discount_rules_product_price_on_before_calculate_discount',
//            $product_price, $product, $quantity, $cart_item, $calculate_discount_from);

        /** flycart discount plugin */

        add_filter('advanced_woo_discount_rules_product_price_on_before_calculate_discount',
            array($this, 'flyCartPriceToApplyDiscount'), 10, 5);

        add_action('ywdpd_after_calculate_discounts', array($this, 'update_options_price'), 99);
        add_filter('ywdpd_cart_item_display_price', array($this, 'item_display_price'), 99, 2);
        add_filter('ywdpd_cart_item_adjusted_price', array($this, 'item_adjusted_price'), 99, 2);

//        return apply_filters( 'ywdpd_get_price_to_discount', $default_price, $cart_item, $cart_item_key, $this );
        /** Yith discount plugin */
//        add_filter('ywdpd_get_price_to_discount',function ( $default_price, $cart_item){
//            return beforeCalculateDiscount($default_price, $cart_item);
//        }, 10, 2);

//        add_filter('wad_before_calculate_sale_price',
//            array($this, 'wad_before_calculate_sale_price'), 10, 2);


        add_filter('wcpa_product_price', array($this, 'product_price_filters'), 10, 2);
        add_filter('wcpa_discount_rule', array($this, 'product_discount_filters'), 10, 2);
        add_filter('wcpa_discount_before_set_product_price', array($this, 'before_set_product_price_filters'), 10, 2);

        add_filter('wcpa_cart_addon_data', array($this, 'wcpa_cart_addon_data'), 10, 2);


    }

    static function cartPrice($total_price, $excludeDiscountPrice)
    {

        $total_price = apply_filters('wcpa_discount_before_set_product_price', $total_price, $excludeDiscountPrice);

        return $total_price;
    }

    /** adding third party discount plugins filters
     *
     * @param $response
     * @param $product
     *
     * @return mixed
     */
    static function product_discount_filters($response, $product)
    {
        /** flycart discount plugin */
        $discountedPrice = apply_filters('advanced_woo_discount_rules_get_product_discount_price_from_custom_price',
            false, $product, 1, 100, 'discounted_price', true, false);

        if ($discountedPrice !== false) {
            $discountedPrice2 = apply_filters('advanced_woo_discount_rules_get_product_discount_price_from_custom_price',
                false, $product, 1, 200, 'discounted_price', true, false);
            if ((100 - $discountedPrice) == (200 - $discountedPrice2)) {// if same, it is fixed type discount
                $response['percentage'] = 0;
                $response['fixed'] = (100 - $discountedPrice);
            } else {
                $response['percentage'] = (100 - $discountedPrice) / 100;
                $response['fixed'] = 0;
            }

            return $response;
        }

        if (function_exists('ywdpd_dynamic_pricing_discounts')) {
            $obj = ywdpd_dynamic_pricing_discounts();
            if (method_exists($obj, 'get_frontend_manager')) {
                $discountedPrice100 = ywdpd_dynamic_pricing_discounts()->get_frontend_manager()->get_quantity_price(100, $product);
                $discountedPrice200 = ywdpd_dynamic_pricing_discounts()->get_frontend_manager()->get_quantity_price(200, $product);
                if ((100 - $discountedPrice100) == (200 - $discountedPrice200)) {// if same, it is fixed type discount
                    $response['percentage'] = 0;
                    $response['fixed'] = (100 - $discountedPrice100);
                } else {
                    $response['percentage'] = (100 - $discountedPrice100) / 100;
                    $response['fixed'] = 0;
                }
            }

        }


        if (class_exists('\AF_C_S_P_Price')) {
            $af_price = new AF_C_S_P_Price();
            $_price = $product->get_price('edit');

            $product->set_price(100);
            $_price100 = $af_price->get_price_of_product($product);
            if ($_price100!==false && $_price100 !== 100) {
                $product->set_price(200);
                $_price200 = $af_price->get_price_of_product($product);
                if ((100 - $_price100) == (200 - $_price200)) { // if same, it is fixed type discount
                    $response['percentage'] = 0;
                    $response['fixed'] = (100 - $_price100);
                } else {
                    $response['percentage'] = (100 - $_price100) / 100;
                    $response['fixed'] = 0;
                }
            }
            $product->set_price($_price);
        }
        /** hack to get discount percentage or fixed  */


        /**
         *tested this method with
         *  "Conditional Discounts for WooCommerce by ORION"
         */

        //TODO need to remove as it causing issues with currency plugins
//        $_price = $product->get_price('edit');
//        $product->set_price(100);
//        $_price100 = $product->get_price();
//
//        if ($_price100 !== 100) {
//            $product->set_price(200);
//            $_price200 = $product->get_price();
//            if ((100 - $_price100) == (200 - $_price200)) { // if same, it is fixed type discount
//                $response['percentage'] = 0;
//                $response['fixed']      = (100 - $_price100);
//            } else {
//                $response['percentage'] = (100 - $_price100) / 100;
//                $response['fixed']      = 0;
//            }
//        }
//        $product->set_price($_price);

        return $response;
    }

    /** adding third party discount plugins filters
     *
     * @param $response
     * @param $product
     *
     * @return mixed|void
     */
    static function product_price_filters($response, $product)
    {

//        $response = ['price' => $sale, 'originalPrice' => $regular];

        /** flycart discount plugin */
        $discountedPrice = apply_filters('advanced_woo_discount_rules_get_product_discount_price_from_custom_price',
            false, $product, 1, 0, 'discounted_price', true, false);
        if ($discountedPrice !== false) {
            return $discountedPrice;
        }


        if (function_exists('ywdpd_dynamic_pricing_discounts')) {
            $obj = ywdpd_dynamic_pricing_discounts();
            if (method_exists($obj, 'get_frontend_manager')) {
                $discountedPrice = ywdpd_dynamic_pricing_discounts()->get_frontend_manager()->get_quantity_price($response['price'], $product);
                $response['price'] = $discountedPrice;
            }

        }


        return $response;
    }

    static function applyDiscountToCartAddonPrice($cartWcpa_price, $product)
    {
        $addonPrice = (float)$cartWcpa_price['addon'];
        $excludeFromDiscount = (float)$cartWcpa_price['excludeDiscount'];
        $discountUnit = self::getDiscountRule($product, true);
        return (($addonPrice - $excludeFromDiscount) * $discountUnit + $excludeFromDiscount);
    }

    static function getDiscountRule($product, $returnUnit = false)
    {
        $response = ['percentage' => 0, 'fixed' => 0];
        $_response = apply_filters('wcpa_discount_rule', $response, $product);

        if (!is_array($_response)) {
            $response['percentage'] = $_response;
        } else {
            $response = $_response;
        }

        if ($returnUnit) {
            return 1 - $response['percentage'];
        }

        /** return percentage out of unit ( out of 1). Dont return 1-<percentage> as we doing the same in front end as well */
        return $response;
    }

    static function getProductPrice($product, $returnRegular = false)
    {


        $price = getPriceFromHtml($product);

        if ($price === false) {
            $isPriceFromHtml = false;
            $sale = $product->get_sale_price();
            $regular = $product->get_regular_price();
            $price = $product->get_price();
            if (isEmpty($sale)) {
                $sale = $price;
            }
            if (isEmpty($regular)) {
                $regular = $price;
            }
        } else {
            $isPriceFromHtml = true;
            $sale = $price['price'];
            $regular = $price['regPrice'];
        }


        $response = ['price' => $sale, 'originalPrice' => $regular];
        if ($response['price'] === '') {
            $response['price'] = $response['originalPrice'];
        }


        $_response = apply_filters('wcpa_product_price', $response, $product);

        if (!is_array($_response) && !empty($_response)) {
            $response['price'] = $_response;
        } else {
            if (!empty($_response['price'])) {
                $response = $_response;
            }
        }


        if ($returnRegular) {

            if ('incl' === get_option('woocommerce_tax_display_shop')) {
                if ($isPriceFromHtml) {
                    return $response['originalPrice'];
                }
                return wc_get_price_including_tax(
                    $product,
                    array(
                        'qty' => 1,
                        'price' => $response['originalPrice'],
                    )
                );
            } else if ('excl' === get_option('woocommerce_tax_display_shop')) {

                return wc_get_price_excluding_tax(
                    $product,
                    array(
                        'qty' => 1,
                        'price' => $response['originalPrice'],
                    )
                );
            }

            return $response['originalPrice'];
        }
        if ('incl' === get_option('woocommerce_tax_display_shop')) {
            if ($isPriceFromHtml) {
                return $response['price'];
            }
            return wc_get_price_including_tax(
                $product,
                array(
                    'qty' => 1,
                    'price' => $response['price'],
                )
            );
        } else if ('excl' === get_option('woocommerce_tax_display_shop')) {
            if ($isPriceFromHtml) {
                return $response['price'];
            }
            return wc_get_price_excluding_tax(
                $product,
                array(
                    'qty' => 1,
                    'price' => $response['price'],
                )
            );
        }

        return $response['price'];
    }

    public function item_adjusted_price($adjusted_price, $cart_item)
    {
        $_price = apply_filters('wcpa_cart_addon_data', false, $cart_item);
        if ($_price !== false) {
            $adjusted_price = $adjusted_price + $_price['excludeFromDiscount'];
        }
        return $adjusted_price;
    }

    public function item_display_price($price, $cart_item)
    {

//        if ( isset( $cart_item['yith_wapo_total_options_price'], $cart_item['yith_wapo_item_price'] ) ) {
//            $price = $cart_item['yith_wapo_item_price'] + $cart_item['yith_wapo_total_options_price'];
//        }
        $_price = apply_filters('wcpa_cart_addon_data', false, $cart_item);
        if ($_price !== false) {
            $price = $price + $_price['excludeFromDiscount'];
        }
        return $price;
    }

    public function update_options_price()
    {
        if (!is_null(WC()->cart)) {
            foreach (WC()->cart->get_cart_contents() as $cart_key => $cart_item) {
                $price = apply_filters('wcpa_cart_addon_data', false, $cart_item);
                if ($price !== false && array_key_exists('ywdpd_discounts', $cart_item)) {
                    $discounted_price = $cart_item['ywdpd_discounts']['price_adjusted'];
                    $cart_item['data']->set_price($discounted_price + $price['excludeFromDiscount']);
                }
            }
        }
    }

    function before_set_product_price_filters($total_price, $excludeDiscountPrice)
    {

        if (defined('YITH_YWDPD_VERSION')) {
            return $total_price - $excludeDiscountPrice;
        }

        return $total_price;
    }

    public function wcpa_cart_apply_discount_on($cart_item)
    {
        if (isset($cart_item['wcpa_price']) && is_array($cart_item['wcpa_price'])) {
            return ($cart_item['wcpa_price']['total'] - $cart_item['wcpa_price']['excludeDiscount']);
        }

        return false;
    }

    public function wcpa_cart_addon_data($data, $cart_item)
    {
        if (isset($cart_item['wcpa_price']) && is_array($cart_item['wcpa_price'])) {
            return [
                'totalPrice' => $cart_item['wcpa_price']['total'],
                'addonPrice' => $cart_item['wcpa_price']['addon'],
                'productPrice' => $cart_item['wcpa_price']['product'],
                'excludeFromDiscount' => $cart_item['wcpa_price']['excludeDiscount']
            ];
        }

        return $data;
    }

    public function flyCartPriceToApplyDiscount(
        $product_price,
        $product,
        $quantity,
        $cart_item,
        $calculate_discount_from
    )
    {
        $price = apply_filters('wcpa_cart_addon_data', false, $cart_item);
        if ($price == false) {
            return $product_price;
        }

        return $price['totalPrice'] - $price['excludeFromDiscount'];
    }

}