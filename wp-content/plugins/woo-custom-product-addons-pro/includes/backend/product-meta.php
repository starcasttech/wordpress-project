<?php

namespace Acowebs\WCPA;


class Product_Meta
{

    static $fieldKey = '_wcpa_product_meta';
    static $orderKey = 'wcpa_product_meta_order';
    private static $_instance = null;

    public function __construct()
    {
        add_filter('woocommerce_product_data_tabs', array($this, 'add_my_custom_product_data_tab'), 101, 1);
        add_action('woocommerce_product_data_panels', array($this, 'add_my_custom_product_data_fields'));
        add_action('woocommerce_process_product_meta', array(
            $this,
            'woocommerce_process_product_meta_fields_save'
        ));

        /** show forms assigned to a product in the product list (backend) */
        add_filter('manage_product_posts_columns', array($this, 'manage_products_columns'), 20, 1);
        add_action('manage_product_posts_custom_column', array($this, 'manage_products_column'), 10, 2);

        /** include wcpa form ids in product export csv */
        add_filter("woocommerce_product_export_product_default_columns", array($this, 'export_product_default_columns'),
            10, 1);
        add_filter("woocommerce_product_export_product_column_wcpa_forms",
            array($this, 'export_product_column_wcpa_forms'), 10, 3);

        add_filter("woocommerce_csv_product_import_mapping_default_columns",
            array($this, 'import_mapping_default_columns'), 10, 1);
        add_filter("woocommerce_csv_product_import_mapping_options", array($this, 'product_import_mapping_options'), 10,
            2);
        add_filter("woocommerce_product_importer_pre_expand_data", array($this, 'product_importer_pre_expand_data'), 10,
            1);
    }

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function product_import_mapping_options($options, $item)
    {
        $options['wcpa_forms'] = __('WCPA Forms', 'woo-custom-product-addons-pro');

        return $options;
    }

    public function import_mapping_default_columns($cols = array())
    {
        $cols[__('WCPA Forms', 'woo-custom-product-addons-pro')] = 'wcpa_forms';

        return $cols;
    }

    public function product_importer_pre_expand_data($data)
    {
        // Images field maps to image and gallery id fields.
        if (isset($data['wcpa_forms'])) {
            $forms = explode(',', $data['wcpa_forms']);
            $meta_field = array();
            foreach ($forms as $form_id) {
                $form_id = (int)sanitize_text_field($form_id);
                $meta_field[] = $form_id;
            }
            unset($data['wcpa_forms']);
            $data['meta:' . WCPA_PRODUCT_META_KEY] = $meta_field;
        }

        return $data;
    }

    public function export_product_default_columns($cols = array())
    {
        $cols['wcpa_forms'] = __('WCPA Forms', 'woo-custom-product-addons-pro');

        return $cols;
    }

    public function export_product_column_wcpa_forms($value, $product, $col_id)
    {

        $pro_id = $product->get_parent_id();
        if ($pro_id == 0) {
            $pro_id = $product->get_id();
        }
        $meta_fields = get_post_meta($pro_id, WCPA_PRODUCT_META_KEY, true);
        if ($meta_fields && is_array($meta_fields)) {
            $value = implode(",", $meta_fields);
        }

        return $value;
    }

    public function manage_products_columns($columns)
    {
        return array_merge(array_slice($columns, 0, -2, true),
            ['wcpa_forms' => __('Product Forms', 'woo-custom-product-addons-pro')], array_slice($columns, -2, null, true));
    }

    public function manage_products_column($column_name, $post_id)
    {
        if ($column_name == 'wcpa_forms') {
            $forms = get_post_meta($post_id, WCPA_PRODUCT_META_KEY, true);
            $link = '';
            if (is_array($forms)) {
                foreach ($forms as $v) {
                    if ( get_post_status( $v ) ) {
                        $link .= '<a href="' . getFormEditUrl($v) . '" target="_blank">' . get_the_title($v) . '</a>, ';
                    }
                }
            }
            echo trim($link, ', ');
        }
    }

    public function woocommerce_process_product_meta_fields_save($post_id)
    {
        if (isset($_POST['wcpa_product_meta'])) {
            $jsonData = json_decode(html_entity_decode(stripslashes($_POST['wcpa_product_meta'])));
            if ($jsonData) {
                $this->save_meta($post_id,
                    [
                        'active' => (array)$jsonData->active,
                        'order' => (array)$jsonData->order,
                        'conf' => (array)$jsonData->conf,
                        'cfs' => (array)$jsonData->cfs,
                    ]);
            }
        }
    }

