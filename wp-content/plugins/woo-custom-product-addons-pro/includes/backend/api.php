<?php

namespace Acowebs\WCPA;

use WP_REST_Response;

class BackendApi
{
    private $token;
    private $version;
    private $assets_url;

    /**
     * Constructor
     */

    public function __construct()
    {
//	    $response = apply_filters( 'rest_request_before_callbacks', $response, $handler, $request );

        add_filter('rest_request_before_callbacks', array($this, 'setLang'), 10, 3);

        add_action('rest_api_init', array($this, 'register_routes'));
        $this->assets_url = WCPA_ASSETS_URL;
        $this->version = WCPA_VERSION;
        $this->token = WCPA_TOKEN;
    }


    public function setLang($response, $handler, $request)
    {
        $lang = $request->get_header('wcpaLang');
        if ($lang) {

            add_filter('wcpa_set_default_lang', function ($currentLang) use ($lang) {
                $currentLang = $lang;

                return $currentLang;
            }, 10, 1);
        }

        return $response;
    }

    /**
     * Register API routes
     */

    public function register_routes()
    {
        $this->add_route('/get_forms', 'get_forms');
        $this->add_route('/get_forms/(?P<tab>[a-zA-Z0-9-]+)', 'get_forms');
        $this->add_route('/get_forms/(?P<tab>[a-zA-Z0-9-]+)/(?P<page>[0-9]+)', 'get_forms');
        $this->add_route('/get_forms/(?P<tab>[a-zA-Z0-9-]+)/(?P<page>[0-9]+)/(?P<per_page>[0-9]+)', 'get_forms');
        $this->add_route('/get_forms/(?P<tab>[a-zA-Z0-9-]+)/(?P<page>[0-9]+)/(?P<per_page>[0-9]+)/(?P<search>.*)',
            'get_forms');

        $this->add_route('/get_fields', 'get_fields');
        $this->add_route('/get_fields/(?P<id>[0-9]+)', 'get_fields');

        $this->add_route('/save/(?P<id>[0-9]+)', 'save_form', 'POST');
        $this->add_route('/trash_form', 'trash_form', 'POST');
        $this->add_route('/delete_form', 'delete_form', 'POST');
        $this->add_route('/restore', 'restore_forms', 'POST');
        $this->add_route('/duplicate/(?P<id>[0-9]+)', 'duplicate_form', 'POST');


        $this->add_route('/update_status/(?P<id>[0-9]+)', 'update_form_status', 'POST');

        $this->add_route('/setScreenOptions', 'set_screen_options', 'POST');

        $this->add_route('/translate/(?P<id>[0-9]+)', 'translate_form', 'POST');
        $this->add_route('/translate_options/(?P<id>[0-9]+)', 'translate_options', 'POST');

        $this->add_route('/change_lang/(?P<id>[0-9]+)', 'change_form_lang', 'POST');


        $this->add_route('/get_settings', 'get_settings');
        $this->add_route('/save_settings', 'save_settings', 'POST');

        $this->add_route('/license', 'get_license');
        $this->add_route('/license', 'save_license', 'POST');

        //DeActivate License
        $this->add_route('/deactivate', 'deactivate_license', 'POST');

        //Reset License Key
        $this->add_route('/reset_key', 'reset_key');

        $this->add_route('/get_designs', 'get_designs');
        $this->add_route('/get_style/(?P<style>[a-zA-Z0-9_-]+)', 'get_style');
        $this->add_route('/activate_theme/', 'activate_theme', 'POST');
        $this->add_route('/save_theme_conf/', 'save_theme_conf', 'POST');


        $this->add_route('/get_product_forms/(?P<id>[0-9]+)', 'get_product_forms');
        $this->add_route('/save_product_meta/(?P<id>[0-9]+)', 'save_product_meta', 'POST');

        //Export Form
        $this->add_route('/export/(?P<form_id>[0-9]+)', 'export_form', 'GET');
        //Import Form
        $this->add_route('/import/(?P<form_id>[0-9]+)', 'import_form', 'POST');

        //Export Bulk Forms
        $this->add_route('/lists/export', 'export_bulk_forms', 'POST');
        $this->add_route('/lists/export', 'export_bulk_forms', 'GET');

        //Import Bulk Forms
        $this->add_route('/lists/import', 'import_bulk_forms', 'POST');

        //Products Listing
        $this->add_route('/list/products/(?P<form_id>[0-9]+)', 'products_listing', 'POST');
        //Product Searching
        $this->add_route('/search/products/(?P<q>[a-zA-Z0-9-]+)', 'products_searching');


        //Product Assigning to Form
        $this->add_route('/save/products_meta/(?P<form_id>[0-9]+)', 'save_products_meta', 'POST');
        //Product Removing from Form
        $this->add_route('/remove/products_meta/(?P<form_id>[0-9]+)', 'remove_products_meta', 'POST');

        //Checkout Fields
        $this->add_route('/get_checkout_fields', 'get_checkout_fields');

        /**
         * Field Options API
         */
        $this->add_route('/get_options_lists', 'get_options_lists');
        $this->add_route('/get_options_lists/(?P<tab>[a-zA-Z0-9-]+)', 'get_options_lists');
        $this->add_route('/get_options_lists/(?P<tab>[a-zA-Z0-9-]+)/(?P<page>[0-9]+)', 'get_options_lists');
        $this->add_route('/get_options_lists/(?P<tab>[a-zA-Z0-9-]+)/(?P<page>[0-9]+)/(?P<per_page>[0-9-]+)',
            'get_options_lists');
        $this->add_route('/get_options_lists/(?P<tab>[a-zA-Z0-9-]+)/(?P<page>[0-9]+)/(?P<per_page>[0-9-]+)/(?P<search>.*)',
            'get_options_lists');
        $this->add_route('/get_options_lists/(?P<tab>[a-zA-Z0-9-]+)/(?P<page>[0-9]+)/(?P<per_page>[0-9-]+)/(?P<search>.*)',
            'get_options_lists');

        $this->add_route('/options_lists/export', 'export_bulk_options_lists', 'POST');
        $this->add_route('/options_lists/export', 'export_bulk_options_lists', 'GET');
        $this->add_route('/options_lists/import', 'import_bulk_options_lists', 'POST');

        $this->add_route('/options/get_options/(?P<id>[0-9]+)', 'get_options', 'GET');
        $this->add_route('/options/get_options/(?P<id>[0-9]+)', 'get_options');
        $this->add_route('/options/get_options_by_key/(?P<key>[a-zA-Z0-9-]+)', 'get_options_by_key');

        $this->add_route('/options/save/(?P<options_id>[0-9]+)', 'save_options_list', 'POST');
        $this->add_route('/options/trash', 'trash_options_list', 'POST');
        $this->add_route('/options/delete', 'delete_options_list', 'POST');
        $this->add_route('/options/restore', 'restore_options_list', 'POST');
        $this->add_route('/options/duplicate/(?P<options_id>[0-9]+)', 'duplicate_options_list', 'POST');
        $this->add_route('/options/export/(?P<options_id>[0-9]+)', 'export_options_list', 'GET');
        $this->add_route('/options/import/(?P<options_id>[0-9]+)', 'import_options_list', 'POST');


        $this->add_route('/get_order_meta/(?P<item_id>[0-9]+)', 'get_order_items', 'GET');
        $this->add_route('/get_order_meta/(?P<item_id>[0-9]+)', 'save_order_items', 'POST');

        $this->add_route('/purge_caches/', 'purge_caches', 'POST');

        $this->add_route('/verify_bucket/', 'verify_bucket', 'POST');


    }


