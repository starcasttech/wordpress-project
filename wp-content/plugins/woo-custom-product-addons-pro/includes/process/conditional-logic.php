<?php


namespace Acowebs\WCPA;


class CLogic
{
    public $form_data = false;
    public $fields = false;
    public $product = false;
    public $parentProduct = false;
    public $quantity = false;

    public function __construct($form_data, $fields, $product, $parentProduct, $quantity)
    {
        $this->form_data = $form_data;
        $this->fields = $fields;
        $this->product = $product;
        $this->parentProduct = $parentProduct;
        $this->quantity = $quantity;
    }

    /** formData is not passed as reference, so each  time it has to update as changes occures
     * @param $form_data
     */
    public function setFormData($form_data)
    {
        $this->form_data = $form_data;
    }

    /**
     * @param $cl_dependency
     * @param $processed_ids
     *
     * @since 5.0
     */
    public function processClDependency($cl_dependency, &$processed_ids)
    {
        if ($cl_dependency && is_array($cl_dependency) && count($cl_dependency)) {
            foreach ($cl_dependency as $elementID) {
                if ($processed_ids !== false && !in_array($elementID, $processed_ids)) { // skip it is not in $processed_ids, as this will process again later
                    return;
                }
                $fieldIndex = findFieldById($this->form_data, $elementID, true);
                if ($fieldIndex === false) {
                    return;
                }
                $field = $this->fields->{$fieldIndex['sectionKey']}->fields[$fieldIndex['rowIndex']][$fieldIndex['colIndex']];
                $clStatus = $this->evalConditions($field->cl_rule,
                    $field->relations); // returns false if it catch error
                if ($clStatus !== false &&
                    $this->form_data[$fieldIndex['sectionKey']]['fields'][$fieldIndex['rowIndex']][$fieldIndex['colIndex']]['clStatus'] != $clStatus) {
                    /**
                     * process dependency only if clStatus changed
                     */
                    $this->form_data[$fieldIndex['sectionKey']]['fields'][$fieldIndex['rowIndex']][$fieldIndex['colIndex']]['clStatus'] = $clStatus;

                    if ($field->cl_dependency) {
                        $this->processClDependency($field->cl_dependency, $processed_ids);
                    }
                }
            }
        }
    }

    /**
     * Process conditional logic with relations provided
     *
     * @param $clRule
     * @param $relations
     * @param $product_id
     *
     * @return string
     */

    public function evalConditions($clRule, $relations)
    {

        $eval_str = '';
        foreach ($relations as $relation) {
            if (is_array($relation->rules) && count($relation->rules)) {
                $eval_str .= '(';
                foreach ($relation->rules as $rule) {
                    $eval_str .= '(';
                    if ($this->eval_relation($rule->rules)) {
                        $eval_str .= ' true ';
                    } else {
                        $eval_str .= ' false ';
                    }
                    $eval_str .= ') ' . (($rule->operator !== false) ? $rule->operator : '') . ' ';
                }

                if (count($relation->rules) > 0) {
                    preg_match_all('/\(.*\)/', $eval_str, $match);
                    $eval_str = $match[0][0] . ' ';
                }

                $eval_str .= ') ' . (($relation->operator !== false) ? $relation->operator : '') . ' ';
            }
        }
        if (count($relations) > 0) {
            preg_match_all('/\(.*\)/', $eval_str, $match);
            $eval_str = $match[0][0] . ' ';
        } else {
            return 'visible';// jus return visible if no relations are set
        }

        $eval_str = str_replace(['and', 'or'], ['&&', '||'], $eval_str);

        $result = eval('return ' . $eval_str . ';');
        $clStatus = false;
        if ($result === true) {
            /** In PHP end 'disable' treats as hidden */
            if ($clRule === 'show') {
                $clStatus = 'visible';
            } else {
                $clStatus = 'hidden';
            }
        } else {
            if ($clRule === 'show') {
                $clStatus = 'hidden';
            } else {
                $clStatus = 'visible';
            }
        }

        return $clStatus;
    }

