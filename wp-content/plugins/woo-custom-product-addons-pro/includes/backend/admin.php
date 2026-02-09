<?php

namespace Acowebs\WCPA;

use Plugin_Upgrader;
use WC_Tax;
use WP_Error;
use function get_option;
use function pll_get_post_language;
use function pll_get_post_translations;
use function pll_save_post_translations;

class Admin
{
    private $assets_url;
    private $version;
    private $token;
    private $isWooActive;
    private $hook_suffix = [];


    /**
     * Admin constructor.
     *
     * @param $isWooActive
     */
    public function __construct($isWooActive)
    {
        $this->isWooActive = $isWooActive;
        add_action('admin_menu', [$this, 'add_menu'], 10);

        $this->assets_url = WCPA_ASSETS_URL;
        $this->version = WCPA_VERSION;
        $this->token = WCPA_TOKEN;

        $form = new Form();
        $form->init();
        $options = new Options();
        $options->init();

        $updater = new Updater(WCPA_STORE_URL, WCPA_FILE, array(
                'version' => WCPA_VERSION, // current version number
                'license' => get_option('wcpa_activation_license_key'),
                // license key (used get_option above to retrieve from DB)
                'item_id' => WCPA_ITEM_ID, // id of this product in EDD
                'author' => 'Acowebs', // author of this plugin
                'url' => home_url()
            )
        );
        add_action('upgrader_process_complete', array($this, 'up_grader_process'), 10, 2);

        if ($isWooActive) {
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'), 10, 1);
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_styles'), 10, 1);
            add_action('current_screen', array($this, 'this_screen'));

            add_action('save_post', array($this, 'delete_transient'), 1);
            add_action('edited_term', array($this, 'delete_transient'));
            add_action('delete_term', array($this, 'delete_transient'));
            add_action('created_term', array($this, 'delete_transient'));
            Product_Meta::instance();
        } else {
            add_action('admin_notices', array($this, 'notice_need_woocommerce'));
        }
        register_deactivation_hook(WCPA_FILE, array($this, 'deactivation'));

        $plugin = plugin_basename(WCPA_FILE);
        add_filter("plugin_action_links_$plugin", array($this, 'add_settings_link'));

        add_action('admin_footer', array($this, 'popup_container'));


    }


    public function popup_container()
    {
        echo '<div id="wcpa_order_popup"></div>';
    }

    public function add_settings_link($links)
    {
//        $settings = '<a href="' . admin_url('/admin.php?page=wcpa-admin-ui') . '">' . __('Settings') . '</a>';
        $products = '<a href="' . admin_url('/admin.php?page=wcpa-admin-ui#') . '">' . __('Create Forms', 'woo-custom-product-addons-pro') . '</a>';
//        array_push($links, $settings);
        array_push($links, $products);
        return $links;

    }

    /**
     * Append plugin update failed guideline for update error
     *
     * @param $that
     * @param $action
     */
    public function up_grader_process($that, $action)
    {
        if ($action &&
            isset($action['plugins'][0]) && $action['plugins'][0] == 'woo-custom-product-addons-pro/start.php' &&
            $that->result == null &&
            $that instanceof Plugin_Upgrader) {
            $errors = $that->skin->get_errors();
            if (count($errors->errors)) {
                $that->skin->error(new WP_Error('error_guideline',
                    sprintf(__('Please ensure you have activated license for current domain, <a href="%s" target="_blank" >Check our Guideline</a> for more details',
                        'woo-custom-product-addons-pro'), 'https://acowebs.com/guideline/general/plugin-update-failed/')
                ));
            }
        }
    }

    public function delete_transient($arg = false)
    {
        if ($arg) {
            if (in_array(get_post_type($arg), ['product', Form::$CPT])) {
//                delete_transient(WCPA_PRODUCTS_TRANSIENT_KEY);
                refreshCaches();
            }
        } else {
//            delete_transient(WCPA_PRODUCTS_TRANSIENT_KEY);
            refreshCaches();
        }
    }