    private function add_route($slug, $callBack, $method = 'GET')
    {
        register_rest_route(
            $this->token . '/admin',
            $slug,
            array(
                'methods' => $method,
                'callback' => array($this, $callBack),
                'permission_callback' => array($this, 'getPermission'),
            ));
    }

    public function purge_caches($data)
    {

        refreshCaches();
        return new WP_REST_Response(true, 200);
    }

    public function translate_form($data)
    {
        $post_data = $data->get_params();
        if (!isset($data['id'])) {
            return new WP_REST_Response([], 400);
        }

        $form = new Form();
        $newLang = $post_data['lang'];
        $status = $form->translate_form($data['id'], $newLang);

        return new WP_REST_Response($status, 200);
    }

    /** Verify s3 bucket
     * @param $data
     */
    public function verify_bucket($data)
    {
        $s3 = new S3();
        $creds = $data->get_params();
        $status = $s3->verify($creds['data']);
        return new WP_REST_Response($status, 200);
    }

    public function translate_options($data)
    {
        $post_data = $data->get_params();
        if (!isset($data['id'])) {
            return new WP_REST_Response([], 400);
        }

        $form = new Options();
        $newLang = $post_data['lang'];
        $status = $form->translate_options($data['id'], $newLang);

        return new WP_REST_Response($status, 200);
    }

