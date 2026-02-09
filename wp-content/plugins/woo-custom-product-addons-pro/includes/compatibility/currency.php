<?php

namespace Acowebs\WCPA;


if ( ! defined('ABSPATH')) {
    exit;
}

class Currency
{
    public function __construct()
    {
        add_filter('wcpa_currency_conversion_unit', array($this, 'multi_currency_filters'), 10, 3);
        add_filter('wcpa_currency_converted_product_price', array($this, 'converted_product_price'), 10, 3);
        add_filter('wcpa_currency_before_set_product_price', array($this, 'before_set_product_price'), 10, 3);
        if (defined('WCML_VERSION')) {
            new WPMLCurrency();
        }
    }

    /**
     *Return product price converted
     *
     * @param $product
     *
     * @return float
     */
    static function getProductPrice($price, $product)
    {
        $price = apply_filters('wcpa_currency_converted_product_price', $price, $product);

        return floatval($price);
    }

    /** Some Multi currency plugins convert the price set in cart again. some doesnt convert it again
     * So we have to return the price based on the typ
     *
     * @param $priceConverted
     * @param $originalPrice
     *
     * @return mixed|void
     */
    static function cartSetPrice($priceConverted, $originalPrice,$priceNoMC=0.0)
    {
        return apply_filters('wcpa_currency_before_set_product_price', $priceConverted, $originalPrice,$priceNoMC);
    }
//
//    /** some currency convert plugin will not take care the conversion automatically always
//     * in such case it can handle based on the currency converter plugin used
//     *
//     * @param $price
//     * @param  bool  $section
//     *
//     * @return mixed|void
//     */
//    static function mayBeConvert($price, $section = false)
//    {
//        $unit = self::getConUnit(true, $section === 'add_fee');
//
//        return $price * $unit;
//
////        return $price;
//        $from_currency = get_option('woocommerce_currency');
//        $to_currency   = get_woocommerce_currency();
//
//        $price = apply_filters('wc_aelia_cs_convert', $price, $from_currency, $to_currency);
//        if (function_exists('wcpbc_the_zone') && ! is_bool(wcpbc_the_zone())) {
//            $wcpbc = wcpbc_the_zone();
//            if (is_callable($wcpbc, 'get_exchange_rate_price') || method_exists($wcpbc, 'get_exchange_rate_price')) {
//                $price = $wcpbc->get_exchange_rate_price($price);
//            }
//        }
//
//        if ($section == 'add_fee') {
//            global $WOOCS;
//            if (function_exists('wmc_get_price')) {
//                $price = wmc_get_price($price);
//            } elseif ($WOOCS !== null) {
//                /** https://wordpress.org/plugins/woocommerce-currency-switcher */
//                if (method_exists($WOOCS, 'woocs_exchange_value')) {
//                    $price = $WOOCS->woocs_exchange_value($price);
//                }
//            }
//        }
//
//
//        return $price;
//    }

    static function convertCurrency($price, $isCart = false, $isFee = false)
    {
        $converted = apply_filters('wcpa_convert_currency', false, $price, $isCart, $isFee);
        if ($converted === null || $converted === false) {
            $conversion_unit = self::getConUnit($isCart, $isFee);

            return $conversion_unit * $price;
        }

        return $converted;
    }

    static function getConUnit($isCart = false, $isFee = false)
    {
        /** avoid using product price ratio as conversion unit. We can implement each currency switcher conversion unit specifically
         * taking product price ration can cause issue when discounts applied
         */

        $conversion_unit = apply_filters('wcpa_currency_conversion_unit', false, $isCart, $isFee);

        if ($conversion_unit === false) {
            return 1;
        }

        return $conversion_unit;
    }

    public function before_set_product_price($priceConverted, $originalPrice,$priceNoMC=0.0)
    {
        if($priceNoMC>0){
            $convertToDefault = $priceNoMC/self::getConUnit(true);
        }else{
            $convertToDefault = 0.0;
        }
        if (class_exists('\ACOWCS_Public')) {
            /** acowcs currency convertor support */
            return $originalPrice-$priceNoMC+$convertToDefault;
        }
        if (class_exists('WOOCS_STARTER')) {
            /** https://wordpress.org/plugins/woocommerce-currency-switcher */
            return $originalPrice-$priceNoMC+$convertToDefault;
        }
        if (function_exists('wmc_get_price')) {
            return $originalPrice-$priceNoMC+$convertToDefault;
        }

        if (class_exists('\Yay_Currency\WooCommerceCurrency')) {
            return $originalPrice-$priceNoMC+$convertToDefault;
        }
        if (class_exists('\Yay_Currency\Helpers\YayCurrencyHelper')) {
            return $originalPrice-$priceNoMC+$convertToDefault;
        }
        if (class_exists('WCPay\MultiCurrency\Currency')) {
            return $originalPrice-$priceNoMC+$convertToDefault;
        }
        return $priceConverted;
    }

