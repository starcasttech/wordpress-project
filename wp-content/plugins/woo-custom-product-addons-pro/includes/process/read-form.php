<?php


namespace Acowebs\WCPA;


use stdClass;

class ReadForm
{
    private $processObject = null;

    /**
     * Using this when order again data processing
     * @var null
     */
    private $metaData = null;
    /**
     * To store the meta data is version 1 type or newer
     * @var null
     */
    private $isMetaV1 = null;


    public function __construct(Process $process = null)
    {
        $this->processObject = $process;
    }

    public function read_from_order_data($data, $field, $hide_empty, $zero_as_empty)
    {
        $fieldValue = '';
        $this->metaData = $data;
        $this->isMetaV1 = is_array($data) && isset($data[0]);

        $dField = $this->find_meta($field);

        if ($dField === false) {
            return '';
        }

        if (isset($dField['value'])) {
            if (in_array($field->type, [
                'select',
                'radio-group',
                'checkbox-group',
                'color-group',
                'image-group'
            ])) {
                if (is_array($dField['value'])) {
                    $values = array_map(function ($v) {
                        return $v['value'];
                    }, $dField['value']);
                    $fieldValue = $this->readOptionsField($field, $values);
                }
            } elseif (in_array($field->type, ['productGroup'])) {
                $values = array_map(function ($v) {
                    return $v['value'];
                }, $dField['value']);

                $fieldValue = $this->readProductGroup($field, $values);
            } elseif (in_array($field->type, ['placeselector'])) {
                if (isset($dField['value']['value'])) {
                    $fieldValue = $dField['value'];
                    $fieldValue = wp_parse_args(
                        $fieldValue,
                        array(
                            'split' => array(),
                            'cords' => array()
                        )
                    );
                }
            } elseif (in_array($field->type, ['date', 'datetime-local'])) {
                if ($field->picker_mode == 'range') {
                    if (!isset($dField['value']['start'])) {
                        $fieldValue['start'] = $dField['value'];
                        $fieldValue['end'] = $dField['value'];
                    } else {
                        $fieldValue = $dField['value'];
                    }
                } elseif ($field->picker_mode == 'multiple') {
                    if (!is_array($dField['value'])) {
                        $fieldValue = [$dField['value']];
                    } else {
                        $fieldValue = $dField['value'];
                    }
                } elseif (is_string($dField['value'])) {
                    $fieldValue = $dField['value'];
                }
            } else {
                if (is_string($dField['value'])) {
                    $fieldValue = $dField['value'];
                }
            }


          //  if (isset($dField['quantity']) && $dField['quantity'] !== false) {
         ///       $fieldValue['quantity'] = $dField['quantity'];
        //        $fieldValue['value'] = $dField['value'];
       //     }
            //  else {
            //  //   $fieldValue = $dField['value'];
            //     $fieldValue = $fieldValue;
            // }
        }


        return $fieldValue;
    }

    public function find_meta($field)
    {
        if ($this->isMetaV1) {
            /** check with id */
            return $this->searchField($this->metaData, $field);
        } else {
            /** check with id */
            $elementId = $field->elementId;
            foreach ($this->metaData as $sectionKey => $section) {
                foreach ($section['fields'] as $rowIndex => $row) {
                    $value = $this->searchField($row, $field);
                    if ($value !== false) {
                        return $value;
                    }
                }
            }
        }

        return false;
    }

    public function searchField($data, $field)
    {
        $elementId = $field->elementId;
        $arr = array_filter($data, function ($v) use ($elementId) {
            if(isset($v['elementId'])){
            return $v['elementId'] === $elementId;
            }
            if(isset($v['form_data']) && isset($v['form_data']->elementId)){
                return $v['form_data']->elementId === $elementId;
            }
        });

        if ($arr !== false && !isEmpty($arr)) {
            return reset($arr);
        } else {
            /** check with name */
            $name = $field->name;
            $arr = array_filter($data, function ($v) use ($name) {
                return $v['name'] === $name;
            });
            if ($arr !== false) {
                return reset($arr);
            }

            return false;
        }
    }