    public function change_form_lang($data)
    {
        $post_data = $data->get_params();

        if (!isset($data['id'])) {
            return new WP_REST_Response([], 400);
        }
        $lang = $post_data['lang'];
        $post_id = (int)$data['id'];
        $form = new Form();
        $response = $form->change_form_lang($post_id, $lang);

        return new WP_REST_Response($response, 200);
    }


    public function delete_form($data)
    {
        $post_data = $data->get_params();
        if (
            !isset($post_data['forms']) ||
            empty($post_data['forms']) ||
            !is_array($post_data['forms'])
        ) {
            return new WP_REST_Response([], 400);
        }
        $forms = array_map('intval', $post_data['forms']);
        $form = new Form();
        $response = $form->delete_form($forms);

        return new WP_REST_Response($response, 200);
    }


    public function trash_form($data)
    {
        $post_data = $data->get_params();
        if (
            !isset($post_data['forms']) ||
            empty($post_data['forms']) ||
            !is_array($post_data['forms'])
        ) {
            return new WP_REST_Response([], 400);
        }
        $forms = array_map('intval', $post_data['forms']);
        $form = new Form();
        $response = $form->trash_form($forms);

        return new WP_REST_Response($response, 200);
    }


    public function restore_forms($data)
    {
        $post_data = $data->get_params();
        $forms = isset($post_data['forms']) && !empty($post_data['forms']) ? $post_data['forms'] : false;
        if ($forms == false) {
            return new WP_REST_Response([], 400);
        }

        $form = new Form();
        $response = $form->restore_forms($forms);

        return new WP_REST_Response($response, 200);
    }


    public function update_form_status($data)
    {
        if (!isset($data['id'])) {
            return new WP_REST_Response([], 400);
        }
        $post_data = $data->get_params();
        $status = isset($post_data['status']) && $post_data['status'] ? 'publish' : 'draft';
        $post_id = (int)$data['id'];
        $form = new Form();
        $response = $form->update_form_status($post_id, $status);

        return new WP_REST_Response($response, 200);
    }

    public function duplicate_form($data)
    {
        if (!isset($data['id'])) {
            return new WP_REST_Response([], 400);
        }
        $post_data = $data->get_params();
        $post_id = (int)$data['id'];
        $form = new Form();
        $response = $form->duplicate_form($post_id);

        return new WP_REST_Response($response, 200);
    }

    public function save_form($data)
    {
        $post_data = $data->get_params();

        if (!isset($data['id'])) {
            return new WP_REST_Response([], 400);
        }
        $post_id = (int)$data['id'];

        $form = new Form();
        $response = $form->save_form($post_id, $post_data);

        return new WP_REST_Response($response, 200);
    }

    public function set_screen_options($data)
    {
        $post_data = $data->get_params();

        if (!(isset($post_data['options']) && !empty($post_data['options']))) {
            return new WP_REST_Response(false, 400);
        }

        $settings = new Settings;

        return $settings->update_screen_options($post_data['options']);
    }

    public function save_product_meta($data)
    {
        if (!isset($data['id'])) {
            return new WP_REST_Response(false, 400);
        }
        $post_data = $data->get_params();
        $meta = new Product_Meta();
        $forms = $meta->save_meta($data['id'], $post_data);

        return new WP_REST_Response($forms, 200);
    }


    public function get_product_forms($data)
    {
        if (!isset($data['id'])) {
            return new WP_REST_Response(false, 400);
        }
        $productId = $data['id'];
        $meta = new Product_Meta();
        $forms = $meta->get_forms($productId);

        $productCfs = Config::getWcpaCustomFieldByProduct($productId, false, true);

        $customFields = Config::get_config('product_custom_fields', []);

        return new WP_REST_Response([
            'forms' => $forms,
            'customFields' => [
                'product' => $productCfs,
                'all' => $customFields
            ]
        ], 200);
    }

