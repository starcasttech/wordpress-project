<?php


namespace Acowebs\WCPA;

class MetaDisplay
{
    public $config;
    /**
     * @var bool
     */
    private $show_price;

    private $isCart;
    private $priceMultiplier;
    private $quantityMultiplier;

    private $discountUnit;
    /**
     * @var int|mixed
     */
    private $_priceMultiplier;
    private $currency=false;
    public function __construct(
        $isCart,
        $show_price = true,
        $priceMultiplier = 1,
        $quantityMultiplier = 1,
        $discountUnit = 1,
        $currency=false
    ) {
        $this->config = [
            'show_meta_in_cart'           => Config::get_config('show_meta_in_cart'),
            'show_meta_in_checkout'       => Config::get_config('show_meta_in_cart'),
            'cart_hide_price_zero'        => Config::get_config('cart_hide_price_zero'),
            'show_price_in_cart'          => Config::get_config('show_price_in_cart'),
            'show_price_in_checkout'      => Config::get_config('show_price_in_checkout'),
            'show_field_price_x_quantity' => Config::get_config('show_field_price_x_quantity'),
        ];

        $this->show_price       = $show_price;
        $this->isCart           = $isCart;
        $this->discountUnit     = $discountUnit;
        $this->_priceMultiplier = $priceMultiplier;
        $this->priceMultiplier  = $this->_priceMultiplier * $quantityMultiplier;

        $this->currency = $currency;
    }

    public function setQuantityMultiplier($quantityMultiplier)
    {
        $this->priceMultiplier = $this->_priceMultiplier * $quantityMultiplier;
    }

    public function setDiscountUnit($discountUnit)
    {
        $this->discountUnit = $discountUnit;
    }

    public function display($field, $formRules = false, $value = false,$returnValue=false)
    {
        $showPriceHere = true;

        $value   = $value == false ? $field['value'] : $value;
        $display = $value;


        switch ($field['type']) {
            case 'date':
            case 'datetime-local':

                if ($value !== '') {
                    $format = isset($field['dateFormat']) ? $field['dateFormat'] : false;
                    if (is_array($value)) {
                        if (isset($value['start'])) {
                            $display = formattedDate($value['start'], $format).
                                       __(' to ', 'woo-custom-product-addons-pro').
                                       formattedDate($value['end'], $format);
                        } else {
                            $display = '';
                            foreach ($value as $dt) {
                                $display .= formattedDate($dt, $format).', ';
                            }
                            $display = trim($display, ',');
                        }
                    } else {
                        $display = formattedDate($value, $format);
                    }
                    //TODO render range and multi date picker
                }
                break;
            case 'time':
                if ($value !== '') {
                    $format = isset($field['dateFormat']) ? $field['dateFormat'] : false;
                    $display = formattedDate($value, $format);
                }

                break;
            case 'content':
                $display = do_shortcode(nl2br($value));
                break;
            case 'textarea':
                $display = nl2br($value);
                break;
            case 'color':
                $display = '<span  style="color:'.$value.';font-size: 20px; padding: 0;line-height: 0;">&#9632;</span>'.$value;
                break;
            case 'file':
                $display = $this->file($field);
                break;
            case 'placeselector':
                $display = $this->place($field);
                break;
            case 'select':
            case 'checkbox-group':
            case 'radio-group':
                $showPriceHere = false;

                $display = $this->group($field,$returnValue);
                break;
            case 'image-group':
            case 'productGroup':
                $display       = $this->image($field);
                $showPriceHere = false;

                break;
//            case 'productGroup':
//                $display       = $this->image($field);
//                $showPriceHere = false;
//                break;
            case 'color-group':
                $display       = $this->colorGroup($field);
                $showPriceHere = false;
                break;
        }
        if ($showPriceHere && (isset($field['quantity']) && $field['quantity'] > 1)) {
            $display .= ' <span class="wcpa_cart_addon_quantity"><i>x</i> '.$field['quantity'].'</span> ';
        }
        if ($showPriceHere) {
            if (!is_array($field['price']) && $field['price'] && $this->show_price && ( ! $this->config['cart_hide_price_zero'] || $field['price'] != 0)) {
                $price   = $this->priceMultiplier * $field['price'];
                $display = $display.' <span class="wcpa_cart_price">('.wcpaPrice($price * $this->discountUnit,
                        $price,0,['currency'=>$this->currency]).')</span>';
            }
        }


        if ($display == '') {
            $display = '&nbsp;';
        }


        return $display;
        //TODO add this hook
//        return apply_filters(
//            'wcpa_display_cart_value',
//            '<div class="wcpa_cart_val wcpa_cart_type_' . $field['type'] . '" >' . $display . '</div>',
//            $display,
//            $field
//        );
    }

