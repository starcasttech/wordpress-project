<?php

namespace Acowebs\WCPA;

use WP_Post;

class Product
{
    private $data;
    private $formConf;
    private $cart_error;
    private $has_custom_fields;
    private $relations = array();
    private $form;
    private $ml;
    private $options;
    private $price_dependency = array();
    public $translate_keys = [
        'label',
        'placeholder',
        'description',
        'value',
        'tooltip',
        'repeater_section_label',
        'repeater_field_label',
        'repeater_add_label',
        'repeater_remove_label',
        'quantity_label',
        'check_value',

        'requiredError',
        'validEmailError',
        'validUrlError',
        'minFieldsError',
        'maxFieldsError',
        'groupMinError',
        'groupMaxError',
        'otherFieldError',
        'quantityRequiredError',
        'allowedCharsError',
        'patternError',
        'maxlengthError',
        'minlengthError',
        'minValueError',
        'maxValueError',
        'minQuantityError',
        'maxQuantityError',
        'maxFileCountError',
        'minFileCountError',
        'maxFileSizeError',
        'minFileSizeError',
        'fileExtensionError',
        'summary_title',
        'options_total_label',
        'total_label',
        'options_product_label',
        'discount_label',
    ];

    public function __construct()
    {
        add_filter( 'weglot_get_regex_checkers',  array($this, 'custom_weglot_add_regex_checkers'));
        $custom_fields = Config::get_config('product_custom_fields', []);
        if (is_array($custom_fields) && !empty($custom_fields)) {
            $this->has_custom_fields = true;
        }
        $this->ml = new ML();
    }

    public function get_price_dependency()
    {
        return $this->price_dependency;
    }

    public function custom_weglot_add_regex_checkers( $regex_checkers ) {

        $regex_checkers[] = new \Weglot\Parser\Check\Regex\RegexChecker( '#var wcpa_front = ((.|\s)+?);#', 'JSON', 1, $this->translate_keys );
        $regex_checkers[] = new \Weglot\Parser\Check\Regex\RegexChecker( "#data-wcpa='(.*)'#", 'JSON', 1, $this->translate_keys, "html_entity_decode" , "htmlentities" );

        return $regex_checkers;
    }


