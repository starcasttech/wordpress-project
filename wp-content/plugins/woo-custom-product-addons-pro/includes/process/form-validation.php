<?php


namespace Acowebs\WCPA;

use function get_allowed_mime_types;

class FormValidation
{
    public $errorMessages = [];
    private $product;
    private $quantity = 1;

    public function __construct($product = false, $quantity = 1)
    {
        $this->product = $product;
        $this->quantity = $quantity;

        /**
         * This messages are different from the messages configured in backend,
         * This message using when the user submit data , and some how it skipp the js validation. Also it uses in Free version of plugin where js validation doesnt provide
         * here it need to use the field label in message to identify the field (where as in js validation as it is showing against fields, it doesnt need to replace label)
         */
        $this->errorMessages = [
            'requiredError' => __('Field %s is required', 'woo-custom-product-addons-pro'),
            'minFieldsError' => __('You have to select minimum %s items for  field %s', 'woo-custom-product-addons-pro'),
            'maxFieldsError' => __('You can select only maximum %s items for  field %s', 'woo-custom-product-addons-pro'),

            'allowedCharsError' => __('Field %s is required', 'woo-custom-product-addons-pro'),

            'patternError' => __('Field %s is required', 'woo-custom-product-addons-pro'),

            'minlengthError' => __('Field %s is required', 'woo-custom-product-addons-pro'),
            'maxlengthError' => __('Field %s is required', 'woo-custom-product-addons-pro'),

            'minValueError' => __('Field %s is required', 'woo-custom-product-addons-pro'),
            'maxValueError' => __('Field %s is required', 'woo-custom-product-addons-pro'),

            'maxFileCountError' => __('The uploaded file exceeds the maximum upload limit for field %s',
                'woo-custom-product-addons-pro'),
            'maxFileSizeError' => __('The uploaded file exceeds the maximum upload limit for field %s',
                'woo-custom-product-addons-pro'),
            'minFileSizeError' => __('Field %s is required', 'woo-custom-product-addons-pro'),

            'fileUploadError' => __('The uploaded file error for field %s', 'woo-custom-product-addons-pro'),

            'fileExtensionError' => __('Field %s is required', 'woo-custom-product-addons-pro'),
            'quantityRequiredError' => __('Field %s is required', 'woo-custom-product-addons-pro'),
            'otherFieldError' => __('Field %s is required', 'woo-custom-product-addons-pro'),
            'charleftMessage' => __('Field %s is required', 'woo-custom-product-addons-pro'),

            'groupMaxError' => __('Group Max Validation Failed', 'woo-custom-product-addons-pro'),
            'groupMinError' => __('Group Min Validation Failed', 'woo-custom-product-addons-pro'),


        ];
    }