    public function file($field)
    {
        $display   = '';
        $hideImage = $this->hideImage($field);

        $value = $field['value'];
        if (is_array($value)) {
            foreach ($value as $val) {
                if (isset($val['url'])) {
                    $display .= '<a href="'.$val['url'].'"  target="_blank" download="'.$val['file_name'].'">';
                    if ( ! $hideImage && in_array(
                            $val['type'],
                            array(
                                'image/jpg',
                                'image/png',
                                'image/gif',
                                'image/jpeg',
                            )
                        )) {
                        $display .= '<img class="wcpa_img" style="max-width:75px"  src="'.$val['url'].'" />';
                    } else if(!$hideImage){
                        $display .= '<img class="wcpa_icon" style="max-width:75px" src="'.wp_mime_type_icon($val['type']).'" />';
                    }
                    $display .= '<span>'.$val['file_name'].'</span></a>';
                }
            }
        }

        return $display;
    }

    public function hideImage($field)
    {
        if ($this->isCart && isset($field['form_data']->hideImageIn_cart) && $field['form_data']->hideImageIn_cart) {
            return true;
        }
        if ($this->isCart == false) {
            if (is_wc_endpoint_url() && isset($field['form_data']->hideImageIn_order) && $field['form_data']->hideImageIn_order) {
                return true;
            }
            if ( ! is_wc_endpoint_url() && isset($field['form_data']->hideImageIn_email) && $field['form_data']->hideImageIn_email) {
                return true;
            }
        }

        return false;
    }

    public function place($field)
    {
        $display = '';
        $strings = [
            'street'    => Config::get_config('place_selector_street'),
            'city'      => Config::get_config('place_selector_city'),
            'state'     => Config::get_config('place_selector_state'),
            'zip'       => Config::get_config('place_selector_zip'),
            'country'   => Config::get_config('place_selector_country'),
            'latitude'  => Config::get_config('place_selector_latitude'),
            'longitude' => Config::get_config('place_selector_longitude'),
        ];
        $value   = $field['value'];
        if ( ! empty($value['value'])) {
            $display = $value['value'].'<br>';
            if ( ! empty($value['split']['street_number'])) {
                $display .= $strings['street'].' '.$value['split']['street_number'].' '.$value['split']['route'].' <br>';
            }
            if ( ! empty($value['split']['locality'])) {
                $display .= $strings['city'].' '.$value['split']['locality'].'<br>';
            }
            if ( ! empty($value['split']['administrative_area_level_1'])) {
                $display .= $strings['state'].' '.$value['split']['administrative_area_level_1'].'<br>';
            }
            if ( ! empty($value['split']['postal_code'])) {
                $display .= $strings['zip'].' '.$value['split']['postal_code'].'<br>';
            }
            if ( ! empty($value['split']['country'])) {
                $display .= $strings['country'].' '.$value['split']['country'].'<br>';
            }
            if (isset($value['cords']['lat']) && ! empty($value['cords']['lat'])) {
                $display .= $strings['latitude'].' '.$value['cords']['lat'].'<br>';
                $display .= $strings['longitude'].' '.$value['cords']['lng'].'<br>';
                $display .= '<a href="https://www.google.com/maps/?q='.$value['cords']['lat'].','.$value['cords']['lng'].'" target="_blank">'.__(
                        'View on map',
                        'woo-custom-product-addons-pro'
                    ).'</a> <br>';
                //TODO view on map text field in settings
            }
        }

        return $display;
    }

