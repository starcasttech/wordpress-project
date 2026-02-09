<?php

namespace Acowebs\WCPA;

if ( ! defined('ABSPATH')) {
    exit;
}

class WPMLCurrency
{

    /**
     * @var mixed
     */
    private $currency_options;
    /**
     * @var string
     */
    private $currency;

    public function __construct()
    {
        add_action('wp_footer', array($this, 'scripts'), 98);
        add_filter('wcpa_convert_currency', array($this, 'convertCurrency'), 10, 2);
    }

    public function convertCurrency($status, $price)
    {
        global $woocommerce_wpml;
        if (method_exists($woocommerce_wpml, 'get_setting')) {
            return apply_filters('wcml_raw_price_amount', $price);
        }

        return $price;
    }

    public function scripts()
    {
        global $woocommerce_wpml;
        if (method_exists($woocommerce_wpml, 'get_setting')) {
            $currency_options = $woocommerce_wpml->get_setting('currency_options');
            $currency         = get_woocommerce_currency();
            if ($currency && isset($currency_options[$currency])) {
                $this->currency_options = $currency_options[$currency];
                $this->currency         = $currency;
                if (isset($this->currency_options['rounding']) && $this->currency_options['rounding'] !== "disabled") {
                    $this->renderScript();
                }
            }
        }
    }

    public function renderScript()
    {
        ?>
        <script>
            window.wcpaWPMlCurrencyOptions = <?php echo json_encode($this->currency_options); ?>;

            if (typeof wp !== 'undefined' && wp.hooks) {
                wp.hooks.addFilter('wcpa_convert_currency', 'wcpa', (status, price) => {
                    price = window.wcpa_front.mc_unit * price;
                    if (window.wcpaWPMlCurrencyOptions.rounding_increment > 1) {
                        price = price / window.wcpaWPMlCurrencyOptions.rounding_increment;
                    }
                    var rounded_price;
                    switch (window.wcpaWPMlCurrencyOptions.rounding) {
                        case 'up':
                            rounded_price = Math.ceil(price);
                            break;
                        case 'down':
                            rounded_price = Math.floor(price);
                            break;
                        case 'nearest':
                        default:

                            if (price - Math.floor(price) < 0.5) {
                                rounded_price = Math.floor(price);
                            } else {
                                rounded_price = Math.ceil(price);
                            }
                            break;
                    }

                    if (rounded_price > 0) {
                        price = rounded_price;
                    }

                    if (window.wcpaWPMlCurrencyOptions.rounding_increment > 1) {
                        price = price * window.wcpaWPMlCurrencyOptions.rounding_increment;
                    }

                    if (price * window.wcpaWPMlCurrencyOptions.auto_subtract && window.wcpaWPMlCurrencyOptions.auto_subtract < price) {
                        price = price - window.wcpaWPMlCurrencyOptions.auto_subtract;
                    }
                    return price
                }, 99);
                document.dispatchEvent(new Event("wcpaTrigger", {bubbles: true}));
            }


        </script>
        <?php
    }

}