    public function readOptionsField($field, $values = false)
    {
        //fieldFromName

//		if ( is_array( $field->name ) ) {
//			$val = $_POST;
//			foreach ( $field->name as $v ) {
//				$val = $val[ $v ];
//			}
//			$values = $val;
//		} else {
//			$values = $_POST[ $field->name ];
//		}
        if ($values === false) {
            $values = $this->fieldFromName($field->name);
        }

//        $values = $_REQUEST[$field->name];
        $values_data = array();
        if (!is_array($values)) {
            $values = array($values);
        }
        foreach ($values as $l => $val) {
            $item = false;
            //TODO for checkbox, it was different method earlier, so need to check
            foreach ($field->values as $j => $_v) {
                if (isset($_v->options) && is_array($_v->options)) {
                    foreach ($_v->options as $k => $__v) {
                        if ($__v->value === $val || addslashes($__v->value) === $val) {
                            $item = $__v;
                            break;
                        }
                    }
                    if ($item !== false) {
                        break;
                    }
                } else {
                    if ($_v->value === $val || addslashes($_v->value) === $val) {
                        $item = $_v;
                        break;
                    }
                }
            }

            if ($item === false && $val == 'WCPAOTH') {
                $item = new stdClass;
//				$val  = str_replace( 'WCPAOTH ', '', $val );
                $val = $this->fieldFromName($field->name, 'value', 'other_value');
                if (isset($field->other_text) && !empty($field->other_text)) {
                    $item->label = $field->other_text;
                } else {
                    $item->label = Config::get_config('other_text');
                }
                $j = 'other';
            }
            if ($item === false) {
                continue;
            }

            if (isset($field->enable_product_image) && $field->enable_product_image) {
                if (isset($item->pimage_id) && $item->pimage_id > 0) {
                    $thumb_image = $item->pimage_id;
                    $this->processObject->setThumbImage($thumb_image);
                }
            } elseif (isset($field->show_as_product_image) && $field->show_as_product_image) {
                if (isset($item->image_id) && $item->image_id > 0) {
                    $thumb_image = $item->image_id;
                    $this->processObject->setThumbImage($thumb_image);
                }
            }


//            $values_data[$j] = array(
//                'i' => $j,
//                'value' => $this->sanitize_values($val),
//                'label' => isset($item->label) ? $this->sanitize_values($item->label) : false,
//                'price' => isset($item->price) ? $item->price : 0,
//                'weight' => isset($item->weight) ? $item->weight : 0
//                /** must set zero as price if not defined, dont set False, as it using directly while price calculation*/
//            );
            $_data = array(
                'i' => $j,
                'value' => $this->sanitize_values($val),
                'label' => isset($item->label) ? $this->sanitize_values($item->label) : false,
                'price' => isset($item->price) ? $item->price : 0,
                'weight' => isset($item->weight) ? $item->weight : 0
                /** must set zero as price if not defined, dont set False, as it using directly while price calculation*/
            );

            if ($field->type == 'productGroup') {

                $_data['parentId'] = isset($item->parentId) ? $item->parentId : false;
                $_data['image_id'] = isset($item->image_id) ? $item->image_id : false;
                $_data['image'] = isset($item->image) ? $item->image : false;
                $_data['quantity'] = 1;
            }
            if (isset($field->enable_quantity) && $field->enable_quantity) {
                $quantity = $this->fieldFromName($field->name, 'value', '_quantity');
                $_data['quantity'] = is_array($quantity) ? floatval($quantity[$j]) : floatval($quantity);
            }
            if ($field->type == 'color-group') {
                $_data['color'] = isset($item->color) ? $item->color : false;
            }
            if ($field->type == 'image-group') {
                $_data['image_id'] = isset($item->image_id) ? $item->image_id : false;
                $_data['image'] = isset($item->image) ? $item->image : false;
            }
            if (isset($field->enable_quantity) && $field->enable_quantity) {
                $quantity = $this->fieldFromName($field->name, 'value', '_quantity');
                $_data['quantity'] = is_array($quantity) ? floatval($quantity[$j]) : floatval($quantity);
                if($_data['quantity']>0){
                    $values_data[$j] = $_data;
                }
            }else{
                $values_data[$j] = $_data;
            }


            if (isset($field->multiple) && $field->multiple === false) {
                break;
            }
        }


        return $values_data;
    }

    public function fieldFromName($name, $action = 'value', $prefix = false)
    {
        return fieldFromName($name, $action, $prefix);
//		if ( $action == 'isset' ) {
//			if ( is_array( $name ) ) {
//				$val = $_POST;
//				if ( $prefix !== false ) {
//					$name[ count( $name ) - 2 ] = $name[ count( $name ) - 2 ] . $prefix;
//				}
//				foreach ( $name as $v ) {
//					if ( ! isset( $val[ $v ] ) ) {
//						return false;
//					}
//					$val = $val[ $v ];
//				}
//
//				return true;
//			} else {
//				if ( $prefix !== false ) {
//					return isset( $_POST[ $name . $prefix ] );
//				}
//
//				return isset( $_POST[ $name ] );
//			}
//		}
//
//		if ( $action == 'value' ) {
//			if ( $prefix !== false ) {
//				$name[ count( $name ) - 2 ] = $name[ count( $name ) - 2 ] . $prefix;
//			}
//			if ( is_array( $name ) ) {
//				$val = $_POST;
//				foreach ( $name as $v ) {
//					$val = $val[ $v ];
//				}
//
//				return $val;
//			} else {
//				if ( $prefix !== false ) {
//					return $_POST[ $name . $prefix ];
//				}
//
//				return $_POST[ $name ];
//			}
//		}

    }