    /**
     * Get Designs
     */
    public function get_designs()
    {
        $designs = new Designs();
        $a = $designs->get_designs();

        return new WP_REST_Response($a, 200);
    }

    public function activate_theme($data)
    {
        $post_data = $data->get_params();
        if (empty($post_data['theme'])) {
            return new WP_REST_Response(false, 400);
        }

        $designs = new Designs();
        $res = $designs->activate_theme($post_data['theme']);

        return new WP_REST_Response($res, 200);
    }

    public function save_theme_conf($data)
    {
        $post_data = $data->get_params();


        $designs = new Designs();
        $res = $designs->save_theme_conf($post_data);
        if ($res == false) {
            return new WP_REST_Response(false, 400);
        }

        return new WP_REST_Response($res, 200);
    }

    public function get_style($data)
    {
        $style = $data['style'];
        $designs = new Designs();
        $a = $designs->get_style($style);
        if ($a == false) {
            return new WP_REST_Response($a, 400);
        }

        return new WP_REST_Response($a, 200);
    }

    /**
     * Get forms
     */

    public function get_forms($data)
    {
        $tab = $data['tab'];
        $page = (isset($data['page']) && !empty($data['page'])) ? intval($data['page']) : 20;
        $per_page = (isset($data['per_page']) && !empty($data['per_page'])) ? intval($data['per_page']) : 20;
        $search = (isset($data['search']) && !empty($data['search'])) ? urldecode($data['search']) : '';
        $form = new Form();
        $forms = $form->get_forms($tab, $page, $per_page, $search);

        return new WP_REST_Response($forms, 200);
    }

    /**
     * Get forms Fields
     *
     * @param $data
     *
     * @return WP_REST_Response
     */

    public function get_fields($data)
    {
        if (!isset($data['id'])) {
            return new WP_REST_Response(false, 400);
        }
        $form_id = $data['id'];
        $form = new Form();
        $fields = $form->get_fields($form_id);

        return new WP_REST_Response($fields, 200);
    }

    public function save_order_items($data)
    {
        if (!isset($data['item_id'])) {
            return new WP_REST_Response(false, 400);
        }
        $item_id = $data['item_id'];
        $post_data = $data->get_params();
        $order = new Order();
        $res = $order->saveOrderMeta($item_id, $post_data['fields']);

        return new WP_REST_Response($res, 200);
    }

    public function get_order_items($data)
    {
        if (!isset($data['item_id'])) {
            return new WP_REST_Response(false, 400);
        }
        $item_id = $data['item_id'];
        $order = new Order();
        $fields = $order->getOrderMeta($item_id);

        return new WP_REST_Response($fields, 200);
    }

    /**
     * get global settings
     */
    public function get_settings()
    {
        $settings = new Settings();

        return new WP_REST_Response($settings->get_settings(true), 200);
    }

    public function save_settings($data)
    {
        $post_data = $data->get_params();
        $settings = new Settings();
        if (!isset($post_data['data'])) {
            return new WP_REST_Response([], 400);
        }
        $status = $settings->save_settings($post_data['data']);

        return new WP_REST_Response($status, 200);
    }

    /**
     * get global settings
     */
    public function get_license()
    {
        $settings = new Settings();

        return new WP_REST_Response($settings->get_license(), 200);
    }

    public function save_license($data)
    {
        $post_data = $data->get_params();


        $settings = new Settings();
        $status = $settings->save_license($post_data);

        return new WP_REST_Response($status, 200);
    }

    //DeActivate License
    public function deactivate_license($data)
    {
        $post_data = $data->get_params();

        $settings = new Settings();
        $status = $settings->deactivate_license($post_data['licenseKey']);

        return new WP_REST_Response($status, 200);
    }

    //Reset License Key
    public function reset_key()
    {
        $settings = new Settings();
        $status = $settings->reset_key();

        return new WP_REST_Response($status, 200);
    }

