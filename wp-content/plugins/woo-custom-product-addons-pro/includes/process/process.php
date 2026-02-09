<?php


namespace Acowebs\WCPA;

use stdClass;
use WC_AJAX;
use WC_Product;
use WC_Session_Handler;
use WP_REST_Response;

class Process
{
    public $thumb_image = false;
    public $subProducts = [];
    public $checkoutFields = [];
    private $processed_data = array();
    private $form_data = array();
    private $fields = false;
    private $product = false;
    private $product_id = false;
    private $quantity = 1;
    private $token;
    private $orderAgainData = false;

    /**
     * @var mixed
     */
    private $formConf;
    private $formulas;
    /**
     * @var false|WC_Product|null
     */
    private $parentProduct = false;

    public function __construct()
    {
        $this->token = WCPA_TOKEN;
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 4);
        add_filter('wcpa_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 5);
        add_filter('woocommerce_add_to_cart_validation', array($this, 'add_to_cart_validation'), 10, 4);
        add_action('rest_api_init', array($this, 'register_routes'));
        add_filter('wp_check_filetype_and_ext', [$this, 'add_multiple_mime_types'], 99, 3);
        add_action('wc_ajax_wcpa_ajax_add_to_cart', array($this, 'ajax_add_to_cart'));

        add_filter('woocommerce_order_again_cart_item_data', array($this, 'order_again_cart_item_data'), 50, 3);

        add_filter('pllwc_add_cart_item_data', array($this, 'pllwc_cart_item_data'), 10, 2); // polylang
    }


    public function order_again_cart_item_data($cart_item_data, $item, $order)
    {
        $meta_data = $item->get_meta(WCPA_ORDER_META_KEY);
        $this->orderAgainData = $meta_data;
        $product_id = (int)$item->get_product_id();
        $variation_id = (int)$item->get_variation_id();
        $quantity = $item->get_quantity();
        foreach ( $item->get_meta_data() as $meta ) {
            //using variation data for cl logic
            if ( taxonomy_is_product_attribute( $meta->key ) || meta_is_product_attribute( $meta->key, $meta->value, $product_id ) ) {
                $_REQUEST['attribute_'.$meta->key] = $meta->value;
            }
        }
        $passed = $this->add_to_cart_validation(true,
            $product_id, $quantity, $variation_id, true);
        if (!$passed) {
// set error
            $product = $item->get_product();
            $name = '';
            if ($product) {
                $name = $product->get_name();
            }
            wc_add_notice(sprintf(
            /* translators: %s Product Name */
                __('Addon options of product %s has been changed, Cannot proceed with older data. 
            You can go to product page and fill the addon fields again inorder to make new order',
                    'woo-custom-product-addons-pro'),
                $name),
                'error');

            return $cart_item_data;
        }

        $cart_item_data = $this->add_cart_item_data($cart_item_data, $product_id, $variation_id, $quantity);

        /** remove validation as already done */
        remove_filter('woocommerce_add_to_cart_validation', array($this, 'add_to_cart_validation'));

        return $cart_item_data;
    }

    /**
     * @param $passed
     * @param $product_id
     * @param int $qty
     * @param false $variation_id
     * @param false $variations Optional, it will be passed for order again validation action
     * @param false $cart_item_data Optional, it will be passed for order again validation action
     *
     * @return bool
     */
    public function add_to_cart_validation(
        $passed,
        $product_id,
        $qty = 1,
        $variation_id = false,
        $ignoreCaptcha = false
    )
    {
        if ((($pid = wp_get_post_parent_id($product_id)) != 0) &&
            ($variation_id == false)
        ) {
            $variation_id = $product_id;
            $product_id = $pid;
        }


        /**
         * ignore checking if $passed is false, as it can be validation error thrown by other plugins or woocommerce itself
         */
        if ($passed === true) {
            /** must pas $product-id, dont pass $variation id */
            $this->setFields($product_id);
            if (!$ignoreCaptcha && $this->formConf['enable_recaptcha']) {
                if ($this->is_recaptcha_valid() !== true) {
                    wc_add_notice(__('Please verify you are not a bot', 'woo-custom-product-addons-pro'), 'error');
                    $passed = false;
                    Main::setCartError($product_id, !$passed);

                    return $passed;
                }
            }

            $this->set_product($product_id, $variation_id, $qty);

            $status = $this->read_form();
            if ($status !== false) {
                $this->process_cl_logic();
                $passed = $this->validateFormData();
            } else {
                $passed = false;
            }
        }

        Main::setCartError($product_id, !$passed);

        if ($passed) {
            /** in cart edit case, remove items */
            if (isset($_POST['wcpa_current_cart_key']) && !empty($_POST['wcpa_current_cart_key'])) {
                $cart_key = sanitize_text_field($_POST['wcpa_current_cart_key']);
//                if ($cart_key == $cart_item_key) {
//                    /** when the use resubmit without any changes in values, the key will be same, and the system will increase the quantity
//                     *Here we need to reset the quantity with new value
//                     */
//                    WC()->cart->set_quantity($cart_item_key, $quantity);
//                } else {
//                    WC()->cart->remove_cart_item($cart_key);
//                }
                WC()->cart->remove_cart_item($cart_key);
                unset($_POST['wcpa_current_cart_key']);
                /** reset this once executed, other wise it can cause issue if add on as product groups */
            }
        }

        return $passed;
    }

    /**
     * Initiate form fields if not initiated already,
     *
     * @param $product_id id must be product parent id, dont pass variation id
     *
     * @since 5.0
     */
    public function setFields($product_id)
    {

        $this->fields = false;
        $wcpaProduct = new Product();
        $data = $wcpaProduct->get_fields($product_id);

        if (!$data['fields']) {
            return;
        }
        $this->fields = $data['fields'];
        $this->formConf = $data['config'];
        $this->formulas = $data['formulas'];
    }