    public function eval_relation($rule)
    {

        if (!isset($rule->cl_field) || empty($rule->cl_field)) {
            return true;
        }
        if ($rule->cl_relation === '0') {
            return false;
        }

        $inputVal = [];

        $multipleAllowed = in_array($rule->cl_relation, [
            'is_in',
            'is_not_in',
            'year_is',
            'week_day_is',
            'month_day_is',
            'month_is'
        ]);
        $relationVal = $rule->cl_val;

        if (!is_array($relationVal)) {
            $relationVal = [$relationVal];
        }
        if (!$multipleAllowed) {
            /** there is chance to have multiple values in array even if it is 'is' comparison, that need to omit and take the first index value only */
            $relationVal = isset($relationVal[0]) ? [$relationVal[0]] : [];
        }
        $isDate = false;
        /**
         * Conditional Relation field grouped as Extra and Form Fields
         */
        if (in_array($rule->cl_field,
            [
                'quantity',
                'attribute',
                'user_roles',
                'custom_attribute',
                'stock_quantity',
                'stock_status',
                'product_ids',
                'product_skus',
                'custom_field',
                'user_roles',
            ]
        )) {
            switch ($rule->cl_field) {
                case 'quantity':

                    $inputVal[] = $this->quantity;

                    break;
                case 'stock_status':
                    $inputVal[] = $this->product->get_stock_status('edit');
                    break;
                case 'stock_quantity':
                    $inputVal[] = $this->product->get_stock_quantity('edit');
                    break;
                case 'product_ids':
                    $product_id = $this->product->get_id();
                    $parentId = $this->product->get_parent_id();
                    $inputVal[] = $product_id;
                    if ($parentId !== $product_id) {
                        $inputVal[] = $parentId;
                    }
                    break;
                case 'product_skus':
                    $inputVal[] = $this->product->get_sku('edit');
                    break;

                case 'user_roles':
                    if (is_user_logged_in()) {
                        $user = wp_get_current_user();
                        $roles = ( array )$user->roles;
                        $inputVal = $roles;
                    } else {
                        $inputVal[] = 'guest';
                    }

                    break;

                case 'custom_field':
                    if ($rule->cl_field_sub) {
                        if($this->parentProduct){
                            $cField = Config::getWcpaCustomField($rule->cl_field_sub, $this->parentProduct->get_id());
                        }else{
                            $cField = Config::getWcpaCustomField($rule->cl_field_sub, $this->product->get_id());
                        }

                        if ($cField !== false) {
                            $inputVal[] = strtolower($cField);
                        }
                    }

                    break;
                case 'attribute':
                case 'custom_attribute':
                    //TODO to verify custom attribute case
                    $name = 'attribute_' . $rule->cl_field_sub;
                    $encoded = strtolower(urlencode($name));  // in case of unicode variations
                    if (isset($_REQUEST[$name])) {
                        $inputVal[] = $_REQUEST[$name];
                    } else if (isset($_REQUEST[$encoded])) {
                        $inputVal[] = $_REQUEST[$encoded];
                    }

//                    $product_attributes = get_pro_attr_list($this->product);
                    if ($this->parentProduct) {
                        $product_attributes = get_pro_attr_list($this->parentProduct);
                    } else {
                        $product_attributes = get_pro_attr_list($this->product);
                    }

                    if (isset($product_attributes[$rule->cl_field_sub])) {

                        $inputVal = array_merge($inputVal,$product_attributes[$rule->cl_field_sub]['value']);

                    }

                    break;
            }

            /** Read rel_val */

            if (in_array($rule->cl_field, [
                'quantity',
                'stock_quantity',
                'product_ids'
            ])) {
                $relationVal = array_map('intval', $relationVal);
                $inputVal = array_map('intval', $inputVal);
            } elseif ('custom_attribute' == $rule->cl_field) {
                $relationVal = array_map(function ($v) {
                    return strtolower($v);
                }, $relationVal);
                $inputVal = array_map(function ($v) {
                    return strtolower($v);
                }, $inputVal);
            }
        } else {
            $fieldIndex = findFieldById($this->form_data, $rule->cl_field, true);
            /** if field not submitted, it can be taken as empty , and can match with is_empty/is_not_empty */
            if ($fieldIndex !== false) {
                $dataField = $this->form_data[$fieldIndex['sectionKey']]['fields'][$fieldIndex['rowIndex']][$fieldIndex['colIndex']];
                $dataSection = $this->form_data[$fieldIndex['sectionKey']]['extra'];
                if ($dataSection !== false) {
                    $is_visible = $dataSection->clStatus === 'visible';
                } else {
                    $is_visible = false;
                }
                if ($is_visible) {
                    $is_visible = $dataField['clStatus'] === 'visible';
                }
                if ($rule->cl_relation === 'is_visible' || $rule->cl_relation === 'is_not_visible') {
                    $inputVal[] = $is_visible;
                }else  if ($dataField && $dataField['value']) {


                        if ($is_visible) {
                            switch ($dataField['type']) {
                                case 'hidden':
                                case 'text':
                                case 'color':
                                case 'textarea':
                                case 'url':
                                case 'email':
                                    $inputVal[] = strtolower($dataField['value']);
                                    $relationVal = array_map('strtolower', $relationVal);
                                    break;
                                case 'file':
                                    $inputVal = array_map(function ($file) {
                                        return strtolower($file['file_name']);
                                    }, $dataField['value']);
                                    $relationVal = array_map('strtolower', $relationVal);
                                    break;
                                case 'number':
                                    $inputVal[] = floatval($dataField['value']);
                                    $relationVal = array_map('floatval', $relationVal);
                                    break;
                                case 'checkbox':
                                    $inputVal[] = $dataField['value'];
                                    break;
                                case 'placeselector':
                                    $inputVal[] = isset($dataField['value']['value']) ? strtolower($dataField['value']['value']) : '';
                                    $relationVal = array_map('strtolower', $relationVal);
                                    break;
                                case 'select':
                                case 'checkbox-group':
                                case 'radio-group':
                                case 'image-group':
                                case 'color-group':

                                    if (in_array($rule->cl_relation, [
                                        'contains',
                                        'not_contains',
                                        'starts_with',
                                        'ends_with',
                                    ])) {
                                        $inputVal = array_map(function ($v) {
                                            return strtolower(str_replace('WCPAOTH ', '', $v['value']));
                                        }, $dataField['value']);
                                    } else {
                                        $inputVal = array_map(function ($v) {
                                            if (substr($v['value'], 0, 8) === "WCPAOTH ") {
                                                return 'other';
                                            }

                                            return strtolower($v['value']);
                                        }, $dataField['value']);
                                    }
                                    $relationVal = array_map('strtolower', $relationVal);
                                    break;
                                case 'productGroup':
                                    $inputVal = [];
                                    foreach ($dataField['value'] as $v) {
                                        $inputVal[] = strtolower($v['value']);
                                        if ($v['parentId']) {
                                            $inputVal[] = strtolower($v['parentId']);
                                        }
                                    }


                                    $relationVal = array_map('strtolower', $relationVal);
                                    break;


                                case 'date':
                                case 'datetime-local':
                                    /** date value will not be array always */
                                    $isDate = true;
                                    $inputVal = processDateValueForCl(isset($dataField['value'][0]) ? $dataField['value'] : [$dataField['value']]);// array_map( 'processDateValueForCl', is_array( $dataField['value'] ) ? $dataField['value'] : [ $dataField['value'] ] );
                                    if (in_array($rule->cl_relation, [
                                        'year_is',
                                        'week_day_is',
                                        'month_is',
                                        'month_day_is'
                                    ])) {
                                        $relationVal = array_map('intval', $relationVal);
                                    } else {
                                        $relationVal = processDateValueForCl($relationVal);// array_map( 'processDateValueForCl', $relationVal );
                                    }

                                    break;
                                case 'time':
                                    $BASE_DATE = '2022-01-01';
                                    $t = getUNIDate($BASE_DATE . ' ' . $dataField['value']);
                                    if ($t) {
                                        $inputVal[] = $t->getTimestamp();
                                    } else {
                                        $inputVal[] = $dataField['value'];
                                    }

                                    break;
                            }
                        }
                    }
            }
        }

        $inputVal = array_map(function ($v) {
            return wp_unslash($v);
        }, $inputVal);
        $inputVal = array_values($inputVal);// reset array index

        if (count($inputVal) === 0) {
            switch ($rule->cl_relation) {
                case 'is_empty':
                    return true;
                default:
                    return false;
            }
        }

        switch ($rule->cl_relation) {
            case 'is':
            case 'is_not':

            case 'is_in':
            case 'is_not_in':
                $is_in = false;
                if ($isDate) {
                    foreach ($relationVal as $r) {
                        if (is_object($r)) {
                            foreach ($inputVal as $v) {
                                if (is_object($v)) {
                                    if (($v->start >= $r->start && $v->start <= $r->end) || ($v->end >= $r->start && $v->end <= $r->end)) {
                                        $is_in = true;
                                        break;
                                    }
                                } else {
                                    if ($v >= $r->start && $v <= $r->end) {
                                        $is_in = true;
                                    }
                                }
                            }
                            if ($is_in) {
                                break;
                            }
                        }
                        if ($is_in) {
                            break;
                        }
                        foreach ($inputVal as $v) {
                            if (is_object($v)) {
                                if ($r >= $v->start && $r <= $v->end) {
                                    $is_in = true;
                                }
                            } else {
                                if ($v == $r) {
                                    $is_in = true;
                                }
                            }
                            if ($is_in) {
                                break;
                            }
                        }
                        if ($is_in) {
                            break;
                        }
                    }


                    return $is_in ? ($rule->cl_relation == 'is_in' || $rule->cl_relation == 'is')
                        : ($rule->cl_relation == 'is_not_in' || $rule->cl_relation == 'is_not');
                }

                foreach ($relationVal as $r) {
                    if (in_array($r, $inputVal)) {
                        $is_in = true;
                    }else if(in_array(htmlentities($r), $inputVal)){
                        $is_in = true;
                    }
                    if ($is_in) {
                        break;
                    }
                }

                return $is_in ? ($rule->cl_relation == 'is_in' || $rule->cl_relation == 'is')
                    : ($rule->cl_relation == 'is_not_in' || $rule->cl_relation == 'is_not');


            case 'is_empty':
            case 'is_not_empty':
                if (count($inputVal) === 0 || $inputVal[0] === "" || $inputVal[0] === null) {
                    return $rule->cl_relation == 'is_empty';
                } else {
                    return $rule->cl_relation == 'is_not_empty';
                }

            case 'is_visible':
            case 'is_not_visible':
                $is_visible = false;
                foreach ($inputVal as $r) {
                    if ($r) {
                        $is_visible = true;
                    }

                }
                return $is_visible ? ($rule->cl_relation == 'is_visible')
                    : ($rule->cl_relation == 'is_not_visible');

            case "is_greater":


                /** greater if all of the array values are greater */
                $is_less = false;
                foreach ($inputVal as $e) {
                    if ($isDate && is_object($e)) {
                        if ($e->start <= $relationVal[0]) {
                            $is_less = true;
                        }
                    } else {
                        if ($e <= $relationVal[0]) {
                            $is_less = true;
                        }
                    }
                    if ($is_less) {
                        break;
                    }
                }

                return !$is_less;

            case "is_lessthan_or_equal":
                $is_greater = false;

                /** check if any of the value less, then it is false */
                foreach ($inputVal as $e) {
                    if ($isDate && is_object($e)) {
                        if ($e->end > $relationVal[0]) {
                            $is_greater = true;
                        }
                    } else {
                        if ($e > $relationVal[0]) {
                            $is_greater = true;
                        }
                    }
                    if ($is_greater) {
                        break;
                    }
                }

                return !$is_greater;
            case "is_lessthan":
                $is_greater = false;

                foreach ($inputVal as $e) {
                    if ($isDate && is_object($e)) {
                        if ($e->end >= $relationVal[0]) {
                            $is_greater = true;
                        }
                    } else {
                        if ($e >= $relationVal[0]) {
                            $is_greater = true;
                        }
                    }
                    if ($is_greater) {
                        break;
                    }
                }

                return !$is_greater;
            case "is_greater_or_equal":
                $is_less = false;

                foreach ($inputVal as $e) {
                    if ($isDate && is_object($e)) {
                        if ($e->start < $relationVal[0]) {
                            $is_less = true;
                        }
                    } else {
                        if ($e < $relationVal[0]) {
                            $is_less = true;
                        }
                    }
                    if ($is_less) {
                        break;
                    }
                }

                return !$is_less;
            case "contains":
            case "not_contains":
                $contains = false;
                foreach ($inputVal as $e) {
                    if (strpos($e, $relationVal[0]) !== false) {
                        $contains = true;
                    }
                    if ($contains) {
                        break;
                    }
                }

                return $contains ? $rule->cl_relation == 'contains' : $rule->cl_relation == 'not_contains';

            case "starts_with":

                foreach ($inputVal as $e) {
                    if (strpos($e, $relationVal[0]) === 0) {
                        return true;
                    }
                }

                return false;

            case "ends_with":

                foreach ($inputVal as $e) {
                    $strlen = strlen($e);
                    $testlen = strlen($relationVal[0]);
                    if ($testlen <= $strlen) {
                        if (substr_compare($e, $relationVal[0], $strlen - $testlen, $testlen) === 0) {
                            return true;
                        }
                    }
                }

                return false;
            case "week_day_is":
            case "month_is":
            case "month_day_is":
            case "year_is":
                $dF = function ($d, $cl_relation) {
                    // $dObject = getUNIDate($d);
                    $dObject = date_create("@$d");
                    if (!$dObject) {
                        return false;
                    }
                    if ($cl_relation == 'week_day_is') {
                        return $dObject->format('w');
                    }
                    if ($cl_relation == 'month_is') {
                        return $dObject->format('n');
                    }
                    if ($cl_relation == 'month_day_is') {
                        return $dObject->format('j');
                    }
                    if ($cl_relation == 'year_is') {
                        return $dObject->format('Y');
                    }

                    return false;
                };

                foreach ($inputVal as $e) {
                    if ($isDate && is_object($e)) {
                        for ($_i = $e->start; $_i <= $e->end; $_i = $_i + 86400) {
                            if (in_array($dF($_i, $rule->cl_relation), $relationVal)) {
                                return true;
                            }
                        }
                    }
                    if (!is_object($e) && in_array($dF($e, $rule->cl_relation), $relationVal)) {
                        return true;
                    }
                }

                return false;
        }

        return false;
    }