    public function sanitize_values($value, $isMultiLine = false)
    {
//        /**
//         * sanitize functions removes %20 from urls, so it need to find url from string and escape it
//         */
//        $filtered = $value;
//        while ( preg_match( '/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', $value, $match ) ) {
//            $filtered = str_replace( $match[0], urldecode($match[0]), $filtered );
//            $value = str_replace( $match[0], '', $value );
//        }
//
//        if ($isMultiLine) {
//            return  sanitize_textarea_field(wp_unslash($filtered));
//        }
//
//        return sanitize_text_field(wp_unslash($filtered));
//
        /** sanitize_text_field this removes octets (%20) characters, so using custom sanitizer  */
        $str = (string)$value;
        $str = wp_unslash($str);

        $filtered = wp_check_invalid_utf8($str);

        if (strpos($filtered, '<') !== false) {
            $filtered = wp_pre_kses_less_than($filtered);
            // This will strip extra whitespace for us.
            $filtered = wp_strip_all_tags($filtered, false);

            // Use HTML entities in a special case to make sure no later
            // newline stripping stage could lead to a functional tag.
            $filtered = str_replace("<\n", "&lt;\n", $filtered);
        }

        if (!$isMultiLine) {
            $filtered = preg_replace('/[\r\n\t ]+/', ' ', $filtered);
        }
        $filtered = trim($filtered);
        return $filtered;
    }

    public function readProductGroup($field, $values = false)
    {
//        processProductGroup($field); removed as already did it in _read_form starting


//        setThumbImage
        return $this->readOptionsField($field, $values);
//        $values = $this->sanitize_values($field);
//        $quantities = $_REQUEST[$v->name . '_quantity'];
//
//        if ($hide_empty && empty($values) && empty($quantities)) {
//            continue;
//        }
//        if (!empty($values)) {
//            if (count($values) > 0) {
//                $this->submited_data[] = array(
//                    'type' => $v->type,
//                    'name' => $v->name,
//                    'label' => (isset($v->label)) ? (($v->label == '') ? WCPA_EMPTY_LABEL : $v->label) : WCPA_EMPTY_LABEL,
//                    'value' => $values,
//                    'quantities' => $quantities,
//                    'independent' => (isset($v->independent) && $v->independent) ? true : false,
//                    'independ_quantity' => (isset($v->independentQuantity) && $v->independentQuantity) ? true : false,
//                    'is_fee' => (isset($v->use_as_fee) && $v->use_as_fee) ? true : false,
//                    'is_show_price' => (isset($v->is_show_price) && $v->is_show_price) ? true : false,
//                    'price' => $this->element_price($v, $product_id, array('values' => $values, 'quantities' => $quantities)),
//                    'quantity_depend' => $this->is_quantity_dependent($v, $product_id, $values),
//                    'cur_swit' => $this->getCurrSwitch(),
//                    'form_data' => $form_data
//                );
//            }
//        }
    }

    public function _read_form($field, $hide_empty, $zero_as_empty)
    {
        if ($field->type == 'productGroup') {
            processProductGroup($field);
        }

        $fieldValue = false;
        if ($field->type == 'file') {
            $fieldValue = $this->readFieldFile($field);
            if (isEmpty($fieldValue) && $hide_empty) {
                $fieldValue = '';
            }
        } elseif (in_array($field->type, array('content', 'header'))) {
            if (isset($field->show_in_checkout) && $field->show_in_checkout == true) {
                $fieldValue = (isset($field->value)) ? $field->value : '';
            }
        } elseif ($this->fieldFromName($field->name, 'isset')) {
            if (in_array($field->type, [
                'select',
                'radio-group',
                'checkbox-group',
                'color-group',
                'image-group'
            ])) {
                $fieldValue = $this->readOptionsField($field);
            } elseif (in_array($field->type, ['productGroup'])) {
                $fieldValue = $this->readProductGroup($field);
            } elseif (in_array($field->type, ['placeselector'])) {
                $fieldValue = $this->readPlaceSelector($field);
            } elseif (in_array($field->type, ['date', 'datetime-local'])) {
                $fieldValue = $this->readDateFields($field);
            } else {
                $fieldValue = $this->readTextFields($field);
            }
        }

        return $fieldValue;
    }