    public function group($value,$returnValue=false)
    {
        $display   = '';
        $hide_zero = $this->config['cart_hide_price_zero'];

        if (is_array($value['value'])) {
            foreach ($value['value'] as $k => $v) {
                if ($k === 'other') {
                    //Other text has to apply i18n
                    $display .= '<span>'.$v['label'].': '.$v['value'].'</span>';
                } else {
                    //Label no need to apply i18n.
                    if(is_string($v)){
                        /** free version data */
                        $display .= '<span>'.$v.' </span>';
                    }else{
                        $display .= '<span>'.($returnValue?$v['value']:$v['label']).' </span>';
                    }

                }
                if ((isset($v['quantity']) && ! empty($v['quantity']))) {
                    $display .= ' <span class="wcpa_cart_addon_quantity"><i>x</i> '.$v['quantity'].'</span> ';
                }
                if ($value['price'] !== false && is_array($value['price']) && $this->show_price) {
                    if (isset($value['price'][$k]) && $value['price'][$k] !== false && ( ! $hide_zero || $value['price'][$k] != 0)) {
                        $price = $this->priceMultiplier * $value['price'][$k];

                        $display .= '<span class="wcpa_cart_price">('.wcpaPrice($price * $this->discountUnit,
                                $price,0,['currency'=>$this->currency]).')</span>';
                    }
                } else {
                    if ($value['price'] !== false && $this->show_price && ( ! $hide_zero || $value['price'] != 0)) {
//                        $price   = $value['price'] * $this->priceMultiplier;
                        $price   = $this->priceMultiplier * $value['price'];
                        $display .= ' <span class="wcpa_cart_price">('.wcpaPrice($price * $this->discountUnit,
                                $price,0,['currency'=>$this->currency]).')</span>';
                    }
                }
                $display .= '<br />';
            }
        } else {
            $display = $value['value'];
            if ($value['price'] && $this->show_price && ( ! $hide_zero || $value['price'] != 0)) {
//                $price   = $value['price'] * $this->priceMultiplier;
                $price   = $this->priceMultiplier * $value['price'];
                $display = $display.' <span class="wcpa_cart_price">('.wcpaPrice($price * $this->discountUnit,
                        $price,0,['currency'=>$this->currency]).')</span>';
            }
        }

        return $display;
    }

    public function image($field)
    {
        $display   = '';
        $class     = '';
        $hide_zero = $this->config['cart_hide_price_zero'];

        if (isset($field['form_data']->img_preview) && $field['form_data']->img_preview) {
            $class = 'class="wcpa_cart_img_preview ';
            if (isset($field['form_data']->img_preview_disable_mobile) && $field['form_data']->img_preview_disable_mobile) {
                $class .= 'wcpa_product_img_preview_disable_mobile ';
            }
            $class .= '"';
        }
        $hideImage = $this->hideImage($field);
        if ($field['type'] == 'productGroup' && ( ! isset($field['form_data']->show_image) || $field['form_data']->show_image == false)) {
            /** for productGroup, if show image is false, hideImage */
            $hideImage = true;
        }
        $value = $field['value'];
        if (is_array($value)) {
            if ($field['type'] == 'productGroup') {
                if (isset($value[0]) && is_object($value[0])) {
                    //convert older version productGroup object as array
                    $value = array_map(function ($prdct) {
                        return [
                            'label' => $prdct->get_title(),
                            'value' => $prdct->get_id(),
                            'image' => ''
                        ];
                    }, $value);
                }
            }
            foreach ($value as $k => $v) {
                if ($k === 'other') {
                    //TODO need to check other label
                    $display .= '<p>'.$v['label'].': '.$v['value'].'';
                } else {
                    $img_size_style = ((isset($field['form_data']->disp_size_img) && $field['form_data']->disp_size_img->width > 0) ? 'style="width:'.$field['form_data']->disp_size_img->width.'px"' : '');

                    $display .= '<p '.$class.'>'.(! $hideImage ? '<img '.$img_size_style.' data-src="'.$v['image'].'" src="'.$v['image'].'" />' : '');
                    if ( ! empty($v['label'])) {
                        $display .= ' <span >'.$v['label'].'</span> ';

                        if ((isset($v['quantity']) && ! empty($v['quantity']))) {
                            $display .= ' <span class="wcpa_cart_addon_quantity"><i>x</i> '.$v['quantity'].'</span> ';
                        }
                    }
                }

                if ($field['price'] && is_array($field['price']) && $this->show_price) {
                    if (isset($field['price'][$k]) && $field['price'][$k] !== false && ( ! $hide_zero || $field['price'][$k] != 0)) {
//                        $price   = $field['price'][$k] * $this->priceMultiplier;
                        $price = $this->priceMultiplier * $field['price'][$k];

                        $display .= '<span class="wcpa_cart_price">('.wcpaPrice($price * $this->discountUnit,
                                $price,0,['currency'=>$this->currency]).')</span>';
                    }
                } else {
                    if ($field['price'] !== false && $this->show_price && ( ! $hide_zero || $field['price'] != 0)) {
                        $price   = $field['price'] * $this->priceMultiplier;
                        $display .= ' <span class="wcpa_cart_price">('.wcpaPrice($price * $this->discountUnit,
                                $price,0,['currency'=>$this->currency]).')</span>';
                    }
                }

                $display .= '</p>';
            }
        } else {
            $display = $value;

            if ($field['price'] && $this->show_price && ( ! $hide_zero || $field['price'] != 0)) {
//                $price   = $field['price'] * $this->priceMultiplier;
                $price   = $field['price'] * $this->priceMultiplier;
                $display = $display.' <span class="wcpa_cart_price">('.wcpaPrice($price * $this->discountUnit,
                        $price,0,['currency'=>$this->currency]).')</span>';
            }
        }

        return $display;
    }

