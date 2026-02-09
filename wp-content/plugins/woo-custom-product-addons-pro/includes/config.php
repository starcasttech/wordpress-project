<?php

namespace Acowebs\WCPA;

class Config
{

    static $key;
    static $values = false;


    public function __construct()
    {
        self::$key = Settings::$key;
    }

    static function getValidationMessage($field, $key)
    {
        if (isset($field->{requiredError}) && ! empty($field->{requiredError})) {
            return $field->{requiredError};
        }
        $validationMessages = self::get_config('wcpa_validation_strings');
        if (isset($validationMessages['validation_'.$key]) && ! empty($validationMessages['validation_'.$key])) {
            return $validationMessages['validation_'.$key];
        }

        return '';
    }

    static function get_config($option, $default = false, $translate = false)
    {
        if (self::$values == false) {
            $cacheKey = 'wcpa_settings_'.WCPA_VERSION;
            $ml = new ML();
            if($ml->is_active()){
                $cacheKey = $cacheKey.'_'.$ml->current_language();
            }
            $values = get_transient($cacheKey);
            if (false === $values) {
                $settings                = new Settings();
                $design                  = new Designs();
                $values                  = $settings->get_settings();
                $values['active_design'] = $design->get_active_design();
//                wp_cache_set($cacheKey, $values, '', time() + 3600 * 24 * 7);
                set_transient($cacheKey, $values);
            }
            self::$values = $values;
        }
        $values = self::$values;

       
        $values   = apply_filters('wcpa_configurations', $values);
        $response = isset($values[$option]) ? $values[$option] : $default;
        if ($translate) {
            if (function_exists('pll__')) {
                return pll__($response);
            } else {
                return __($response, 'woo-custom-product-addons-pro');
            }
        }

        return $response;
    }

    /**
     * @param $productId
     * @param  bool  $withPrefix  - using to remove prefix for product meta backend
     * @param  false  $excludeGlobal  - using for product meta backend
     *
     * @return mixed
     */

    static function getWcpaCustomFieldByProduct($productId, $withPrefix = true, $excludeGlobal = false)
    {
        $cf_prefix     = self::get_config('wcpa_cf_prefix', 'wcpa_pcf_');
        $custom_fields = Config::get_config('product_custom_fields', []);
        $product_cfs   = array();
        if (is_array($custom_fields)) {
            foreach ($custom_fields as $cf) {
                $meta = get_post_meta($productId, $cf_prefix.$cf['name'], true);
                if ($meta) {
                    $product_cfs[$withPrefix ? 'wcpa_pcf_'.$cf['name'] : $cf['name']] = $meta;
                } elseif ($excludeGlobal == false) {
                    $product_cfs[$withPrefix ? 'wcpa_pcf_'.$cf['name'] : $cf['name']] = $cf['value'];
                }
            }
        }
//        $product_data['custom_fields'] = $product_cfs;

        return $product_cfs;
    }

    static function getWcpaCustomField($key, $productId)
    {
        $cf_prefix = self::get_config('wcpa_cf_prefix', 'wcpa_pcf_');

        $product_cfs = array();
        $meta        = get_post_meta($productId, $cf_prefix.$key, true);
        if ($meta) {
            return $meta;
        }
        //find default value if it has not set for product
        $product_custom_fields = self::get_config('product_custom_fields');
        if (is_array($product_custom_fields)) {
            foreach ($product_custom_fields as $cf) {
                if ($key === $cf['name']) {
                    return $cf['value'];
                }
            }
        }

        return false;
    }
}
