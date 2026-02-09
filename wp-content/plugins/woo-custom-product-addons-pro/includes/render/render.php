<?php

namespace Acowebs\WCPA;


class Render
{
    public $token;
    public $action_hook_tag = false;
    public $product = false;
    /**
     * @var bool
     */
    private $cartError = false;
    /**
     * @var int
     */
    private $product_id;

    public function __construct()
    {
        $this->token = WCPA_TOKEN;

        /** need to verify */
        add_action('rest_api_init', array($this, 'wc_api_support'));
        add_action( 'graphql_register_types', array($this,'extend_wpgraphql_schema') );

        $this->init();
    }

    public function init()
    {
        if ($this->action_hook_tag !== false) {
            remove_action($this->action_hook_tag[0], array($this, 'render_form'), $this->action_hook_tag[1]);
        }


//        add_action($this->action_hook_tag[0], array($this, 'render_form'), $this->action_hook_tag[1]);
        /** why 'woocommerce_before_add_to_cart_form' hook? all product type has this hook.
         *Why not using directly the before_add to cart hook? to access global $product variable for hook filter
         */
		add_action('woocommerce_before_single_product', array($this, 'render_init'), 10); // added this after rnb plugin not showed the form in some sites
        add_action('woocommerce_before_add_to_cart_form', array($this, 'render_init'), 10);
        add_action('woocommerce_before_add_to_cart_button', array($this, 'render_init'), 1);
        add_action('wp_footer', array($this, 'popup_container'));


        add_filter('wcpa_render_form', array($this, 'wcpa_render_form_filter'), 10, 3);
    }

    public function wcpa_render_form_filter($data, $product,$isRest=false)
    {
        $product_id = $product->get_id();
//        $this->product = $product;
//        $this->product_id = $product_id;
        if($isRest){
            return $this->render_form(true, true,$product_id);
        }
        return $data . $this->render_form(false, true,$product_id);
    }


    public function render_form($isRest = false, $doReturn = false, $product_id = false)
    {
//        if (!$this->product) {
        if ($product_id) {
            $product = wc_get_product($product_id);
        } else {
            global $product;
        }
        if (!is_a($product, 'WC_Product')) {
            return;
        }
        $product_id = $product->get_id();
        $this->product = $product;
        $this->product_id = $product_id;
//        }

        $wcpaProduct = new Product();

        $data = $wcpaProduct->get_fields($this->product_id);


        if ($data['fields'] && !emptyObj($data['fields'])) {
            /** checking fields not empty */
            $fields = $data['fields'];


            if (isset(Main::$cartError[$this->product_id]) && Main::$cartError[$this->product_id]) {
                $this->cartError = true;
            }


            $this->processFields($fields);


//            $currency = new Currency();

            $cartEdit = $this->checkCartEdit($fields);
            $loadMapApi = false;
            if ($data['scripts']) {
                foreach ($data['scripts'] as $tag => $status) {
                    if ($status) {
                        if ($tag == 'googlemapplace') {
                            $loadMapApi = true;
                        } else {

                            wp_enqueue_script($this->token . '-' . $tag);

                        }
                    }
                }
            }

            wp_enqueue_script($this->token . '-front');
            if ($loadMapApi) {
                wp_enqueue_script($this->token . '-googlemapplace');
            }


            $tax_rate = getTaxRate($this->product);

            $design = Config::get_config('active_design', false);

            $wcpaData = [
                'product' => $this->getProductData(),
                'fields' => $data['fields'],
                'config' => $data['config'],
                'mc_unit' => Currency::getConUnit(),
                'tax_rate' => $tax_rate,
                'tax_rate_real' => getRealTaxRate($this->product),
                'discount' => Discounts::getDiscountRule($this->product),
                'design' => $design['common'],
                'formulas' => $data['formulas'],
                'clones' => $cartEdit['clones'],
                'cartKey' => $cartEdit['cart_key']

            ];

            if ($isRest === true) { // REST api request
                return [
                    'wcpaData' => $wcpaData
                ];
            }
//
//echo '<textarea>'.htmlspecialchars(wp_json_encode($wcpaData), ENT_QUOTES).'</textarea>';

            $fieldsCount = min(5,isset($data['fieldsCount'])?$data['fieldsCount']:3);

            $html = '<div class="wcpa_form_outer" 
           data-gt-translate-attributes=\'[{"attribute":"data-wcpa", "format":"json"}]\'
            data-product=\'' . htmlspecialchars(wp_json_encode(['wc_product_price' => 'backward_comp_dont_use']), ENT_QUOTES) . '\'
				 data-wcpa=\'' . htmlspecialchars(wp_json_encode($wcpaData), ENT_QUOTES) . '\' >
			 <div class="wcpa_skeleton_loader_area">' . str_repeat(
                    '<div class="wcpa_skeleton_loader">
				 <div class="wcpa_skeleton_label"></div>
				 <div class="wcpa_skeleton_field"></div>
			 </div>',
                    $fieldsCount
                ) . '
			
			</div>
			</div>';
            if ($doReturn) {
                return $html;
            } else {
                echo $html;
            }
        }