    public function deactivation()
    {
        Cron::clear();
    }

    /**
     * Load admin Javascript.
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public function admin_enqueue_scripts($hook = '')
    {
        wp_enqueue_script('jquery');

        wp_enqueue_media();


        if (!isset($this->hook_suffix) || empty($this->hook_suffix)) {
            return;
        }


        $screen = get_current_screen();


        $ml = new ML();
        if (in_array($screen->id, $this->hook_suffix)) {
            //Enqueue scripts moved from front.php to avoid it loading everywhere
            wp_enqueue_script($this->token . '-front');
            wp_enqueue_script($this->token . '-googlemapplace');

            $form = new Form();
            $settings = new Settings;
            $formsList = $form->forms_list();
            //wpml language menu remove all
            add_filter('wpml_admin_language_switcher_items', [$ml, 'modify_lang_menu']);

            wp_enqueue_script('wp-i18n');
            $time_format = __(get_option('time_format'), 'woo-custom-product-addons-pro');

            $globalVars = array(
                'license_status' => get_option('wcpa_activation_license_status'),
                'forms_url' => admin_url('/admin.php?page=wcpa-admin-ui'),
                'api_nonce' => wp_create_nonce('wp_rest'),
                'root' => ($ml->is_active() && $ml->current_language() == 'all') ? str_replace('/all', '', rest_url($this->token . '/admin/')) : rest_url($this->token . '/admin/'),
                'assets_url' => $this->assets_url,
                'default_image_url' => $this->assets_url . 'images/default-image.jpg',
                'screen_options' => $settings->get_screen_options(),
                'wcpa_acd_count' => Config::get_config('wcpa_acd_count'),
                'prod_cats' => $this->get_taxonomy_hierarchy('product_cat'),
                'isMlActive' => $ml->is_active(),
                'formsList' => $formsList,
                'date_format' => __(get_option('date_format'), 'woo-custom-product-addons-pro'),
                'time_format' => __(get_option('time_format'), 'woo-custom-product-addons-pro'),
                'strings' => array(
                    'place_selector_street' => Config::get_config('place_selector_street'),
                    'place_selector_city' => Config::get_config('place_selector_city'),
                    'place_selector_state' => Config::get_config('place_selector_state'),
                    'place_selector_zip' => Config::get_config('place_selector_zip'),
                    'place_selector_country' => Config::get_config('place_selector_country'),
                    'place_selector_latitude' => Config::get_config('place_selector_latitude'),
                    'place_selector_longitude' => Config::get_config('place_selector_longitude'),
                    'file_button_text' => Config::get_config('file_button_text'),
                    'file_droppable_action_text' => Config::get_config('file_droppable_action_text'),
                    'file_droppable_desc_text' => Config::get_config('file_droppable_desc_text'),
                    'other' => Config::get_config('other_text'),
                    'clearSelection' => Config::get_config('clearSelection_text'),
                    'repeater_add' => Config::get_config('repeater_add_text'),
                    'repeater_remove' => Config::get_config('repeater_remove_text'),
                    'file_droppable_text' => Config::get_config('file_droppable_text'),
                ),
                'ml' => $ml->is_active() ? [
                    'langList' => $ml->lang_list(),
                    'currentLang' => $ml->current_language(),
                    'defaultLang' => $ml->default_language(),
                    'isDefault' => $ml->is_default_lan() ? $ml->is_default_lan() : (($ml->current_language() === 'all') ? true : false)
                ] : false
            );

            $attr_tax = [];
            if (function_exists('wc_get_attribute_taxonomies')) {
                $attr_tax = wc_get_attribute_taxonomies();
            }

            $attributes = array();
            foreach ($attr_tax as $atr) {
                $temp['attribute_id'] = $atr->attribute_id;
                $temp['attribute_label'] = $atr->attribute_label;
                $temp['attribute_name'] = wc_attribute_taxonomy_name($atr->attribute_name);
                $terms = get_terms(array(
                    'taxonomy' => wc_attribute_taxonomy_name($atr->attribute_name),
                    'hide_empty' => false,
                    'fields' => 'all'
                ));
                if (is_array($terms) && ! is_wp_error( $terms )) {
                    $temp['terms'] = array_map(function ($t) {
                        return ['slug' => $t->slug, 'name' => $t->name, 'term_id' => $t->term_id];
                    }, $terms);
                } else {
                    $temp['terms'] = [];
                }

                $attributes[] = $temp;
            }
            $globalVars['attributes'] = $attributes;
            $globalVars['user_roles'] = [['value' => 'guest', 'label' => 'Guest']];
            foreach (wp_roles()->roles as $role => $object) {
                $globalVars['user_roles'][] = ['value' => $role, 'label' => $object['name']];
            }


            $globalVars['custom_fields'] = Config::get_config('product_custom_fields');
            $globalVars['addons'] = addonsList();


            $tax_classes = WC_Tax::get_tax_class_slugs(); // Retrieve all tax classes.
            if (!in_array('', $tax_classes)) { // Make sure "Standard rate" (empty class name) is present.
                array_unshift($tax_classes, '');
            }
            $globalVars['tax_classes'] = $tax_classes;
            wp_enqueue_script($this->token . '-backend',
                esc_url($this->assets_url) . 'js/backend/main.js', array('wp-i18n'),
                $this->version, true);


            wp_set_script_translations($this->token . '-backend', 'woo-custom-product-addons-pro',
                plugin_dir_path(WCPA_FILE) . '/languages/');

            wp_localize_script($this->token . '-backend', $this->token . '_object', $globalVars);
        }
        if ($screen->id == 'product') {
            wp_enqueue_script($this->token . '-product', esc_url($this->assets_url) . 'js/backend/product.js',
                array('wp-i18n'), $this->version, true);
            wp_set_script_translations($this->token . '-product', 'woo-custom-product-addons-pro',
                plugin_dir_path(WCPA_FILE) . '/languages/');

            wp_localize_script($this->token . '-product', $this->token . '_object', array(
                    'api_nonce' => wp_create_nonce('wp_rest'),
                    'root' => ($ml->is_active() && $ml->current_language() == 'all') ? str_replace('/all', '', rest_url($this->token . '/admin/')) : rest_url($this->token . '/admin/'),
                    'assets_url' => $this->assets_url,
                    'isMlActive' => $ml->is_active(),
                    'append_global_form' => Config::get_config('append_global_form'),
                    'ml' => $ml->is_active() ? [
                        'langList' => $ml->lang_list(),
                        'currentLang' => $ml->current_language(),
                        'defaultLang' => $ml->default_language(),
                        'isDefault' => $ml->is_default_lan() ? $ml->is_default_lan() : (($ml->current_language() === 'all') ? true : false)
                    ] : false
                )
            );
        }
        if (!in_array($screen->id, $this->hook_suffix) && $screen->id !== 'product') {
//        if ($screen->id == 'shop_order' || $screen->id == 'shop_subscription') {
            wp_enqueue_script('wp-i18n');
            wp_enqueue_script($this->token . '-order', esc_url($this->assets_url) . 'js/backend/order.js',
                array('wp-i18n'), $this->version, true);
            wp_set_script_translations($this->token . '-product', 'woo-custom-product-addons-pro',
                plugin_dir_path(WCPA_FILE) . '/languages/');


            wp_localize_script($this->token . '-order', $this->token . '_object', array(
                    'api_nonce' => wp_create_nonce('wp_rest'),
                    'root' => rest_url($this->token . '/admin/'),
                    'assets_url' => $this->assets_url,
                    'product_url' => admin_url('post.php?post={productId}') . '&action=edit',

                    'wc_currency_symbol' => get_woocommerce_currency_symbol(''),
                    'wc_thousand_sep' => wc_get_price_thousand_separator(),
                    'wc_price_decimals' => wc_get_price_decimals(),
                    'wc_price_format' => get_woocommerce_price_format(),
                    'wc_decimal_sep' => wc_get_price_decimal_separator(),
                    'wc_currency_pos' => get_option('woocommerce_currency_pos'),
                    'strings' => [
                        'street' => Config::get_config('place_selector_street'),
                        'city' => Config::get_config('place_selector_city'),
                        'state' => Config::get_config('place_selector_state'),
                        'zip' => Config::get_config('place_selector_zip'),
                        'country' => Config::get_config('place_selector_country'),
                        'latitude' => Config::get_config('place_selector_latitude'),
                        'longitude' => Config::get_config('place_selector_longitude'),
                    ]
                )
            );
        }

    }


    public function get_taxonomy_hierarchy($taxonomy, $parent = 0)
    {
        // only 1 taxonomy
        $taxonomy = is_array($taxonomy) ? array_shift($taxonomy) : $taxonomy;
        // get all direct decendants of the $parent
        $terms = get_terms($taxonomy, array('parent' => $parent, 'hide_empty' => false));
        // prepare a new array.  these are the children of $parent
        // we'll ultimately copy all the $terms into this new array, but only after they
        // find their own children
        $children = array();
        // go through all the direct decendants of $parent, and gather their children
        foreach ($terms as $term) {
            // recurse to get the direct decendants of "this" term
            $term->children = $this->get_taxonomy_hierarchy($taxonomy, $term->term_id);
            // add the term to our new array
            $children[] = $term;
//            $children[ $term->term_id ] = $term;
        }

        // send the results back to the caller
        return $children;
    }

    function this_screen()
    {
        $current_screen = get_current_screen();

        if ($current_screen->post_type === Form::$CPT) {
            $ml = new ML();

            if ($ml->is_active()) {
                if ($ml->is_default_lan() || $ml->is_all_lan()) {
                    if (isset($_GET['post'])) {
                        $post_id = $_GET['post'];

                        return $this->redirectMl($post_id);
                    } else {
                        return $this->redirectMl(0);
                    }
                } else {
                    if (isset($_GET['post'])) {
                        $post_id = $_GET['post'];
                        $lang_code = $ml->current_language();
                        $this->redirectMl($post_id, $lang_code);
                    } elseif ($ml->is_duplicating()) {
                        $from_post_id = $ml->from_post_id();
                        $new_lang_code = $ml->get_new_language();
                        // Create post object
                        $my_post = array(
                            'post_title' => '',
                            'post_type' => Form::$CPT,
                            'post_status' => 'draft',
                        );
// Insert the post into the database
                        $new_post_id = wp_insert_post($my_post);
                        $fb_json_value = $ml->default_fb_meta();
                        update_post_meta($new_post_id, Form::$META_KEY_2, $fb_json_value);
//                        pll_set_post_language($new_post_id, $lang_code);
                        $fromPostCode = pll_get_post_language($from_post_id, 'slug');

                        $translations = pll_get_post_translations($from_post_id);
                        $translations[$new_lang_code] = $new_post_id;
                        pll_save_post_translations($translations);

                        if ($from_post_id) {
                            return $this->redirectMl($new_post_id, $new_lang_code);
                        }
                    }
                }
            } else {
                echo '<p style="text-align: center;
    font-size: 18px;
    color: #000000;
    margin-top: 10%;" >' . sprintf(__('This menu has been changed in new version of Product addon plugin,
                 Please go to menu <a href="%s" />Product Addons</a> on left sidebar', 'woo-custom-product-addons-pro'), admin_url('admin.php?page=wcpa-admin-ui#/')) . '</p>';

                exit();
//                    $post_id = $_GET['post'];
//
//                    return $this->redirectMl($post_id);
//                } else {
//                    return $this->redirectMl(0);
//                }
            }


        }
    }

    function redirectMl($post_id, $lang = false)
    {
        if ($lang) {
            $url = 'admin.php?lang=' . $lang . '&page=wcpa-admin-ui#/form/' . $post_id . '/';
        } else {
            $url = 'admin.php?page=wcpa-admin-ui#/form/' . $post_id . '/';
        }
        $url = admin_url($url);
        wp_redirect($url);
    }

    /**
     * Load admin CSS.
     * @access  public
     * @return  void
     * @since   1.0.0
     */
    public function admin_enqueue_styles($hook = '')
    {
        if (!isset($this->hook_suffix) || empty($this->hook_suffix)) {
            return;
        }
        $screen = get_current_screen();
        wp_register_style($this->token . '-common',
            esc_url($this->assets_url) . 'css/backend/common.css', array(), $this->version);
        wp_enqueue_style($this->token . '-common');

        if (in_array($screen->id, $this->hook_suffix)) {
            wp_register_style($this->token . '-admin',
                esc_url($this->assets_url) . 'css/backend/main.css', array(), $this->version);
            wp_enqueue_style($this->token . '-admin');
        }
        if ($screen->id == 'product') {
            wp_register_style($this->token . '-product', esc_url($this->assets_url) . 'css/backend/product.css', array(),
                $this->version);
            wp_enqueue_style($this->token . '-product');
        }
//        if ($screen->id == 'shop_order' || $screen->id == 'shop_subscription') {
        if (!in_array($screen->id, $this->hook_suffix) && $screen->id !== 'product') {
            wp_register_style($this->token . '-order', esc_url($this->assets_url) . 'css/backend/order.css', array(),
                $this->version);
            wp_enqueue_style($this->token . '-order');
        }
    }