    public function get_fields($product_id = false)
    {
        if (($this->data !== null && !empty($this->data))) {
            return ['fields' => $this->data, 'config' => $this->formConf];
        }

        $cacheKey = $product_id;
        if ($this->ml->is_active()) {
            $cacheKey = $cacheKey . '_' . $this->ml->current_language();
        }
        if (false !== ($data = $this->getCache($cacheKey))) {
            return $data;
        }


        $this->form = new Form();
        $this->form->init(); // as it depends wpml
        $this->options = new Options();

        $this->data = array();
        //      $this->cart_error = WCPA_Front_End::get_cart_error($product_id); // need to recheck


        $post_ids = $this->get_form_ids($product_id);

        $prod = wc_get_product($product_id);

        $this->formConf = [
            'price_override' => '',

            'enable_recaptcha' => false,
            'bind_quantity' => false,
            'quantity_bind_formula' => false,

            'disp_summ_show_option_price' => false,
            'disp_summ_show_product_price' => false,
            'disp_summ_show_total_price' => false,
            'disp_summ_show_fee' => false,
            'disp_summ_show_discount' => false,

            'summary_title' => false,
            'options_total_label' => false,
            'total_label' => false,
            'options_product_label' => false,
            'fee_label' => false,
            'discount_label' => false,
            'has_price' => false,
            'has_quantity_formula' => false,

        ];

        $scripts = [
            'file' => false,
            'datepicker' => false,
            'color' => false,
            'select' => false,
            'productGallery' => false,
            'googlemapplace' => false,
            'recaptcha' => false,
        ];

        $formulas = [];


//		if ( Config::get_config( 'form_loading_order_by_date' ) === true ) {
        $post_ids = array_filter($post_ids);//remove null/empty elements
        if (is_array($post_ids) && count($post_ids)) {
            $post_ids = get_posts(
                array(
                    'include' => $post_ids,
                    'fields' => 'ids',
                    'post_type' => Form::$CPT,
                    'lang' => '', // deactivates the Polylang filter
                    'posts_per_page' => -1,
                    'orderby' => 'post__in'
                )
            );
        }
//		}

//        $post_ids = $this->re_order_forms($post_ids, $product_id);

        foreach ($post_ids as $id) {
            if (get_post_status(
                    $id
                ) == 'publish') {  // need to check if this check needed as post_ids will be published posts only
                $json_encoded = $this->form->get_form_meta_data($id);
                $formulas = array_merge($formulas, $this->form->get_formulas($id));


                $form_settings = new FormSettings($id);

                foreach ($this->formConf as $key => $v) {
                    if (($key == 'bind_quantity' || $key=='quantity_bind_formula') && ($v === false || $v == '')) {
                        $this->formConf['bind_quantity'] = $form_settings->get('bind_quantity');
                        if ($this->formConf['bind_quantity']) {
                            $this->formConf['quantity_bind_formula'] = html_entity_decode($form_settings->get('quantity_bind_formula'));
                            if (empty($this->formConf['quantity_bind_formula']) || trim(
                                    $this->formConf['quantity_bind_formula']
                                ) == '') {
                                $this->formConf['bind_quantity'] = false;
                            }
                        }
                    } elseif ($v === false || $v === '') {
                        // once it is set as true for a for, it must be true even if the product has multiple forms assigned
                        $this->formConf[$key] = $form_settings->get($key);
                    }
                }


                $form_rules = [
                    'exclude_from_discount' => (Config::get_config('remove_discount_from_fields') ? true : $form_settings->get('exclude_from_discount')),

                    'fee_label' => $form_settings->get('fee_label'),

                    'disp_hide_options_price' => $form_settings->get('disp_hide_options_price'),
                    'disp_show_section_price' => $form_settings->get('disp_show_section_price'),
                    'disp_show_field_price' => $form_settings->get('disp_show_field_price'),

                    'layout_option' => $form_settings->get('layout_option'),
                    'pric_use_as_fee' => $form_settings->get('pric_use_as_fee'),
                    'process_fee_as' => $form_settings->get('process_fee_as')
                ];


                /**
                 * @var keep track of connected global forms, remove if already imported to avoide infinite loop
                 */
                $globalForms = []; //
                $rowsToResetIndex = [];
                if ($json_encoded && is_object($json_encoded)) {
                    $sectionReIterate = true;

                    while ($sectionReIterate) {
                        $sectionReIterate = false;
                        foreach ($json_encoded as $sectionKey => $section) {
                            $reIterate = true;
                            if (!isset($rowsToResetIndex[$sectionKey])) {
                                $rowsToResetIndex[$sectionKey] = [];
                            }
                            while ($reIterate) {
                                $reIterate = false;


                                /**
                                 * Form rules&form_id will be taken from the parent form only, will not be considering form rules from other global form fields added in this form,
                                 */
                                $section->extra->form_id = $id;
                                $section->extra->form_rules = $form_rules;

                                $layOut = isset($section->extra->layout_option) ? $section->extra->layout_option : false;
                                if ($layOut == false || $layOut == null || $layOut == 'default') {
                                    $layOut = $form_rules['layout_option'];
                                }
                                $section->extra->layout_option = $layOut;

                                $this->process_cl($section->extra, $prod);

                                foreach ($section->fields as $rowIndex => $row) {
                                    foreach ($row as $colIndex => $field) {
                                        if (isset($field->active) && $field->active === false) {
                                            //TODO remove empty row, or section
                                            unset($section->fields[$rowIndex][$colIndex]);
                                            $rowsToResetIndex[$sectionKey][] = $rowIndex;
                                            continue;
                                        }


                                        if ($field->type == 'formselector') {
                                            $globalFormFields = $this->getGlobalFormFields($field);
                                            if ($globalFormFields) {
                                                if ($field->type == 'formselector' && isset($field->form_id) && is_numeric($field->form_id)) {
                                                    $formulas = array_merge($formulas, $this->form->get_formulas($field->form_id));
                                                }

                                                if (!in_array($globalFormFields['key'], $globalForms)) {
                                                    $globalForms[] = $globalFormFields['key'];

                                                    if ($globalFormFields['type'] == 'fields') {
                                                        /**
                                                         * If the global form fields are just fields without sections, just append fields
                                                         */
                                                        array_splice(
                                                            $section->fields[$rowIndex],
                                                            $colIndex,
                                                            1,
                                                            $globalFormFields['fields']
                                                        );
                                                        $newArr = fix_cols($section->fields[$rowIndex]);
                                                        array_splice($section->fields, $rowIndex, 1, $newArr);
                                                    } elseif ($globalFormFields['type'] == 'section') {
                                                        /**
                                                         *   if global form has multiple sections,
                                                         * Split the main section here, and insert the sections after this,
                                                         *  parent section will be split as two parts , part 1 will be as above, and part 2 will be appended after the globally added section
                                                         */

                                                        /** @var  $part1 split the fields as part 1 till the current row index */
                                                        $part1 = array_slice($section->fields, 0, $rowIndex);

                                                        /** split the current row if it has multiple columns, $part1Col, is the first split till current column */
                                                        $part1Col = array_slice(
                                                            $section->fields[$rowIndex],
                                                            0,
                                                            $colIndex
                                                        );
                                                        if (count($part1Col) > 0) {
                                                            /* if the is a column , insert it as a new row  */
                                                            $part1[] = $part1Col;
                                                        }

                                                        /** @var  $part1Col2 split columns after current column, and prepend it to the part2 */
                                                        $part1Col2 = array_slice(
                                                            $section->fields[$rowIndex],
                                                            $colIndex + 1,
                                                            null
                                                        );

                                                        // exclude field in between which will be the formselector
                                                        $part2 = array_slice($section->fields, $rowIndex + 1, null);
                                                        if (count($part1Col2) > 0) {
                                                            $part1Col2[0] = $part1Col2;
                                                            $part2 = array_merge($part1Col2, $part2);
                                                        }

                                                        if (count($part1) > 0) {
                                                            $section->fields = $part1;
                                                        }

                                                        if (count($part2) > 0) {
                                                            $_section = clone $section;
                                                            $_section->extra = clone $section->extra;
                                                            $newKey = $_section->extra->section_id . '_part2';
                                                            $_section->extra->key = $newKey;
                                                            $_section->extra->section_id = $newKey;
                                                            $_section->fields = $part2;
                                                        }


                                                        $json_encoded_arr = (array)$json_encoded;
                                                        $sectionsToAppend = (array)$globalFormFields['fields'];
                                                        $split = array_search(
                                                            $sectionKey,
                                                            array_keys($json_encoded_arr)
                                                        );

                                                        if (count($part1) > 0 && count($part2) > 0) {

                                                            $json_encoded_NewArr = array_merge(
                                                                array_slice(
                                                                    $json_encoded_arr,
                                                                    0,
                                                                    $split + 1,
                                                                    true
                                                                ),
                                                                $sectionsToAppend,
                                                                [$sectionKey . '_part2' => $_section],
                                                                array_slice(
                                                                    $json_encoded_arr,
                                                                    $split + 1,
                                                                    null,
                                                                    true
                                                                )
                                                            );
//                                                            $json_encoded_NewArr = array_slice(
//                                                                    $json_encoded_arr,
//                                                                    0,
//                                                                    $split + 1,
//                                                                    true
//                                                                ) +
//                                                                $sectionsToAppend +
//                                                                [$sectionKey . '_part2' => $_section] +
//                                                                array_slice(
//                                                                    $json_encoded_arr,
//                                                                    $split + 1,
//                                                                    null,
//                                                                    true
//                                                                );
                                                        } elseif (count($part1) > 0 && count($part2) == 0) {
//                                                            $json_encoded_NewArr = array_slice(
//                                                                    $json_encoded_arr,
//                                                                    0,
//                                                                    $split + 1,
//                                                                    true
//                                                                ) +
//                                                                $sectionsToAppend +
//                                                                array_slice(
//                                                                    $json_encoded_arr,
//                                                                    $split + 1,
//                                                                    null,
//                                                                    true
//                                                                );

                                                            $json_encoded_NewArr = array_merge(array_slice(
                                                                $json_encoded_arr,
                                                                0,
                                                                $split + 1,
                                                                true
                                                            ), $sectionsToAppend, array_slice(
                                                                $json_encoded_arr,
                                                                $split + 1,
                                                                null,
                                                                true
                                                            ));

                                                        } elseif (count($part1) == 0 && count($part2) == 0) {
                                                            $json_encoded_NewArr = array_merge(array_slice(
                                                                $json_encoded_arr,
                                                                0,
                                                                $split,
                                                                true
                                                            ),
                                                                $sectionsToAppend,
                                                                array_slice(
                                                                    $json_encoded_arr,
                                                                    $split + 1,
                                                                    null,
                                                                    true
                                                                )
                                                            );
                                                        } elseif (count($part1) == 0 && count($part2) > 0) {
                                                            $json_encoded_NewArr = array_merge(array_slice(
                                                                $json_encoded_arr,
                                                                0,
                                                                $split,
                                                                true
                                                            ), $sectionsToAppend,
                                                                [$sectionKey . '_part2' => $_section],
                                                                array_slice(
                                                                    $json_encoded_arr,
                                                                    $split + 1,
                                                                    null,
                                                                    true
                                                                )
                                                            );
//                                                            $json_encoded_NewArr = array_slice(
//                                                                    $json_encoded_arr,
//                                                                    0,
//                                                                    $split,
//                                                                    true
//                                                                ) +
//                                                                $sectionsToAppend +
//                                                                [$sectionKey . '_part2' => $_section] +
//                                                                array_slice(
//                                                                    $json_encoded_arr,
//                                                                    $split,
//                                                                    null,
//                                                                    true
//                                                                );
                                                        }

//                                                            $json_encoded_NewArr = array_slice($json_encoded_arr, 0, $split, true) +
//                                                                $sectionsToAppend + array_slice($json_encoded_arr, $split, null, true);
                                                        $json_encoded = (object)$json_encoded_NewArr;

                                                        $sectionReIterate = true;
                                                        break;
                                                    }

                                                    $reIterate = true;
                                                    break;
                                                } else {
                                                    array_splice($section->fields[$rowIndex], $colIndex, 1);
                                                }
                                            } else {
                                                array_splice($section->fields[$rowIndex], $colIndex, 1);
                                            }
                                        }

                                        $this->find_price_dependency($field, $section->extra->form_id);
                                        /** TODO  */
                                        $this->update_global_options($field);
                                        $this->replace_custom_fields($field, $prod);
                                        $this->process_cl($field, $prod);
                                        $this->processFields($field, $section->extra->form_id);
                                        $this->setTranslationKeys($field);

                                        $this->findScriptsRequired($field, $scripts);

                                        if (!$this->formConf['has_price']) {
                                            $this->formConf['has_price'] = true;
                                        }
                                    }
                                    if ($reIterate || $sectionReIterate) {
                                        break;
                                    }
                                }
                                if ($sectionReIterate) {
                                    break;
                                }
                            }
                            if ($sectionReIterate) {
                                break;
                            }
                        }
                    }
                    // check for external forms


                    //   $json_encoded = $this->appendGlobalForm($json_encoded);
                    /**
                     * resetting array index when an column removed from row
                     * @var  $rowIndexes
                     */
                    foreach ($rowsToResetIndex as $sec => $rowIndexes) {
                        $resetSecFieldsIndex = false;
                        foreach ($rowIndexes as $rowIndex) {
                            if (isset($json_encoded->{$sec}->fields[$rowIndex])) {
                                $json_encoded->{$sec}->fields[$rowIndex] = array_values(
                                    $json_encoded->{$sec}->fields[$rowIndex]
                                );
                                if (count($json_encoded->{$sec}->fields[$rowIndex]) == 0) {
                                    unset($json_encoded->{$sec}->fields[$rowIndex]);
                                    $resetSecFieldsIndex = true;
                                }
                            }
                        }
                        if ($resetSecFieldsIndex) {
                            $json_encoded->{$sec}->fields = array_values($json_encoded->{$sec}->fields);
                        }
                    }
                    $this->data = array_merge($this->data, (array)$json_encoded);
                }
            }
        }

//            if ($bind_quantity) {
//                if ($matches = $this->check_field_price_dependency($quantity_bind_formula)) {
//                    foreach ($matches as $match) {
//                        if (!isset($this->price_depends[$match])) {
//                            $this->price_depends[$match] = array();
//                        }
//                        if (isset($v->elementId)) {
//                            if (!in_array($v->elementId, $this->price_depends[$match])) {
//                                $this->price_depends[$match][] = $v->elementId;
//                            }
//                        }
//                    }
//                }
//            }
        $totalFieldsCount = 0;
        if ($this->data !== null) {
            $this->data = (object)$this->data;
            $totalFieldsCount = $this->map_dependencies();
        }

        if ($this->formConf['enable_recaptcha']) {
            $scripts['recaptcha'] = true;
        }

        foreach ($formulas as $key => $f) {
            $formulas[$key] = $this->replace_custom_field($f, $prod);
        }


        if (strpos(json_encode($this->data), "{quantity}") !== false || strpos(json_encode($formulas), "{quantity}") !== false || strpos(json_encode($this->data), '"cl_field":"quantity"') !== false ) {
            $this->formConf['has_quantity_formula'] = true;
        }


        $data = [
            'fields' => $this->data,
            'config' => $this->formConf,
            'scripts' => $scripts,
            'formulas' => $formulas,
            'fieldsCount' => $totalFieldsCount
        ];
        $this->setCache($cacheKey, $data);

        return $data;

    }