    /** is a field has a relation with userRole, it need validate before rendering
     *  from PHP side itself, to avoid leaking sensitive data to front end
     *
     * @param $clRule
     * @param $relations
     */
    public function evalUserRoleRelation($relations, $cl_rule)
    {
        $eval_str = '';
        $hasUserRole = false;
        foreach ($relations as $relation) {
            if (is_array($relation->rules) && count($relation->rules)) {
                $eval_str .= '(';
                foreach ($relation->rules as $rule) {

                    if ($rule->rules->cl_field == 'user_roles') {
                        $hasUserRole = true;
                    }
                    $eval_str .= '(';
                    if (!isset($rule->rules->cl_field)) {
                        $eval_str .= ' false ';
                    } elseif ($rule->rules->cl_field !== 'user_roles') {
                        $eval_str .= ' true ';
                    } elseif ($this->eval_relation($rule->rules)) {
                        $eval_str .= ' true ';
                    } else {
                        $eval_str .= ' false ';
                    }
//                    if ($rule->cl_field=='user_roles' &&  $this->eval_relation( $rule->rules ) ) {
//                        $eval_str .= ' true ';
//                    } else {
//                        $eval_str .= ' false ';
//                    }
                    $eval_str .= ') ' . (($rule->operator !== false) ? $rule->operator : '') . ' ';
                }

                if (count($relation->rules) > 0) {
                    preg_match_all('/\(.*\)/', $eval_str, $match);
                    $eval_str = $match[0][0] . ' ';
                }

                $eval_str .= ') ' . (($relation->operator !== false) ? $relation->operator : '') . ' ';
            }
        }
        if (count($relations) > 0) {
            preg_match_all('/\(.*\)/', $eval_str, $match);
            $eval_str = $match[0][0] . ' ';
        }

        $eval_str = str_replace(['and', 'or'], ['&&', '||'], $eval_str);

        //        $clStatus = false;
//        if ($result === true) {
//            /** In PHP end 'disable' treats as hidden */
//            if ($clRule === 'show') {
//                $clStatus = 'visible';
//            } else {
//                $clStatus = 'hidden';
//            }
//        } else {
//            if ($clRule === 'show') {
//                $clStatus = 'hidden';
//            } else {
//                $clStatus = 'visible';
//            }
//        }
        if ($hasUserRole) {
            $clStatus = eval('return ' . $eval_str . ';');
            if ($cl_rule == 'show' && $clStatus == false) {
                return true;
            } elseif ($cl_rule == 'hide' && $clStatus == true) {
                return true;
            }
        }

        return false;
    }

