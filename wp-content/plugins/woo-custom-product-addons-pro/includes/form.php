<?php

namespace Acowebs\WCPA;


use StdClass;
use WP_Query;
use function get_post_meta;


/**
 * Handling Form related functions
 *
 */
class Form
{

    static $CPT = "wcpa_pt_forms";
    static $META_KEY_1 = "_wcpa_fb-editor-data";
    static $META_KEY_2 = "_wcpa_fb_json_data";
    static $META_FORMULA_KEY = "_wcpa_fb_formula_data";

    public $settings;

    private $ml;

    /**
     * Class Constructor
     *
     */

    public function __construct()
    {
        $this->ml = new ML();
    }

    /**
     * for showing form selector in backend
     */
    public function forms_list()
    {
        if ($this->ml->is_active()) {
            $posts = $this->ml->get_original_forms();
        } else {
            $args = [
                'post_type' => self::$CPT,
                'posts_per_page' => -1,
                'post_status' => ['draft', 'publish']
            ];
            $posts = get_posts($args);
        }


        $forms = [];
        foreach ($posts as $f) {
            $forms[] = [
                'form_id' => $f->ID,
                'title' => html_entity_decode($f->post_title),
                'post_status' => $f->post_status,
                'sections' => $this->getFormSections($f->ID)
            ];
        }

        return $forms;
    }

    public function getFormSections($form_id)
    {
        $value = get_post_meta($form_id, self::$META_KEY_2, true);
        $sections = [];
        if ($value == '') {
            // check if the wcpa 1.0 meta key exists
            $value = get_post_meta($form_id, self::$META_KEY_1, true);
            $json_decode = json_decode($value);
            if (is_array($json_decode)) {
                $sections[] = ['section_id' => 'temp_id', 'name' => 'Default'];
            }
        } else {
            $json_decode = json_decode($value);
            if ($json_decode && is_object($json_decode)) {
                foreach ($json_decode as $key => $section) {
                    $sections[] = ['section_id' => $key, 'name' => $section->extra->name];
                }
            }
        }


        return $sections;
    }

    /**
     *  To ensure the post_type in QP_Query has not modified.
     * Some customers writing custom codes to filter out 'posts' from front end search by setting post type 'product'
     * This can cause issue it rest api requests for forms, options fetching
     * @param $query
     * @return mixed
     */
    public function suppress_filters($query)
    {
        $query->set('post_type', array(self::$CPT));
        $query->set('post__in', array());
        return $query;
    }

    public function get_forms($tab, $page = 1, $per_page = 20, $search = '')
    {

        $this->init(); // added as wpml listing all languages

        $args = [
            'post_type' => self::$CPT,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => array('publish', 'draft'),
            's' => $search,

            //    'lang'=>'en',
//            'suppress_filters' => true // set false avoid listing all translation for wpml
        ];
        if ($this->ml->is_active()) {
            $args = $this->ml->listArgs($args);
        }

        if ($tab == 'trash') {
            $args['post_status'] = 'trash';
        }
        add_filter('pre_get_posts', array($this, 'suppress_filters'), 999, 1);
        $posts = new WP_Query($args);
        remove_filter('pre_get_posts', array($this, 'suppress_filters'), 999);

        $forms = [];
        if ($posts->have_posts()): while ($posts->have_posts()) {
            $posts->the_post();
            if ((int)$search != get_the_ID()) {
                $products_list = $this->products_listing(get_the_ID());
                $p = [
                    'id' => get_the_ID(),
                    'title' => html_entity_decode(get_the_title()),
                    'categories' => wp_get_post_terms(get_the_ID(), 'product_cat'),
                    'active' => get_post_status() === 'publish',
                    'post_parent' => wp_get_post_parent_id(get_the_ID()),
                    'author' => get_the_author(),
                    'products' => $products_list['products'],
                ];
                if ($this->ml->is_active()) {
                    $p['translations'] = $this->ml->get_post_translations_links(get_the_ID());
                    $p['lang'] = $this->ml->get_post_language(get_the_ID());
                }
                $forms[] = $p;
            }
        } endif;
        wp_reset_postdata();

        if ($search != '' && (int)$search != 0) {
            $args_by_id = [
                'post_type' => self::$CPT,
                'post_status' => array('publish', 'draft'),
                'p' => $search,
            ];
            if ($this->ml->is_active()) {
                $args_by_id = $this->ml->listArgs($args_by_id);
            }
            if ($tab == 'trash') {
                $args_by_id['post_status'] = 'trash';
            }
            add_filter('pre_get_posts', array($this, 'suppress_filters'), 999, 1);
            $post_by_id = new WP_Query($args_by_id);

            remove_filter('pre_get_posts', array($this, 'suppress_filters'), 999);

            if ($post_by_id->have_posts()): while ($post_by_id->have_posts()) {
                $post_by_id->the_post();
                $products_list = $this->products_listing(get_the_ID());
                $p_by_id = [
                    'id' => get_the_ID(),
                    'title' => html_entity_decode(get_the_title()),
                    'categories' => wp_get_post_terms(get_the_ID(), 'product_cat'),
                    'active' => get_post_status() === 'publish' ? true : false,
                    'post_parent' => wp_get_post_parent_id(),
                    'author' => get_the_author(),
                    'products' => $products_list['products'],
                ];
                if ($this->ml->is_active()) {
                    $p_by_id['translations'] = $this->ml->get_post_translations_links(get_the_ID());
                    $p_by_id['lang'] = $this->ml->get_post_language(get_the_ID());
                }
                $forms[] = $p_by_id;
            } endif;
            wp_reset_postdata();
        }

        return ['forms' => $forms, 'totalForms' => $posts->found_posts, 'totalPages' => $posts->max_num_pages];
    }

    public function init()
    {
        $this->register_cpt();
    }

    /**
     * Register Custom Post Type
     *
     */
    public function register_cpt()
    {
        $labels = array(
            'name' => _x('Product Forms ', 'Form Custom Post Type Name', "woo-custom-product-addons-pro"),
            'singular_name' => _x('Product Form ', 'Form Custom Post Type Name', "woo-custom-product-addons-pro"),
            'name_admin_bar' => _x('Product Form ', 'Form Custom Post Type Name', "woo-custom-product-addons-pro"),
            'add_new' => __('Add New Product Form', 'woo-custom-product-addons-pro'),
            'add_new_item' => __('Add New Form', "woo-custom-product-addons-pro"),
            'edit_item' => __('Edit Form', "woo-custom-product-addons-pro"),
            'new_item' => __('New Form', "woo-custom-product-addons-pro"),
            'all_items' => __('Custom Product Options', "woo-custom-product-addons-pro"),
            'view_item' => __('View Form', "woo-custom-product-addons-pro"),
            'search_items' => __('Search Form', "woo-custom-product-addons-pro"),
            'not_found' => __('No Form Found', "woo-custom-product-addons-pro"),
            'not_found_in_trash' => __('No Form Found In Trash', "woo-custom-product-addons-pro"),
            'parent_item_colon' => __('Parent Form', "woo-custom-product-addons-pro"),
            'menu_name' => 'Custom Product Options'
        );

        $args = array(
            'labels' => apply_filters(self::$CPT . '_labels', $labels),
            'description' => '',
            'public' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_ui' => false,
            'show_in_menu' => 'edit.php?post_type=product',
            'show_in_nav_menus' => false,
            'query_var' => false,
            'can_export' => true,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'rest_base' => self::$CPT,
            'hierarchical' => false,
            'show_in_rest' => false,
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'supports' => array('title', 'author'),
            'menu_position' => 5,
            'menu_icon' => 'dashicons-admin-post',
            'taxonomies' => array('product_cat')
        );

        register_post_type(self::$CPT, apply_filters(self::$CPT . '_register_args', $args, self::$CPT));
    }