    public function getCache($product_id)
    {
        return get_transient('wcpa_fields_' . $product_id);
    }

    /**
     * get forms assigned to product by product id
     *
     * @param $product_id
     *
     * @return array|int|int[]|mixed|void|WP_Post[]
     */
    public function get_form_ids($product_id)
    {
        if ($this->ml->is_active()) {
            $product_id = $this->ml->lang_object_ids($product_id, 'post', true);
        }
        $key_1_value = get_post_meta($product_id, 'wcpa_exclude_global_forms', true);
        $form_ids = array();

        if (empty($key_1_value)) {
            $terms = wp_get_object_terms(
                $product_id,
                'product_cat',
                array(
                    'orderby' => 'name',
                    'order' => 'ASC',
                    'fields' => 'ids',
                )
            );
//            if ($this->ml->is_active()) {
//                $terms = $this->ml->lang_object_ids($terms, 'product_cat', true);
//                $currentLag = $this->ml->current_language();
//                $this->ml->setCurrentLang($this->ml->default_language());
//                $form_ids = get_posts(
//                    array(
//                        'tax_query' => array(
//                            array(
//                                'taxonomy' => 'product_cat',
//                                'field' => 'ids',
//                                'include_children' => false,
//                                'terms' => $terms,
//                            ),
//                        ),
//                        'fields' => 'ids',
//                        'post_type' => Form::$CPT,
//                        'posts_per_page' => -1,
//                        'lang' => $this->ml->default_language()
//
//                    )
//                );
//                $this->ml->setCurrentLang($currentLag);
//            } else {
            if ($this->ml->is_active()) {
                $currentLag = $this->ml->current_language();
                $this->ml->setCurrentLang($this->ml->default_language());
                $form_ids = get_posts(
                    array(
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'product_cat',
                                'field' => 'ids',
                                'include_children' => false,
                                'terms' => $terms,
                            ),
                        ),
                        'fields' => 'ids',
                        'post_type' => Form::$CPT,
                        'posts_per_page' => -1

                    )
                );
                $this->ml->setCurrentLang($currentLag);
            } else {
                $form_ids = get_posts(
                    array(
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'product_cat',
                                'field' => 'ids',
                                'include_children' => false,
                                'terms' => $terms,
                            ),
                        ),
                        'fields' => 'ids',
                        'post_type' => Form::$CPT,
                        'posts_per_page' => -1

                    )
                );
            }

