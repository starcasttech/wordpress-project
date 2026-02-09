<?php


namespace Acowebs\WCPA;


use DateTime;
use DateTimeZone;
use Throwable;

class Price
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
    public function processPriceDependencies(&$dependencyFields, &$formData)
    {

        $count = count($dependencyFields);
        foreach ($dependencyFields as $k => $index) {
            $dField = &$formData[$index[0]]['fields'][$index[1]][$index[2]];
            $field = $this->fields->{$index[0]}->fields[$index[1]][$index[2]];
            $this->form_data = $formData;
            $status = $this->setFieldPrice($dField, $field);
            //TODO need to check

//            $this->fields->{$index[0]}->fields[$index[1]][$index[2]]->price = $calcPrice;
            if ($status === true) {
//                $dependencyFields[] = $index;
                unset($dependencyFields[$k]);
            }
        }

        if (!empty($dependencyFields) && $count > count($dependencyFields)) { // if the count not decreasing on each processing, it can lead to infinite loop, so avoiding infinit check
            $this->processPriceDependencies($dependencyFields, $formData);
        }
    }

    /**
     * set field price, return 'dependency' if there is dependency
     *
     * @param $dField
     * @param $field
     *
     * @return array|false|int|mixed|string|string[]|void
     */
    public function setFieldPrice(&$dField, $field)
    {
        if (!isset($field->enablePrice) || !$field->enablePrice) {
            return;
        }

        if (!isset($field->price)) {
            $field->price = 0;
        }
        $response = true;

        $dField['is_fee'] = isset($field->use_as_fee) && $field->use_as_fee;
        $dField['is_show_price'] = isset($field->is_show_price) && $field->is_show_price;

        if (is_array($dField['value']) && isset($field->values) &&
            is_array($field->values)) {
            /** Process fields with options, each options price will be calculated separately.
             * , dont process other fields which have array values here,
             */


            $dField['priceFormula'] = [];
            foreach ($dField['value'] as $k => $val) {
                if (isset($field->priceOptions) && $field->priceOptions === 'different_for_all' && isset($val['price'])) {
                    $price = $val['price']; // this price has set in dField while reading form

                } else {
                    $price = $field->price;
                }
                /** storing price as array, and will calculating total for cart */
                $priceCalculated = $this->calculate_price($price, $dField, $field, $k);
                if ($dField['price'] === false) {
                    $dField['price'] = [];
                    $dField['rawPrice'] = [];
                }
                if ($priceCalculated !== 'dependency') {
                    $optionQuantity = 1;
                    if (isset($val['quantity']) && $val['quantity']) {
                        $optionQuantity = floatval($val['quantity']);
                    }
//                    if (isset($priceCalculated->hasQuantity)) {
//                        $dField['price'][$k] = $priceCalculated->price * $optionQuantity;
//                        $dField['rawPrice'][$k] = $priceCalculated->rawPrice * $optionQuantity;
//                        $dField['priceFormula'][$k] = $priceCalculated->hasQuantity;
//                    } else {
                    $dField['price'][$k] = $priceCalculated->price * $optionQuantity;
                    $dField['rawPrice'][$k] = $priceCalculated->rawPrice * $optionQuantity;
//                    }
                } else {
                    $dField['price'][$k] = false;
                    $dField['rawPrice'][$k] = false;
                }

                if ($response === true && $priceCalculated === 'dependency') {
                    $response = 'dependency';
                }
            }


            return $response;
        }
        $price = $field->price;
        $priceCalculated = $this->calculate_price($price, $dField, $field);
        if ($priceCalculated !== 'dependency') {
            $optionQuantity = 1;
            if (isset($dField['quantity']) && $dField['quantity']) {
                $optionQuantity = floatval($dField['quantity']);
            }
//            if (isset($priceCalculated->hasQuantity)) {
//                $dField['price'] = $priceCalculated->price * $optionQuantity;
//                $dField['rawPrice'] = $priceCalculated->rawPrice * $optionQuantity;
//                $dField['priceFormula'] = $priceCalculated->hasQuantity;
//            } else {
            $dField['price'] = $priceCalculated->price * $optionQuantity;
            $dField['rawPrice'] = $priceCalculated->rawPrice * $optionQuantity;
//            }
        } else {
            $dField['price'] = false;
            $dField['rawPrice'] = false;
        }


        if ($response === true && $priceCalculated === 'dependency') {
            $response = 'dependency';
        }

        return $response;
    }

    public function calculate_price($price, $dField, $field, $index = false)
    {
        if (is_string($price)) {
            $price = trim($price);
        }
        $pricingType = isset($field->pricingType)?$field->pricingType:'fixed';
        $doConvertCurrency = true;

        if (isset($field->disableMC) && $field->disableMC) {
            $doConvertCurrency = false;
        }

        $response = (object)['price' => 0, 'rawPrice' => 0];
        $fieldPrice = 0;
        if (isEmpty($dField['value']) && $field->type !== 'content') {
            return $response; //TODO need to test
        }
        if ($pricingType === 'custom') {
            $fieldPrice = $this->process_custom_formula($price, $dField, $field, $index);
            if ($fieldPrice === 'dependency') {
                return $fieldPrice;
            }
        } else {
            if (!$this->isPriceNumeric($price)) {
                return $response;

            } else {
                $price = $this->priceToFloat($price);
            }
            if ($index !== false) {
                $value = $dField['value'][$index];
                if (is_array($value) && array_key_exists('value', $value)) {
                    $value = $value['value'];
                }
            } else {
                $value = $dField['value'];
                if (is_array($value)) {
                    /** make the value string for price calculation.
                     *For custom formula, it  has to pass the value as it is
                     */
                    $value = getValueFromArrayValues($value);
                }
            }
            switch ($pricingType) {
                case 'per_car':
                    if ($value !== false) {
                        $value_filtered = $value;
                        if (isset($field->excl_chars_frm_length) && $field->excl_chars_frm_length !== '') {
                            $exclude_chars = $field->excl_chars_frm_length;
                            if (isset($field->excl_chars_frm_length_is_regex) && $field->excl_chars_frm_length_is_regex) {
                                if ($exclude_chars[0] != '/') {
                                    $exclude_chars = '/' . $exclude_chars . '/i';
                                } else {
                                    $exclude_chars = preg_replace('/\/g/', '/', $exclude_chars);
                                }
                                try {
                                    $value_filtered = preg_replace($exclude_chars, '', $value_filtered);
                                } catch (Exception $e) {
                                    $value_filtered = $value;
                                }
                            } else {
                                $exclude_chars = str_replace('\s', ' ', $exclude_chars);
                                $value_filtered = str_replace(str_split($exclude_chars), '', $value_filtered);
                            }
                        }
                        $fieldPrice = mb_strlen($value_filtered) * $price;
                    }
                    break;
                case 'fixed':
                    if ($value || $value == '0' || $value === 0 || $field->type == 'content') {
                        $fieldPrice = $price * 1;
                    } else {
                        $fieldPrice = 0;
                    }

                    break;
                case 'multiply':
                    if ($value) {
                        $fieldPrice = ($this->isPriceNumeric($value) ? $this->priceToFloat($value) : 1) * $price; //added + sign to convert to int/float value
                    } else {
                        $fieldPrice = 0;
                    }
                    if ($fieldPrice < 0) {
                        $fieldPrice = 0; // not allow to set -ve, use custom formula for setting negative.
                        //Why? to protect users trying unwanted negative values
                    }

                    break;

                case 'percentage':
                    if ($value) {
                        $fieldPrice = ($price * Discounts::getProductPrice($this->product)) / 100;
                    } else {
                        $fieldPrice = 0;
                    }
                    $doConvertCurrency = false;
                    break;
            }
        }


//        if (isset($fieldPrice['hasQuantity'])) {
//            $response->hasQuantity = $fieldPrice['hasQuantity'];
//            $response->rawPrice = $fieldPrice['price'];
//        } else {
        $response->rawPrice = $fieldPrice;
//        }

        if ($doConvertCurrency) {
//            if (isset($fieldPrice['hasQuantity'])) {
//                $response->price = Currency::convertCurrency($fieldPrice['price'], true);
//            } else {
            $response->price = Currency::convertCurrency($fieldPrice, true);
//            }
        } else {
            $response->price = $fieldPrice;
        }

        return $response;
//        return apply_filters('wcml_raw_price_amount', $fieldPrice);//TODO need to check this filter works here as custom formula can return array instead price value if there is quantity relation
    }

    public function process_custom_formula(
        $formula,
        $dField,
        $field = false,
        $index = false,
        $isLabel = false
    )
    {
        $elementId = ($field ? $field->elementId : $dField['elementId']);
        $fieldSelector = "field.{$elementId}";

        /** replace custom fields */
        if (preg_match_all('/\{(\s)*?wcpa_pcf_([^}]*)}/', $formula, $matches)) {
            foreach ($matches[2] as $k => $match) {
                $pro_id = $this->product->get_parent_id();
                if ($pro_id == 0) {
                    $pro_id = $this->product->get_id();
                }
                $cf_value = Config::getWcpaCustomField(trim($match), $pro_id);

                if ($cf_value == '' || $cf_value == false) {
                    $cf_value = 0;
                }
                $formula = str_replace($matches[0][$k], $cf_value, $formula);
            }
        }
        $today = [
            'unixDays' => floor(current_time('timestamp') / (60 * 60 * 24)),
            'unixSeconds' => current_time('timestamp'),
        ];

        $utcDateTime = new DateTime('now', new DateTimeZone('UTC'));

        $now = [
            'year' => $utcDateTime->format('Y'),
            'month' => $utcDateTime->format('n'),
            'date' => $utcDateTime->format('j'),
            'day' => intval($utcDateTime->format('w')) + 1,
            'hour' => $utcDateTime->format('G'),
            'minute' => intval($utcDateTime->format('i')),

        ];
        $product_price = $this->product ? Discounts::getProductPrice($this->product) : 0;// when calling custom_formula from cart, product might not be set
        $product_price_regular = $this->product ? Discounts::getProductPrice($this->product, true) : 0;// when calling custom_formula from cart, product might not be set
        $product_name = $this->product->get_title();

        $price_excluding_tax = wc_get_price_excluding_tax( $this->product, array( 'qty' => 1, 'price' => $this->product->get_price()  ) );

        $mc_unit = Currency::getConUnit(true);
        /** replace all 'this' with fieldId selector */
        $product_weight = $this->product ? ($this->product)->get_weight() : 0;
        $replaces = [
            '{product_weight}' => $product_weight,
            '{product_price}' => $product_price/$mc_unit,
            '{product_name}' => $product_name,
            '{product_price_regular}' => $product_price_regular,
            '{product_price_excluding_tax}' => $price_excluding_tax,
            '{today.unixDays}' => $today['unixDays'],
            '{today.unixSeconds}' => $today['unixSeconds'],

            '{now.year}' => $now['year'],
            '{now.month}' => $now['month'],
            '{now.date}' => $now['date'],
            '{now.day}' => $now['day'],
            '{now.hour}' => $now['hour'],
            '{now.minute}' => $now['minute'],

            '{this.quantity}' => "{{$fieldSelector}.quantity}",
            '{this.value}' => "{{$fieldSelector}.value}",
            '{value}' => "{{$fieldSelector}.value}",
            '{this.value.length}' => "{{$fieldSelector}.value.length}",
            '{value.length}' => "{{$fieldSelector}.value.length}",
            '{this.count}' => "{{$fieldSelector}.count}",
            '{count}' => "{{$fieldSelector}.count}",
            '{unixDays}' => "{{$fieldSelector}.unixDays}",
            '{this.unixDays}' => "{{$fieldSelector}.unixDays}",
            '{unixSeconds}' => "{{$fieldSelector}.unixSeconds}",
            '{this.unixSeconds}' => "{{$fieldSelector}.unixSeconds}",
            '{inMinutes}' => "{{$fieldSelector}.inMinutes}",
            '{this.inMinutes}' => "{{$fieldSelector}.inMinutes}",
            '{timestamp}' => "{{$fieldSelector}.timestamp}",
            '{daysCount}' => "{{$fieldSelector}.daysCount}",
            '{isClone}' => "{{$fieldSelector}.isClone}",
            '{this.isClone}' => "{{$fieldSelector}.isClone}",
            '{this.repeaterCount}' => "{{$fieldSelector}.repeaterCount}",
            '{repeaterCount}' => "{{$fieldSelector}.repeaterCount}",
            '{price}' => "{{$fieldSelector}.price}",
            '{weight}' => "{{$fieldSelector}.weight}",
            'Math.round' => "round",
            'Math.ceil' => "ceil",
            'Math.floor' => "floor",
            'Math.abs' => "abs",
            'Math.sin' => "sin",
            'Math.cos' => "cos",
            'Math.tan' => "tan",
            'Math.min' => "min",
            'Math.max' => "max",
            'Math.log' => "log",
            'Math.pow' => "pow",
            'Math.exp' => "exp",
            // why need to refer price of same field? It will be valid for setting formula for other label/value
        ];
        if ($index !== false) {
            /** for options field, this.value , value will be the value of the option field */
            $option = $dField['value'][$index];
            $optionValue = $option['value'];
            $str_length = mb_strlen($optionValue);


            if (is_numeric($optionValue) || $isLabel) {
                $optionValue = $optionValue;
            } else {
                $optionValue = '"' . $optionValue . '"';
            }

            $replaces['{this.value}'] = $optionValue;
            $replaces['{value}'] = $optionValue;

            if (isset($field->enable_quantity) && $field->enable_quantity) {
                $replaces['{this.quantity}'] = $option['quantity'];
            }
            $replaces['{this.value.length}'] = $str_length;
            $replaces['{value.length}'] = $str_length;
        }
        $formula = str_replace(array_keys($replaces), array_values($replaces), $formula);


        /** start finding fields */

        if (preg_match_all('/\{(\s)*?field\.([^}]*)}/', $formula, $matches)) {
            foreach ($matches[2] as $k => $match) {
                $ele = explode('.', $match);
                /** replace stores the data to be replaced in formula
                 * for empty or not set fields, set replace as zero , but for  empty 'value' set null
                 *
                 * @var $replace any
                 */
                $replace = 0;
                if (is_array($ele) && count($ele) > 1) {
                    /** Processing following tags    'value',
                     * 'price',
                     * 'count',
                     * 'selected',
                     * 'unixDays',
                     * 'unixSeconds',
                     * 'timestamp',
                     * 'daysCount', */
                    $fieldElementId = $ele[0];
                    $formulaTag = strtolower($ele[1]);
                    $subDField = findFieldById($this->form_data, $fieldElementId);

                    if ($subDField === false || (isset($subDField['clStatus']) && $subDField['clStatus'] === 'hidden')) {
                        //  the element is not available, need to set as zero
                        $replace = null;
//						$formula = str_replace( $matches[0][ $k ], 0, $formula );
                    } else {
//						$replace = 0;
                        if ($formulaTag == 'price' || $formulaTag == 'priceConverted') {
                            if (!isset($subDField['price'])) {
                                //set with zero
//								$formula = str_replace( $matches[0][ $k ], 0, $formula );
                                $replace = 0;
                            } elseif ($subDField['price'] === false) { // no pricing enabled , or price is not processed yet
                                if (isset($subDField['form_data']->enablePrice) && $subDField['form_data']->enablePrice) {
                                    return 'dependency'; // there is dependency with price
                                    //TODO need to check this action
                                } else {
                                    $formula = str_replace($matches[0][$k], 0, $formula);
                                }
                            } else {
                                if (is_array($subDField['price'])) {
                                    if (in_array(false, $subDField['price'], true)) {
                                        return 'dependency';
                                    }
                                    $replace = ($formulaTag == 'price' ? array_sum($subDField['rawPrice']) : array_sum($subDField['price']));
                                } else {
                                    $replace = ($formulaTag == 'price' ? $subDField['rawPrice'] : $subDField['price']);
                                }
                            }
                        } elseif ($formulaTag == 'value') {
                            if (!isset($subDField['value']) || $subDField['value'] === false || $subDField['value'] === null || $subDField['value'] === '') {
                                $replace = null;
                                if (isset($ele[2])) {
                                    if ($ele[2] == 'length') {
                                        $replace = 0;
                                    }
                                }
                            } else {
                                if (is_array($subDField['value'])) {
                                    $replace = getValueFromArrayValues($subDField['value']);
                                } else {
                                    $replace = $subDField['value'];
                                }


                                if ($replace !== '' && ($subDField['type'] == 'date' || $subDField['type'] == 'datetime-local') && isset($subDField['dateFormat'])) {
                                    $replace = date($subDField['dateFormat'], strtotime($replace));//TODO need to test

                                }

                                if (isset($ele[2])) {
                                    if ($ele[2] == 'length') {
                                        $replace = mb_strlen($replace);
                                    }
                                }
                            }
                        } elseif ($formulaTag == 'quantity') {
                            if (!isset($subDField['quantity']) || $subDField['quantity'] === false || $subDField['quantity'] === null || $subDField['quantity'] === '') {
                                $replace = null;
                            } else {
                                if (is_array($subDField['quantity'])) {
                                    /** sum selected field quantities */
                                    $replace = array_sum(array_column($subDField['value'], 'value'));
                                } else {
                                    if (isset($subDField['value']) && $subDField['value'] != '') {
                                        $replace = $subDField['quantity'];
                                    } else {
                                        $replace = 0;
                                    }
                                }
                            }
                        } elseif ($formulaTag == 'count' || $formulaTag == 'selected') {
                            /**
                             *  1. Selected options count
                             * 2. Selected days count for multiple Date Picker
                             * 3. Uploaded Files count
                             */
                            if (is_array($subDField['value'])) {
                                if (isset($subDField['value']['value']) || isset($subDField['value']['start'])) {
                                    $replace = 1;
                                } else {
                                    $replace = count($subDField['value']);
                                }
                            } else {
                                $replace = empty($subDField['value']) ? 0 : 1;
                            }
//							$formula = str_replace( $matches[0][ $k ], $_count, $formula );
                        } elseif ($formulaTag == 'unixdays' || $formulaTag == 'unixseconds' || $formulaTag == 'timestamp') {
                            if (!isset($subDField['value']) || $subDField['value'] === false || $subDField['value'] === null || $subDField['value'] === '') {
                                $replace = 0;
//								$formula = str_replace( $matches[0][ $k ], 0, $formula );
                            } elseif (isset($subDField['value']) && $subDField['value'] !== false) {
                                if (is_array($subDField['value'])) {
                                    $replace = getValueFromArrayValues($subDField['value']);
                                } else {
                                    $replace = $subDField['value'];
                                }

//                                    $unixTimestamp = strtotime($sub_data['value']);
                                $date = getUNIDate($replace,$subDField['type']);
                                if ($date == false) {
                                    $date = date_create_from_format(__(get_option('date_format'), 'woo-custom-product-addons-pro'),
                                        $replace);
                                }

                                if ($date !== false) {
                                    $unixTimestamp = $date->getTimestamp();
                                    $days = floor($unixTimestamp / (60 * 60 * 24));
                                    $seconds = $unixTimestamp;
                                } else {
                                    $days = 0;
                                    $seconds = 0;
                                }
                                $replace = ($formulaTag == 'unixdays') ? $days : $seconds;
//								$formula = str_replace( $matches[0][ $k ], $replace, $formula );


                            }
                        } elseif ($formulaTag == 'dayscount') {
                            if ($subDField['type'] == 'date' || $subDField['type'] == 'datetime-local') {
                                if (isset($subDField['value']['start']) && $subDField['value']['end']) {
                                    $start = getUNIDate($subDField['value']['start'],$subDField['type']);
                                    $end = getUNIDate($subDField['value']['end'],$subDField['type']);
                                    $replace = $end->diff($start)->format("%a") + 1;
                                } elseif (is_array($subDField['value'])) {
                                    $replace = count($subDField['value']);
                                }
                            }

                        } else if ($formulaTag == 'selectedoption') {
                            $index = 0;
                            $prop = 'value';
                            $replace = null;
// TODO as of now not giving support in php

//                            if (isset($ele[2])) {
//                                if (is_numeric($ele[2])) {
//                                    $index = $ele[2];
//                                } else {
//                                    $prop = $ele[2];
//                                }
//                            }
//                            $selField = null;
//                            if (isset($subDField['value']) &&
//                                is_array($subDField['value']) &&
//                                isset($subDField['value'][$index])
//                            ) {
//                                $selField = $subDField['selectedOptions'][$index];
//                            }
//                            if ($selField) {
//                                if (isset($ele[3])) {
//                                    $prop = $ele[3];
//                                }
//                                $replace = $selField[$prop];
//                                if (isset($selField[$prop])) {
//                                    $replace = $selField[$prop];
//                                }
//                            }
                        } else if ($formulaTag == 'address') {
                            $replace = null;
                            if ($subDField['type'] == 'placeselector' && !isEmpty($subDField['value'])) {
                                $prop = 'value';

                                if (isset($ele[2])) {
                                    $prop = $ele[2];
                                }

                                if ($prop === false) {
                                    if (is_array($subDField['value'])) {

                                        $replace = getValueFromArrayValues($subDField['value']);
                                    } else {
                                        $replace = $subDField['value'];
                                    }
                                } else {
                                    if (isset($subDField['value']['split']) && isset($subDField['value']['split'][$prop])) {
                                        $replace = $subDField['value']['split'][$prop];
                                    }
                                }

                            }
                        } else if ($formulaTag == 'cords') {
                            $replace = null;
                            if ($subDField['type'] == 'placeselector' && !isEmpty($subDField['value'])) {
                                $prop = false;
                                if (isset($ele[2])) {
                                    $prop = $ele[2];
                                }
                                if ($prop !== false) {
                                    if (isset($subDField['value']['cords']) && isset($subDField['value']['cords'][$prop])) {
                                        $replace = '' . $subDField['value']['cords'][$prop];
                                    }
                                }
                            }

                        } else if ($formulaTag == 'repeatercount') {
                            $replace = 1;
                            if (isset($subDField['clonedCount'])) {
                                $replace = $subDField['clonedCount'] + 1;
                            }
                        } else if ($formulaTag == 'isclone') {
                            $replace = 0;
                            if (isset($subDField['form_data']->isClone) && $subDField['form_data']->isClone) {
                                $replace = 1;
                            }
                        } else if ($formulaTag == 'weight') {
                            if (!isset($subDField['weight'])) {
                                //set with zero
//								$formula = str_replace( $matches[0][ $k ], 0, $formula );
                                $replace = 0;
                            } elseif ($subDField['weight'] === false) { // no weight enabled , or weight is not processed yet
                                if (isset($subDField['form_data']->enableWeight) && $subDField['form_data']->enableWeight) {
                                    return 'dependency'; // there is dependency with weight
                                    //TODO need to check this action
                                } else {
                                    $formula = str_replace($matches[0][$k], 0, $formula);
                                }
                            } else {
                                if (is_array($subDField['weight'])) {
                                    $replace = array_sum($subDField['weight']);
                                } else {
                                    $replace = $subDField['weight'];
                                }
                            }
                        } else if ($formulaTag == 'inminutes') {
                            if (!isset($subDField['value']) || $subDField['value'] === false || $subDField['value'] === null || $subDField['value'] === '') {
                                $replace = 0;
                            } elseif (isset($subDField['value']) && $subDField['value'] !== false) {
                                if (is_array($subDField['value'])) {
                                    $replace = getValueFromArrayValues($subDField['value']);
                                } else {
                                    $replace = $subDField['value'];
                                }

                                $date = getUNIDate('2020-01-01 ' . $replace, 'datetime');
                                if ($date !== false) {
                                    $date2 = getUNIDate('2020-01-01 00:00', 'datetime');
                                    $diff = $date->getTimestamp() - $date2->getTimestamp();
                                    $replace = floor($diff / (60));
                                } else {
                                    $replace = 0;
                                }

//								$formula = str_replace( $matches[0][ $k ], $replace, $formula );


                            }
                        }
                    }
                }
                if ($isLabel) {
                    //TODO need to check how NULL value works
                    $formula = str_replace($matches[0][$k], $replace, $formula);
                } else {
                    $replace = $replace == null ? 0 : $replace;
                    if (is_numeric($replace)) {
                        $formula = str_replace($matches[0][$k], $replace, $formula);
                    } else {
                        $formula = str_replace($matches[0][$k], "'" . $replace . "'", $formula);
                    }
                }
            }

        }
        if (preg_match_all('/\{(\s)*?section\.([^}]*)}/', $formula, $secMatches)) {
            foreach ($secMatches[2] as $k => $match) {
                $ele = explode('.', $match);
                /** replace stores the data to be replaced in formula
                 * for empty or not set fields, set replace as zero , but for  empty 'value' set null
                 *
                 * @var $replace any
                 */
                $replace = 0;
                if (is_array($ele) && count($ele) > 1) {

                    $fieldSecKey = $ele[0];
                    $formulaTag = strtolower($ele[1]);
                    $subSection = isset($this->form_data[$fieldSecKey]) ? $this->form_data[$fieldSecKey] : false;
                    if ($subSection !== false) {
                        $subDSection = $subSection['extra'];
                    }
                    if ($subSection === false || (isset($subDSection->clStatus) && $subDSection->clStatus === 'hidden')) {
                        $replace = null;
                    } else {
                        //  TODO price dependency case
                        if ($formulaTag == 'price' || $formulaTag == 'priceConverted') {
                            if (!isset($subSection['price'])) {

                                $replace = 0;
                            } elseif ($subSection['price'] === false) {
                                $replace = 0;
                            } else {
                                $replace = ($formulaTag == 'price' ? $subSection['rawPrice'] : $subSection['price']);
                            }
                        } else if ($formulaTag == 'repeatercount') {
                            $replace = 1;
                            if (isset($subDSection->clonedCount)) {
                                $replace = $subDSection->clonedCount + 1;
                            }
                        }
                    }
                }
                if ($isLabel) {
                    //TODO need to check how NULL value works
                    $formula = str_replace($secMatches[0][$k], $replace, $formula);
                } else {
                    $replace = $replace == null ? 0 : $replace;
                    if (is_numeric($replace)) {
                        $formula = str_replace($secMatches[0][$k], $replace, $formula);
                    } else {
                        $formula = str_replace($secMatches[0][$k], "'" . $replace . "'", $formula);
                    }
                }
            }


        }

        try {
            if ($isLabel) {
                return $formula;
            }

            if (strpos($formula, '{quantity}') !== false) {

                $formula = str_replace(['{quantity}'], [$this->quantity], $formula);
            }

            $elem_price = eval('return ' . $formula . ';');
//            if ($hasQuantity) {
//                $elem_price = [
//                    'hasQuantity' => $hasQuantity,
//                    'price' => $elem_price
//                ];
//            }
//			if ( $return_formula ) { // this formula returns for calculate price when quantity updated
//				return $formula; //str_replace(['{this.value}', '{value}', '{product_price}', '{this.value.length}', '{value.length}', '{this.count}'], [$value, $value, $product_price, $str_length, $str_length, $count], $price);
//
//			}
        } catch (Throwable $t) {
            $elem_price = 0;
        }
        if ($elem_price == null || $elem_price == false) {
            $elem_price = 0;
        }

        return $elem_price;
    }

    public function isPriceNumeric($price)
    {
        return isPriceNumeric($price);
    }

    function priceToFloat($price)
    {

        return priceToFloat($price);
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
//                    $hasQuantity = false;
                    if ($replace === '' || $replace === '0') {
                        $formula = str_replace($matches[0][$k], '', $formula);
                    } else {
                        if (strpos($replace, '{quantity}') !== false) {

                            $replace = str_replace(['{quantity}'], [$this->quantity], $replace);
                        }
                        try {
                            $replace = eval('return ' . $replace . ';');
                            if (is_numeric($replace) && $replace % 1 != 0) {
                                $replace = number_format($replace, wc_get_price_decimals(),
                                    wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
                            }
                            $formula = str_replace($matches[0][$k], $replace, $formula);
//                            if (!$hasQuantity) {
//                                $quantity_depend_formula = str_replace($matches[0][$k], $replace,
//                                    $quantity_depend_formula);
//                            }
                        } catch (Throwable $t) {
                            $formula = str_replace($matches[0][$k], $replace, $formula);
                        }
//
                    }
                }
            }