    public function readFieldFile($field)
    {
        $fieldValue = [];


        if ($field->upload_type && $field->upload_type == 'basic') {
            $isSetFile = true;
            if (is_array($field->name)) {
                $_file = $_FILES;
                foreach ($field->name as $v) {
                    if (!isset($_file[$v])) {
                        $isSetFile = false;
                        break;
                    }
                    $_file = $_file[$v];
                }
            } else {
                if (isset($_FILES[$field->name])) {
                    $_file = $_FILES[$field->name];
                } else {
                    $isSetFile = false;
                }
            }
            if ($isSetFile) {
                $files = [];
                if (is_array($_file['name'])) {
                    foreach ($_file['name'] as $i => $name) {
                        $files[] = array(
                            'tmp_name' => $_file['tmp_name'][$i],
                            'name' => $_file['name'][$i],
                            'size' => $_file['size'][$i],
                            'type' => $_file['type'][$i],
                            'error' => $_file['error'][$i],
                        );
                    }
                } else {
                    $files[] = array(
                        'tmp_name' => $_file['tmp_name'],
                        'name' => $_file['name'],
                        'size' => $_file['size'],
                        'type' => $_file['type'],
                        'error' => $_file['error'],
                    );
                }


                $allowedCount = 1;
                if (isset($field->multiple_upload) && $field->multiple_upload) {
                    if (isset($field->max_file_count) && !empty($field->max_file_count)) {
                        $allowedCount = $field->max_file_count;
                    } else {
                        $allowedCount = false;
                    }
                }
                if ($allowedCount !== false) {
                    $files = array_slice($files, 0, $allowedCount);
                }
                $Validation = new FormValidation();
                $File = new File();
                foreach ($files as $file) {
                    $status = $Validation->validateFileUpload($field, $file);
                    if ($status === true) {
                        $fieldValue[] = $File->handle_upload($field, $file);
                    }
                }
            }
        } elseif ($field->upload_type && ($field->upload_type == 'ajax' || $field->upload_type == 'droppable') && $this->fieldFromName($field->name,'isset')) {
            $allowedCount = 1;
            if (isset($field->multiple_upload) && $field->multiple_upload) {
                if (isset($field->max_file_count) && !empty($field->max_file_count)) {
                    $allowedCount = $field->max_file_count;
                } else {
                    $allowedCount = false;
                }
            }
            $_files = $this->fieldFromName($field->name);
            $files = is_array($_files)?$_files:json_decode(wp_unslash($_files));
            if ($files && is_array($files)) {
                $File = new File();
                if ($allowedCount !== false) {
                    $files = array_slice($files, 0, $allowedCount);
                }
                foreach ($files as $file) {
                    $move_file = $File->move_file($field, $file);
                    if ($move_file === false) {
                        return $move_file;
                    }
                    $fieldValue[] = $move_file;
                }
            }
        }

        if (isset($field->enable_quantity) && $field->enable_quantity) {
            $quantity = $this->fieldFromName($field->name, 'value', '_quantity');

            return ['value' => $fieldValue, 'quantity' => $quantity];
        }

        return $fieldValue;
    }

    public function readPlaceSelector(
        $field
    )
    {
        $_val = $this->fieldFromName($field->name);


        if (isEmpty($_val)) {
            return '';
        }
        $value = array(
            'value' => $_val,
            'split' => array(),
            'cords' => array()
        );
        $split = [
            'street_number',
            'route',
            'locality',
            'administrative_area_level_1',
            'postal_code',
            'country'
        ];
        foreach ($split as $fl_name) {
            if ($this->fieldFromName($field->name, 'isset', '_' . $fl_name)) {
                $value['split'][$fl_name] = $this->fieldFromName($field->name, 'value', '_' . $fl_name);
            }
        }
        if ($this->fieldFromName($field->name, 'isset', '_lat')) {
            $value['cords']['lat'] = $this->fieldFromName($field->name, 'value', '_lat');
        }
        if (isset($_REQUEST[$field->name . '_lng'])) {
            $value['cords']['lng'] = $this->fieldFromName($field->name, 'value', '_lng');
        }

        return $value;
    }

    public
    function readDateFields(
        $field,
        $value = false
    )
    {
        if ($value == false) {
            $value = $this->fieldFromName($field->name);
        }

        $value = $this->sanitize_values($value);
        if ($field->picker_mode == 'multiple') {
            $value = explode(',', $value);
        } elseif ($field->picker_mode == 'range') {
            $sp = preg_split('/\sto\s/', $value);
            if (count($sp) == 2) {
                $value = [];
                $value['start'] = $sp[0];
                $value['end'] = $sp[1];
            }
        }

        if (isset($field->enable_quantity) && $field->enable_quantity) {
            $quantity = $this->fieldFromName($field->name, 'value', '_quantity');

            return ['value' => $value, 'quantity' => floatval($quantity)];
        }

        return $value;
    }

    public
    function readTextFields(
        $field
    )
    {
        $value = $this->fieldFromName($field->name);


        $value = $this->sanitize_values($value, 'textarea' == $field->type);
        if (isset($field->enable_quantity) && $field->enable_quantity) {
            $quantity = $this->fieldFromName($field->name, 'value', '_quantity');

            return ['value' => $value, 'quantity' => floatval($quantity)];
        }

        return $value;
    }
}