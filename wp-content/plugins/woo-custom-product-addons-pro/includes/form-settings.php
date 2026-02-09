<?php

namespace Acowebs\WCPA;

class FormSettings
{
    static $META_KEY = "wcpa_meta_settings_key";
    /**
     *   Form settings key and it is type, default value and if it can be inherited from global config
     * @var array $keys = [
     * 'key'=>[data Type,Default Value,can be Inherited from Global]
     * ]
     */
    private $keys;
    private $post_id;
    /**
     * @var settings values
     */
    private $values = false;

    public function __construct($post_id)
    {
        $this->post_id = $post_id;
        /**
         *   Form settings key and it is type, default value and if it can be inherited from global config
         * @var array $this ->keys = ['key'=>[data Type,Default Value,can be Inherited from Global]
         */
        $this->keys = [
            'layout_option'                      => ['select', 'standard'],
            'disp_use_global'                    => [
                'boolean',
                true,
                [
                    'disp_show_field_price'   => ['boolean', true],
                    'disp_show_section_price' => ['boolean', false],


                    'disp_summ_show_total_price'   => ['boolean', true],
                    'disp_summ_show_product_price' => ['boolean', true],
                    'disp_summ_show_option_price'  => ['boolean', true],
                    'disp_summ_show_fee'           => ['boolean', false],
                    'disp_summ_show_discount'      => ['boolean', false],

                    'disp_hide_options_price' => ['boolean', false],
                ]
            ],  // [type,default value]

            /** keep it for backward support */
            'pric_overide_base_price'            => ['boolean', false],
            'pric_overide_base_price_if_gt_zero' => ['boolean', false],
            'pric_overide_base_price_fully'      => ['boolean', false],

            'price_override'  => ['text', ''], // base_price,base_price_if_gt_zero,base_price_fully

            /** keep it for backward support */
//			'pric_cal_option_once' => [ 'boolean', false ], /** removed this two based on the new option 'process_fee_as' */
            'pric_use_as_fee' => ['boolean', false],

            'process_fee_as' => ['text', 'woo_fee'], // woo_fee/custom */

            'exclude_from_discount' => ['boolean', false],


            'cont_use_global'       => [
                'boolean',
                true,
                [
                    'summary_title'         => ['text', ''],
                    // dont translate strings here, it will be managed at render time
                    'options_total_label'   => ['text', 'Options Price'],
                    // dont translate strings here, it will be managed at render time
                    'options_product_label' => ['text', 'Product Price'],
                    'total_label'           => ['text', 'Total'],
                    'fee_label'             => ['text', 'Fee'],
                    'discount_label'        => ['text', 'Discount'],
                ]
            ],

//            'wcpa_drct_prchsble' => ['boolean', false, false], // this is saved as different meta field
            'enable_recaptcha'      => ['boolean', false],
            'bind_quantity'         => ['boolean', false],
            'quantity_bind_formula' => ['text', ''],
//            'export_data' => ''
        ];
    }

    public function save($postSettings)
    {
        $settings = get_post_meta($this->post_id, self::$META_KEY, true);

        if ( ! is_array($settings)) {
            $settings = array();
        }

        /**
         * Sanitizing values
         */
        foreach ($this->keys as $key => $val) {
            list($type) = $val;
            if ($type == 'text' || $type == 'select') {
                if (isset($postSettings[$key])) {
                    $settings[$key] = sanitize_text_field($postSettings[$key]);
                }
            } elseif ($type == 'boolean') {
                if (isset($postSettings[$key]) && $postSettings[$key]) {
                    $settings[$key] = true;
                } else {
                    $settings[$key] = false;
                }
            }

            if (isset($val[2]) && is_array($val[2])) {
                foreach ($val[2] as $k => $v) {
                    list($t) = $v;
                    if ($t == 'text' || $t == 'select') {
                        if (isset($postSettings[$k])) {
                            $settings[$k] = sanitize_text_field($postSettings[$k]);
                        }
                    } elseif ($t == 'boolean') {
                        if (isset($postSettings[$k]) && $postSettings[$k]) {
                            $settings[$k] = true;
                        } else {
                            $settings[$k] = false;
                        }
                    }
                }
            }
        }
        $settings['wcpa_drct_prchsble'] = isset($postSettings['wcpa_drct_prchsble']) ? $postSettings['wcpa_drct_prchsble'] : false;

        $this->insert($settings);
    }