    /**
     * Products Listing in Form Detail Page
     *
     * @param int $form_id
     *
     * @return array $response
     */
    public function products_listing($form_id, $returnIds = false)
    {
        $response = ['status' => true, 'id' => $form_id, 'products' => []];

        if ($form_id == 0) {
            return $response;
        }
        if ($this->ml->is_active()) {
            $form_id = $this->ml->base_form($form_id);
        }

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => WCPA_PRODUCT_META_KEY,
                    'value' => 'i:' . $form_id . ';',
                    'compare' => 'LIKE',
                )
            )
        );

        $prolist = get_posts($args);

        $products = [];
        if (is_array($prolist)) {
            foreach ($prolist as $v) {
                $products[] = [
                    'id' => $v->ID,
                    'title' => html_entity_decode(get_the_title($v)) . ' (' . $v->ID . ')',
                    'link' => get_the_permalink($v)
                ];
            }
        }
        if ($returnIds) {
            return array_map(function ($v) {
                return $v['id'];
            }, $products);
        }
        $response['products'] = $products;

        return $response;
    }

    /**
     * @param $formula
     * @param $form_id
     * @param $_from_id ( in case of Global form, each field has its own form id as well)
     *
     * @return string
     */
    public function replace_vars($formula, $form_id, $_from_id)
    {
        if (is_string($formula) && preg_match_all('/{(\s)*?var\.([^}]*)}/',
                $formula, $matches)) {
            //TODO need take consideration of $_from_id as well,
            $json_string = get_post_meta($form_id, self::$META_FORMULA_KEY, true);


            $jsonDecoded = json_decode($json_string);
            if (!$jsonDecoded) {
                return $formula;
            }
            $variables = is_array($jsonDecoded->variables) ? $jsonDecoded->variables : [];
            if ($_from_id) {
                $_json_string = get_post_meta($_from_id, self::$META_FORMULA_KEY, true);
                $_jsonDecoded = json_decode($_json_string);
                if ($_jsonDecoded) {
                    $_variables = is_array($_jsonDecoded->variables) ? $_jsonDecoded->variables : [];
                    $variables = array_merge($_variables, $variables);
                }
            }

            foreach ($matches[2] as $k => $match) {
                $var = trim($match);
                $variable = array_filter($variables, function ($v) use ($var) {
                    return $v->key == $var;
                });
                if (count($variable)) {
                    $_variable = array_values($variable);
                    $varValue = $_variable[0]->value;
                } else {
                    $varValue = '';
                }
                if (!is_numeric($varValue) && $varValue !== '') {
//                    $varValue = '('.$varValue.')';
                    $varValue = $varValue;
                }
                $formula = str_replace($matches[0][$k], $varValue, $formula);
            }
        }

        return $formula;
    }

    public function get_formulas($form_id, $returnVersion = false)
    {
        $json_string = get_post_meta($form_id, self::$META_FORMULA_KEY, true);
        $jsonDecoded = json_decode($json_string);
        $formulas = [];
        if (!$jsonDecoded) {
            return $formulas;
        }
        $variables = is_array($jsonDecoded->variables) ? $jsonDecoded->variables : [];

        if (isset($jsonDecoded->formulas) && is_array($jsonDecoded->formulas)) {
            foreach ($jsonDecoded->formulas as $formula) {
                $formulaString = $formula->formulaString;
                /** replace variables in formula */
                if (is_string($formula->formulaString) && preg_match_all('/\{(\s)*?var\.([^}]*)}/',
                        $formula->formulaString, $matches)) {
                    foreach ($matches[2] as $k => $match) {
                        $var = trim($match);
                        $variable = array_filter($variables, function ($v) use ($var) {
                            return $v->key == $var;
                        });
                        if (count($variable)) {
                            $_variable = array_values($variable);
                            $varValue = $_variable[0]->value;
                        } else {
                            $varValue = '';
                        }
                        if (!is_numeric($varValue) && $varValue !== '') {
                            $varValue = '(' . $varValue . ')';
                        }
                        $formulaString = str_replace($matches[0][$k], $varValue, $formulaString);
                    }
                }
                $formulas[$formula->id] = $formulaString;
            }
        }

        return $formulas;
    }

    public function toRowCol($json_decode)
    {
        $newArray = array();
        $row = 0;
        $col = 0;
        foreach ($json_decode as $i => $item) {
            $newItem = $item;
            $newItem->active = true;

            if (($col + $newItem->col) > 6) {
                $row++;
                $col = $newItem->col;
            } else {
                $col += $newItem->col;
            }
            $newArray[$row][] = $newItem;
        }


        return $newArray;
    }

    public function translate_form($post_id, $newLang)
    {
        $this->init();
        // check if post has already translation in the same lang
        $langList = $this->ml->get_post_translations_links($post_id);

        $base_form_id = $this->ml->base_form($post_id);

        //check $newLang if in $langList object array
        foreach ($langList as $l) {
            if ($l['code'] == $newLang) {
                // a translation already exists in this lang, so redirect to that form
                return ['status' => true, 'new_post_id' => $l['post_id']];
            }
        }
        // creating a new form with details from base form;
        $originalPost = get_post($base_form_id);
        $title = $originalPost->post_title . ' - ' . $newLang;
        /**  get_the_title(  ) converts special characters */
        $formJson = get_post_meta($base_form_id, self::$META_KEY_2, true);
        $formJson = wp_slash(json_encode(json_decode($formJson)));
        $formulaData = get_post_meta($base_form_id, self::$META_FORMULA_KEY, true);
        $new_post_id = $this->insert($title, $formJson, $formulaData, $newLang, $base_form_id);
        if ($new_post_id) {
            $currentSettings = new FormSettings($base_form_id);
            $settings = new FormSettings($new_post_id);
            $settings->insert($currentSettings->getValues());

            return [
                'status' => true,
                'new_post_id' => $new_post_id,
                // 'redirect'    => get_edit_post_link($new_post_id, 'link')
                'redirect' => admin_url('admin.php?page=wcpa-admin-ui#/form/' . $new_post_id)
            ];
        }

        return ['status' => false];
    }

    public function insert($title, $formJson, $formulaJson, $lang = false, $base_lang_id = false,$modDate=false)
    {
        $my_post = array(
            'post_title' => $title,
            'post_type' => self::$CPT,
            'post_status' => 'publish',
        );
        // Insert the post into the database
        $post_id = wp_insert_post($my_post);

        if ($lang) {
            $this->ml->set_post_lang($post_id, $lang, $base_lang_id);
        }

        update_post_meta($post_id, self::$META_KEY_2, $formJson);
        update_post_meta($post_id, self::$META_FORMULA_KEY, $formulaJson);

        return $post_id;
    }

    public function change_form_lang($post_id, $lang)
    {
        $this->ml->set_post_lang($post_id, $lang);
        $response = [
            'status' => true
            // 'redirect' => get_edit_post_link($post_id, 'link')
        ];

        return $response;
    }

    public function delete_form($posts)
    {
        $response = array();
        if (is_array($posts)) {
            foreach ($posts as $post_id) {
                $post_type = get_post_type($post_id);
                $status = false;
                if ($post_type === Form::$CPT) {
                    $status = wp_delete_post($post_id);
                }
                if ($status) {
                    $response[$post_id] = ['status' => true];
                } else {
                    $response[$post_id] = ['status' => false];
                }
            }
        }

        return $response;
    }

    public function trash_form($posts)
    {
        $response = array();
        if (is_array($posts)) {
            foreach ($posts as $post_id) {
                $post_type = get_post_type($post_id);
                $status = false;
                if ($post_type === Form::$CPT) {
                    $status = wp_trash_post($post_id);
                }

                if ($status) {
                    $response[$post_id] = ['status' => true];
                } else {
                    $response[$post_id] = ['status' => false];
                }
            }
        }
        refreshCaches();
        return $response;
    }

    public function restore_forms($posts)
    {
        $response = array();
        if (is_array($posts) && !empty($posts)) {
            foreach ($posts as $post_id) {
                $status = wp_untrash_post($post_id);
                if ($status) {
                    $response[$post_id] = ['status' => true];
                } else {
                    $response[$post_id] = ['status' => false];
                }
            }
        }
        refreshCaches();
        return $response;
    }

    public function duplicate_form($form_id)
    {
        $response = array('status' => false);
        if ($form_id) {
            $_duplicate = get_post($form_id);

            if (!isset($_duplicate->post_type) || $_duplicate->post_type !== self::$CPT) {
                return ['status' => false];
            }


            $duplicate['post_title'] = $_duplicate->post_title . ' ' . __('Copy', 'woo-custom-product-addons-pro');
            $duplicate['post_type'] = self::$CPT;

            $duplicate_id = wp_insert_post($duplicate);


            $json_decode = $this->get_form_meta_data($form_id);
            $old_ids = array();
            $dupJson = new StdClass();
            if ($json_decode && is_object($json_decode)) {
                /* Finding old elementIds that need to be replaced in formula, relations and in other field bind  fields */
                foreach ($json_decode as $key => $section) {
                    $section->extra->key = 'sec_' . uniqSectionId();
                    $section->extra->section_id = $section->extra->key;

                    foreach ($section->fields as $i => $row) {
                        foreach ($row as $j => $field) {
                            if (isset($field->elementId)) {
                                $_tmp = $field->elementId;
                                $field->elementId = sanitize_title($field->type) . '_' . uniqSectionId();
                                $old_ids[$_tmp] = $field->elementId;
                                //to replace id in relation
                            }
                            if (isset($field->name)) {
                                $field->name = $field->elementId;
                            }
                        }
                    }

                    $dupJson->{$section->extra->key} = $section;
                }


                /** chnaged this methods, and directly replaced ids in jsonString */
//                /* Change Section Relation fields with new IDs */
//                foreach ($json_decode as $key => $section) {
//                    $section->extra->key        = 'sec_'.uniqSectionId();
//                    $section->extra->section_id = $section->extra->key;
//                    if (isset($section->extra->relations) && is_array($section->extra->relations)) {
//                        foreach ($section->extra->relations as $rel) {
//                            if (isset($rel->rules) && is_array($rel->rules)) {
//                                foreach ($rel->rules as $rul) {
//                                    if (isset($rul->rules->cl_field) && isset($old_ids[$rul->rules->cl_field])) {
//                                        $rul->rules->cl_field = $old_ids[$rul->rules->cl_field];
//                                    }
//                                }
//                            }
//                        }
//                    }
//
//                    foreach ($section->fields as $i => $row) {
//                        foreach ($row as $j => $field) {
//                            /* Change Field  Relation fields with new IDs */
//                            if (isset($field->relations) && is_array($field->relations)) {
//                                foreach ($field->relations as $rel) {
//                                    if (isset($rel->rules) && is_array($rel->rules)) {
//                                        foreach ($rel->rules as $rul) {
//                                            if (isset($rul->rules->cl_field) && isset($old_ids[$rul->rules->cl_field])) {
//                                                $rul->rules->cl_field = $old_ids[$rul->rules->cl_field];
//                                            }
//                                        }
//                                    }
//                                }
//                            }
//                            /* Change Custom Formula fields with new IDs */
//                            if (isset($field->enablePrice) && $field->pricingType == 'custom') {
//                                $field->price = $this->replaceOldIds($field->price, $old_ids);
//                            }
//                            /*Repeater Bind filed */
//                            if (isset($field->repeater_bind_field) && isset($old_ids[$field->repeater_bind_field])) {
//                                $field->repeater_bind_field = $old_ids[$field->repeater_bind_field];
//                            }
//
//                            /* GroupValidation Field Types */
//                            if ($field->type == 'groupValidation' && isset($field->fields) && is_array($field->fields)) {
//                                foreach ($field->fields as $k => $v) {
//                                    if (isset($old_ids[$v])) {
//                                        $field->fields[$k] = $old_ids[$v];
//                                    }
//                                }
//                            }
//                        }
//                    }
//                }
            }

            $jsonString = json_encode($dupJson);
            $jsonString = $this->replaceOldIds($jsonString, $old_ids);
            update_post_meta($duplicate_id, self::$META_KEY_2, wp_slash($jsonString));


            $baseSettingsObject = new FormSettings($form_id);
            $dupSettingsObject = new FormSettings($duplicate_id);
            $settingsValues = $baseSettingsObject->getValues();
            if (isset($settingsValues['quantity_bind_formula']) && !empty($settingsValues['quantity_bind_formula'])) {
                $settingsValues['quantity_bind_formula'] = $this->replaceOldIds($settingsValues['quantity_bind_formula'],
                    $old_ids);
            }
            $dupSettingsObject->insert($settingsValues);

            $formulaData = get_post_meta($form_id, self::$META_FORMULA_KEY, true);
            $fb_formula_json = wp_slash(json_encode($formulaData));
            $fb_formula_json = $this->replaceOldIds($fb_formula_json, $old_ids);

            update_post_meta($duplicate_id, self::$META_FORMULA_KEY, $formulaData);
            $p = [
                'id' => $duplicate_id,
                'title' => $duplicate['post_title'],
                'categories' => array(),
                'active' => get_post_status($duplicate_id) === 'publish',
                'post_parent' => wp_get_post_parent_id($duplicate_id)
            ];
            if ($this->ml->is_active()) {
                $p['translations'] = $this->ml->get_post_translations_links($duplicate_id);
                $p['lang'] = $this->ml->get_post_language($duplicate_id);
            }
            $response = ['status' => true, 'item' => $p];

            return $response;
        }

        return $response;
    }

    public function get_form_meta_data($form_id, $returnVersion = false)
    {
        $json_string = get_post_meta($form_id, self::$META_KEY_2, true);

        $isOlder = false;
        if ($json_string == '') {
            $json_string = get_post_meta($form_id, self::$META_KEY_1, true);
            $json_decode = json_decode($json_string);
            if ($json_string !== '' && is_array($json_decode)) {
                $isOlder = true;
                $json_decode = toRowCol($json_decode);
                $json_decode = generateSectionFields($json_decode);
                $migrate = new Migration();
                $migrate->fieldMigrationsToV5($json_decode, $form_id);
            }
        } else {
            $json_decode = json_decode($json_string);
        }


        return $returnVersion ? ['data' => $json_decode, 'isOlder' => $isOlder] : $json_decode;
    }

    public function replaceOldIds($str, $ids)
    {
        foreach ($ids as $old => $new) {
            $str = str_replace($old, $new, $str);
        }

        return $str;
    }

    public function update_form_status($post_id, $status = 'publish')
    {
        $response = array();
        if ($post_id) {
            $form = array(
                'ID' => $post_id,
                'post_status' => $status,
            );
            $status = wp_update_post($form);
            if ($status) {
                $response = ['status' => true];
            } else {
                $response = ['status' => false];
            }
        }
        refreshCaches($post_id);
        return $response;
    }

    public function save_form($post_id, $post_data)
    {
        $this->init();

        $response = ['status' => true, 'id' => $post_id, 'redirect' => false];
        $allowedHtml = array(
            'a' => array(// on allow a tags
                'href' => true, // and those anchors can only have href attribute
                'target' => true,
                'class' => true,// and those anchors can only have href attribute
                'style' => true
            ),
            'b' => array('style' => true, 'class' => true),
            'strong' => array('style' => true, 'class' => true),
            'i' => array('style' => true, 'class' => true),
            'img' => array('style' => true, 'class' => true, 'src' => true),
            'span' => array('style' => true, 'class' => true),
            'p' => array('style' => true, 'class' => true)
        );

//        $fb_data = $post_data['fields'];
        $fb_data = json_decode((base64_decode($post_data['fields'])));
        if($fb_data===null){ // in case json decode failed, return from here , to avoid flushing data
            $response = ['status' => false, 'id' => $post_id, 'redirect' => true];
            return $response;
        }
        $formulaData = $post_data['formulaData'];

        $post = $post_data['post'];
        $title = $post['title'];
        $modDate = $post['modDate'];

        sanitizeFields($fb_data, $allowedHtml);
        $this->replaceTags($fb_data);

        /** filter out draft sections */
        $fb_data = array_filter((array)$fb_data, function ($data) {
            return $data->extra->status >= 0;
        });

        $fb_data_json = wp_slash(json_encode($fb_data));
        $fb_formula_json = wp_slash(json_encode($formulaData));


        $lang = false;
        if ($this->ml->is_active()) {
            $lang = $post['lang'];
        }

        if ($post_id === 0) {
            $new_post_id = $this->insert($title, $fb_data_json, $fb_formula_json, $lang,$modDate);
            $response['id'] = $new_post_id;
            $link = admin_url('admin.php?page=wcpa-admin-ui#/form/' . $new_post_id);
            // $response['redirect'] = get_edit_post_link($new_post_id, 'link');
            $response['redirect'] = $link;
            $post_id = $new_post_id;
        } else {
            $this->update($post_id, $title, $fb_data_json, $fb_formula_json, $lang,$modDate);
        }

        /**
         * set form categories
         */
        wp_set_post_terms($post_id, $post['categories'], 'product_cat');

        $postSettings = $post['settings'];
        $settings = new FormSettings($post_id);
        $settings->save($postSettings);


        if ($this->ml->is_active()) {
            $this->ml->sync_data($post_id);
        }

        refreshCaches($post_id);


        Cron::schedule_cron();

        return $response;
    }

    public function replaceTags(&$fb_data, $reverse = false)
    {
        foreach ($fb_data as $sectionKey => $section) {
            if (isset($section->fields)) {
                $fields = $section->fields;
            } else {
                $fields = $section['fields'];
            }
            foreach ($fields as $rowIndex => $row) {
                foreach ($row as $colIndex => $field) {
                    if (isset($fb_data->{$sectionKey}->fields)) {
                        $_field = &$fb_data->{$sectionKey}->fields[$rowIndex][$colIndex];
                    } else {
                        $_field = &$fb_data[$sectionKey]['fields'][$rowIndex][$colIndex];
                    }

                    $tagReplace = [
                        'minQuantityError' => '{minQuantity}',
                        'maxQuantityError' => '{maxQuantity}',

                        'minFieldsError' => '{minOptions}',
                        'maxFieldsError' => '{maxOptions}',

                        'groupMinError' => '{minValue}',
                        'groupMaxError' => '{maxValue}',

                        'minValueError' => '{minValue}',
                        'maxValueError' => '{maxValue}',

                        'charleftMessage' => '{charLeft}',

                        'minlengthError' => '{minLength}',
                        'maxlengthError' => '{maxLength}',

                        'patternError' => '{pattern}',
                        'allowedCharsError' => '{characters}',

                        'minFileCountError' => '{minFileCount}',
                        'maxFileCountError' => '{maxFileCount}',

                        'minFileSizeError' => '{minFileSize}',
                        'maxFileSizeError' => '{maxFileSize}',

                        'uploadError' => '{fileName}'
                    ];
                    if ($reverse) {
                        foreach ($tagReplace as $key => $tag) {
                            if (isset($_field->{$key}) && !empty($_field->{$key})) {
                                $_field->{$key} = str_ireplace('%s', $tag, $_field->{$key});
                            }
                        }
                    } else {
                        foreach ($tagReplace as $key => $tag) {
                            if (isset($_field->{$key}) && !empty($_field->{$key})) {
                                $_field->{$key} = str_ireplace($tag, '%s', $_field->{$key});
                            }
                        }
                    }
                }
            }
        }
    }

    public function update($post_id, $title, $formJson, $formulaJson, $lang = false,$modDate=false)
    {
        update_post_meta($post_id, self::$META_KEY_2, $formJson);
        update_post_meta($post_id, self::$META_FORMULA_KEY, $formulaJson);
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $title,
            'post_status' => 'publish',
        ));
        if($modDate){
            try {
                $dateTime = new \DateTime($modDate);
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_date'=>$dateTime->format('Y-m-d H:i:s')
                ));
            } catch (Exception $e) {

            }

        }
        if ($lang) {
            $this->ml->set_post_lang($post_id, $lang);
//            pll_set_post_language($post_id, $lang);
        }
    }

    /**
     * merging form fields with different languages
     *
     * @param $base_id form base language form id,
     * @param $tran_id
     *
     * @return array|string
     */
    public function merge_meta($base_id, $tran_id)
    {
//		$original_json = get_post_meta( $base_id, self::$META_KEY_2, true );
//		$trans_json    = get_post_meta( $tran_id, self::$META_KEY_2, true );
        $original = $this->get_form_meta_data($base_id);

        $trans = $this->get_form_meta_data($tran_id);
//		$original = json_decode( $original_json );
//		$trans    = json_decode( $trans_json );

        if ($original && $trans) {
            foreach ($original as $key => $section) {
                foreach ($section->fields as $i => $row) {
                    foreach ($row as $j => $col) {
                        $flag = false;
                        foreach ($trans->{$key}->fields as $_i => $_row) {
                            foreach ($_row as $_j => $_col) {
                                if ($_col->elementId == $col->elementId) {
                                    $original->{$key}->fields[$i][$j] = $this->merge_data($col, $_col);

                                    $flag = true;
                                    break;
                                }
                            }
                            if ($flag) {
                                break;
                            }
                        }
                    }
                }


                foreach (
                    [
                        'name',
                        'repeater_add_label',
                        'repeater_remove_label',
                        'repeater_section_label'
                    ] as $k
                ) {
                    if (isset($trans->{$key}->extra->{$k})) {
                        $original->{$key}->extra->{$k} = $trans->{$key}->extra->{$k};
                    }
                }


                /** relations will be sync with base language. in case needed different sting for value, use is_in feature and
                 * add multiple strings
                 */
//                if (isset($original->{$key}->extra->relations) && is_array($original->{$key}->extra->relations)
//                    && isset($trans->{$key}->extra->relations) && is_array($trans->{$key}->extra->relations)) {
//                    foreach ($original->{$key}->extra->relations as $l => $rel) {
//                        if (isset($rel->rules) && is_array($rel->rules) && isset($trans->{$key}->extra->relations[$l]->rules) && is_array($trans->{$key}->extra->relations[$l]->rules)) {
//                            foreach ($rel->rules as $i => $rule) {
//                                if (isset($trans->{$key}->extra->relations[$l]->rules[$i]->rules->cl_val)
//                                    && ! isEmpty($trans->{$key}->extra->relations[$l]->rules[$i]->rules->cl_val)) {
//                                    $rule->rules->cl_val = $trans->{$key}->extra->relations[$l]->rules[$i]->rules->cl_val;
//                                }
//                            }
//                        }
//                    }
//                }
            }
        }
        $fb_data_json = wp_slash(json_encode($original));
        update_post_meta($tran_id, self::$META_KEY_2, $fb_data_json);
    }

    /**
     *  Merge each fields data with translated version, here it limits to certain fields only, not syncing all fields,
     * only fields which are translatable are synced
     *
     * @param $base_data
     * @param $trans_data
     *
     * @return mixed
     */
    public function merge_data($base_data, $trans_data)
    {
        $keys = array(
            'label',
            'description',
            'placeholder',
            'tooltip',
            'fee_label',
            'repeater_section_label',
            'repeater_add_label',
            'repeater_remove_label',
            'repeater_field_label',
            'requiredError',
            'validEmailError',
            'validUrlError',
            'other_text',
            'minFieldsError',
            'maxFieldsError',
            'groupMinError',
            'groupMaxError',
            'otherFieldError',
            'maxFileCountError',
            'minFileCountError',
            'maxFileSizeError',
            'minFileSizeError',
            'fileExtensionError',
            'minQuantityError',
            'maxQuantityError',


            'quantityRequiredError',
            'allowedCharsError',
            'maxlengthError',
            'minlengthError',
            'minValueError',
            'maxValueError',
            'patternError',
            'charleftMessage',

            'value',
            'wpml_sync',
            'quantity_label',
            'cartLabel',
            'check_value'
        );
        $options = array(
            'label',
            'image',
            'color',
            'tooltip',
            'description'


        );
        foreach ($keys as $key => $val) {
            if (isset($trans_data->{$val}) && !isEmpty($trans_data->{$val})) {
                $base_data->{$val} = $trans_data->{$val};
            }
        }

        if (isset($trans_data->values) && (!isset($trans_data->wpml_sync) || !$trans_data->wpml_sync)) { //$trans_data->values
            $i=0;
            foreach ($trans_data->values as $k => $v) {  // $trans_data->values as $k=>$v ( )
                foreach ($options as $ke => $va) { //   0=>label, 1=>value,2=>image
                    if (isset($v->{$va}) && !isEmpty($v->{$va}) && isset($base_data->values[$i]) && $v->value == $base_data->values[$i]->value) { // $trans_data->values items, $item->label, $item->value, so on
                        $base_data->values[$i]->{$va} = $v->{$va};
                        $i++;
                    }
                }
            }
        }
        /** relations will be sync with base language. in case needed different sting for value, use is_in feature and
         * add multiple strings
         */
//        if (isset($base_data->relations) && is_array($base_data->relations)) {
//            foreach ($base_data->relations as $l => $rel) {
//                if (isset($rel->rules) && is_array($rel->rules)) {
//                    foreach ($rel->rules as $i => $rule) {
//                        if (isset($trans_data->relations[$l]->rules[$i]->rules->cl_val) && ! isEmpty($trans_data->relations[$l]->rules[$i]->rules->cl_val)) {
//                            $rule->rules->cl_val = $trans_data->relations[$l]->rules[$i]->rules->cl_val;
//                        }
//                    }
//                }
//            }
//        }

        return $base_data;
    }

    /**
     * @return string
     */
    function get_post_meta($pos_id, $key, $default = false)
    {
        $settings = get_post_meta($pos_id, WCPA_META_SETTINGS_KEY, true);

        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Export only forms to a JSON file
     *
     * @param int $form_id
     *
     * @return array $result
     */
    public function export_form($form_id)
    {
        $form_data = $this->get_fields($form_id);

        $post = $form_data['post'];
        $fields = $form_data['fields'];
        $formulaData = $form_data['formulaData'];
        $categories = $post['categories'];
        $settings = $post['settings'];

        $this->download_send_headers("wcpa-form-" . $form_id . "-" . date('d-m-Y') . ".json");

        ob_start();
        $df = fopen("php://output", 'w');

        $result = [];
        $result['title'] = get_the_title($form_id);

        if (isset($categories) && !empty($categories)) {
            $categoryArray = [];
            foreach ($categories as $category) {
                $cat = get_term_by('id', $category, 'product_cat');

                $object = (object)['term_id' => $cat->term_id, 'name' => $cat->name, 'slug' => $cat->slug];
                $categoryArray[] = $object;
            }
            $result['categories'] = $categoryArray;
        }

        $result['fields'] = $fields;
        $result['formulaData'] = $formulaData;
        $result['settings'] = $settings;

        $finalArray = array($result);
        fwrite($df, json_encode($finalArray));

        fclose($df);
        ob_flush();
        exit;
    }

    public function get_fields($form_id)
    {
        $response = ['post' => ['title' => ''], 'fields' => []];

        $post = get_post($form_id);
        if (!function_exists('wp_terms_checklist')) {
            include ABSPATH . 'wp-admin/includes/template.php';
        }
        $settings = new FormSettings($form_id);
        if ($post) {
            $response['post'] = array(
                'title' => $post->post_title,
                'id' => $post->ID,
                'date' => get_the_date('M d, Y H:i',$post),
                'modDate' => false,
                'categories' => wp_get_post_terms($post->ID, 'product_cat', ['fields' => 'ids']),
                'settings' => $settings->getValues(true)

            );
            if ($this->ml->is_active()) {
                $postLang = $this->ml->get_post_language($post->ID);
                $response['post']['lang'] = $postLang == false ? $this->ml->default_language() : $postLang;
                $response['post']['translations'] = $this->ml->get_post_translations_links($post->ID);
            }
        } else {
            $response['post'] = array(
                'title' => '',
                'id' => 0,
                'date' => '',
                'modDate' => false,
                'categories' => [],
                'settings' => $settings->getValues(true),

            );
            if ($this->ml->is_active()) {
                $response['post']['lang'] = $this->ml->default_language(); // always set default language for new posts
                $response['post']['translations'] = [];
            }
        }


        $data = $this->get_form_meta_data($form_id, true);
        $json_decode = $data['data'];
        $json_formula_string = get_post_meta($form_id, self::$META_FORMULA_KEY, true);

        $response['post']['isOlder'] = $data['isOlder'];
//        $value = get_post_meta($form_id, self::$META_KEY_2, true);
//
//        if ($value == '') {
//            // check if the wcpa 1.0 meta key exists
//            $value = get_post_meta($form_id, self::$META_KEY_1, true);
//        }
//
//        $fb_class = "";
//        $json_decode = json_decode($value);

        $new_arr = array();

        if ($json_decode == null) { // no valid form fields exists
            /**
             *  generate a brand new section with empty fields
             */
            $new_arr = generateSectionFields([]);
        }
//        else if (!is_object($json_decode) && isset($json_decode[0])) { // old wcpa form structure
//            /**
//             * Convert wcpa 1 structure  to new structure
//             * Converted the form fields to row>>col format,
//             * and then added it as part if a new section
//             */
//            $json_decode = $this->toRowCol($json_decode);
//            $new_arr = generateSectionFields($json_decode);
////            $new_arr['default']['fields'] = $json_decode;
//        }

        else {
            $new_arr = $json_decode;
        }

        $this->replaceTags($new_arr, true);
        $response['fields'] = $new_arr;

        $response['formulaData'] = json_decode($json_formula_string);

        return $response;
    }

    private function download_send_headers($filename)
    {
        // disable caching
        $now = gmdate("D, d M Y H:i:s");
        header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
        header("Last-Modified: {$now} GMT");

        // force download
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");

        // disposition / encoding on response body
        header("Content-Disposition: attachment;filename={$filename}");
        header("Content-Transfer-Encoding: binary");
    }

    /**
     * Import via JSON file
     *
     * @param int $form_id
     *
     * @return array $result
     */
    public function import_form($form_id, $post_data)
    {
        $response = ['status' => true, 'id' => $form_id, 'settings' => [], 'fields' => []];
        $allowedHtml = array(
            'a' => array(// on allow a tags
                'href' => true, // and those anchors can only have href attribute
                'target' => true,
                'class' => true,// and those anchors can only have href attribute
                'style' => true
            ),
            'b' => array('style' => true, 'class' => true),
            'strong' => array('style' => true, 'class' => true),
            'i' => array('style' => true, 'class' => true),
            'img' => array('style' => true, 'class' => true, 'src' => true),
            'span' => array('style' => true, 'class' => true),
            'p' => array('style' => true, 'class' => true)
        );

        $data = [];
        $headings = [];
        $removeExisting = (isset($post_data['removeExisting']) && ($post_data['removeExisting'] == 'true'))
            ? true : false;
        $uploadImages = (isset($post_data['uploadImages']) && ($post_data['uploadImages'] == 'true'))
            ? true : false;

        if (isset($_FILES['file']) && !empty($_FILES['file'])) {
            $json_file = $_FILES['file'];
            if (isset($json_file['type']) && $json_file['type'] == 'application/json') {
                if (is_uploaded_file($json_file["tmp_name"])) {
                    $jsonData = file_get_contents($json_file["tmp_name"]);
                    $form = json_decode($jsonData);
                    if (!empty($form)) {
                        $title = $form->title;
                        $fields = $form->fields;
                        $formulaData = $form->formulaData;
                        $categories = isset($form->categories) ? $form->categories : [];
                        $postSettings = (array)$form->settings;

                        $catArray = [];

                        foreach ($categories as $cats) {
                            $catArray[] = $cats->term_id;
                        }

                        // sanitizeFields($fields, $allowedHtml);
                        $fields_json = wp_slash(json_encode($fields));
                        $formulaData_json = wp_slash(json_encode($formulaData));

                        $lang = false;

                        if ($form_id === 0) {
                            $new_post_id = $this->insert($title, $fields_json, $formulaData_json, $lang);
                            $response['id'] = $new_post_id;
                            $response['redirect'] = get_edit_post_link($new_post_id, 'link');
                        } else {
                            $this->update($form_id, $title, $fields_json, $formulaData_json, $lang);
                        }

                        /**
                         * set form categories
                         */
                        wp_set_post_terms($form_id, $catArray, 'product_cat');

                        $settings = new FormSettings($form_id);
                        $settings->save($postSettings);
                    }
                }
            }
        }

        $response['title'] = $title;
        $response['settings'] = $postSettings;
        $response['fields'] = $fields;
        $response['formulaData'] = $formulaData;

        return $response;
    }

    /**
     * Export Forms Lists to json
     *
     * @param array $forms
     *
     * @return array $result
     */
    function export_bulk_forms($posts = 'all')
    {
        $response = ['status' => true, 'data' => []];
        $args = array(
            'post_type' => self::$CPT,
            'posts_per_page' => -1,
            'orderby ' => 'ID',
            'order' => 'ASC',
            'post_status' => array('publish', 'draft')
        );
        $post__in = [];
        if (!($posts == 'all')) {
            if (is_array($posts) && !empty($posts)) {
                $post__in  = $posts;
            }
             $args['post__in'] =  $post__in ;
        }
        add_filter('pre_get_posts', function ($query) use ($post__in){
            $query->set('post_type', array(self::$CPT));
            $query->set('post__in',  $post__in);
            return $query;
        }, 999, 1);
        $opt_query = new WP_Query($args);

        $result = [];
        if ($opt_query->have_posts()) {
            while ($opt_query->have_posts()) {
                $opt_query->the_post();
                // $settings = new FormSettings(get_the_ID());
                $element = [];
                // if(in_array(get_the_ID(), $posts)) {
                    $element['title'] = get_the_title();

                    $categories = wp_get_post_terms(get_the_ID(), 'product_cat', ['fields' => 'ids']);

                    if (isset($categories) && !empty($categories)) {
                        $categoryArray = [];
                        foreach ($categories as $category) {
                            $cat = get_term_by('id', $category, 'product_cat');

                            $object = (object)[
                                'term_id' => $cat->term_id, 'name' => $cat->name, 'slug' => $cat->slug
                            ];
                            $categoryArray[] = $object;
                        }
                        $element['categories'] = $categoryArray;
                    }

                    // $element['fields'] = json_decode(get_post_meta(get_the_ID(), self::$META_KEY_2, true));
                    $element['fields'] = $this->get_form_meta_data(get_the_ID());
                    $element['formulaData'] = json_decode(get_post_meta(get_the_ID(), self::$META_FORMULA_KEY, true));
                    $element['settings'] = get_post_meta(get_the_ID(), FormSettings::$META_KEY, true);

                    $result[] = $element;
                // }
            }
        }
        wp_reset_postdata();

        // if ($posts == 'all') {
        //     $this->download_send_headers("wcpa-forms-lists-export-" . date('d-m-Y') . ".json");
        //     $df = fopen("php://output", 'w');
        //     fwrite($df, json_encode($result));
        //     fclose($df);
        //     exit;
        // }

        $response['data'] = $result;

        return $response;
    }

    /**
     * Import Forms Lists from json
     * @return array $result
     */
    public function import_bulk_forms($post_data)
    {
        $response = ['status' => true];
        $allowedHtml = array(
            'a' => array(// on allow a tags
                'href' => true, // and those anchors can only have href attribute
                'target' => true,
                'class' => true,// and those anchors can only have href attribute
                'style' => true
            ),
            'b' => array('style' => true, 'class' => true),
            'strong' => array('style' => true, 'class' => true),
            'i' => array('style' => true, 'class' => true),
            'img' => array('style' => true, 'class' => true, 'src' => true),
            'span' => array('style' => true, 'class' => true),
            'p' => array('style' => true, 'class' => true)
        );

        $data = [];
        $headings = [];

        $uploadImages = (isset($post_data['uploadImages']) && ($post_data['uploadImages'] == 'true'))
            ? true : false;
        if (isset($_FILES['file']) && !empty($_FILES['file'])) {
            $json_file = $_FILES['file'];
            if (isset($json_file['type']) && $json_file['type'] == 'application/json') {
                if (is_uploaded_file($json_file["tmp_name"])) {
                    $jsonData = file_get_contents($json_file["tmp_name"]);
                    $posts = json_decode($jsonData);
                    if (!empty($posts)) {
                        foreach ($posts as $p) {
                            $fields_data = $p->fields;
                            $formulaData = $p->formulaData;

                            $categories = isset($p->categories) ? $p->categories : [];
                            $catArray = [];

                            foreach ($categories as $cats) {
                                $catArray[] = $cats->term_id;
                            }

                            $settings = json_decode(json_encode($p->settings), true);

                            if ($fields_data && is_object($fields_data)) {
                                foreach ($fields_data as $key => $section) {
                                    foreach ($section->fields as $i => $row) {
                                        foreach ($row as $j => $field) {
                                            if (isset($field->type) && ($field->type == "image-group" || $field->type == "select" || $field->type == "checkbox-group" || $field->type == "radio-group" || $field->type == "color-group" || $field->type == "productGroup")) {
                                                if (isset($field->values) && is_array($field->values)) {
                                                    foreach ($field->values as $index => $val) {

                                                        $newVal = $val;
                                                        $modVal = $val;

                                                        if (isset($val->image) && isset($val->image_id)) {
                                                            $imageArray = array("id" => $val->image_id, "url" => $val->image, "thumb" => $val->thumb);
                                                            $imageField = isset($imageArray) ? $imageArray : ['id' => '', 'url'=>'', 'thumb'=>''];

                                                            $modVal = (object)$this->may_be_add_media_to_library($imageField,
                                                                $uploadImages);

                                                            $newVal->image = $modVal->url;
                                                            $newVal->image_id = $modVal->id;
                                                            $newVal->thumb = $modVal->thumb;
                                                        }

                                                        if (isset($val->pimage) && !empty($val->pimage)) {
                                                            $pimageArray = array("id" => $val->pimage_id, "url" => $val->pimage, "thumb" => $val->pthumb);
                                                            $pimageField = isset($pimageArray) ? $pimageArray : ['id' => '', 'url' => '', 'thumb' => ''];

                                                            $pmodVal = (object)$this->may_be_add_media_to_library($pimageField,
                                                                $uploadImages);

                                                            $newVal->pimage = $pmodVal->url;
                                                            $newVal->pimage_id = $pmodVal->id;
                                                            $newVal->pthumb = $pmodVal->thumb;
                                                        }

                                                        $field->values[$index] = $newVal;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            $fields_data = json_decode(json_encode($fields_data));
                            $formulaData = json_decode(json_encode($formulaData), true);
                            sanitizeFields($fields_data, $allowedHtml);
                            $fields_data_json = wp_slash(json_encode($fields_data));
                            $formulaData_json = wp_slash(json_encode($formulaData));

                            $lang = false;
                            if ($this->ml->is_active()) {
                                $lang = $this->ml->default_language();
                            }

                            $new_post_id = $this->insert($p->title, $fields_data_json, $formulaData_json, $lang);

                            wp_set_post_terms($new_post_id, $catArray, 'product_cat');

                            $settingsObj = new FormSettings($new_post_id);
                            $settingsObj->save($settings);
                        }
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Add media to library
     * if not exist in the current site.
     */
    private function may_be_add_media_to_library($media_data, $mustUpload = false)
    {
        if (!(isset($media_data['url']) && !empty($media_data['url']))) {
            return $media_data;
        }

        // if file not found
        $headers = @get_headers($media_data['url']);
        if (strpos($headers[0], '404') !== false) {
            return ['url' => '', 'id' => '', 'thumb' => ''];
        }

        if ($mustUpload) {
            $attachment_id = $this->uploadImportFileByUrl($media_data['url']);
            $thumb = wp_get_attachment_image_src($attachment_id);
            return $attachment_id !== false
                ? ['url' => wp_get_attachment_url($attachment_id), 'id' => $attachment_id, 'thumb' => $thumb[0]]
                : ['url' => '', 'id' => '', 'thumb' => ''];
        } else {
            $site_url = get_site_url();

            //If domain is same
            if (strpos($media_data['url'], $site_url) !== false) {
                $imageId = attachment_url_to_postid($media_data['url']);
                if ($imageId !== 0) {
                    if ($imageId == $media_data['id']) {
                        return $media_data;
                    } else {
                        return ['url' => $media_data['url'], 'id' => $imageId];
                    }
                }
            }

            $attachment_id = $this->uploadImportFileByUrl($media_data['url']);
            $thumb = wp_get_attachment_image_src($attachment_id);
            return $attachment_id !== false
                ? ['url' => wp_get_attachment_url($attachment_id), 'id' => $attachment_id, 'thumb' => $thumb[0]]
                : ['url' => '', 'id' => '', 'thumb' => ''];
        }
    }

    /**
     * Upload file to media by url
     *
     */
    private function uploadImportFileByUrl($url)
    {
        require_once(ABSPATH . "/wp-load.php");
        require_once(ABSPATH . "/wp-admin/includes/image.php");
        require_once(ABSPATH . "/wp-admin/includes/file.php");
        require_once(ABSPATH . "/wp-admin/includes/media.php");

        // Download url to a temp file
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return false;
        }

        // Get the filename and extension ("photo.png" => "photo", "png")
        $filename = pathinfo($url, PATHINFO_FILENAME);
        $extension = pathinfo($url, PATHINFO_EXTENSION);

        // Upload by "sideloading": "the same way as an uploaded file is handled by media_handle_upload"
        $args = array(
            'name' => "$filename.$extension",
            'tmp_name' => $tmp,
        );

        // Do the upload
        $attachment_id = media_handle_sideload($args);

        // Cleanup temp file
        @unlink($tmp);

        // Error uploading
        if (is_wp_error($attachment_id)) {
            return false;
        }

        // Success, return attachment ID (int)
        return (int)$attachment_id;
    }


    /**
     * Products Searching in Form Detail Page
     *
     * @param int $search
     *
     * @return array $response
     */
    public function products_searching($search)
    {
        $response = ['status' => true, 'search' => $search, 'searchOptions' => ''];
        $args = array(
            'post_type' => 'product',
            's' => $search,
            'posts_per_page' => 30

        );
        $q1 = new WP_Query($args);
        $q2 = null;
        if (strlen($search) > 3) {
            $q2 = new WP_Query(array(
                'post_type' => 'product',
                'posts_per_page' => 30,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => '_sku',
                        'value' => $search,
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key' => 'product_id',
                        'value' => $search,
                        'compare' => 'LIKE'
                    )
                )
            ));
        }

        $q3 = null;
        if (is_numeric($search)) {
            $q3 = new WP_Query(array(
                'post_type' => 'product',
                'p' => trim($search)
            ));
        }
        $result = new WP_Query();
        $result->posts = array_unique(array_merge($q1->posts, $q2!==null?$q2->posts:[], $q3!==null ? $q3->posts : []), SORT_REGULAR);
        $result->post_count = count($result->posts);

        $searchOptions = [];
        if ($result->have_posts()): while ($result->have_posts()) {
            $result->the_post();
            $sky = get_post_meta(get_the_ID(), '_sku', true);
            $searchOptions[] = [
                'label' => html_entity_decode(get_the_title()) . ' (' . get_the_ID() . ($sky !== '' ? ' | ' . $sky : '') . ')',
                'value' => get_the_ID()
            ];
        }
        endif;


        $response['searchOptions'] = $searchOptions;

        return $response;
    }

    /**
     * Get Checkout Fields
     * @return array $response
     */
    public function get_checkout_fields()
    {
        $response = ['status' => true, 'groups' => [], 'fields' => []];

        // wc()->frontend_includes();
        // WC()->session = new WC_Session_Handler();
        // WC()->session->init();
        // WC()->customer = new WC_Customer(get_current_user_id(), true);

        $fields = WC()->checkout()->checkout_fields;

        $checkOutGroups = [];
        $checkOutGroupFieldsArray = [];

        if (!function_exists('AWCFE')) {
            foreach ($fields as $key => $value) {
                $checkOutGroupFields = [];
                $checkOutGroups[] = array('label' => ucwords($key), 'value' => $key);

                foreach ($value as $keyVal => $val) {
                    $checkOutGroupFields[] = array('value' => $keyVal, 'label' => ucwords($val['label']));
                }
                $checkOutGroupFieldsArray[] = $checkOutGroupFields;
            }
        } else {
            // $customSections = get_option(AWCFE_FIELDS_KEY);

            foreach ($fields as $key => $value) {
                $checkOutGroupFields = [];
                $checkOutGroups[] = array('label' => ucwords($value['extra']['name']), 'value' => $key);

                foreach ($value['fields'] as $combo_key => $combo_val) {
                    foreach ($combo_val as $field_key => $field_val) {
                        if ($field_val['active'] === true) {
                            $checkOutGroupFields[] = array(
                                'value' => $field_val['bindingKey'] ? $field_val['bindingKey'] : $field_val['name'],
                                'label' => ucwords($field_val['label'])
                            );
                        }
                    }
                }
                $checkOutGroupFieldsArray[] = $checkOutGroupFields;
            }
        }

        $response['groups'] = $checkOutGroups;
        $response['fields'] = $checkOutGroupFieldsArray;

        return $response;
    }

    public function get_wcpaProducts()
    {
        global $wpdb;
        $cacheKey = WCPA_PRODUCTS_TRANSIENT_KEY;

        if ($this->ml->is_active()) {
            $cacheKey = $cacheKey . '_' . $this->ml->current_language();
        }
        $pro_ids_main = get_transient($cacheKey);

        if (false === $pro_ids_main) {
            $pro_ids_main = array('full' => [], 'direct_purchasable' => []);
            $post_ids_main = array('full' => [], 'direct_purchasable' => []);

            $post_ids_main['direct_purchasable'] = get_posts(
                array(
                    'fields' => 'ids',
                    'post_type' => self::$CPT,
                    'posts_per_page' => -1,
                    'meta_query' => array(
                        'relation' => 'OR',
                        array(
                            'key' => 'wcpa_drct_prchsble',
                            'value' => true,
                            'type' => 'BOOLEAN',
                        ),
                    ),
                )
            );

            $post_ids_main['full'] = get_posts(
                array(
                    'fields' => 'ids',
                    'post_type' => self::$CPT,
                    'posts_per_page' => -1,
                )
            );

            foreach ($post_ids_main as $key => $post_ids) {
                if ($post_ids && count($post_ids)) {
                    if($this->ml->is_active()){
                        $post_ids = $this->ml->lang_object_ids($post_ids,'post',true);
                    }
                    // get all products matching forms assigned categories
                    $query = "SELECT
distinct object_id from $wpdb->term_relationships
 where term_taxonomy_id"
                        . " in (select tr.term_taxonomy_id from $wpdb->term_relationships as tr left join $wpdb->term_taxonomy as tt on(tt.term_taxonomy_id=tr.term_taxonomy_id) where tr.object_id in (" . implode(',',
                            $post_ids) . ")"
                        . "and  tt.taxonomy = 'product_cat')";

                    $pro_ids = $wpdb->get_col($query);

                    $excluded_ids = get_posts(
                        array(
                            'fields' => 'ids',
                            'post_type' => 'product',
                            'numberposts' => -1,
                            'meta_query' => array(
                                array(
                                    'key' => 'wcpa_exclude_global_forms',
                                    'value' => '1',
                                    'type' => 'BOOLEAN',
                                ),
                            ),
                        )
                    );

                    $pro_ids = array_diff($pro_ids, $excluded_ids);


                    $temp = array_reduce($post_ids, function ($a, $b) {
                        return $a . " `meta_value` LIKE '%:$b;%' OR";
                    });
                    $temp .= trim($temp, 'OR');
                    $pro_ids2 = $wpdb->get_col("SELECT post_id  from $wpdb->postmeta WHERE meta_key = '" . WCPA_PRODUCT_META_KEY . "' and ($temp)");

                    if ($pro_ids2) {
                        $pro_ids2 = array_map('intval', $pro_ids2);

                        $pro_ids = array_unique(array_merge($pro_ids, $pro_ids2));
                    }

                } else {
                    $pro_ids = array();
                }
                if($this->ml->is_active()){
                    $pro_ids = $this->ml->lang_object_ids($pro_ids,'post',false);
                }
                $pro_ids_main[$key] = $pro_ids;
            }

//            set_transient(WCPA_PRODUCTS_TRANSIENT_KEY, $pro_ids_main, 24 * HOUR_IN_SECONDS);
            set_transient($cacheKey, $pro_ids_main); //TODO to check expiration
        }

        return $pro_ids_main;
    }
}