    //Export Form
    public function export_form($data)
    {
        if (!isset($data['form_id'])) {
            return new WP_REST_Response([], 400);
        }

        if ($data['form_id'] == 0) {
            return new WP_REST_Response(['status' => false], 400);
        }

        $form_id = intval($data['form_id']);
        $_forms = new Form();
        $_forms->export_form($form_id);

        return new WP_REST_Response(null, 200);
    }

    public function import_form($data)
    {
        $post_data = $data->get_params();
        if (!isset($data['form_id'])) {
            return new WP_REST_Response([], 400);
        }

        $form_id = intval($data['form_id']);
        $_forms = new Form();
        $response = $_forms->import_form($form_id, $post_data);

        return new WP_REST_Response($response, 200);
    }


    /**
     * Export forms bulk
     */
    public function export_bulk_forms($data)
    {
        $post_data = $data->get_params();

        if (isset($post_data['forms']) && is_array($post_data['forms'])) {
            $forms = array_map('intval', $post_data['forms']);
        } else {
            $forms = 'all';
        }

        $_forms = new Form();
        $response = $_forms->export_bulk_forms($forms);

        return new WP_REST_Response($response, 200);
    }

    /**
     * Import Forms bulk
     */
    public function import_bulk_forms($data)
    {
        $post_data = $data->get_params();
        if (!isset($_FILES['file'])) {
            return new WP_REST_Response([], 400);
        }

        $_options = new Form();
        $response = $_options->import_bulk_forms($post_data);

        return new WP_REST_Response($response, 200);
    }

    /**
     * Products Listing
     */
    public function products_listing($data)
    {
        if (!isset($data['form_id'])) {
            return new WP_REST_Response([], 400);
        }

        $form_id = intval($data['form_id']);
        $_forms = new Form();
        $response = $_forms->products_listing($form_id);

        return new WP_REST_Response($response, 200);
    }

    /**
     * Products Searching
     */
    public function products_searching($data)
    {
        if (!isset($data['q'])) {
            return new WP_REST_Response([], 400);
        }

        $search = $data['q'];
        $_forms = new Form();
        $response = $_forms->products_searching($search);

        return new WP_REST_Response($response, 200);
    }

    /**
     * Products Assigning to Form
     */
    public function save_products_meta($data)
    {
        if (!isset($data['form_id'])) {
            return new WP_REST_Response(false, 400);
        }
        $post_data = $data->get_params();

        $form_id = (int)$data['form_id'];
        $product_ids = $post_data['selectOpts'];
        $form = new Product_Meta();
        $response = $form->save_products_meta($form_id, $product_ids);

        return new WP_REST_Response($response, 200);
    }

    /**
     * Products Removing from Form
     */
    public function remove_products_meta($data)
    {
        if (!isset($data['form_id'])) {
            return new WP_REST_Response(false, 400);
        }
        $post_data = $data->get_params();

        $form_id = (int)$data['form_id'];
        $product_id = (int)$post_data['product_id'];
        $form = new Product_Meta();
        $response = $form->remove_products_meta($form_id, $product_id);

        return new WP_REST_Response($response, 200);
    }

    /**
     * Checkout Fields
     */
    public function get_checkout_fields()
    {
        $_forms = new Form();
        $response = $_forms->get_checkout_fields();

        return new WP_REST_Response($response, 200);
    }
    /**
     * Field Options API's
     *
     *
     */

    /**
     * Get Fields options
     */
    public function get_options_lists($data)
    {
        $tab = $data['tab'];
        $page = (isset($data['page']) && !empty($data['page'])) ? intval($data['page']) : 20;
        $per_page = (isset($data['per_page']) && !empty($data['per_page'])) ? intval($data['per_page']) : 20;
        $search = (isset($data['search']) && !empty($data['search'])) ? urldecode($data['search']) : '';
        $options = new Options();
        $fieldOptions = $options->get_options_lists($tab, $page, $per_page, $search);

        return new WP_REST_Response($fieldOptions, 200);
    }

    public function delete_options_list($data)
    {
        $post_data = $data->get_params();
        if (
            !isset($post_data['options']) ||
            empty($post_data['options']) ||
            !is_array($post_data['options'])
        ) {
            return new WP_REST_Response([], 400);
        }
        $options = array_map('intval', $post_data['options']);
        $_options = new Options();
        $response = $_options->delete_options_list($options);

        return new WP_REST_Response($response, 200);
    }