    public function insert($settings)
    {
        update_post_meta($this->post_id, self::$META_KEY, $settings);
        update_post_meta($this->post_id, 'wcpa_drct_prchsble',
            isset($settings['wcpa_drct_prchsble']) ? $settings['wcpa_drct_prchsble'] : false);
        $this->values = $settings; // just updating this attribute
    }

    /**
     * ML compatibility
     * Merge settings with different languages
     *
     * @param $base_id
     * @param $tran_id
     *
     * @return mixed
     */
    public function merge_settings_with($tran_id)
    {
        $original      = $this->getValues();
        $transSettings = new FormSettings($tran_id);
        $trans         = $transSettings->getValues();

        $translatableOptions = [
            'summary_title'         => 'text',
            'options_total_label'   => 'text',
            'options_product_label' => 'text',
            'total_label'           => 'text',
            'fee_label'             => 'text',
            'discount_label'        => 'text'
        ];

        foreach ($translatableOptions as $k => $v) {
            if (isset($trans[$k]) && ! isEmpty($trans[$k])) {
                $original[$k] = $trans[$k];
            }
        }

        $transSettings->insert($original);
    }

    public function getValues($isBackend = false)
    {
        if ($this->values) {
            return $this->values;
        }
        $this->values = [];
        $settings     = get_post_meta($this->post_id, self::$META_KEY, true);
        if ( ! is_array($settings)) {
            $settings = array();
        }

        if ( ! is_array($settings)) {
            $settings = array();
        }
        foreach ($this->keys as $key => $val) {
            list($type, $default) = $val;
            $value = isset($settings[$key]) ? $settings[$key] : $default;
            if ($type == 'boolean') {
                $value = metaToBoolean($value);
            }
            $this->values[$key] = $value;
            if (isset($val[2]) && is_array($val[2])) {
                foreach ($val[2] as $k => $v) {
                    list($t, $d) = $v;
                    if ($this->values[$key] && ! $isBackend) { // if use global value has  set TRUE
                        $value = Config::get_config($k, $d,true);
                        if ($t == 'boolean') {
                            $value = metaToBoolean($value);
                        }
                        $this->values[$k] = $value;
                    } else {
                        $value = isset($settings[$k]) ? $settings[$k] : $d;
                        if ($t == 'boolean') {
                            $value = metaToBoolean($value);
                        }
                        $this->values[$k] = $value;
                    }
                }
            }
        }

        if ( ! isset($this->values['process_fee_as'])) {
            /** version migration */
            if ($this->values['pric_use_as_fee']) {
                $this->values['process_fee_as'] = 'woo_fee';
            } elseif ($this->values['pric_cal_option_once']) {
                $this->values['pric_use_as_fee'] = true;
                $this->values['process_fee_as']  = 'custom';
            }
        }


        if ( ! isset($this->values['price_override'])) {
            /** version migration */
            if ($this->values['pric_overide_base_price']) {
                $this->values['price_override'] = 'maximum';
            } elseif ($this->values['pric_overide_base_price_if_gt_zero']) {
                $this->values['price_override'] = 'if_gt_zero';
            } elseif ($settings['pric_overide_base_price_fully']) {
                $this->values['price_override'] = 'always';
            } else {
                $this->values['price_override'] = '';
            }
        }


        $this->values['wcpa_drct_prchsble'] = get_post_meta($this->post_id, 'wcpa_drct_prchsble', true);

        if ( ! $isBackend) {
            if (Config::get_config('captcha_for_all_forms')) {
                $this->values['enable_recaptcha'] = true;
            }
        }

        return $this->values;
    }

    public function get($key)
    {
        $values = $this->getValues();

        return (isset($values[$key]) ? $values[$key] : false);
    }
}