    public function add_menu()
    {
        $this->hook_suffix[] = add_menu_page(
            __('Custom Product Addons', 'woo-custom-product-addons-pro'),
            __('Product Addons', 'woo-custom-product-addons-pro'),
            'manage_woocommerce',
            $this->token . '-admin-ui',
            array($this, 'adminUi'),
            'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIHZpZXdCb3g9IjAgMCAxNiAxNiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGcgY2xpcC1wYXRoPSJ1cmwoI2NsaXAwXzEzODJfMTg1NSkiPgo8cGF0aCBmaWxsLXJ1bGU9ImV2ZW5vZGQiIGNsaXAtcnVsZT0iZXZlbm9kZCIgZD0iTTguNzYxNzIgM0g0Ljc2MTcyQzQuNzYxNzIgMS44OTcgNS42NTg3MiAxIDYuNzYxNzIgMUM3Ljg2NDcyIDEgOC43NjE3MiAxLjg5NyA4Ljc2MTcyIDNNMy43NjE3MiAzSDEuMjYxNzJDMC45ODUyMTkgMyAwLjc2MTcxOSAzLjIyNCAwLjc2MTcxOSAzLjVWMTUuNUMwLjc2MTcxOSAxNS43NzYgMC45ODUyMTkgMTYgMS4yNjE3MiAxNkgxMi4yNjE3QzEyLjUzODIgMTYgMTIuNzYxNyAxNS43NzYgMTIuNzYxNyAxNS41VjEzLjEyMUwxMi4zMjIyIDEzLjU2MDVDMTIuMDM5MiAxMy44NDQgMTEuNjYyMiAxNCAxMS4yNjE3IDE0SDkuNzYxNzJDOC45MzQ3MiAxNCA4LjI2MTcyIDEzLjMyNyA4LjI2MTcyIDEyLjVWMTFDOC4yNjE3MiAxMC41OTk1IDguNDE4MjIgMTAuMjIyNSA4LjcwMTIyIDkuOTM5NUwxMi43MDEyIDUuOTM5NUMxMi43MTk3IDUuOTIwNSAxMi43NDIyIDUuOTA3NSAxMi43NjE3IDUuODg5NVYzLjVDMTIuNzYxNyAzLjIyNCAxMi41MzgyIDMgMTIuMjYxNyAzSDkuNzYxNzJDOS43NjE3MiAxLjM0NTUgOC40MTYyMiAwIDYuNzYxNzIgMEM1LjEwNzIyIDAgMy43NjE3MiAxLjM0NTUgMy43NjE3MiAzWiIgZmlsbD0id2hpdGUiLz4KPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik0xMy4zMzE4IDYuODE3MDdMOS4yMjkzNiAxMC45MTk2QzkuMTMyOTUgMTEuMDE2IDkuMDc5MSAxMS4xNDYyIDkuMDc5MSAxMS4yODIxVjEyLjgyMDVDOS4wNzkxIDEzLjEwMzYgOS4zMDgzMyAxMy4zMzM0IDkuNTkxOTEgMTMuMzMzNEgxMS4xMzAzQzExLjI2NjggMTMuMzMzNCAxMS4zOTcgMTMuMjc5NSAxMS40OTI5IDEzLjE4MzFMMTUuNTk1NCA5LjA4MDYxQzE1Ljc5NTkgOC44ODAxMSAxNS43OTU5IDguNTU2MDEgMTUuNTk1NCA4LjM1NTVMMTQuMDU3IDYuODE3MDdDMTMuODU2NCA2LjYxNjU2IDEzLjUzMjMgNi42MTY1NiAxMy4zMzE4IDYuODE3MDciIGZpbGw9IndoaXRlIi8+CjwvZz4KPGRlZnM+CjxjbGlwUGF0aCBpZD0iY2xpcDBfMTM4Ml8xODU1Ij4KPHJlY3Qgd2lkdGg9IjE2IiBoZWlnaHQ9IjE2IiBmaWxsPSJ3aGl0ZSIvPgo8L2NsaXBQYXRoPgo8L2RlZnM+Cjwvc3ZnPgo=',
            25
        );
        $this->hook_suffix[] = add_submenu_page(
            $this->token . '-admin-ui/',
            __('Forms', 'woo-custom-product-addons-pro'),
            __('Forms', 'woo-custom-product-addons-pro'),
            'manage_woocommerce',
            $this->token . '-admin-ui',
            array($this, 'adminUi')
        );
        $this->hook_suffix[] = add_submenu_page(
            $this->token . '-admin-ui/',
            __('Designs', 'woo-custom-product-addons-pro'),
            __('Designs', 'woo-custom-product-addons-pro'),
            'manage_woocommerce',
            $this->token . '-admin-ui#/designs',
            array($this, 'adminUi')
        );
        $this->hook_suffix[] = add_submenu_page(
            $this->token . '-admin-ui/',
            __('Options Lists', 'woo-custom-product-addons-pro'),
            __('Options Lists', 'woo-custom-product-addons-pro'),
            'manage_woocommerce',
            $this->token . '-admin-ui#/options',
            array($this, 'adminUi')
        );
        $this->hook_suffix[] = add_submenu_page(
            $this->token . '-admin-ui/',
            __('Settings', 'woo-custom-product-addons-pro'),
            __('Settings', 'woo-custom-product-addons-pro'),
            'manage_woocommerce',
            $this->token . '-admin-ui#/settings',
            array($this, 'adminUi')
        );
    }

    /**
     * Calling view function for admin page components
     */
    public function adminUi()
    {
        if ($this->isWooActive) {
            echo(
                '<div id="' . $this->token . '_ui_root">
            <div class="' . $this->token . '_loader"><h1>' . __('Acowebs Custom Product Addon',
                    'woo-custom-product-addons-pro') . '</h1><p>' . __('Plugin is loading Please wait for a while..',
                    'woo-custom-product-addons-pro') . '</p></div>
            </div>'
            );
        } else {
            echo(
                '<div id="' . $this->token . '_ui_root">Product addon need WooCommerce to function</div>'
            );
        }
    }

    /**
     * WooCommerce not active notice.
     * @access  public
     * @return string Fallack notice.
     */
    public function notice_need_woocommerce()
    {
        $error = sprintf(__(WCPA_PLUGIN_NAME . ' requires %sWooCommerce%s to be installed & activated!',
            'woo-custom-product-addons-pro'), '<a href="http://wordpress.org/extend/plugins/woocommerce/">', '</a>');
        $message = '<div class="error"><p>' . $error . '</p></div>';
        echo $message;
    }
}