    public function trash_options_list($data)
    {
        $post_data = $data->get_params();
        if (
            !isset($post_data['options']) ||
            empty($post_data['options']) ||
            !is_array($post_data['options'])
        ) {
            return new WP_REST_Response([], 400);
        }
        $options = array_map('intval', $post_data['options']);
        $_options = new Options();
        $response = $_options->trash_options_list($options);

        return new WP_REST_Response($response, 200);
    }

    public function restore_options_list($data)
    {
        $post_data = $data->get_params();
        $options = isset($post_data['options']) && !empty($post_data['options']) ? $post_data['options'] : false;
        if ($options == false) {
            return new WP_REST_Response([], 400);
        }

        $_options = new Options();
        $response = $_options->restore_options_list($options);

        return new WP_REST_Response($response, 200);
    }

    public function duplicate_options_list($data)
    {
        if (!isset($data['options_id'])) {
            return new WP_REST_Response([], 400);
        }
        $post_data = $data->get_params();
        $post_id = (int)$data['options_id'];
        $_options = new Options();
        $response = $_options->duplicate_options_list($post_id);

        return new WP_REST_Response($response, 200);
    }

    public function save_options_list($data)
    {
        $post_data = $data->get_params();

        if (!isset($data['options_id'])) {
            return new WP_REST_Response([], 400);
        }
        $post_id = (int)$data['options_id'];

        $options = new Options();
        $response = $options->save_options_list($post_id, $post_data);

        return new WP_REST_Response($response, 200);
    }

    public function export_options_list($data)
    {
        if (!isset($data['options_id'])) {
            return new WP_REST_Response([], 400);
        }

        if ($data['options_id'] == 0) {
            return new WP_REST_Response(['status' => false], 400);
        }

        $post_id = intval($data['options_id']);
        $_options = new Options();
        $response = $_options->export_options($post_id);

        return new WP_REST_Response($response, 200);
    }

    public function import_options_list($data)
    {
        $post_data = $data->get_params();
        if (!isset($data['options_id'])) {
            return new WP_REST_Response([], 400);
        }

        $post_id = intval($data['options_id']);
        $_options = new Options();
        $response = $_options->import_options($post_id, $post_data);

        return new WP_REST_Response($response, 200);
    }

    /**
     * Export options list bulk
     */
    public function export_bulk_options_lists($data)
    {
        $post_data = $data->get_params();

        if (isset($post_data['options']) && is_array($post_data['options'])) {
            $options = array_map('intval', $post_data['options']);
        } else {
            $options = 'all';
        }

        $_options = new Options();
        $response = $_options->export_options_lists($options);

        return new WP_REST_Response($response, 200);
    }

    /**
     * Import options list bulk
     */
    public function import_bulk_options_lists($data)
    {
        $post_data = $data->get_params();
        if (!isset($_FILES['file'])) {
            return new WP_REST_Response([], 400);
        }

        $_options = new Options();
        $response = $_options->import_bulk_options_lists($post_data);

        return new WP_REST_Response($response, 200);
    }


    /**
     * Get Options List Options
     *
     * @param $data
     *
     * @return WP_REST_Response
     */

    public function get_options($data)
    {
        if (!isset($data['id'])) {
            return new WP_REST_Response(false, 400);
        }
        $option_id = $data['id'];
        $options = new Options();
        $fields = $options->get_options($option_id);

        return new WP_REST_Response($fields, 200);
    }

    /**
     * Get Options fields by slug
     *
     * @param $data
     *
     * @return WP_REST_Response
     */

    public function get_options_by_key($data)
    {
        if (!isset($data['key'])) {
            return new WP_REST_Response(false, 400);
        }
        $key = $data['key'];
        $options = new Options();
        $fields = $options->get_options_by_key($key);

        return new WP_REST_Response($fields, 200);
    }


    /**
     * Permission Callback
     **/
    public
    function getPermission()
    {
        if (current_user_can('administrator') || current_user_can('manage_woocommerce')) {
            return true;
        } else {
            return false;
        }
    }
}