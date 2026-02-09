<?php


namespace Acowebs\WCPA;


use Throwable;

class Weight
{
    public $form_data = false;
    public $fields = false;
    public $product = false;
    private $quantity;

    public function __construct($form_data, $fields, $product, $quantity)
    {
        $this->form_data = $form_data;
        $this->fields = $fields;
        $this->product = $product;
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
     * Process the price field which has dependency on price which are not processed while exicuting a field price
     * This method is different in frontend (js). Where it check it using the dependency ids already set with field.
     * So we can reduce repeated iteration of whole form each time a field change
     *
     * @param $dependencyFields
     */
    public function processWeightDependencies(&$dependencyFields)
    {
        $count = count($dependencyFields);
        foreach ($dependencyFields as $k => $index) {
            $dField = $this->form_data[$index[0]]['fields'][$index[1]][$index[2]];
            $field = $this->fields->{$index[0]}->fields[$index[1]][$index[2]];

            $status = $this->setFieldWeight($dField, $field);

            // $this->fields->{$index[0]}->fields[$index[1]][$index[2]]->weight = $calcWeight;
            if ($status === true) {
                unset($dependencyFields[$k]);
            }
        }

        if (!empty($dependencyFields) && $count > count($dependencyFields)) { // if the count not decreasing on each processing, it can lead to infinite loop, so avoiding infinit check
            $this->processWeightDependencies($dependencyFields);
        }
    }

    /**
     *
     * @param $formula
     * @param $dField
     * @param $field
     *
     * @return string|Object  object if it has quantity dependency , other wise string
     */
    public function contentFormula($formula, $dField, $field = false)
    {

        if (preg_match('/\#\=(.+?)\=\#/', $formula) === 1) {
            $quantity_depend_formula = $formula;
            if (preg_match_all('/\#\=(.+?)\=\#/', $formula, $matches) >= 1) {
                foreach ($matches[1] as $k => $match) {
                    $replace = $this->process_custom_formula($match, $dField, $field, false, true);
                    // $hasQuantity = false;
                    if ($replace === '' || $replace === '0') {
                        $formula = str_replace($matches[0][$k], '', $formula);
                    } else {
                        if (strpos($replace, '{quantity}') !== false) {
                            // $hasQuantity = true;
                            $replace = str_replace(['{quantity}'], [$this->quantity], $replace);
                        }
                        try {
                            $replace = eval('return ' . $replace . ';');
                            if (is_numeric($replace) && $replace % 1 != 0) {
                                $replace = number_format($replace, wc_get_price_decimals(),
                                    wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
                            }
                            $formula = str_replace($matches[0][$k], $replace, $formula);
                            // if (!$hasQuantity) {
                            //     $quantity_depend_formula = str_replace($matches[0][$k], $replace,
                            //         $quantity_depend_formula);
                            // }
                        } catch (Throwable $t) {
                            $formula = str_replace($matches[0][$k], $replace, $formula);
                        }
//
                    }
                }
            }
            // if (strpos($quantity_depend_formula, '{quantity}') !== false) {
            //     return [
            //         'formula' => $quantity_depend_formula,
            //         'label' => $formula
            //     ];
            // }
        }

        return $formula;
    }

    // public function getValueFromArrayValues($val)
    // {
    //     if (is_array($val)) {
    //         if (isset($val['value'])) {
    //             /** place selector */
    //             return $val['value'];
    //         } elseif (isset($val['start'])) {
    //             /* date picker range */
    //             return $val['start'];
    //         } elseif (count($val)) {
    //             /**
    //              * For array of values, sum the values if the values are numeric
    //              * Otherwise return the first value
    //              */

    //             $p_temp = array_values($val)[0];
    //             if (count($val) == 1 || isset($p_temp['file_name'])) {
    //                 $p_temp = is_array($p_temp) ? (isset($p_temp['file_name']) ? $p_temp['file_name'] : $p_temp['value']) : $p_temp; /* $p_temp['name'] => for files */

    //                 return $p_temp;
    //             } else {
    //                 $_i = -1;
    //                 $valueSum = 0.0;
    //                 foreach ($val as $_p) {
    //                     $_i++;
    //                     if (is_array($_p)) {
    //                         if (is_numeric($_p['value'])) {
    //                             $valueSum += (float)$_p['value'];
    //                         } elseif ($_i == 0) {
    //                             $valueSum = $_p['value'];
    //                             break;
    //                         }
    //                     } else {
    //                         if (is_numeric($_p)) {
    //                             $valueSum += (float)$_p;
    //                         } elseif ($_i == 0) {
    //                             $valueSum = $_p;
    //                             break;
    //                         }
    //                     }
    //                 }

    //                 return $valueSum;
    //             }
    //         }

    //         return false;
    //     }

    //     return $val;
    // }

    /**
     * set field price, return 'dependency' if there is dependency
     *
     * @param $dField
     * @param $field
     *
     * @return array|false|int|mixed|string|string[]|void
     */
    public function setFieldWeight(&$dField, $field)
    {
        if (!isset($field->enableWeight) || !$field->enableWeight) {
            return;
        }

        if (!isset($field->weight)) {
            $field->weight = 0;
        }
        $response = true;

        // $dField['is_fee'] = isset($field->use_as_fee) && $field->use_as_fee;
        // $dField['is_show_price'] = isset($field->is_show_price) && $field->is_show_price;

        if (is_array($dField['value']) && isset($field->values) &&
            is_array($field->values)) {
            /** Process fields with options, each options price will be calculated separately.
             * , dont process other fields which have array values here,
             */


            $dField['weightFormula'] = [];
            foreach ($dField['value'] as $k => $val) {
                if (isset($field->weightOptions) && $field->weightOptions === 'different_for_all' && isset($val['weight'])) {
                    $weight = $val['weight']; // this price has set in dField while reading form

                } else {
                    $weight = $field->weight;
                }
                /** storing price as array, and will calculating total for cart */
                $weightCalculated = $this->calculate_weight($weight, $dField, $field, $k);
                if ($dField['weight'] === false) {
                    $dField['weight'] = [];
                    // $dField['rawPrice'] = [];
                }
                if ($weightCalculated !== 'dependency') {
                    $optionQuantity = 1;
                    if (isset($val['quantity']) && $val['quantity']) {
                        $optionQuantity = floatval($val['quantity']);
                    }
                    // if (isset($weightCalculated->hasQuantity)) {
                    //     $dField['weight'][$k] = $weightCalculated->weight * $optionQuantity;
                    //     // $dField['rawPrice'][$k] = $priceCalculated->rawPrice * $optionQuantity;
                    //     $dField['weightFormula'][$k] = $weightCalculated->hasQuantity;
                    // } else {
                    //     $dField['weight'][$k] = $weightCalculated->weight * $optionQuantity;
                    //     // $dField['rawPrice'][$k] = $priceCalculated->rawPrice * $optionQuantity;
                    // }
                    $dField['weight'][$k] = $weightCalculated->weight * $optionQuantity;

                } else {
                    $dField['weight'][$k] = 0;
                    // $dField['rawPrice'][$k] = 0;
                }

                if ($response === true && $weightCalculated === 'dependency') {
                    $response = 'dependency';
                }
            }


            return $response;
        }
        $weight = $field->weight;
        $weightCalculated = $this->calculate_weight($weight, $dField, $field);
        if ($weightCalculated !== 'dependency') {
            $optionQuantity = 1;
            if (isset($dField['quantity']) && $dField['quantity']) {
                $optionQuantity = floatval($dField['quantity']);
            }
            // if (isset($weightCalculated->hasQuantity)) {
            //     $dField['weight'] = $weightCalculated->weight * $optionQuantity;
            //     // $dField['rawPrice'] = $priceCalculated->rawPrice * $optionQuantity;
            //     $dField['priceFormula'] = $weightCalculated->hasQuantity;
            // } else {
            //     $dField['weight'] = $weightCalculated->weight * $optionQuantity;
            //     // $dField['rawPrice'] = $priceCalculated->rawPrice * $optionQuantity;
            // }
            $dField['weight'] = $weightCalculated->weight * $optionQuantity;
        } else {
            $dField['weight'] = 0;
            // $dField['rawPrice'] = 0;
        }


        if ($response === true && $weightCalculated === 'dependency') {
            $response = 'dependency';
        }

        return $response;
    }

    public function calculate_weight($weight, $dField, $field, $index = false)
    {
        $priceObject = new Price($this->form_data, $this->fields, $this->product, $this->quantity);
        if (is_string($weight)) {
            $weight = trim($weight);
        }
        $weightType = isset($field->weightType)?$field->weightType:'fixed';
        // $doConvertCurrency = true;
        $response = (object)['weight' => 0];
        $fieldWeight = 0;
        if (isEmpty($dField['value'])) {
            return $response; //TODO need to test
        }
        if ($weightType === 'custom') {
            $fieldWeight = $priceObject->process_custom_formula($weight, $dField, $field, $index);
            if($fieldWeight == 'dependency') {
                return $fieldWeight;
            }
        } else {
            if (!is_numeric($weight)) {
                return $response;

            } else {
                $weight = (float)($weight);
            }
            if ($index !== false) {
                $value = $dField['value'][$index];
                if (is_array($value) && array_key_exists('value', $value)) {
                    $value = $value['value'];
                }
            } else {
                $value = $dField['value'];
                if (is_array($value)) {
                    /** make the value string for weight calculation.
                     *For custom formula, it  has to pass the value as it is
                     */
                    $value = $priceObject->getValueFromArrayValues($value);
                }
            }
            switch ($weightType) {

                case 'fixed':
                    if ($value || $value == '0' || $value === 0) {
                        $fieldWeight = $weight * 1;
                    } else {
                        $fieldWeight = 0;
                    }

                    break;
                case 'multiply':
                    if ($value) {
                        $fieldWeight = (is_numeric($value) ? (float)($value) : 1) * $weight; //added + sign to convert to int/float value
                    } else {
                        $fieldWeight = 0;
                    }
                    if ($fieldWeight < 0) {
                        $fieldWeight = 0; // not allow to set -ve, use custom formula for setting negative.
                        //Why? to protect users trying unwanted negative values
                    }

                    break;

                case 'percentage':
                    if ($value) {
                        $fieldWeight = ($weight * (float)($this->product->get_weight())) / 100;
                    } else {
                        $fieldWeight = 0;
                    }
                    break;
            }
        }


        // if (isset($fieldWeight['hasQuantity'])) {
        //     $response->hasQuantity = $fieldWeight['hasQuantity'];
        //     $response->rawPrice = $fieldWeight['weight'];
        // } else {
            // $response->rawPrice = $fieldWeight;
        // }

        // if ($doConvertCurrency) {
        //     if (isset($fieldPrice['hasQuantity'])) {
        //         $response->weight = Currency::convertCurrency($fieldPrice['weight'], true);
        //     } else {
        //         $response->weight = Currency::convertCurrency($fieldPrice, true);
        //     }
        // } else {
        //     $response->weight = $fieldPrice;
        // }
        $response->weight = $fieldWeight;
        return $response;
//        return apply_filters('wcml_raw_weight_amount', $fieldPrice);//TODO need to check this filter works here as custom formula can return array instead price value if there is quantity relation
    }

    // public function isPriceNumeric($price)
    // {
    //     $locale = localeconv();
    //     $decimals = array(wc_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point']);
    //     $price = str_replace($decimals, '.', $price);

    //     return is_numeric($price);
    // }

    // function priceToFloat($price)
    // {
    //     $locale = localeconv();
    //     $decimals = array(wc_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point']);
    //     $price = str_replace($decimals, '.', $price);

    //     return (float)$price;
    // }


}