    public function validateGroup($field, $dField, $fieldData,$sectionKey)
    {
        $status = true;
        $total = 0;
        $priceObject = new Price($fieldData, false, $this->product,
            $this->quantity);
        switch ($field->validationType) {
            case 'customFormula':

                if (isset($field->customFormula) && $field->customFormula) {
                    $total = $priceObject->process_custom_formula(
                        $field->customFormula,
                        false,
                        $field,
                        false,
                        false
                    );
                    if (isset($total['hasQuantity'])) {
                        $total = $total['price'];
                    }
                    if ($total == 'dependency') {
                        return true; //TODO now it just returning to avoid error in php side
                    }
                }
                break;

            case 'optionsCount':
            case 'sumOfValues':
            case 'totalQuantity':

                if (isset($field->fields) && is_array($field->fields)) {
                    foreach ($field->fields as $fId) {


                        $resp = findFieldById($fieldData, $fId,true);
                        $dField =  $fieldData[$resp['sectionKey']]['fields'][$resp['rowIndex']][$resp['colIndex']];
                        if ($dField && (!isset($dField['clStatus']) || $dField['clStatus'] !== 'hidden')) {

                            $value = $dField['value'];
                            if ($value !== false) {


                                $refSectionKey = $resp['sectionKey'];
                                $refSection = $fieldData[$resp['sectionKey']];
                                $checkCloneOverSections = false;
                                if($refSection['extra']->repeater && $sectionKey!==$refSectionKey){
                                    $checkCloneOverSections = true;
                                }
                                if ($field->validationType == 'optionsCount') {
                                    if (in_array($dField['type'], [
                                            'select',
                                            'radio-group',
                                            'checkbox-group',
                                            'color-group',
                                            'image-group', 'productGroup'
                                        ]) && is_array($value)) {
                                        $total += count($value);
                                    } else {
                                        if (isset($value['start']) && !empty($value['start'])) {
                                            $total++;
                                        } elseif (is_string($value) && trim($value) !== '') {
                                            $total++;
                                        }
                                    }
                                } elseif ($field->validationType == 'sumOfValues') {
                                    if (is_array($value)) {
                                        foreach ($value as $v) {
                                            if (!is_object($v) && is_numeric($v)) {
                                                $total += floatval($v);
                                            }
                                        }
                                    } elseif (is_string($value) && trim($value) !== '') {
                                        if (!is_object($value) && is_numeric($value)) {
                                            $total += floatval($value);
                                        }
                                    }
                                } elseif ($field->validationType == 'totalQuantity') {

                                    $sumQnty = function ($dField,$value,$total){
                                        if (in_array($dField['type'], [
                                                'select',
                                                'radio-group',
                                                'checkbox-group',
                                                'color-group',
                                                'image-group', 'productGroup'
                                            ]) && is_array($value)) {
                                            foreach ($value as $v) {
                                                if (isset($v['quantity'])) {
                                                    $total += floatval($v['quantity']);
                                                }
                                            }
                                        } else {
                                            $quantity = $dField['quantity']?$dField['quantity']:0;
                                            if (isset($value['start']) && !empty($value['start'])) {
                                                $total += floatval($quantity);
                                            } elseif (is_string($value) && trim($value) !== '') {
                                                $total += floatval($quantity);
                                            }
                                        }
                                        return $total;
                                    };

                                    $total = $sumQnty($dField,$value,$total);
                                    if($checkCloneOverSections){
                                        foreach ($fieldData as $_secKey => $_section) {

                                            if ($_section['extra']->parentKey == $refSectionKey) {
                                                $newId = $_secKey.'_s_'.$fId;
                                            $repField = findFieldById($fieldData,$newId);

                                            if($repField  && $repField['value']){
                                                $total = $sumQnty($repField,$repField['value'],$total);
                                            }

                                        }


                                        }
                                    }

                                }
                            }
                        }
                    }
                }
        }

        if (isset($field->max) && $field->max !== '') {
            if (!is_numeric($field->max)) {
                $res = $priceObject->process_custom_formula(
                    $field->max,
                    false,
                    $field,
                    false,
                    false
                );
                if (isset($res['hasQuantity'])) {
                    $max = $res['price'];
                } else {
                    $max = $res;
                }
                //TODO test this
            } else {
                $max = floatval($field->max);
            }
            $total = floatval($total);
            if ($total > $max) {
                $status = false;
            }

            if ($status === false) {
                $this->add_cart_error(sprintf($this->errorMessages['groupMaxError'], $field->label));

                return false;
            }
        }

        if (isset($field->min) && $field->min !== '') {
            if (!is_numeric($field->min)) {
                $res = $priceObject->process_custom_formula(
                    $field->min,
                    false,
                    $field,
                    false,
                    false
                );
                if (isset($res['hasQuantity'])) {
                    $min = $res['price'];
                } else {
                    $min = $res;
                }
                //TODO test this
            } else {
                $min = floatval($field->min);
            }
            $total = floatval($total);
            if ($total < $min) {
                $status = false;
            }

            if ($status === false) {
                $this->add_cart_error(sprintf($this->errorMessages['groupMinError'], $field->label));

                return false;
            }
        }
    }


    private function add_cart_error($message)
    {
        wc_add_notice($message, 'error');
    }