    public function save_meta($post_id, $data)
    {
        $active = $data['active'];
        $order = $data['order'];
        $conf = $data['conf'];
        $cfs = $data['cfs'];
        $meta_field = [];
        $form_order = [];
        if (is_array($active)) {
            foreach ($active as $v) {
                $form_id = (int)sanitize_text_field($v);
                if (!in_array(get_post_status($form_id), ['publish', 'draft'])) {
                    continue;
                }
                $meta_field[] = $form_id;
                if ($order && isset($order[$form_id]) && $order[$form_id] !== '') {
                    // null and 0 need to be treated as different, if value is null, it will order based on the form default order
                    $form_order[$form_id] = (int)sanitize_text_field($order[$form_id]);
                }
            }
        }
        if (isset($order[0])) {
            $form_order[0] = (int)sanitize_text_field($order[0]);
        }
        update_post_meta($post_id, self::$fieldKey, $meta_field);
        update_post_meta($post_id, self::$orderKey, $form_order);
        if (isset($conf['wcpa_exclude_global_forms']) && $conf['wcpa_exclude_global_forms']) {
            update_post_meta($post_id, 'wcpa_exclude_global_forms', true);
        } else {
            update_post_meta($post_id, 'wcpa_exclude_global_forms', false);
        }


        $cf_prefix = Config::get_config('wcpa_cf_prefix', 'wcpa_pcf_');
        $custom_fields = Config::get_config('product_custom_fields', []);
        foreach ($custom_fields as $k => $field) {
            if (isset($cfs[$field['name']]) && !empty($cfs[$field['name']])) {
                update_post_meta($post_id, $cf_prefix . $field['name'], $cfs[$field['name']]);
            } else {
                delete_post_meta($post_id, $cf_prefix . $field['name']);
            }
        }
//        delete_transient(WCPA_PRODUCTS_TRANSIENT_KEY);
        refreshCaches(false, $post_id);
        return true;
    }

    public function get_forms($post_id)
    {
        $form = new Form();
        $forms_list = $form->forms_list();
        $conf = [];
        $meta_field = get_post_meta($post_id, self::$fieldKey, true);
        $form_order = get_post_meta($post_id, 'wcpa_product_meta_order', true);

        $conf['wcpa_exclude_global_forms'] = metaToBoolean(get_post_meta($post_id, 'wcpa_exclude_global_forms', true));

        return [
            'forms' => $forms_list,
            'active' => $meta_field ? array_values($meta_field) : [],// ensure it has index starting from 0
            'conf' => $conf,
            'order' => ($form_order == '' ? [] : $form_order)
        ];
        //

    }

    public function add_my_custom_product_data_tab($product_data_tabs)
    {
        $product_data_tabs['wcpa_product-meta-tab'] = array(
            'label' => __('Product Addons', 'my_text_domain'),
            'target' => 'wcpa_product-meta-tab',
            'priority' => 90
        );

        return $product_data_tabs;
    }


    public function add_my_custom_product_data_fields()
    {
        global $post;
        $ml = new ML();
        $meta_class = '';
        $preventEdit = false;
        if ($ml->is_active() && $ml->current_language() !== false && !$ml->is_default_lan()) {
            $meta_class = 'wcpa_wpml_pro_meta';
            $preventEdit = true;
        }

        ?>

        <div id="wcpa_product-meta-tab" class="panel woocommerce_options_panel <?php
        echo $meta_class; ?>">
            <?php
            if ($ml->is_active() && $ml->current_language() !== false && !$ml->is_default_lan()) {
                echo '<p class="wcpa_editor_message" >' . sprintf(__('You cannot manage form fields from this language. You can manage fields from base language only.
                All changes in base language will be synced with all translated version of product')) . '</p>';
            }
            ?>
            <div id="wcpa_product_meta" class="<?php
            echo $preventEdit ? 'wcpa_ml_prevent' : '' ?>"
                 data-postId="<?php
                 echo $post->ID ?>"></div>

        </div>
        <?php
    }

    public function save_products_meta($form_id, $products_ids)
    {
        $response = ['status' => true];

        if (is_array($products_ids)) {
            foreach ($products_ids as $v) {
                $product_id = (int)sanitize_text_field($v);
                $meta_field = get_post_meta($product_id, self::$fieldKey, true);

                if (is_array($meta_field)) {
                    array_push($meta_field, $form_id);
                } else {
                    $meta_field = [$form_id];
                }
                array_unique($meta_field);
                update_post_meta($product_id, self::$fieldKey, $meta_field);
                refreshCaches(false, $product_id);
            }
        }

        return $response;
    }

    public function remove_products_meta($form_id, $product_id)
    {
        $response = ['status' => true];
        $meta_field = get_post_meta($product_id, self::$fieldKey, true);

        // if (($key = array_search($form_id, $meta_field)) !== false) {
        //     unset($meta_field[$key]);
        //     update_post_meta($product_id, self::$fieldKey, $meta_field);
        // }
        $meta_field_new = array_diff($meta_field, array($form_id));
        update_post_meta($product_id, self::$fieldKey, $meta_field_new);
        refreshCaches(false, $product_id);
        return $response;
    }

}