    public function getFieldValueForCL($dataField, $cl_relation = false)
    {
        if ($dataField['value'] == false) {
            return [];
        }
        $val = [];

        if (in_array($dataField['type'], [
            'text',
            'color',
            'email',
            'url',
            'hidden',
            'textarea'
        ])
        ) {
            $val[] = strtolower($dataField['value']);
        } elseif (in_array($dataField['type'], ['file'])) {
            $val = array_map(function ($file) {
                return strtolower($file);
            }, $dataField['value']);
        } elseif (in_array($dataField['type'], ['number'])) {
            $val[] = (float)$dataField['value'];
        } elseif (in_array($dataField['type'], [
                'checkbox-group',
                'radio-group',
                'select',
                'image-group',
                'color-group',
                'productGroup',
            ]) && $dataField['value'] !== false) {
            $_values = $dataField['value'];
            if (in_array($cl_relation, [
                'contains',
                'not_contains',
                'starts_with',
                'ends_with',
            ])) {
                array_walk($_values, function (&$a, $b) {
                    strtolower(str_replace('WCPAOTH ', '', $a));
                }); // using this array_walk method to preserve the keys
                $val = $_values;
            } else {
                foreach ($_values as $l => $v) {
                    if (substr($v, 0, 8) === "WCPAOTH ") {
                        $val[] = 'other';
                    } else {
                        $val[] = strtolower($v);
                    }
                }
            }
        } elseif (in_array($dataField['type'], ['date', 'datetime-local'])) {
            if ($dataField['value'] !== false) {
                if (in_array($cl_relation, [
                    'contains',
                    'not_contains',
                    'starts_with',
                    'ends_with',
                ])) {
                    $val[] = strtolower($dataField['value']);
                } else {
//					  $dateTemp = date_create_from_format('Y-m-d', $dataField['value']);
                    $dateTemp = getUNIDate($dataField['value']);
                    if ($dateTemp !== false) {
                        if ($dataField['type'] == 'date') {
                            $dateTemp->setTime(0, 0, 0);
                        }
                        $val[] = $dateTemp->getTimestamp();
                    }
                }
            }
        } elseif (in_array($dataField['type'], ['time'])) {
            if ($dataField['value'] !== false) {
                if (in_array($cl_relation, [
                    'contains',
                    'not_contains',
                    'starts_with',
                    'ends_with',
                ])) {
                    $val[] = strtolower($dataField['value']);
                } else {
                    $val[] = strtotime($dataField['value']);
                    //TODO to test
                }
            }
        }

        return $val;
    }