    public function validate($field, $dField)
    {
        /**
         * Required field validation
         */
        $status = true;
        if (isset($field->required) && $field->required) {
            if ($dField['value'] === false) {
                $status = false;
            }
            if (is_string($dField['value']) && trim($dField['value']) == "") {
                $status = false;
            }
            if ($status === false) {
                $this->add_cart_error(sprintf($this->errorMessages['requiredError'], isEmpty($field->label) ? $field->elementId : $field->label));

                return false;
            }
        }


        if (isset($field->min_options) && $field->min_options > 0 && $dField['value'] !== false) {
            if (is_array($dField['value'])) {
                if (count($dField['value']) < $field->min_options) {
                    $status = false;

                    $this->add_cart_error(sprintf($this->errorMessages['minFieldsError'], $field->min_options,
                        $field->label));
                }
            } else {
                if (empty($dField['value'])) {
                    $status = false;
                    $this->add_cart_error(sprintf($this->errorMessages['minFieldsError'], $field->min_options,
                        $field->label));
                }
            }
        }
        if (isset($v->max_options) && $v->max_options > 0 && isset($_REQUEST[$v->name])) {
            if (is_array($dField['value'])) {
                if (count($dField['value']) > $field->min_options) {
                    $status = false;
                    $this->add_cart_error(sprintf($this->errorMessages['maxFieldsError'], $field->max_options,
                        $field->label));
                }
            }
        }

        /** if fields is empty, no further validations to be processed */
        /* TODO, need to verify
         */
        if ($dField['value'] === false) {
            return true;
        }
        if (is_string($dField['value']) && trim($dField['value']) == "") {
            return true;
        }

        if (in_array($field->type, ['text', 'textarea'])) {
            if (isset($field->allowed_chars) && $field->allowed_chars != '') {
                $allowed_chars = $field->allowed_chars;
                $new_val = '';
                if ($allowed_chars[0] != '/') {
                    $allowed_chars = '/' . $allowed_chars . '/i';
                } else {
                    $allowed_chars = preg_replace('/\/g/', '/', $allowed_chars);
                }
                try {
                    $value_filtered = preg_replace($allowed_chars, '',
                        $dField['value']); // remove all allowed characters and check if any left
                } catch (Exception $e) {
                    $value_filtered = '';
                }
                if (trim($value_filtered) !== '') {
                    $status = false;
                    $this->add_cart_error(sprintf(__('Characters %s is not supported for field %s', 'woo-custom-product-addons-pro'),
                        $value_filtered, $field->label));
                }
            }
        }

        if (in_array($field->type, ['number'])) {
            if (isset($field->max) && $field->max !== '') {
                if ($dField['value'] > $field->max) {
                    $status = false;
                    $this->add_cart_error(sprintf(__('Value must be less than or equal to %d for field %s ',
                        'woo-custom-product-addons-pro'), $field->max, $field->label));
                }
            }
            if (isset($v->min) && $v->min !== '') {
                if ($dField < $field->min) {
                    $status = false;
                    $this->add_cart_error(sprintf(__('Value must be greater than or equal to %d for field %s ',
                        'woo-custom-product-addons-pro'), $field->min, $field->label));
                }
            }
        }
        if (in_array($field->type, ['productGroup'])) {
            foreach ($dField['value'] as $val) {
                $product_id = intval($val['value']);
                $quantity = $val['quantity'];
                if (!isset($field->independentQuantity) || !$field->independentQuantity) {
                    $quantity = $quantity * $this->quantity;
                }
                $product = wc_get_product($product_id);
                if (!$product->is_purchasable()) {
                    {
                        $status = false;
                        $message = sprintf(__('Sorry, the product %s cannot be purchased.', 'woo-custom-product-addons-pro'),
                            $product->get_title());
                        $this->add_cart_error($message);
                    }
                }

                if (!$product->has_enough_stock($quantity)) {
                    $status = false;
                    $stock_quantity = $product->get_stock_quantity();
                    $message = sprintf(__('You cannot add the amount of &quot;%1$s&quot; because there is not enough stock (%2$s remaining).',
                        'woo-custom-product-addons-pro'), $product->get_name(),
                        wc_format_stock_quantity_for_display($stock_quantity, $product));
                    $this->add_cart_error($message);
                }
            }
        }

//TODO , not fully done  now, need to do other validations as well

        return $status;
    }