        if ($isRest) { // REST api request
            return null;
        }
    }

    /**
     * Prefill if the form validation got wrong
     * Prefill data passed in URL
     * prefill data for cart edit
     *
     */

    public function processFields(
        &$fields
    )
    {

        $cLogic = new CLogic(false, false, false, false, 1);

        //TODO not handling repeated fields */
        $sectionsToRemove = [];
        $rowsToResetIndex = [];
        foreach ($fields as $sectionKey => $section) {
            if (isset($section->extra->enableCl) && $section->extra->enableCl && isset($section->extra->relations) && is_array(
                    $section->extra->relations
                )) {
                $doSkip = $cLogic->evalUserRoleRelation($section->extra->relations, $section->extra->cl_rule);
                if ($doSkip) {
                    // remove section
                    $sectionsToRemove[] = $sectionKey;
                    continue;
                }
            }
            foreach ($section->fields as $rowIndex => $row) {
                foreach ($row as $colIndex => $field) {
                    if (isset($field->enableCl) && $field->enableCl && isset($field->relations) && is_array(
                            $field->relations
                        )) {
                        $doSkip = $cLogic->evalUserRoleRelation($field->relations, $field->cl_rule);
                        if ($doSkip) {
                            $rowsToResetIndex[$sectionKey][] = $rowIndex;
                            unset($section->fields[$rowIndex][$colIndex]);
                            continue;
                        }


                    }


                    if ($field->type == 'productGroup') {
                        processProductGroup($field);
                    }
                    $field->value = apply_filters('wcpa_field_default_value', isset($field->value) ? $field->value : '',
                        $field, $this->product_id);


                    $this->setDefaultValue($field);
                    if ($field->type == 'content' && isset($field->value)) {
                        $field->value = do_shortcode($field->value);
                    }

                    $field = apply_filters('wcpa_form_field', $field, $this->product_id);

                }
            }
        }

        foreach ($rowsToResetIndex as $sec => $rowIndexes) {
            $resetSecFieldsIndex = false;
            foreach ($rowIndexes as $rowIndex) {
                if (isset($fields->{$sec}->fields[$rowIndex])) {
                    $fields->{$sec}->fields[$rowIndex] = array_values(
                        $fields->{$sec}->fields[$rowIndex]
                    );
                    if (count($fields->{$sec}->fields[$rowIndex]) == 0) {
                        unset($fields->{$sec}->fields[$rowIndex]);
                        $resetSecFieldsIndex = true;
                    }
                }
            }
            if ($resetSecFieldsIndex) {
                $fields->{$sec}->fields = array_values($fields->{$sec}->fields);
            }
        }

        foreach ($sectionsToRemove as $key) {
            unset($fields->{$key});
        }
    }

    public function setDefaultValue(&$field, $default = false)
    {
        if (!isset($field->name)) {
            return;
        }
        if ($this->cartError && fieldFromName(
                $field->name,
                'isset'
            )) { // if there is a validation error, it has persist the user entered values,

            $default_value = fieldFromName($field->name, 'value');
        } elseif ((isset($field->name)) && isset($_GET[$field->name])) { // using get if there is any value passed using url/get method
            $default_value = $_GET[$field->name];
        } else {
            return;
        }


        $field->preSetValue = $this->sanitizeValue($field->type, $default_value);
    }

    public function sanitizeValue($type, $value)
    {
        switch ($type) {
            case 'text':
            case 'date':
            case 'number':
            case 'color':

            case 'hidden':
            case 'time':
            case 'datetime-local':
                return sanitize_text_field(wp_unslash($value));

                break;
            case 'textarea':
                return sanitize_textarea_field(wp_unslash($value));

                break;
            case 'file':

                $files = json_decode(wp_unslash($value));
                if ($files && is_array($files)) {
                    $value = [];
                    foreach ($files as $file) {
                        $value[] = [
                            'name' => sanitize_text_field($file->file_name),
                            'url' => sanitize_text_field($file->url),
                            'type' => sanitize_text_field($file->type)
                        ];
                    }
                }

                return $value;

                break;
            case 'select':
            case 'checkbox-group':
            case 'radio-group':
            case 'image-group':
            case 'color-group':
            case 'productGroup':
                if (is_array($value)) {
                    $_values = $value;
                    $_values = array_map(
                        function ($v) {
                            return sanitize_text_field(wp_unslash($v));
                        },
                        $_values
                    );

                    /* some plugins/themes send checkbox/radio field data even if they are not checked, it can filter using null*/
                    $_values = array_filter(
                        $_values,
                        function ($value) {
                            return ($value !== null && $value !== false && $value !== '');
                        }
                    );
                    $value = array_values($_values);// in front end , to treat it as array, set the index from 0
                } else {
                    $value = sanitize_text_field($value);
                }

                return $value;
                break;
            //TODO reset of field types
        }
    }

    public function checkCartEdit(&$fields)
    {
        $clones = false;
        $cart_key = false;
        $copy_cart = apply_filters('wcpa_copy_cart_data', false, $this->product_id);
        $response = ['clones' => $clones, 'cart_key' => $cart_key];
        if ((isset($_GET['cart_key']) && !empty($_GET['cart_key']))) {
            $cart_key = sanitize_text_field($_GET['cart_key']);
            $response['cart_key'] = $cart_key;
        } else if ($copy_cart !== false) {
            $cart_key = sanitize_text_field($copy_cart);
        }
        if ($cart_key !== false) {

//            $cart_key = sanitize_text_field($_GET['cart_key']);

            $cartItem = WC()->cart->get_cart_item($cart_key);
            if (isset($cartItem) && !empty($cartItem) &&
                isset($cartItem[WCPA_CART_ITEM_KEY]) &&
                !empty($cartItem[WCPA_CART_ITEM_KEY])
            ) {
                $cartData = [];

                /** find any section has cloned,
                 * and then find the clone Count,
                 * Then create cloneSections to the fields
                 */
                $cloneCount = 0;
                $clones = ['sections' => [], 'fields' => []];
                foreach ($cartItem[WCPA_CART_ITEM_KEY] as $sectionKey => $section) {
                    if (!isset($fields->{$sectionKey})) {
                        if (isset($section['extra']->isClone) && $section['extra']->isClone) {
                            $firstRow = reset($section['fields']);
                            if (!is_array($firstRow)) {
                                continue;
                            }
                            $firstField = reset($firstRow);
                            if (!is_array($firstField)) {
                                continue;
                            }

                            $parentKey = $section['extra']->parentKey;
                            if (isset($clones['sections'][$parentKey])) {
                                $clones['sections'][$parentKey]['count']++;
                            } else {
                                $clones['sections'][$parentKey] = [
                                    'count' => 1,
                                    'values' => []
                                ];
                            }
                        }
                    }
                }

                foreach ($cartItem[WCPA_CART_ITEM_KEY] as $sectionKey => $section) {
                    foreach ($section['fields'] as $rowIndex => $row) {
                        foreach ($row as $colIndex => $field) {
                            if (!isset($fields->{$sectionKey}->fields[$rowIndex][$colIndex])) {
                                $formData = $field['form_data'];
                                if (isset($formData->isClone) && $formData->isClone && is_array($field['name']) && isset($formData->parentId)) {
                                    $parentId = $formData->parentId;
                                    $value = fieldValueFromCartValue($field['type'], $field['value']);

                                    if (isset($clones['fields'][$parentId])) {
                                        $clones['fields'][$parentId]['count']++;
                                        $clones['fields'][$parentId]['values'][] = $value;
                                    } else {
                                        $clones['fields'][$parentId] = [
                                            'count' => 1,
                                            'values' => [$value]
                                        ];
                                    }
                                } elseif ($section['extra']->isClone) {
                                    $parentKey = $section['extra']->parentKey;
                                    $value = fieldValueFromCartValue(
                                        $field['type'],
                                        $field['value']
                                    );

                                    $clones['sections'][$parentKey]['values'][$formData->elementId] = $value;
                                }
                            }
                        }
                    }
                }


                foreach ($fields as $sectionKey => $section) {
                    foreach ($section->fields as $rowIndex => $row) {
                        foreach ($row as $colIndex => $field) {
                            $_field = &$fields->{$sectionKey}->fields[$rowIndex][$colIndex];
                            if (isset($cartItem[WCPA_CART_ITEM_KEY][$sectionKey]['fields'][$rowIndex][$colIndex])) {
                                $cartField = $cartItem[WCPA_CART_ITEM_KEY][$sectionKey]['fields'][$rowIndex][$colIndex];
                                if ($field->type !== $cartField['type']) {
                                    continue; // in case the form got changed from backend and the cart data doesnt match, just skip it
                                }
                                $value = $cartField['value'];

                                $_field->preSetValue = fieldValueFromCartValue(
                                    $field->type,
                                    $value
                                );
                                if (is_array($value) && isset($value[0]['quantity'])) {
                                    /** extract quantity from this  */
                                    $_field->preSetQuantity = array_map(function ($e) {
                                        return ['value' => $e['value'], 'quantity' => $e['quantity']];
                                    }, $value);
                                } elseif (isset($cartField['quantity'])) {
                                    $_field->preSetQuantity = $cartField['quantity'];
                                }
                                // dont set this as Value, as it can cause issues for
                                // repeated fields , when adding new field, if we set as value, this value will be treat as default value in the cloned field as well
                            } else {
//                                $_field->value = is_array(
//                                    $_field->value
//                                ) ? [] : ''; // No need to reset the default value, instead set the preSetValue as null
                                $_field->preSetValue = (isset($_field->value) && is_array(
                                        $_field->value
                                    )) ? [] : '';
                            }
                        }
                    }
                }


                if (isset($cartItem['quantity'])) {
                    add_filter(
                        'woocommerce_quantity_input_args',
                        function ($args, $product) use ($cartItem) {
                            $args['input_value'] = $cartItem['quantity'];

                            return $args;
                        },
                        2,
                        10
                    );
                }
            }
        }
        $response['clones'] = $clones;
        return $response;
    }

    public function getProductData()
    {
        $product_data = array();
        $product_data['product_price'] = Discounts::getProductPrice($this->product);
        $price_html = $this->product->get_price_html();
        $product_data['price_html'] = $price_html;
        $product_data['original_product_price'] = Discounts::getProductPrice($this->product, true);

        $product_data['price_including_tax'] = wc_get_price_including_tax( $this->product, array( 'qty' => 1, 'price' => $this->product->get_price()  ) );
        $product_data['price_excluding_tax'] = wc_get_price_excluding_tax( $this->product, array( 'qty' => 1, 'price' => $this->product->get_price()  ) );


        //$wcpa_price ? $wcpa_price : (apply_filters('raw_woocommerce_price', wcpa_get_price_shop($this->product)) / $this->get_con_unit(true));
        $product_data['product_id'] = ['parent' => $this->product->get_id(), 'variation' => false];
        $product_data['is_variable'] = $this->product->is_type('variable') ? true : false;

        $product_data['stock_status'] = $this->product->get_stock_status('edit');
        $product_data['stock_quantity'] = $this->product->get_stock_quantity('edit');
        $product_data['parent_sku'] = $this->product->get_sku('edit');


        $product_data['product_attributes'] = $this->get_pro_attr_list();

        $product_data['custom_fields'] = Config::getWcpaCustomFieldByProduct($this->product->get_id());
        $product_data['is_taxable'] = wc_tax_enabled() && 'taxable' === $this->product->get_tax_status() ;
        $product_data['product_name'] = $this->product->get_title();
        $product_data['hasImage'] = false;




//        $suffix = get_option( 'woocommerce_price_display_suffix' );
//        $product_data['tax_suffix'] = $suffix;
//
//        if ( $suffix && wc_tax_enabled() && 'taxable' === $this->product->get_tax_status() ) {
//            $price = $this->product->get_price();
//            $replacements = array(
//                '{price_including_tax}' =>  wc_get_price_including_tax( $this->product, array( 'qty' => 1, 'price' => $price  ) ), // @phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.ArrayItemNoNewLine, WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
//                '{price_excluding_tax}' =>  wc_get_price_excluding_tax( $this->product, array( 'qty' => 1, 'price' => $price  ) ), // @phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
//            );
//            $product_data['tax_suffix_replacements'] = $replacements;
//
//        }

        return $product_data;
    }

    public function get_pro_attr_list($pro = false)
    {
        if ($pro === false) {
            $pro = $this->product;
        }
        $attributes = $pro->get_attributes();

        $product_attributes = [];
        foreach ($attributes as $key => $attribute) {
            $values = array();
            $data = $attribute->get_data();
            if ($data['is_variation'] && !$pro->is_type('simple')) {
                // checking is_type('simple') to fix thi issue " when a custom variation set as variable,and then product switched back to single product, the custom attribute wont change its variation type,"
                // this case has to re-check
//                $product_attributes[sanitize_title_with_dashes($attribute->get_name())] = [
//                    'is_variation' => true,
//                    'values'       => []
//                ]; // not sure why earlier used like this sanitize_title_with_dashes($attribute->get_name()), changed to $key
                // as it causing issues with germen characters for attribute names
                $product_attributes[$key] = [
                    'is_variation' => true,
                    'values' => []
                ];
            } else {
                $product_attributes[$key] = [
                    'is_variation' => false,
                    'values' => []
                ];
                if ($attribute->is_taxonomy()) {
                    $attribute_values = wc_get_product_terms(
                        $pro->get_id(),
                        $attribute->get_name(),
                        array('fields' => 'all')
                    );
                    foreach ($attribute_values as $attribute_value) {
                        $value_slug = esc_html($attribute_value->slug);
                        $values[] = $value_slug;
                    }
                } else {
                    $values = $attribute->get_options();
                }
                $product_attributes[$key]['values'] = $values;
            }
        }

        return $product_attributes;
    }

    public function extend_wpgraphql_schema(){
        register_graphql_field( 'product', 'wcpaFormFields', [
            'type' => 'String',
            'description' => __( 'Acowebs Product addons fields', '' ),
            'resolve' => function($product) {
                return json_encode($this->get_form_fields_api(['id'=>$product->ID]));
            }
        ] );

    }
    public function wc_api_support()
    {
        register_rest_field('product', 'wcpa_form_fields', array(
            'get_callback' => array($this, 'get_form_fields_api'),
            'schema' => null,
        ));


    }

    public function get_form_fields_api($object)
    {
        $pro_id = $object['id'];
//        if (!$this->product) {
//            $this->product = wc_get_product($pro_id);
//            $this->product_id = $pro_id;
//        }

        return $this->render_form(true, false, $pro_id);

    }

    public function popup_container()
    {
        if (Config::get_config('enqueue_cs_js_all_pages') || is_product()) {
            wp_enqueue_script($this->token . '-front'); // to ensure front script loaded after all depends scripts loaded
        }
        echo '<div id="wcpa_img_preview"></div>';
    }

    public function render_init()
    {

		remove_action('woocommerce_before_add_to_cart_form', array($this, 'render_init'), 10);
        remove_action('woocommerce_before_add_to_cart_button', array($this, 'render_init'), 1);
        global $product;
        $hook_simple = [
            'simple' => [Config::get_config('render_hook'), Config::get_config('render_hook_priority')],
            'variable' => [
                Config::get_config('render_hook_variable'), Config::get_config('render_hook_variable_priority')
            ]
        ];
        $this->action_hook_tag = apply_filters('wcpa_form_render_hook', $hook_simple, $product);
        if ($this->action_hook_tag['simple'][0] == $this->action_hook_tag['variable'][0]
            && $this->action_hook_tag['simple'][1] == $this->action_hook_tag['variable'][1]) {
            add_action($this->action_hook_tag['simple'][0], array($this, 'render_form'),
                $this->action_hook_tag['simple'][1]);
        } else {
            if ($product->is_type('variable')) {
                add_action($this->action_hook_tag['variable'][0], array($this, 'render_form'),
                    $this->action_hook_tag['variable'][1]);
            } else {
                add_action($this->action_hook_tag['simple'][0], array($this, 'render_form'),
                    $this->action_hook_tag['simple'][1]);
            }
        }
    }
}