    public function getRelValueForCL($type, $rules)
    {
        $rel_val = false;
        if (!isset($rules->cl_val)) {
            return $rel_val;
        }
        if (in_array($type, ['attribute', 'custom_attribute', 'custom_field', 'product_skus'])) {
            $rel_val = $rules->cl_val;
            if ($type == 'custom_attribute') {
                $rel_val = strtolower($rel_val);
            }
        } elseif ($type == 'quantity' || $type == 'stock_quantity') {
            $rel_val = (float)($rules->cl_val);
        } elseif ($type == 'product_ids') {
            $rel_val = ($rules->cl_val);
            $rel_val = preg_split('/[\ \n\,]+/', $rel_val);
            $rel_val = array_map('intval', $rel_val);
        } elseif ($type == 'datetime-local' || $type == 'date' || $type == 'time') {
            if (in_array($rules->cl_relation, ['contains', 'not_contains', 'starts_with', 'ends_with'])) {
                $rel_val = strtolower($rules->cl_val);
            } else {
                $rel_val = strtotime($rules->cl_val);
            }
        } elseif ($type == 'number') {
            $rel_val = (float)($rules->cl_val);
        } else {
            $rel_val = strtolower($rules->cl_val);
        }

        return $rel_val;
    }

    public function getExtraValueForCL($cl_field, $cl_relation)
    {
        $fieldValues = [];
        switch ($cl_field) {
            case 'quantity':
//                if (isset($_REQUEST['quantity'])) {
//                    $fieldValues[] = (int) $_REQUEST['quantity'];
//                }
                $fieldValues[] = $this->quantity;
                break;
            case 'stock_status':
                $fieldValues[] = $this->product->get_stock_status('edit');
                break;
            case 'product_ids':
                $fieldValues[] = $this->product->get_id();
                break;
            case 'product_skus':
                $fieldValues[] = $this->product->get_sku('edit');
                break;
            case 'stock_quantity':
                $fieldValues[] = $this->product->get_stock_quantity('edit');
                break;
            case 'custom_field':
                $cField = Config::getWcpaCustomField($cl_relation, $this->product->get_id());

                if ($cField !== false) {
                    $fieldValues[] = $cField;
                }
                break;
            case 'attribute':
                $name = 'attribute_' . $cl_relation;
                if (isset($_REQUEST[$name])) {
                    $fieldValues[] = $_REQUEST[$name];
                }

                $product_attributes = get_pro_attr_list($this->product);
                if (isset($product_attributes[$rules->cl_relation])) {
                    $fieldValues = $product_attributes[$cl_relation]['value'];
                }
                break;
            case 'custom_attribute':
                $name = 'attribute_' . $cl_relation;
                $encoded = strtolower(urlencode($name));  // in case of unicode variations
                if (isset($_REQUEST[$name])) {
                    $fieldValues[] = strtolower($_REQUEST[$name]);
                } else if (isset($_REQUEST[$encoded])) {
                    $fieldValues[] = strtolower($_REQUEST[$encoded]);
                }

                if (!$this->product->is_type('variable')) {
                    $product_attributes = get_pro_attr_list($this->product);
                    if (isset($product_attributes[$rules->cl_relation])) {
                        $fieldValues = $product_attributes[$cl_relation]['value'];
                    }
                }

                break;
        }

        return $fieldValues;
    }
}