//            }
        }
        $form_ids_set2 = maybe_unserialize(get_post_meta($product_id, WCPA_PRODUCT_META_KEY, true));

        if ($form_ids_set2 && is_array($form_ids_set2)) {
            /** reorder ids based on form created date */
            /** @since 5.0.14 * */
            /* Earlier form was loaded based on the order the user checks the form in backend */
            if ($this->ml->is_active()) {
                $currentLag = $this->ml->current_language();
                $this->ml->setCurrentLang($this->ml->default_language());
                $form_ids_set2 = get_posts(
                    array(
                        'include' => $form_ids_set2,
                        'fields' => 'ids',
                        'post_type' => Form::$CPT,
                        'lang' => '', // deactivates the Polylang filter
                        'posts_per_page' => -1,
                    )
                );
                $this->ml->setCurrentLang($currentLag);

            } else {
                $form_ids_set2 = get_posts(
                    array(
                        'include' => $form_ids_set2,
                        'fields' => 'ids',
                        'post_type' => Form::$CPT,
                        'lang' => '', // deactivates the Polylang filter
                        'posts_per_page' => -1,
                    )
                );
            }

        }


        $form_ids = $this->re_order_and_merge_forms($form_ids, $form_ids_set2, $product_id);
//
//        if ($form_ids_set2 && is_array($form_ids_set2)) {
//            $form_ids_set2 = $this->re_order_forms($form_ids_set2, $product_id);
//            if (Config::get_config('append_global_form') == 'at_end') {
//                $form_ids = array_unique(array_merge($form_ids_set2, $form_ids));
//            } else {
//                $form_ids = array_unique(array_merge($form_ids, $form_ids_set2));
//            }
//
//        }
//        $form_ids = $this->re_order_forms($form_ids, $product_id);

        if ($this->ml->is_active()) {
            $form_ids = $this->ml->lang_object_ids($form_ids, 'post');
        }

        return $form_ids;
    }

    /**
     * @param $ids
     * @param $p_id
     *
     * @return array
     */
    public function re_order_and_merge_forms($idsByCats, $directIds, $p_id)
    {

        $form_order = get_post_meta($p_id, 'wcpa_product_meta_order', true);
        if (!is_array($directIds)) {
            $directIds = [];
        }
        if ($idsByCats && is_array($idsByCats)) {
            if (Config::get_config('append_global_form') == 'at_end') {
                $directIds = array_unique(array_merge($directIds, $idsByCats));
            } else {
                $directIds = array_unique(array_merge($idsByCats, $directIds));
            }

        }

        $bigNum = 99999;
        if ($form_order && is_array($form_order)) {
            $ids_new = array();
            $form_order_new = array();
            foreach ($directIds as $id) {
                if (isset($form_order[$id])) {
                    $form_order_new[$id] = $form_order[$id];
                } else if (in_array($id, $idsByCats) && isset($form_order[0])) {// order for forms assigned by category
                    $form_order_new[$id] = $form_order[0];
                } else {
                    $form_order_new[$id] = $bigNum++;
                }
            }
//            arsort($form_order_new);
//            $directIds = array_keys($form_order_new);
            $directIds = array_keys($form_order_new);
            // Sort the keys based on the values
            usort($directIds, function ($a, $b) use ($form_order_new) {
                if ($form_order_new[$a] == $form_order_new[$b]) {
                    return ($a < $b) ? 1 : -1;
                }
                return ($form_order_new[$a] > $form_order_new[$b]) ? 1 : -1;
            });


//            array_multisort($order_new_values, SORT_ASC,SORT_NUMERIC , $directIds);
//
//            foreach ($form_order_new as $form_id => $order) {
//                $index = array_search($form_id, $directIds);
//                if ($index !== false) {
//                    unset($directIds[$index]); // remove item at index 0
//                    $directIds = array_values($directIds); // 'reindex' array
//                    $length = count($directIds);
//                    if ($order <= 0) {
//                        $pos = 0;
//                    } elseif ($order > $length) {
//                        $pos = $length;
//                    } else {
//                        $pos = $order - 1;
//                    }
//
//                    array_splice($directIds, $pos, 0, $form_id);
//                }
//            }
        }


        return $directIds;


    }

    public function process_cl($v, $prod)
    {
        if (isset($v->enableCl) && $v->enableCl && isset($v->relations) && is_array($v->relations)) {
            foreach ($v->relations as $val) {
                foreach ($val->rules as $k) {
                    if (!empty($k->rules->cl_field)) {
                        /** change external field_id  */
                        if (strpos($k->rules->cl_field, 'external|') === 0) {
                            $k->rules->cl_field = str_replace('external|', '', $k->rules->cl_field);
                        }
                        if (!isset($this->relations[$k->rules->cl_field])) {
                            $this->relations[$k->rules->cl_field] = array();
                        }
                        if ($this->has_custom_fields && isset($k->rules->cl_val) && !empty($k->rules->cl_val)) {
                            if (is_string($k->rules->cl_val)) {
                                $k->rules->cl_val = $this->replace_custom_field($k->rules->cl_val, $prod);
                            } else {
                                if (isset($k->rules->cl_val->value) && is_string($k->rules->cl_val->value)) {
                                    $k->rules->cl_val->value = $this->replace_custom_field(
                                        $k->rules->cl_val->value,
                                        $prod
                                    );
                                }
                            }
                        }

                        if ($this->ml->is_active()) {
                            // use variation conditions to translations
                            if ($k->rules->cl_field == 'attribute' && $k->rules->cl_field_sub) {
                                $k->rules->cl_val = $this->ml->getAttribute($k->rules->cl_val, $k->rules->cl_field_sub);
                            }
                            if (substr($k->rules->cl_field, 0, 7) === "product") {
                                $k->rules->cl_val = $this->ml->lang_object_ids($k->rules->cl_val, 'post');
                            }
                        }


//removed this as in new version, it saves the arttribute slug directly than the id
//                        if ($k->rules->cl_field === 'attribute' && $k->rules->cl_field_sub) {
//
//                            $atr = wc_get_attribute($k->rules->cl_field_sub);
//                            if ($atr) {
//                                $term                   = get_term_by('id', $k->rules->cl_val->value, $atr->slug);
//                                $k->rules->cl_val       = isset($term->slug) ? $term->slug : '';
//                                $k->rules->cl_field_sub = sanitize_title($atr->slug);
//                            }
//                        }
                        if ($k->rules->cl_field == 'custom_attribute' && $k->rules->cl_field_sub != '') {
                            $k->rules->cl_field_sub = sanitize_title_with_dashes($k->rules->cl_field_sub);
                        }
                        $this->relations[$k->rules->cl_field][] = (isset($v->elementId) ? $v->elementId : false);
                    }
                }
            }
        }
    }

    public function replace_custom_field($string = '', $prod = false)
    {
        $cf_prefix = Config::get_config('wcpa_cf_prefix', 'wcpa_pcf_');

        if (is_string($string) && preg_match_all('/\{(\s)*?wcpa_pcf_([^}]*)}/', $string, $matches)) {
            $pro_id = $prod->get_parent_id();
            if ($pro_id == 0) {
                $pro_id = $prod->get_id();
            }

            foreach ($matches[2] as $k => $match) {
                $cf_value = Config::getWcpaCustomField(trim($match), $pro_id);
//                $cf_value = get_post_meta($pro_id, $cf_prefix . trim($match), true);
//                if ($cf_value == '' || $cf_value == false) {
//                    if (is_array($custom_fields)) {
//                        foreach ($custom_fields as $cf) {
//                            if ($cf['name'] == trim($match)) {
//                                $cf_value = $cf['value'];
//                                break;
//                            }
//                        }
//                    }
//                }
                if ($cf_value !== '' || $cf_value !== false) {
                    $string = str_replace($matches[0][$k], $cf_value, $string);
                }
            }
        }

        return $string;
    }

    public function getGlobalFormFields($field)
    {
        if ($field->type == 'formselector' && isset($field->form_id) && is_numeric($field->form_id)) {
            if ($this->ml->is_active()) {
                $form_id = $this->ml->lang_object_ids($field->form_id, 'post');
            } else {
                $form_id = $field->form_id;
            }
            $json_encoded = $this->form->get_form_meta_data($form_id);
            if (!$json_encoded || $json_encoded == null) {
                return;
            }
            $section_id = '_first_section';
            if (isset($field->section_id) && !empty($field->section_id)) {
                $section_id = $field->section_id;
            }


            if ($section_id == '_first_section') {
                $firstSection = reset($json_encoded);
                /** assigning global form relations to sub fields */
                if (isset($field->enableCl) && $field->enableCl && isset($field->relations) && is_array($field->relations)) {
                    foreach ($firstSection->fields as $i => $row) {
                        foreach ($row as $j => $_field) {
                            $_field->cl_rule = $field->cl_rule;

                            if($_field->enableCl) {
                                // $_field->relations[0]->operator = "and";
                                // $_field->relations[] = $field->relations[0];
                                $_field->relations[count($_field->relations) - 1]->operator = "and";
                                foreach ($field->relations as $relation) {
                                    $_field->relations[] = $relation;
                                }
                            } else {
                            $_field->enableCl = true;
                            $_field->relations = $field->relations;
                        }
                    }
                }
                }
                foreach ($firstSection->fields as $i => $row) {
                    foreach ($row as $j => $_field) {
                        $_field->_form_id = $field->form_id;
                    }
                }

                return ['fields' => $firstSection->fields, 'key' => $form_id . '-' . $section_id, 'type' => 'fields'];
            } elseif ($section_id == '_all') {
                /** assigning global form relations to sub sections */
                if (isset($field->enableCl) && $field->enableCl && isset($field->relations) && is_array($field->relations)) {
                    foreach ($json_encoded as $key => $section) {
                        $section->extra->cl_rule = $field->cl_rule;
                        $section->extra->enableCl = true;
                        $section->extra->relations = $field->relations;
                    }
                }
                foreach ($json_encoded as $key => $section) {
                    foreach ($section->fields as $i => $row) {
                        foreach ($row as $j => $_field) {
                            $_field->_form_id = $field->form_id;
                        }
                    }
                }


                return ['fields' => $json_encoded, 'key' => $form_id . '-' . $section_id, 'type' => 'section'];
            } else {
                if (isset($json_encoded->{$section_id})) {
                    $firstSection = $json_encoded->{$section_id};
                    /** assigning global form relations to sub fields */
                    if (isset($field->enableCl) && $field->enableCl && isset($field->relations) && is_array($field->relations)) {
                        foreach ($firstSection->fields as $i => $row) {
                            foreach ($row as $j => $_field) {
                                $_field->cl_rule = $field->cl_rule;

                                if($_field->enableCl) {
                                    // $_field->relations[0]->operator = "and";
                                    // $_field->relations[] = $field->relations[0];
                                    $_field->relations[count($_field->relations) - 1]->operator = "and";
                                    foreach ($field->relations as $relation) {
                                        $_field->relations[] = $relation;
                                    }
                                } else {
                                $_field->enableCl = true;
                                $_field->relations = $field->relations;
                            }
                        }
                    }
                    }
                    foreach ($firstSection->fields as $i => $row) {
                        foreach ($row as $j => $_field) {
                            $_field->_form_id = $field->form_id;
                        }
                    }

                    return ['fields' => $firstSection->fields, 'key' => $form_id . '-' . $section_id, 'type' => 'fields'];
                }
            }
        }

        return false;
    }

    /**
     * @param $v
     */
    public function find_price_dependency($v, $form_id)
    {
        if (isset($v->enablePrice) && $v->enablePrice && isset($v->pricingType) && $v->pricingType === 'custom') {
            if (isset($v->priceOptions) && $v->priceOptions == 'different_for_all') {
                foreach ($v->values as $e) {
                    //TODO price dependency for formula
                    if (!isset($e->price)) {
                        continue;
                    }
                    $e->price = $this->form->replace_vars($e->price, $form_id,
                        isset($v->_form_id) ? $v->_form_id : false);
                    if ($matches = $this->check_field_price_dependency($e->price)) {
                        foreach ($matches as $match) {
                            if (!isset($this->price_dependency[$match])) {
                                $this->price_dependency[$match] = array();
                            }
                            if (isset($v->elementId)) {
                                if (!in_array($v->elementId, $this->price_dependency[$match])) {
                                    $this->price_dependency[$match][] = $v->elementId;

                                }
                            }
                        }
                    }
                }
            } elseif (isset($v->price)) {
                $v->price = $this->form->replace_vars($v->price, $form_id, isset($v->_form_id) ? $v->_form_id : false);
                if ($matches = $this->check_field_price_dependency($v->price)) {
                    foreach ($matches as $match) {
                        if (!isset($this->price_dependency[$match])) {
                            $this->price_dependency[$match] = array();
                        }
                        if (isset($v->elementId)) {
                            if (!in_array($v->elementId, $this->price_dependency[$match])) {
                                $this->price_dependency[$match][] = $v->elementId;
                            }
                        }
                    }
                }
            }
        }
    }

    public function check_field_price_dependency($price_formula)
    {
        $matches = false;

        if (preg_match_all('/\{(\s)*?field\.([^}]*)}/', $price_formula, $matches)) {
            $ids = array();
            foreach ($matches[2] as $match) {
                $ele = explode('.', $match);
                if (is_array($ele) && count($ele) > 1 && in_array(
                        $ele[1],
                        [
                            'value',
                            'price',
                            'count',
                            'days',
                            'seconds',
                            'timestamp',
                        ]
                    )) {
                    $ids[] = $ele[0];
                }
            }

            return array_unique($ids);
        } else {
            return false;
        }
    }

    public function update_global_options($field)
    {
        if (in_array($field->type, ['select', 'image-group', 'color-group', 'radio-group', 'checkbox-group'])) {
            $options = [];
            foreach ($field->values as $i => $option) {
                if (isset($option->type) && $option->type == 'global' && $option->value !== '') {
                    $gOptions = $this->options->get_options_by_key($option->value);
                    if ($gOptions && count($gOptions) == 1) {
                        /** if the options list has only one group (default one), extract the options list only */
                        $gOptions = $this->filterOptionsBasedOnType($field->type, $gOptions[0]->options);
                    }
                    $type = $field->type;
                    $gOptions = array_map(
                        function ($opt) use ($option, $type) {
                            if (isset($opt->options) && is_array($opt->options)) {
                                $opt->options = array_map(
                                    function ($_opt) use ($option) {
                                        if (!$option->selected) {
                                            $_opt->selected = false;
                                        }

                                        return $_opt;
                                    },
                                    $opt->options
                                );
                                $opt->options = $this->filterOptionsBasedOnType($type, $opt->options);
                            } else {
                                if (!$option->selected) {
                                    $opt->selected = false;
                                }
                            }

                            return $opt;
                        },
                        $gOptions
                    );


                    $options = array_merge($options, $gOptions);
//                    $options[] = ['label' => $option->label, 'options' => $gOptions];
                } else {
                    $options[] = $option;
                }
            }

            $field->values = $options;
        }

    }

    public function filterOptionsBasedOnType($type, $options)
    {

        return array_map(
            function ($option) use ($type) {
                $v = [

                ];
                $_option = clone $option;
                if (isset($option->image) && $type == 'image-group') {
                    $v['image'] = $option->image->url;
                    $v['image_id'] = $option->image->id;
                    unset($_option->image);
                }
                $v2 = [];
                if (isset($option->pimage)) {
                    $v2['pimage'] = $option->pimage->url;
                    $v2['pimage_id'] = $option->pimage->id;
                    unset($_option->pimage);
                }
                $v = array_merge($v, $v2, (array)$_option);
                return (object)$v;
            },
            $options
        );

        return $options;
    }

    public function replace_custom_fields($v, $prod)
    {
        if ($this->has_custom_fields) {
            if (isset($v->label)) {
                $v->label = $this->replace_custom_field($v->label, $prod);
            }
            if (isset($v->value)) {
                $v->value = $this->replace_custom_field($v->value, $prod);
            }
            if (isset($v->placeholder)) {
                $v->placeholder = $this->replace_custom_field($v->placeholder, $prod);
            }
            if (isset($v->description)) {
                $v->description = $this->replace_custom_field($v->description, $prod);
            }
            if (isset($v->customFormula)) {
                $v->customFormula = $this->replace_custom_field($v->customFormula, $prod);
            }
            if (isset($v->price)) {
                $v->price = $this->replace_custom_field($v->price, $prod);
            }
            if (isset($v->values) && is_array($v->values)) {
                foreach ($v->values as $e) {
                    if (isset($e->label)) {
                        $e->label = $this->replace_custom_field($e->label, $prod);
                    }
                    if (isset($e->value)) {
                        $e->value = $this->replace_custom_field($e->value, $prod);
                    }
                    if (isset($e->price)) {
                        $e->price = $this->replace_custom_field($e->price, $prod);
                    }
                    if (isset($e->description)) {
                        $e->description = $this->replace_custom_field($e->description, $prod);
                    }
                }
            }
            if (isset($v->repeater_bind_formula)) {
                $v->repeater_bind_formula = $this->replace_custom_field($v->repeater_bind_formula, $prod);
            }
            if (isset($v->repeater_max)) {
                $v->repeater_max = $this->replace_custom_field($v->repeater_max, $prod);
            }
        }
    }

    public function processFields($field, $form_id)
    {

        //commenting as it done in process_cl
//        if ($this->ml->is_active()) {
//            // use variation conditions to translations
//            if (isset($field->enableCl) && $field->enableCl && isset($field->relations) && is_array(
//                    $field->relations
//                )) {
//                foreach ($field->relations as $relation) {
//                    if (is_array($relation->rules) && count($relation->rules)) {
//                        foreach ($relation->rules as $rule) {
//                            if ($rule->rules->cl_field == 'attribute' && $rule->rules->cl_field_sub) {
//                                $rule->rules->cl_val = $this->ml->getAttribute($rule->rules->cl_val, $rule->rules->cl_field_sub);
//                            }
//                            if (substr($rule->rules->cl_field, 0, 7) === "product") {
//                                $rule->rules->cl_val = $this->ml->lang_object_ids($rule->rules->cl_val, 'post');
//                            }
//                        }
//                    }
//                }
//            }
//
//
//        }

        /** Check has formula in Label , and in value for Content field */
        if (isset($field->label) && hasFormula($field->label)) {
            $field->hasFormula = true;
            $field->label = $this->form->replace_vars($field->label, $form_id,
                isset($field->_form_id) ? $field->_form_id : false);
        }
        if (isset($field->cartLabel) && hasFormula($field->cartLabel)) {
            $field->hasFormula = true;
            $field->cartLabel = $this->form->replace_vars($field->cartLabel, $form_id,
                isset($field->_form_id) ? $field->_form_id : false);
        }
        if (isset($field->description) && hasFormula($field->description)) {
            $field->hasFormula = true;
            $field->description = $this->form->replace_vars($field->description, $form_id,
                isset($field->_form_id) ? $field->_form_id : false);
        }
        if (isset($field->description)) {
            $field->description = nl2br(trim($field->description));
        }
        if (isset($field->tooltip)) {
            $field->tooltip = nl2br(trim($field->tooltip));
        }
        if ($field->type == 'content' && isset($field->value) && hasFormula($field->value)) {
            $field->hasFormula = true;
            $field->value = $this->form->replace_vars($field->value, $form_id,
                isset($field->_form_id) ? $field->_form_id : false);
        }

        if ($field->type == 'image-group') {
            if ($field->values && !empty($field->values)) {
                foreach ($field->values as $k => $val) {
                    if (isset($field->values[$k]->options) && is_array($field->values[$k]->options)) {
                        foreach ($field->values[$k]->options as $_k => $_val) {
                            $field->values[$k]->options[$_k]->thumb_src = $_val->image;
                            if (isset($_val->image_id) && $_val->image_id > 0 && (isset($field->disp_size_img) && $field->disp_size_img->width > 0)) {
                                $img_obj = wp_get_attachment_image_src($_val->image_id, [
                                    $field->disp_size_img->width,
                                    empty($field->disp_size_img->height) ? 0 : $field->disp_size_img->height
                                ]);
                                if ($img_obj) {
                                    $field->values[$k]->options[$_k]->thumb_src = $img_obj[0];
                                }
                            }
                        }
                    } else {
                        $field->values[$k]->thumb_src = $val->image;
                        if (isset($val->image_id) && $val->image_id > 0 && (isset($field->disp_size_img) && $field->disp_size_img->width > 0)) {
                            $img_obj = wp_get_attachment_image_src($val->image_id, [
                                $field->disp_size_img->width,
                                empty($field->disp_size_img->height) ? 0 : $field->disp_size_img->height
                            ]);
                            if ($img_obj) {
                                $field->values[$k]->thumb_src = $img_obj[0];
                            }
                        }
                    }
                }
            }
        }
        if ($field->type == 'file') {
            $allowedFileTypes = fileTypesToExtensions($field);

            $field->allowedFileTypes = implode(',', $allowedFileTypes);
        }
        if ($field->type == 'image-group' && isset($field->show_as_product_image) && $field->show_as_product_image) {
            if ($field->values && !empty($field->values)) {
                foreach ($field->values as $k => $val) {
                    if (isset($val->image_id) && $val->image_id > 0) {
                        $attachProps = wc_get_product_attachment_props($val->image_id);
                        if (isset($attachProps['title'])) {
                            $attachProps['title'] = htmlspecialchars($attachProps['title'], ENT_QUOTES);
                        }
                        $val->productImage = $attachProps + ['image_id' => $val->image_id];
                    } else {
                        $props = [
                            'title' => htmlspecialchars($val->label, ENT_QUOTES),
                            'caption' => '',
                            'url' => $val->image,
                            'alt' => $val->label,
                            'src' => $val->image,
                            'srcset' => false,
                            'sizes' => false,
                            'src_w' => '',
                            'full_src' => $val->image,
                            'full_src_w' => '',
                            'full_src_h' => '',
                            'gallery_thumbnail_src' => $val->image,
                        ];
                        $val->productImage = $props;
                    }
                }
            }
        }
        /**  give priority for enable_product_image  than show_as_product_image, so called it after show_as_product_image   */
        if (isset($field->enable_product_image) && $field->enable_product_image) {
            if ($field->values && !empty($field->values)) {
                foreach ($field->values as $k => $val) {
                    if (isset($val->pimage_id) && $val->pimage_id > 0) {
                        $attachProps = wc_get_product_attachment_props($val->pimage_id);
                        if (isset($attachProps['title'])) {
                            $attachProps['title'] = htmlspecialchars($attachProps['title'], ENT_QUOTES);
                        }
                        $val->productImage = $attachProps + ['image_id' => $val->pimage_id];
                    } elseif (isset($val->pimage) && $val->pimage) {
                        $props = [
                            'title' => htmlspecialchars($val->label, ENT_QUOTES),
                            'caption' => '',
                            'url' => $val->pimage,
                            'alt' => $val->label,
                            'src' => $val->pimage,
                            'srcset' => false,
                            'sizes' => false,
                            'src_w' => '',

                            'full_src' => $val->pimage,
                            'full_src_w' => '',
                            'full_src_h' => '',
                            'gallery_thumbnail_src' => $val->pimage,
                        ];
                        $val->productImage = $props;
                    }
                }
            }
        }
    }

    public function setTranslationKeys($field)
    {
        $transKey = apply_filters('wcpa_attribute_translations_key', 'gt_translate_keys');
        if ($transKey == '' || $transKey === false) {
            return;
        }

        $translate_keys_options = ['label', 'tooltip', 'description'];
        $keys = [];
        foreach ($this->translate_keys as $key) {
            if (isset($field->{$key})) {
                $keys[] = $key;
            }
        }

        $field->{$transKey} = $keys;

        if (isset($field->values) && is_array($field->values)) {

            foreach ($field->values as $k => $v) {
                if (isset($v->options) && is_array($v->options)) {
                    foreach ($v->options as $_k => $_v) {
                        $keys = [];
                        foreach ($translate_keys_options as $key) {
                            if (isset($_v->{$key})) {
                                $keys[] = $key;
                            }
                        }
                        $field->values[$k]->options[$_k]->{$transKey} = $keys;
                    }
                } else {
                    $keys = [];
                    foreach ($translate_keys_options as $key) {
                        if (isset($v->{$key})) {
                            $keys[] = $key;
                        }
                    }
                    $field->values[$k]->{$transKey} = $keys;

                }
            }
        }
    }

    public function findScriptsRequired($field, &$scripts)
    {
        if (!$scripts['file'] && $field->type == 'file' && isset($field->upload_type) && $field->upload_type !== 'basic') {
            $scripts['file'] = true;
        }
        if (!$scripts['datepicker']
            && (in_array($field->type, ['datetime-local', 'date', 'time']))
            && (!isset($field->picker_type) || $field->picker_type !== 'basic')) {
            $scripts['datepicker'] = true;
        }
        if (!$scripts['color']
            && (in_array($field->type, ['color']))
            && isset($field->color_picker_type) && $field->color_picker_type !== 'basic') {
            $scripts['color'] = true;
        }

        if (!$scripts['select']
            && $field->type == 'select') {
            if (isset($field->multiple) && $field->multiple) {
                $scripts['select'] = true;
            } else {
                /** check if is grouped*/
                foreach ($field->values as $v) {
                    if (isset($v->options)) {
                        $scripts['select'] = true;
                        break;
                    }
                }
            }
        }
        if (!$scripts['productGallery']
            && ((isset($field->enable_product_image) && $field->enable_product_image)
                || (isset($field->show_as_product_image) && $field->show_as_product_image))
        ) {
            $scripts['productGallery'] = true;
        }
        if (!$scripts['googlemapplace']
            && $field->type == 'placeselector') {
            $scripts['googlemapplace'] = true;
        }
    }

    public function map_dependencies()
    {
        $totalFieldsCount = 0;
        if ($this->data && $this->data !== null) {
            foreach ($this->data as $sectionKey => $section) {
                foreach ($section->fields as $rowIndex => $row) {
                    foreach ($row as $colIndex => $field) {
                        if (isset($this->price_dependency[$field->elementId])) {
                            $field->price_dependency = $this->price_dependency[$field->elementId];
                        } else {
                            $field->price_dependency = false;
                        }

                        if (isset($this->relations[$field->elementId])) {
                            $field->cl_dependency = $this->relations[$field->elementId];
                        } else {
                            $field->cl_dependency = false;
                        }
                        $totalFieldsCount++;
                    }
                }
            }
        }
        return $totalFieldsCount;
    }

    public function setCache($product_id, $data)
    {
        set_transient('wcpa_fields_' . $product_id, $data, 24 * HOUR_IN_SECONDS);
    }

    /**
     * @param $ids
     * @param $p_id
     *
     * @return array
     */
    public function re_order_forms($ids, $p_id, $applyCatOrder = false)
    {

        $form_order = get_post_meta($p_id, 'wcpa_product_meta_order', true);

        if ($form_order && is_array($form_order)) {
            $ids_new = array();
            $form_order_new = array();
            foreach ($ids as $id) {
                if (isset($form_order[$id])) {
                    $form_order_new[$id] = $form_order[$id];
                } else if ($applyCatOrder && isset($form_order[0])) {
                    $form_order_new[$id] = $form_order[0];
                }
            }
            arsort($form_order_new);

            foreach ($form_order_new as $form_id => $order) {
                $index = array_search($form_id, $ids);
                if ($index !== false) {
                    unset($ids[$index]); // remove item at index 0
                    $ids = array_values($ids); // 'reindex' array
                    $length = count($ids);
                    if ($order <= 0) {
                        $pos = 0;
                    } elseif ($order > $length) {
                        $pos = $length;
                    } else {
                        $pos = $order - 1;
                    }

                    array_splice($ids, $pos, 0, $form_id);
                }
            }
        }

        return $ids;
    }
}