    public function converted_product_price($price, $product)
    {
        if (class_exists('\ACOWCS_Public')) {
            /** acowcs currency convertor support */
            $acowcs = \ACOWCS_Public();
            $calc_value = floatval($acowcs->acowcs_exchange_value($price));
            return $calc_value;
        }

        if (class_exists('WOOCS_STARTER')) {
            /** https://wordpress.org/plugins/woocommerce-currency-switcher */
            $calc_value = apply_filters('woocs_convert_price', $price, false);

            return $calc_value;
        }
        if (function_exists('wmc_get_price')) {
            return wmc_get_price($price);
        }


        if (class_exists('\Yay_Currency\WooCommerceCurrency')) {
            $yay_currency = \Yay_Currency\WooCommerceCurrency::getInstance();

            return $yay_currency->calculate_price_by_currency($price);
        }

        if (class_exists('\Yay_Currency\Helpers\YayCurrencyHelper')) {
            $converted_currency = \Yay_Currency\Helpers\YayCurrencyHelper::converted_currency();
            $apply_currency     = \Yay_Currency\Helpers\YayCurrencyHelper::get_apply_currency($converted_currency);

            return \Yay_Currency\Helpers\YayCurrencyHelper::calculate_price_by_currency($price,false,$apply_currency);
        }

        return $price;
    }

    public function multi_currency_filters($conversion_unit, $isCart, $isFee)
    {
        if ($conversion_unit === false && class_exists('\ACOWCS_Public')) {
            /** acowcs currency convertor support */
            $acowcs = \ACOWCS_Public();
            $conversion_unit = floatval($acowcs->acowcs_exchange_value(10000) / 10000);
        }

        if ($conversion_unit === false && has_filter('wcml_raw_price_amount')) {
            /** wpml currency convertor support */
            if (has_filter('wcml_raw_price_amount')) {
                $conversion_unit = floatval(apply_filters('wcml_raw_price_amount', 100000) / 100000);
            }
        }

        if ($conversion_unit === false && has_filter('wc_aelia_cs_convert')) {
            /**  Aelia Currency Switcher*/
            $from_currency = get_option('woocommerce_currency');
            $to_currency   = get_woocommerce_currency();
            // Updated decimal point length due to trim of the unit price
            $conversion_unit = apply_filters('wc_aelia_cs_convert', 1, $from_currency, $to_currency, 10);
            /** 10 for decimals to return */
        }

        /* Price Based on Country for WooCommerce */
        if ($conversion_unit === false && function_exists('wcpbc_the_zone') && ! is_bool(wcpbc_the_zone())) {
            $wcpbc            = wcpbc_the_zone();
            $converted_amount = 1;
            if (is_callable($wcpbc, 'get_exchange_rate_price') || method_exists($wcpbc,
                    'get_exchange_rate_price')) {
                $converted_amount = $wcpbc->get_exchange_rate_price(1);
            }
            $conversion_unit = $converted_amount;
        }
        if ($conversion_unit === false && has_filter('woocs_convert_price')) {
            $conversion_unit = apply_filters('woocs_convert_price', 10000, false);
            $conversion_unit = floatval($conversion_unit) / 10000;
//            global $WOOCS;
//            if ($WOOCS !== null) {
//                /** https://wordpress.org/plugins/woocommerce-currency-switcher */
//                if (method_exists($WOOCS, 'woocs_exchange_value')) {
//                    $res             = $WOOCS->woocs_exchange_value(1);
//                    $conversion_unit = $res;
//                }
//            }
        }
        if ($conversion_unit === false) {
            /** https://wordpress.org/plugins/woo-multi-currency/ */
            if (function_exists('wmc_get_price')) {
                $conversion_unit = wmc_get_price(10000);
                $conversion_unit = floatval($conversion_unit) / 10000;
            }
        }
        if ($conversion_unit === false && class_exists('\Yay_Currency\WooCommerceCurrency')) {
            $yay_currency    = \Yay_Currency\WooCommerceCurrency::getInstance();
            $conversion_unit = $yay_currency->calculate_price_by_currency(10000);
            $conversion_unit = floatval($conversion_unit) / 10000;
        }

        if ($conversion_unit === false && class_exists('\Yay_Currency\Helpers\YayCurrencyHelper')) {
            $converted_currency = \Yay_Currency\Helpers\YayCurrencyHelper::converted_currency();
            $apply_currency     = \Yay_Currency\Helpers\YayCurrencyHelper::get_apply_currency($converted_currency);
            if (isset($apply_currency['rate'])) {
                return floatval($apply_currency['rate']);
            }
        }

        if(function_exists('WC_Payments_Multi_Currency')){
            $wc_pay = WC_Payments_Multi_Currency();

            $currency        = $wc_pay->get_selected_currency();
            $conversion_unit =  $currency->get_rate();
        }

        return $conversion_unit;
    }
}