    public function is_recaptcha_valid()
    {
        // Make sure this is an acceptable type of submissions
        if (isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])) {
            $captcha = $_POST['g-recaptcha-response'];
            try {
                $url = 'https://www.google.com/recaptcha/api/siteverify';
                $data = [
                    'secret' => Config::get_config('recaptcha_secret_key', ''),
                    'response' => $captcha,
                ];
                $options = [
                    'http' => [
                        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method' => 'POST',
                        'content' => http_build_query($data),
                    ],

                ];
                $context = stream_context_create($options);
                $result = file_get_contents($url, false, $context);

                return json_decode($result)->success;
            } catch (Exception $e) {
                return null;
            }
        } // Not a POST request, set a 403 (forbidden) response code.
        else {
            return false;
        }
    }

    /** set product object, it can use where product objects need
     *
     * @param $product_id
     * @param bool $variation_id
     * @param int $quantity
     */
    public function set_product($product_id, $variation_id = false, $quantity = 1)
    {

        if ($variation_id != false) {
            $this->parentProduct = wc_get_product($product_id);
            $product_id = $variation_id;
        }

        $this->product = wc_get_product($product_id);
        $this->product_id = $product_id;

        $this->quantity = $quantity;
    }

    /** Read user submitted data
     *
     * @param $product_id
     *
     * @since 5.0
     */
    public function read_form()
    {
        if (!$this->fields) {
            return;
        }
        $this->form_data = [];

        $fieldTemp = new stdClass();

        foreach ($this->fields as $sectionKey => $section) {
            $fieldTemp->{$sectionKey} = clone $section;
            $this->form_data[$section->extra->key]['extra'] = (object)[
                'section_id' => $section->extra->section_id,
                'clStatus' => 'visible',
                'key' => $section->extra->key,
                'name' => $section->extra->name,
                'price' => 0,
                'weight' => 0,
                'form_id' => $section->extra->form_id,
                'isClone' => isset($section->extra->isClone) ? $section->extra->isClone : false,
                'parentKey' => isset($section->extra->parentKey) ? $section->extra->parentKey : false,
                'form_rules' => $section->extra->form_rules,
                'repeater'=> isset($section->extra->repeater)?$section->extra->repeater:false
            ];

            $status = $this->_read_form($section, $fieldTemp);
            if ($status === false) {
                /** file field can cause error if no files */
                return false;
            }
//            $sectionCounter = 1;
            if (isset($section->extra->repeater) && $section->extra->repeater) {
                $repeaterIndex = 0;
                if (isset($_POST[$sectionKey]) && is_array($_POST[$sectionKey])) {
                    $repeaterIndex = array_key_last($_POST[$sectionKey]);
                }
                if (isset($_FILES[$sectionKey]) && is_array($_FILES[$sectionKey]['name'])) {
                    $repeaterIndex = max($repeaterIndex, array_key_last($_FILES[$sectionKey]['name']));
                    $newFiles = [];
                    foreach ($_FILES[$sectionKey] as $fileProperties => $_section) {
                        foreach ($_section as $_sectionCounter => $_field) {
                            if (!isset($newFiles[$_sectionCounter])) {
                                $newFiles[$_sectionCounter] = [];
                            }
                            foreach ($_field as $_fieldName => $_nameCounter) {
                                $newFiles[$_sectionCounter][$_fieldName][$fileProperties] = $_nameCounter;
                            }
                        }
                    }
                    $_FILES[$sectionKey] = $newFiles;
                }

                if ($repeaterIndex) {
                    $this->form_data[$section->extra->key]['extra']->clonedCount = $repeaterIndex;
                    for ($i = 1; $i <= $repeaterIndex; $i++) {
//						$newSection                                       = clone $section;
//						$newSection->extra->key                           = $sectionKey . '_' . $i;
//						$newSection->extra->isClone                       = true;
//						$newSection->extra->repeater                      = false;
//						$newSection->extra->parentKey                      = $sectionKey;
//						$name                                             = [ $sectionKey, $sectionCounter ];
//						$newSection->fields                               = array_map( function ( $row ) use ( $name ) {
//							return array_map( function ( $field ) use ( $name ) {
//								$name[]      = $field->name;
//								$field->name = $name;
//
//								return $field;
//							}, $row );
//						}, $newSection->fields );
//						$this->form_data[ $section->extra->key ]['extra'] = [
//							'section_id' => $section->extra->section_id,
//							'clStatus'   => 'visible',
//							'key'        => $section->extra->key,
//							'form_id'    => $section->extra->form_id,
//							'price'      => 0,
//
//							'form_rules' => $section->extra->form_rules
//						];
//						$this->fields[ $newSection->extra->key ]          = $newSection;
//
                        $newKey = $sectionKey . '_cl' . $i;
                        $name = [$sectionKey, $i];
                        $oSection = $this->fields->{$sectionKey};
                        $newSection = cloneSection($oSection, $sectionKey, $newKey, $name, $i);
                        $fieldTemp->{$newKey} = $newSection;
                        $this->form_data[$newKey]['extra'] = (object)[
                            'section_id' => $newSection->extra->section_id,
                            'clStatus' => 'visible',
                            'key' => $newSection->extra->key,
                            'name' => $newSection->extra->name,
                            'form_id' => $newSection->extra->form_id,
                            'price' => 0,
                            'weight' => 0,
                            'isClone' => isset($newSection->extra->isClone) ? $newSection->extra->isClone : false,
                            'parentKey' => isset($newSection->extra->parentKey) ? $newSection->extra->parentKey : false,
                            'form_rules' => $newSection->extra->form_rules
                        ];

                        $status = $this->_read_form($newSection, $fieldTemp);
                        if ($status === false) {
                            return false;
                        }
                    }
                    //
                }
            }
        }
        $this->fields = $fieldTemp;
    }

    public function _read_form($section, &$fieldTemp)
    {
        $readForm = new ReadForm($this);


        $hide_empty = Config::get_config('hide_empty_data', false);
        $zero_as_empty = false;
        if ($hide_empty) {
            $zero_as_empty = apply_filters('wcpa_zero_as_empty', false);
        }
        foreach ($section->fields as $rowIndex => $row) {
            foreach ($row as $colIndex => $field) {
                $field = apply_filters('wcpa_form_field', $field, $this->product_id);
                $form_data = extractFormData($field);
//                unset($form_data->values); //avoid saving large number of data
//                unset($form_data->className); //avoid saving no use data
//                unset($form_data->relations); //avoid saving no use data

                $lastIndex = isset($this->form_data[$section->extra->key]['fields'][$rowIndex]) ? count($this->form_data[$section->extra->key]['fields'][$rowIndex]) : 0; // we cannot use $colIndex as index, as it can be overited if it has repeating field in same row
                $totalIndex = count($row); // total elements on this row
                $this->form_data[$section->extra->key]['fields'][$rowIndex][$lastIndex] = [];// init the form_data with empty data, otherwise the index will be got missed for fields which skipped ( content, empty fields so on)
                if (in_array($field->type, array('separator', 'groupValidation', 'header'))) {
                    continue;
                }

                if ($field->type == 'content' && (!isset($field->show_in_checkout) || $field->show_in_checkout == false)) {
                    continue;
                }

                if (isset($field->enablePrice) && $field->enablePrice && isset($field->pricingType)) {
                    /** for array fields, it need to set price value or formula while reading the options,
                     * so it need set templateFormula before it to process before read
                     */
                    if ($field->pricingType == 'custom' && isset($field->isTemplate) && $field->isTemplate) {
                        $field->price = '';
                        if (isset($field->formulaId) && $this->formulas[$field->formulaId]) {
                            $field->price = $this->formulas[$field->formulaId];
                        }
                        if (isset($field->values) && is_array($field->values) && isset($field->priceOptions) && $field->priceOptions === 'different_for_all') {
                            foreach ($field->values as $j => $_v) {
                                if (isset($_v->options) && is_array($_v->options)) {
                                    foreach ($_v->options as $k => $__v) {
                                        if (isset($__v->formulaId) && $this->formulas[$__v->formulaId]) {
                                            $__v->price = $this->formulas[$__v->formulaId];

                                        }
                                    }

                                } else {
                                    if (isset($_v->formulaId) && $this->formulas[$_v->formulaId]) {
                                        $_v->price = $this->formulas[$_v->formulaId];
                                    }
                                }
                            }
                        }
                    }

                }

                if (isset($field->enableWeight) && $field->enableWeight && isset($field->weightType)) {
                    if ($field->weightType == 'custom' && isset($field->isWeightTemplate) && $field->isWeightTemplate) {
                        $field->weight = '';
                        if (isset($field->weightFormulaId) && $this->formulas[$field->weightFormulaId]) {
                            $field->weight = $this->formulas[$field->weightFormulaId];
                        }
                        if (isset($field->values) && is_array($field->values) && isset($field->weightOptions) && $field->weightOptions === 'different_for_all') {
                            foreach ($field->values as $j => $_v) {
                                if (isset($_v->options) && is_array($_v->options)) {
                                    foreach ($_v->options as $k => $__v) {
                                        if (isset($__v->weightFormulaId) && $this->formulas[$__v->weightFormulaId]) {
                                            $__v->weight = $this->formulas[$__v->weightFormulaId];
                                        }
                                    }
                                } else {
                                    if (isset($_v->weightFormulaId) && $this->formulas[$_v->weightFormulaId]) {
                                        $_v->weight = $this->formulas[$_v->weightFormulaId];
                                    }
                                }
                            }
                        }
                    }
                }


                if ($this->orderAgainData === false) {
                    $_fieldValue = $readForm->_read_form($field, $hide_empty, $zero_as_empty);
                } else {
                    $_fieldValue = $readForm->read_from_order_data($this->orderAgainData, $field, $hide_empty,
                        $zero_as_empty);
                }


                $quantity = false;
                if (isset($field->enable_quantity) && $field->enable_quantity) {
                    if (is_array($_fieldValue) && array_key_exists('quantity',
                            $_fieldValue)) { // isset($_fieldValue['quantity']) returns false for null value in quantity
                        $quantity = floatval($_fieldValue['quantity']);
                        $fieldValue = $_fieldValue['value'];
                    } else {
                        if (is_array($_fieldValue)) {
                            /** sum quantity values from array **/
                            $quantity = array_sum(array_column($_fieldValue, 'quantity'));
                        }
                        $fieldValue = $_fieldValue;
                    }


                } else {
                    $fieldValue = $_fieldValue;
                }


                if ($field->type == 'file' && $fieldValue === false) {
                    /** for file field, it can cause error if the file is missing in temp folder, then throw error */
                    wc_add_notice(
                        sprintf(__('File %s could not be uploaded.', 'woo-custom-product-addons-pro'), $field->label),
                        'error'
                    );

                    return false;
                }
                if (isEmpty($fieldValue) && $hide_empty && $field->type != 'content') {
                    continue;
                }
                if ($zero_as_empty && ($fieldValue === 0 || $fieldValue === '0')) {
                    continue;
                }

                if (isset($field->cartLabel) && !isEmpty(trim($field->cartLabel))) {
                    $label = $field->cartLabel;
                } else {
                    $label = (isset($field->label)) ? (($field->label == '') ? WCPA_EMPTY_LABEL : $field->label) : WCPA_EMPTY_LABEL;
                }


                $this->form_data[$section->extra->key]['fields'][$rowIndex][$lastIndex] = [
                    'type' => $field->type,
                    'name' => isset($field->name) ? $field->name : $field->elementId,
                    'label' => $label,
                    'elementId' => $field->elementId,
                    'value' => $fieldValue,
                    'quantity' => $quantity,
                    //  value fill be false for if the value not set
                    'clStatus' => 'visible',
                    'price' => false,
                    'weight' => false,
                    // price cannot be calculated here, as it can have cl logic dependency, it can calculate after cl logic processed
                    // must set price as false, to ensure this field price is not processed yet.
//                    'options'   => isset($field->values) ? array_map(
//                        function ($f) {
//                            return [
//                                'value'    => $f->value,
//                                'selected' => isset($f->selected) ? $f->selected : false,
//                                'price'    => isset($f->price) ? $f->price : false,
//                            ];
//                        },
//                        $field->values
//                    ) : [], // removed as no use found
                    'form_data' => $form_data,

//				  'cur_swit' => $this->getCurrSwitch(), //TODO need to check this
                    'map_to_checkout' => (isset($field->mapToCheckout) && $field->mapToCheckout
                        && isset($field->mapToCheckoutField) && !empty($field->mapToCheckoutField)
                        && isset($field->mapToCheckoutFieldParent) && !empty($field->mapToCheckoutFieldParent))
                        ? array(
                            'parent' => isset($field->mapToCheckoutFieldParent) ? $field->mapToCheckoutFieldParent : '',
                            'field' => isset($field->mapToCheckoutField) ? $field->mapToCheckoutField : '',
                            'value' => $fieldValue
                        ) : false

                ];

                $colCount = 0; // columns count after this field
                if ($lastIndex < $totalIndex) {
                    // no other elements after this field.
                    $colCount = $totalIndex - $lastIndex - 1;
                }
//                if (isset($field->independent) && $field->independent) {
//                    $this->setSubProduct($this->form_data[$section->extra->key]['fields'][$rowIndex][$lastIndex]);
//                }

                if ($field->type == 'date' || $field->type == 'datetime-local' || $field->type == 'time') {
                    $dateFormat = getDateFormat($field);

                    $this->form_data[$section->extra->key]['fields'][$rowIndex][$lastIndex]['dateFormat'] = $dateFormat;
                }

                if (isset($field->repeater) && $field->repeater) {
                    //isset($_POST[$field->name . '_cl'])
                    $repeaterIndex = 0;
                    $name = $field->name;
                    if (is_array($field->name)) {
                        $name[2] = $name[2] . '_cl';
                        if (isset($_POST[$name[0]][$name[1]][$name[2]])) {
                            $repeaterIndex = array_key_last($_POST[$name[0]][$name[1]][$name[2]]);
                        }
                        if (isset($_FILES[$name[0]][$name[1]][$name[2]]['name'])) {
                            $repeaterIndex = max($repeaterIndex,
                                array_key_last($_FILES[$name[0]][$name[1]][$name[2]]['name']));

                            $newFiles = [];
                            foreach ($_FILES[$name[0]][$name[1]][$name[2]]['name'] as $_i => $_v) {
                                $newFiles[$_i] = array(
                                    'tmp_name' => $_FILES[$name[0]][$name[1]][$name[2]]['tmp_name'][$_i],
                                    'name' => $_FILES[$name[0]][$name[1]][$name[2]]['name'][$_i],
                                    'size' => $_FILES[$name[0]][$name[1]][$name[2]]['size'][$_i],
                                    'type' => $_FILES[$name[0]][$name[1]][$name[2]]['type'][$_i],
                                    'error' => $_FILES[$name[0]][$name[1]][$name[2]]['error'][$_i],
                                );
                            }
                            $_FILES[$name[0]][$name[1]][$name[2]] = $newFiles;
                        }
                    } else {
                        if (isset($_POST[$name . '_cl']) && is_array($_POST[$name . '_cl'])) {
                            $repeaterIndex = array_key_last($_POST[$name . '_cl']);
                        }
                        if (isset($_FILES[$name . '_cl']) && is_array($_FILES[$name . '_cl']['name'])) {
                            $repeaterIndex = max($repeaterIndex, array_key_last($_FILES[$name . '_cl']['name']));
                            $newFiles = [];
                            foreach ($_FILES[$name . '_cl']['name'] as $_i => $_v) {
                                $newFiles[$_i] = array(
                                    'tmp_name' => $_FILES[$name . '_cl']['tmp_name'][$_i],
                                    'name' => $_FILES[$name . '_cl']['name'][$_i],
                                    'size' => $_FILES[$name . '_cl']['size'][$_i],
                                    'type' => $_FILES[$name . '_cl']['type'][$_i],
                                    'error' => $_FILES[$name . '_cl']['error'][$_i],
                                );
                            }
                            $_FILES[$name . '_cl'] = $newFiles;
                        }
                    }
                    $this->form_data[$section->extra->key]['fields'][$rowIndex][$lastIndex]['clonedCount'] = $repeaterIndex;

                    for ($index = 1; $index <= $repeaterIndex; $index++) {
//                        $nField            = clone $field;
//                        $nField->elementId = "{$field->elementId}_cl_{$index}";
//                        $nField->isClone   = true;
//                        $nField->parentId  = $field->elementId;
//                        $name              = $field->name;
//
//                        if (is_array($name)) {
//                            $name[count($name) - 1] = $name[count($name) - 1] . '_cl';
//                            $name[]                 = $index;
//                        } else {
//                            $name = [$field->name . '_cl', $index];
//                        }
//                        $nField->name = $name;// [$field->name . '_cl', $index];//$field->name . '_cl[' . $index . ']';
//                        $row[]        = $nField;
//                        $form_data    = clone $nField;
//                        /** pushing clone field to original fields */
//                        $this->fields->{$section->extra->key}->fields[$rowIndex][] = $nField;

                        $newId = "{$field->elementId}_cl_{$index}";
                        $nField = cloneField($field, $newId, $index);
//                        $form_data    = clone $nField;
                        $form_data = extractFormData($nField);
//                        $this->fields->{$section->extra->key}->fields[$rowIndex][] = $nField;

                        if ($colCount > 0) {
                            array_splice($fieldTemp->{$section->extra->key}->fields[$rowIndex], -1 * $colCount, 0, [$nField]);
                        } else {
                            $fieldTemp->{$section->extra->key}->fields[$rowIndex][] = $nField;
                        }

//                        unset($form_data->values); //avoid saving large number of data
//                        unset($form_data->className); //avoid saving no use data
//                        unset($form_data->relations); //avoid saving no use data

                        $_fieldValue = $readForm->_read_form($nField, $hide_empty, $zero_as_empty);

                        $quantity = false;
                        if (isset($nField->enable_quantity) && $nField->enable_quantity) {
                            if (is_array($_fieldValue) && array_key_exists('quantity',
                                    $_fieldValue)) { // isset($_fieldValue['quantity']) returns false for null value in quantity
                                $quantity = $_fieldValue['quantity'];
                                $fieldValue = $_fieldValue['value'];
                            } else {
                                if (is_array($_fieldValue)) {
                                    /** sum quantity values from array **/
                                    $quantity = array_sum(array_column($_fieldValue, 'quantity'));
                                }
                                $fieldValue = $_fieldValue;
                            }
                        } else {
                            $fieldValue = $_fieldValue;
                        }


                        if ($nField->type == 'file' && $fieldValue === false) {
                            /** for file field, it can cause error if the file is missing in temp folder, then throw error */
                            wc_add_notice(
                                sprintf(__('File %s could not be uploaded.', 'woo-custom-product-addons-pro'),
                                    $nField->label),
                                'error'
                            );

                            return false;
                        }
                        if (($fieldValue === false || $fieldValue == '') && $hide_empty && $field->type != 'content') {
                            continue;
                        }
                        if ($zero_as_empty && ($fieldValue === 0 || $fieldValue === '0')) {
                            continue;
                        }

                        if (isset($nField->cartLabel) && !isEmpty(trim($nField->cartLabel))) {
                            $label = $field->cartLabel;
                        } else {
                            $label = (isset($nField->label)) ? (($nField->label == '') ? WCPA_EMPTY_LABEL : $nField->label) : WCPA_EMPTY_LABEL;
                        }


                        if (isset($field->repeater_field_label)) {
                            $label = str_replace('{field_label}', $label, $field->repeater_field_label);
                            $label = str_replace('{counter}', $index + 1, $label);
                            $label = $label == '' ? WCPA_EMPTY_LABEL : $label;
                        }
                        $dateFormat = false;
                        if ($nField->type == 'date' || $nField->type == 'datetime-local' || $nField->type == 'time') {
                            $dateFormat = getDateFormat($nField);

                        }
                        $this->form_data[$section->extra->key]['fields'][$rowIndex][] = [
                            'type' => $nField->type,
                            'name' => isset($nField->name) ? $nField->name : $nField->elementId,
                            'label' => $label,
                            'elementId' => $nField->elementId,
                            'value' => $fieldValue,
                            'quantity' => $quantity,
                            //  value fill be false for if the value not set
                            'clStatus' => 'visible',
                            'price' => false,
                            'weight' => false,
                            'dateFormat' => $dateFormat,
                            // price cannot be calculated here, as it can have cl logic dependency, it can calculate after cl logic processed
//                            'options'   => isset($nField->values) ? array_map(
//                                function ($f) {
//                                    return [
//                                        'value'    => $f->value,
//                                        'selected' => $f->selected,
//                                        'price'    => isset($f->price) ? $f->price : false,
//                                    ];
//                                },
//                                $nField->values
//                            ) : [], // removed as couldt find any reason
                            'form_data' => $form_data,
                            'map_to_checkout' => isset($nField->mapToCheckout) && array(
                                    'parent' => isset($nField->mapToCheckoutFieldParent) ? $nField->mapToCheckoutFieldParent : '',
                                    'field' => isset($nField->mapToCheckoutField) ? $nField->mapToCheckoutField : '',
                                    'value' => $fieldValue
                                )
                        ];
//                        if (isset($nField->independent) && $nField->independent) {
//                            $this->setSubProduct(end($this->form_data[$section->extra->key]['fields'][$rowIndex]));
//                        }


                    }
                }
            }
        }
    }

    public function setSubProduct($product)
    {
        $this->subProducts[] = $product;
    }

    /**
     * Process conditional logic with user submited data
     *
     * @param $product_id
     *
     * @since 5.0
     */
    public function process_cl_logic()
    {
        $processed_ids = array();
        $processed_sections = array();
        $cLogic = new CLogic($this->form_data, $this->fields, $this->product, $this->parentProduct, $this->quantity);
        if ($this->fields) {
            foreach ($this->fields as $sectionKey => $section) {
                $sectionClStatus = 'visible';

                if (isset($section->extra->enableCl) && $section->extra->enableCl && isset($section->extra->relations) && is_array(
                        $section->extra->relations
                    )) {
                    $processed_sections[] = $sectionKey;
                    $clStatus = $cLogic->evalConditions(
                        $section->extra->cl_rule,
                        $section->extra->relations
                    ); // returns false if it catch error
                    if ($clStatus !== false) {
                        $this->form_data[$sectionKey]['extra']->clStatus = $sectionClStatus = $clStatus;
                    }
                    $cLogic->setFormData($this->form_data);
                }

                //TODO need to check how to handle if the section has cl dependency with already exicuted fields

                /**
                 * avoid processing CL for fields if the section status is hidden
                 */
                if ($sectionClStatus !== 'visible') {
                    continue;
                }

                foreach ($section->fields as $rowIndex => $row) {
                    foreach ($row as $colIndex => $field) {
                        if (isset($field->enableCl) && $field->enableCl && isset($field->relations) && is_array(
                                $field->relations
                            )) {
                            $clStatus = $cLogic->evalConditions(
                                $field->cl_rule,
                                $field->relations
                            ); // returns false if it catch error
                            $processed_ids[] = isset($field->elementId) ? $field->elementId : false;

                            if ($clStatus !== false) {
                                /** we have to keep the cl status even if the field has not set while read_form. It needs to check validation required  */
                                if (!isset($this->form_data[$sectionKey]['fields'][$rowIndex])) {
                                    $this->form_data[$sectionKey]['fields'][$rowIndex] = [];
                                }
                                if (!isset($this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex])) {
                                    $this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex] = [];
                                }
                                $this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex]['clStatus'] = $clStatus;
                                if ($field->cl_dependency) {
                                    $cLogic->processClDependency($field->cl_dependency, $processed_ids);
                                }
                            }
                            $cLogic->setFormData($this->form_data);
                        }
                    }
                }
            }
        }
    }

    public function validateFormData()
    {
        $validation = new FormValidation($this->product, $this->quantity);
        if ($this->fields) {
            foreach ($this->fields as $sectionKey => $section) {
                if ($this->form_data[$sectionKey]['extra']->clStatus === 'hidden') {
                    /** in PHP end, disable status also treat as hidden, so no need to compare 'disable' */
                    continue;
                }
                foreach ($section->fields as $rowIndex => $row) {
                    foreach ($row as $colIndex => $field) {
                        if ($field->type == 'groupValidation') {
                            $status = $validation->validateGroup($field, false, $this->form_data,$sectionKey);
                            if ($status === false) {
                                return false;
                            }
                            continue;
                        }
                        if (!isset($this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex]) || empty($this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex]) ) {
                            if (isset($field->required) && $field->required) {
                                $validation->validate($field, ['value' => false]); // calling this to set error message

                                return false;
                            }
                            continue;
                        }
                        $dField = $this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex];

                        if (isset($dField['clStatus']) && ($dField['clStatus'] === 'hidden')) {

                            continue;
                        }
                        if (!isset($this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex]['type'])) {
                            continue;
                        }

                        if (in_array($field->type, ['content', 'separator', 'header'])) {
                            continue;
                        }
                        $status = $validation->validate($field, $dField);
                        if ($status === false) {
                            return false;
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param $cart_item_data
     * @param $product_id
     * @param false $variation_id
     * @param int $quantity
     * @param false $reRead = forcing reading the daa from request/get again
     * @return array
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id = false, $quantity = 1,$reRead=false)
    {

        if (isset($cart_item_data['wcpaIgnore'])) {
            return $cart_item_data;
        }

        /**
         * Run only if fields are not set, setting fields and reading data already will be done at validation stage
         */

        if ($this->fields == false ||
            ($variation_id == false ? $this->product_id !== $product_id : $this->product_id !== $variation_id) || $reRead
        ) {
            /** must pass $product-id, dont pass $variation id */
            $this->setFields($product_id);
            $this->set_product($product_id, $variation_id, $quantity);
            $this->read_form();
            $this->process_cl_logic();
//
        }

        if ($this->fields == false) {
            return $cart_item_data;
        }


        if (isset($cart_item_data[WCPA_CART_ITEM_KEY])) {
            /*
 This section is using to process data when quantiy changes from cart
 */
            $this->form_data = $cart_item_data[WCPA_CART_ITEM_KEY];
            $this->process_cl_logic();
            $this->processPricing();
            $this->processContentFormula();
            $this->processWeight();
        } else {

            $this->processPricing();
            $this->processContentFormula();
            $this->processWeight();
        }

        /**
         * remove  cl Status hidden fields
         */
        $_form_data = [];

        $checkoutFields = [];
        foreach ($this->form_data as $sectionKey => $section) {
            if ($section['extra']->clStatus !== 'visible') {
                continue;
            }
            $_form_data[$sectionKey]['extra'] = $section['extra'];
            if (!isset($section['fields'])) {
                $section['fields'] = []; // keep empty fields if no fields in this section
                $_form_data[$sectionKey]['fields'] = [];
            }
            foreach ($section['fields'] as $rowIndex => $row) {
                foreach ($row as $colIndex => $field) {

                    if (!isset($field['type'])) {
                        continue;
                    }
                    if ($field['clStatus'] !== 'visible') {
                        continue;
                    }
                    if(isset($field['form_data']->independent) && $field['form_data']->independent){
                        $this->setSubProduct($field);
                    }

                    $_form_data[$sectionKey]['fields'][$rowIndex][$colIndex] = $field;
                    if (isset($field['map_to_checkout']) && is_array($field['map_to_checkout'])) {
                        if (isset($checkoutFields[$field['map_to_checkout']['field']])) {
                            /** if it has already mapped, set again if the value is not empty */
                            if (!isEmpty($field['map_to_checkout']['value'])) {
                                $checkoutFields[$field['map_to_checkout']['field']] = $field['map_to_checkout']['value'];
                            }
                        } else {
                            $checkoutFields[$field['map_to_checkout']['field']] = isset($field['map_to_checkout']['value']) ? $field['map_to_checkout']['value'] : '';
                        }
                    }
                }
            }
            if (!isset($_form_data[$sectionKey]['fields'])) {
                /**  if all fields are clStatus hidden, 'field' can be not set*/
                $_form_data[$sectionKey]['fields'] = [];
            }
        }

        if(!is_array($cart_item_data)){
            $cart_item_data=[];// to avoid conflict with some plugins who returns $cart_item_data as string
        }

        $cart_item_data[WCPA_CART_ITEM_KEY] = $_form_data;

        $separate_cart_items = Config::get_config('separate_cart_items');  //  to make each items as seperate line item even if the options are same

        $cart_item_data['wcpa_cart_rules'] = [
            'price_override' => $this->formConf['price_override'],
//                'pric_use_as_fee'   => $this->formConf['pric_use_as_fee'],
//                'process_fee_as'    => $this->formConf['process_fee_as'],
            'bind_quantity' => $this->formConf['bind_quantity'],
            'thumb_image' => $this->thumb_image,
            'combined_products' => $this->subProducts,
            'checkout_fields' => $checkoutFields,
            'currency' => get_woocommerce_currency(),
            'quantity' => $this->formConf['has_quantity_formula'] ? $quantity : false,
            'timestamp' => ($separate_cart_items ? time() : false) //  to make each items as seperate line item even if the options are same
        ];
        $this->subProducts=[];
        // $cart_item_data['wcpa_combined_products'] = $product_array;
        //  $cart_item_data['wcpa_checkout_fields_data'] = $checkout_field_data;


        return $cart_item_data;
    }

    public function processPricing()
    {
        $dependencyFields = [];


        if ($this->fields) {
            foreach ($this->fields as $sectionKey => $section) {


                foreach ($section->fields as $rowIndex => $row) {
                    foreach ($row as $colIndex => $field) {
                        if (!isset($this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex]['type'])) {
                            /** empty fields might be skipped while read_form */
                            continue;
                        }
                        $price = new Price($this->form_data, $this->fields, $this->product, $this->quantity);
                        $dField = &$this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex];
                        $field = $this->fields->{$sectionKey}->fields[$rowIndex][$colIndex];
                        if (!isset($field->enablePrice) || !$field->enablePrice) {
                            continue;
                        }

                        if (in_array($field->type, ['separator', 'header'])) {
                            continue;
                        }

                        $status = $price->setFieldPrice($dField, $field);

//						$dField['price'] = $calcPrice;
//						$this->fields->{$sectionKey}->fields[ $rowIndex ][ $colIndex ]->price = $calcPrice;

                        if ($status === 'dependency') {
                            $dependencyFields[] = [$sectionKey, $rowIndex, $colIndex];
                        }
                        $price->setFormData($this->form_data);
                    }
                }

                $secPrice = 0.0;
                $secRawPrice = 0.0;
                foreach ($section->fields as $rowIndex => $row) {
                    foreach ($row as $colIndex => $field) {
                        if (!isset($this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex]['type'])) {
                            /** empty fields might be skipped while read_form */
                            continue;
                        }
                        if (!isset($field->enablePrice) || !$field->enablePrice) {
                            continue;
                        }
                        if (in_array($field->type, ['separator', 'header'])) {
                            continue;
                        }
                        $dField = &$this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex];
                        $_price = 0.0;
                        $_rawPrice = 0.0;
                        if (isset($dField['price']) && is_array($dField['price'])) {
                            foreach ($dField['price'] as $p) {
                                $_price += $p;
                            }
                            foreach ($dField['rawPrice'] as $p) {
                                $_rawPrice += $p;
                            }
                        } elseif (isset($dField['price']) && $dField['price']) {
                            $_price += $dField['price'];
                            $_rawPrice += $dField['rawPrice'];

                        }
                        $secPrice += $_price;
                        $secRawPrice += $_rawPrice;

                    }
                }

                $this->form_data[$sectionKey]['price'] = $secPrice;
                $this->form_data[$sectionKey]['rawPrice'] = $secRawPrice;
            }
        }
        if (!empty($dependencyFields)) {
            $price = new Price($this->form_data, $this->fields, $this->product, $this->quantity);
            $price->processPriceDependencies($dependencyFields, $this->form_data);
        }


    }

    public function processContentFormula()
    {
        $price = new Price($this->form_data, $this->fields, $this->product, $this->quantity);
        if ($this->fields) {
            foreach ($this->fields as $sectionKey => $section) {
                foreach ($section->fields as $rowIndex => $row) {
                    foreach ($row as $colIndex => $field) {
                        if (!isset($this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex]['type'])) {
                            /** empty fields might be skipped while read_form */
                            continue;
                        }
                        if (isset($field->hasFormula) && $field->hasFormula) {
                            $dField = &$this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex];
                            if (isset($field->label)) {
                                $formula = $price->contentFormula($field->label, $dField, $field);
                                if (is_string($formula)) {
                                    $dField['label'] = $formula;
                                } else {
                                    $dField['label'] = $formula['label'];
                                    $dField['labelFormula'] = $formula['formula'];
                                }
                            }
                            if (isset($field->cartLabel)) {
                                $formula = $price->contentFormula($field->cartLabel, $dField, $field);
                                if (is_string($formula)) {
                                    $dField['label'] = $formula;
                                }
                            }
                            if (isset($field->description)) {
                                $formula = $price->contentFormula($field->description, $dField, $field);
                                if (is_string($formula)) {
                                    $dField['description'] = $formula;
                                } else {
                                    $dField['description'] = $formula['label'];
                                    $dField['descriptionFormula'] = $formula['formula'];
                                }
                            }
                            if ($field->type == 'content') {
                                $formula = $price->contentFormula($field->value, $dField, $field);
                                if (is_string($formula)) {
                                    $dField['value'] = $formula;
                                } else {
                                    $dField['value'] = $formula['label'];
                                    $dField['valueFormula'] = $formula['formula'];
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function processWeight()
    {
        $dependencyFields = [];

        $weight = new Weight($this->form_data, $this->fields, $this->product, $this->quantity);
        if ($this->fields) {
            foreach ($this->fields as $sectionKey => $section) {
                foreach ($section->fields as $rowIndex => $row) {
                    foreach ($row as $colIndex => $field) {
                        if (!isset($this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex]['type'])) {
                            /** empty fields might be skipped while read_form */
                            continue;
                        }

                        $dField = &$this->form_data[$sectionKey]['fields'][$rowIndex][$colIndex];
                        $field = $this->fields->{$sectionKey}->fields[$rowIndex][$colIndex];
                        if (!isset($field->enableWeight) || !$field->enableWeight) {
                            continue;
                        }

                        if (in_array($field->type, ['separator', 'header'])) {
                            continue;
                        }

                        $status = $weight->setFieldWeight($dField, $field);

                        if ($status === 'dependency') {
                            $dependencyFields[] = [$sectionKey, $rowIndex, $colIndex];
                        }
                        $weight->setFormData($this->form_data);
                    }
                }
            }
        }
        if (!empty($dependencyFields)) {
            $weight->processWeightDependencies($dependencyFields);
        }
    }

    public function pllwc_cart_item_data($cart_item_data, $item)
    {
        if (isset($item[WCPA_CART_ITEM_KEY])) {
            $cart_item_data[WCPA_CART_ITEM_KEY] = $item[WCPA_CART_ITEM_KEY];
        }
        if (isset($item['wcpa_cart_rules'])) {
            $cart_item_data['wcpa_cart_rules'] = $item['wcpa_cart_rules'];
        }

        return $cart_item_data;
    }

    public function setThumbImage($image)
    {
        $this->thumb_image = $image;
    }

    public function setCheckoutField($field)
    {
        $this->checkoutFields[] = $field;
    }

    /**
     * Ajax Add to Cart
     * @since 5.0
     */
    public function ajax_add_to_cart()
    {
        if (!isset($_POST['add-to-cart'])) {
            return;
        }

        $product_id = intval($_POST['add-to-cart']);
        if (isset($_POST['quantity'])) {
            $quantity = intval($_POST['quantity']);
        } else {
            $quantity = 1;
        }

        if (empty(wc_get_notices('error'))) {
            // trigger action for added to cart in ajax
            do_action('woocommerce_ajax_added_to_cart', $product_id);

            if ('yes' === get_option('woocommerce_cart_redirect_after_add')) {
                wc_add_to_cart_message(array($product_id => $quantity), true);
            }

            wc_clear_notices();

            WC_AJAX::get_refreshed_fragments();
        } else {
            // If there was an error adding to the cart, redirect to the product page to show any errors.
            $data = array(
                'error' => true,
                'product_url' => apply_filters(
                    'woocommerce_cart_redirect_after_error',
                    get_permalink($product_id),
                    $product_id
                ),
            );

            wp_send_json($data);
        }
    }

    public function add_multiple_mime_types($check, $file, $filename)
    {
        $mimetypes = getMimeTypes();

        if (empty($check['ext']) && empty($check['type'])) {
            foreach ($mimetypes as $mime) {
                remove_filter('wp_check_filetype_and_ext', [$this, 'add_multiple_mime_types'], 99);
                $mime_filter = function ($mimes) use ($mime) {
                    return array_merge($mimes, $mime);
                };

                add_filter('upload_mimes', $mime_filter, 99);

                $check = wp_check_filetype_and_ext($file, $filename, $mime);

                remove_filter('upload_mimes', $mime_filter, 99);
                add_filter('wp_check_filetype_and_ext', [$this, 'add_multiple_mime_types'], 99, 3);
                if (!empty($check['ext']) || !empty($check['type'])) {
                    return $check;
                }
            }
        }

        return $check;
    }

    /**
     * Register API routes
     */

    public function register_routes()
    {
//        $this->add_route('/upload/(?P<id>[0-9]+)', 'ajax_upload', 'POST');
        $this->add_route('/upload/(?P<id>[0-9]+)/(?P<fname>[,a-zA-Z0-9_-]+)', 'ajax_upload', 'POST');
        $this->add_route('/tus_upload/(?P<id>[0-9]+)/(?P<fname>[,a-zA-Z0-9_-]+)', 'tus_upload', 'POST');
        $this->add_route('/tus_upload/(?P<fname>.*)', 'tus_upload_serve', 'HEAD,PATCH');
        $this->add_route('/s3-sign/(?P<id>[0-9]+)/(?P<fname>[,a-zA-Z0-9_-]+)', 's3_sign', 'POST');

    }

    private function add_route($slug, $callBack, $method = 'GET')
    {
        register_rest_route(
            $this->token . '/front',
            $slug,
            array(
                'methods' => $method,
                'callback' => array($this, $callBack),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function isSetRepeaterField($name)
    {
        if (is_array($name)) {
            $val = $_POST;
            /**  sectionKey,Index,Name */
            $name[2] = $name[2] . '_cl';
            foreach ($name as $v) {
                if (!isset($val[$v])) {
                    return false;
                }
                $val = $val[$v];
            }

            return true;
        } else {
            return isset($_POST[$name . '_cl']);
        }
    }

    public function fieldValFromName($name)
    {
        if (is_array($name)) {
            /**  sectionKey,Index,Name */
            return $_POST[$name[0]][$name[1]][$name[2] . '_cl'];
        } else {
            return $_POST[$name . '_cl'];
        }
    }

    public function processRepeater()
    {
        $repeater = false;
        if ($this->fields) {
            foreach ($this->fields as $sectionKey => $section) {
                if ($section->repeater || $section->clStatus == 'hidden' || $section->clStatus == 'disabled') {
                    return;
                }
                if (!$section->repeater_bind || $section->repeater_bind == '') {
                    return;
                }
            }
        }
    }


    public function s3_sign($data)
    {
        $this->initSession();

        if (!isset($data['id'])) {
            return new WP_REST_Response(false, 400);
        }
        if (!isset($data['fname'])) {
            return new WP_REST_Response(false, 400);
        }
        $this->setFields($data['id']);
        $field = $this->findFieldByFieldName($data['fname']);
        if ($field === false || $field->type !== 'file') {
            return new WP_REST_Response(false, 400);
        }
        $validation = new FormValidation();
        $params = $data->get_params();
        $file = ['error' => 0, 'size' => $params['size'], 'filename' => $params['filename'], 'type' => $params['contentType']];
        $status = $validation->validateFileUpload($field, $file, true, true);

        if ($status !== true) {
            return new WP_REST_Response($status, 422);
        }
        $s3 = new S3();
        $s3->serve($file);
    }

    public function initSession()
    {
        /*https://stackoverflow.com/questions/65541974/access-wc-class-of-woocommerce-from-anywhere*/
        WC()->frontend_includes();
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
    }

    /**
     * @param $fieldName
     *
     * @return false
     */
    public function findFieldByFieldName($fieldName)
    {
        $fieldNameArray = explode(',', $fieldName);
        $length = count($fieldNameArray);
        if ($length == 4) {
            $name = $fieldNameArray[$length - 2];//
        } else if ($length == 3) {
            $name = $fieldNameArray[$length - 1];//
        } else {
            $name = $fieldNameArray[0];

        }
        $name = str_replace('_cl', '', $name);

        if ($this->fields == false) {
            return false;
        }
        foreach ($this->fields as $sectionKey => $section) {
            foreach ($section->fields as $rowIndex => $row) {
                foreach ($row as $colIndex => $field) {
                    if (isset($field->name) && $field->name === $name) {
                        return $field;
                    }
                }
            }
        }

        return false;
    }

    public function tus_upload_serve($data)
    {
        $this->initSession();
        $tus = new Tus();
        $tus->serve();
    }

    public function tus_upload($data)
    {
        $this->initSession();

        if (!isset($data['id'])) {
            return new WP_REST_Response(false, 400);
        }
        if (!isset($data['fname'])) {
            return new WP_REST_Response(false, 400);
        }
        $this->setFields($data['id']);
        $field = $this->findFieldByFieldName($data['fname']);
        if ($field === false || $field->type !== 'file') {
            return new WP_REST_Response(false, 400);
        }
        $validation = new FormValidation();

        $headerData = $params = $data->get_header('Upload-Metadata');
        $headerParts = explode(',', $headerData);
        $params = [];
        foreach ($headerParts as $headerPart) {
            list($key, $value) = explode(' ', $headerPart, 2);
            $params[$key] = base64_decode($value);
        }
        if (!isset($params['name']) || !isset($params['filename'])) {
            return new WP_REST_Response(false, 400);
        }
        $file = ['error' => 0, 'size' => $params['size'], 'filename' => $params['filename'], 'type' => $params['filetype']];

        $status = $validation->validateFileUpload($field, $file, true, true);

        if ($status !== true) {
            return new WP_REST_Response($status, 422);
        }

        $tus = new Tus();
        $tus->serve();
    }

    public function ajax_upload($data)
    {
      //  set_time_limit(300); //
        $this->initSession();

        if (!isset($data['id'])) {
            return new WP_REST_Response(false, 400);
        }
        if (!isset($data['fname'])) {
            return new WP_REST_Response(false, 400);
        }
        $this->setFields($data['id']);
        $field = $this->findFieldByFieldName($data['fname']);
        if ($field === false || $field->type !== 'file') {
            return new WP_REST_Response(false, 400);
        }
        $validation = new FormValidation();

        $status = $validation->validateFileUpload($field, $_FILES['wcpa_file'], true);
        if ($status !== true) {
            return new WP_REST_Response($status, 422);
        }

        $file = new File();
        $status = $file->handle_upload_ajax($field, $_FILES['wcpa_file']);

        return new WP_REST_Response($status, 200);
    }

    /**
     * @param $fieldId
     *
     * @return false
     */
    public function findFieldById($fieldId)
    {
        if ($this->fields == false) {
            return false;
        }
        foreach ($this->fields as $sectionKey => $section) {
            foreach ($section->fields as $rowIndex => $row) {
                foreach ($row as $colIndex => $field) {
                    if ($field->elementId === $fieldId) {
                        return $field;
                    }
                }
            }
        }

        return false;
    }


}