    public function validateFileUpload(
        $field,
        $file, $isAjax = false, $s3 = false
    )
    {
        if (isset($file["error"]) && $file["error"] != 4) {
            $status = true;
            $error_message = false;
            if ($file["error"] != UPLOAD_ERR_OK && $file["error"] != UPLOAD_ERR_NO_FILE) {
                if ($file["error"] == UPLOAD_ERR_INI_SIZE) {
                    $error_message = __('The uploaded file exceeds the maximum upload limit', 'woo-custom-product-addons-pro');
                } elseif (in_array($file["error"], array(
                    UPLOAD_ERR_INI_SIZE,
                    UPLOAD_ERR_FORM_SIZE,
                ))) {
                    $error_message = __('The uploaded file exceeds the maximum upload limit', 'woo-custom-product-addons-pro');
                } else {
                    $error_message = __('The uploaded file error', 'woo-custom-product-addons-pro');
                }
            } elseif (isset($file) && $file["error"] === UPLOAD_ERR_OK) {
                if (isset($field->uploadSize) && $field->uploadSize && ($file["size"] > ($field->uploadSize * 1024 * 1024))) {
                    $status = false;
                    $error_message = (sprintf(__('File exceeds maximum upload size limit of %s MB', 'woo-custom-product-addons-pro'),
                        $field->uploadSize));
                }

                if (isset($field->minUploadSize) && $field->minUploadSize && ($file["size"] < ($field->minUploadSize * 1024 * 1024))) {
                    $status = false;
                    $error_message = (sprintf(__('File is too small in size, Need %s MB or above', 'woo-custom-product-addons-pro'),
                        $field->minUploadSize));
                }

                if (!$s3) {
                    /* check if wordpress supports this file */
                    $validate = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);

                    if (!$validate['ext']) {
                        $isValidMime = false;
                        $status = false;
                        if (!$isAjax) {
                            $this->add_cart_error(sprintf(__('The uploaded file type is not supported for field %s',
                                'woo-custom-product-addons-pro'), $field->label));
                        }

                        $error_message = __('The uploaded file type is not supported', 'woo-custom-product-addons-pro');

                    }else{
                        $isValidMime  = true;
                    }
                } else {
                    $info = pathinfo($file['filename']);
                    $validate = ['ext' => $info['extension'], 'type' => $file['type']];
                    $allowed = get_allowed_mime_types();
                    $isValidMime=true;
                    $allowedTypes = array_values($allowed);
                    $allowedExts = array_keys($allowed);
                    $allowedExts = join('|',$allowedExts);
                    if (strpos($allowedExts,$info['extension'])===false || !in_array($file['type'],$allowedTypes)) {
//                    if (!isset($allowed[$info['extension']]) || $file['type']!==$allowed[$info['extension']]) {
                        $isValidMime = false;
                        $error_message = __('The uploaded file type is not supported', 'woo-custom-product-addons-pro');
                        $mimetypes = getMimeTypes();
                        foreach ($mimetypes as $mime) {
                            if (isset($mime[$info['extension']]) && $file['type']===$mime[$info['extension']]) {
                                $isValidMime = true;
                                break;
                            }
                        }
                    }

                }

                if ($isValidMime) {
                    if (!$this->validate_file_with_config($field, $validate)) {
                        $status = false;
                        $error_message = __('The uploaded file type is not supported', 'woo-custom-product-addons-pro');
                    }
                }else{
                    $status = false;
                }

            }
            if ($status === false) {
                return ['status' => false, 'message' => $error_message];
            }

            return true;
        }

        return ['status' => false, 'message' => $file["error"]];
    }

    public
    function validate_file_with_config(
        $field,
        $ext
    )
    {
        $allowedFileTypes = fileTypesToExtensions($field, 'remove');
        if (count($allowedFileTypes) == 0) {
            return true;
        }
//        $ext = wp_check_filetype($file_name);

        if (in_array('image/*', $allowedFileTypes) && $ext['type'] !== false) {
            if (preg_match('/image\/*/', $ext['type'])) {
                return true;
            }
        }
        if (in_array('video/*', $allowedFileTypes)) {
            if (preg_match('/video\/*/', $ext['type'])) {
                return true;
            }
        }
        if (in_array('audio/*', $allowedFileTypes)) {
            if (preg_match('/audio\/*/', $ext['type'])) {
                return true;
            }
        }
        if (in_array($ext['ext'], $allowedFileTypes)) {
            return true;
        }
        if (in_array('.' . $ext['ext'], $allowedFileTypes)) {
            return true;
        }
        return false;
    }

    public
    function isEmpty()
    {
    }
}