    public function colorGroup($field)
    {
        $display = '';
        if (isset($field['form_data']->disp_size) && isset($field['form_data']->disp_size['width'])  && (
                ! isset($field['form_data']->disp_size['height']) || $field['form_data']->disp_size['height'] == '')) {
            $field['form_data']->disp_size['height'] = $field['form_data']->disp_size['width'];
        }
        $value = $field['value'];

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if ($k === 'other') {
                    $display .= '<p>'.$v['label'].': '.$v['value'].'';
                } else {
                    $display .= '<p>';
                    $size    = '';
                    if (!$this->isCart || (isset($field['form_data']->cart_display_type) && $field['form_data']->cart_display_type == 'text')) {
                        $display .= '<span style="color:'.$v['color'].';font-size: 20px;padding: 0;line-height: 0;">&#9632;</span>'.(! isEmpty(
                                $v['label']
                            ) ? $v['label'] : $v['value']).'  ';
                    } else {
                        if (isset($field['form_data']->disp_size) && $field['form_data']->disp_size['width'] > 10) {
                            $size .= 'height:'.$field['form_data']->disp_size['height'].'px;';
                            if (isset($field['form_data']->show_label_inside) && $field['form_data']->show_label_inside) {
                                $size .= 'min-width:'.$field['form_data']->disp_size['width'].'px;line-height:'.($field['form_data']->disp_size['height'] - 2).'px;';
                            } else {
                                $size .= 'width:'.$field['form_data']->disp_size['width'].'px;';
                            }
                        }

                        if (isset($field['form_data']->show_label_inside) && $field['form_data']->show_label_inside) {
                            $display .= '<span class="wcpa_cart_color label_inside disp_'.$field['form_data']->disp_type.' '.colorClass(
                                    $v['color']
                                ).' '.((isset($field['form_data']->adjust_width) && $field['form_data']->adjust_width) ? 'wcpa_adjustwidth' : '').'"'
                                        .' style="background-color:'.$v['color'].';'.$size.'" >'
                                        .''.$v['label'].'</span>';
                        } else {
                            $display .= '<span class="wcpa_cart_color disp_'.$field['form_data']->disp_type.' '.colorClass(
                                    $v['color']
                                ).' '.((isset($field['form_data']->adjust_width) && $field['form_data']->adjust_width) ? 'wcpa_adjustwidth' : '').'"'
                                        .' style="background-color:'.$v['color'].';'.$size.'" ></span>';
                            if ( ! empty($v['label'])) {
                                $display .= ' <span >'.$v['label'].'</span> ';
                            }
                        }
                    }
                }
                if ((isset($v['quantity']) && ! empty($v['quantity']))) {
                    $display .= ' <span class="wcpa_cart_addon_quantity"><i>x</i> '.$v['quantity'].'</span> ';
                }
                if ($field['price'] && is_array(
                        $field['price']
                    ) && $this->show_price && ( ! $this->config['cart_hide_price_zero'] || $field['price'] != 0)) {
                    if (isset($field['price'][$k]) && $field['price'][$k] !== false) {
                        $price   = $field['price'][$k] * $this->priceMultiplier;
                        $display .= '<span class="wcpa_cart_price">('.wcpaPrice($price * $this->discountUnit,
                                $price,0,['currency'=>$this->currency]).')</span>';
                    }
                } else {
                    if ($field['price'] !== false && $this->show_price && ( ! $this->config['cart_hide_price_zero'] || $field['price'] != 0)) {
                        $price   = $field['price'] * $this->priceMultiplier;
                        $display .= ' <span class="wcpa_cart_price">('.wcpaPrice($price * $this->discountUnit,
                                $price,0,['currency'=>$this->currency]).')</span>';
                    }
                }

                $display .= '</p>';
            }
        } else {
            $display = $value;
            if ($field['price'] && $this->show_price && ( ! $this->config['cart_hide_price_zero'] || $field['price'] != 0)) {
                $price   = $value['price'] * $this->priceMultiplier;
                $display = $display.' <span class="wcpa_cart_price">('.wcpaPrice($price * $this->discountUnit,
                        $price,0,['currency'=>$this->currency]).')</span>';
            }
        }

        return $display;
    }

    public function productGroup($value, $field_price_multiplier = 1)
    {
    }
}