//            if (strpos($quantity_depend_formula, '{quantity}') !== false) {
//                return [
//                    'formula' => $quantity_depend_formula,
//                    'label' => $formula
//                ];
//            }
        }

        return $formula;
    }

    public function getValueFromArrayValues($val)
    {
        if (is_array($val)) {
            if (isset($val['value'])) {
                /** place selector */
                return $val['value'];
            } elseif (isset($val['start'])) {
                /* date picker range */
                return $val['start'];
            } elseif (count($val)) {
                /**
                 * For array of values, sum the values if the values are numeric
                 * Otherwise return the first value
                 */

                $p_temp = array_values($val)[0];
                if (count($val) == 1 || isset($p_temp['file_name'])) {
                    $p_temp = is_array($p_temp) ? (isset($p_temp['file_name']) ? $p_temp['file_name'] : $p_temp['value']) : $p_temp; /* $p_temp['name'] => for files */

                    return $p_temp;
                } else {
                    $_i = -1;
                    $valueSum = 0.0;
                    foreach ($val as $_p) {
                        $_i++;
                        if (is_array($_p)) {
                            if (is_numeric($_p['value'])) {
                                $valueSum += (float)$_p['value'];
                            } elseif ($_i == 0) {
                                $valueSum = $_p['value'];
                                break;
                            }
                        } else {
                            if (is_numeric($_p)) {
                                $valueSum += (float)$_p;
                            } elseif ($_i == 0) {
                                $valueSum = $_p;
                                break;
                            }
                        }
                    }

                    return $valueSum;
                }
            }

            return false;
        }

        return $val;
    }


}