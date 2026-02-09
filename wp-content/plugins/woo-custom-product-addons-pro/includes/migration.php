<?php

namespace Acowebs\WCPA;

class Migration
{

    private $format = '';

    public function __construct()
    {
        $this->format = get_option('date_format');
    }

    public function fieldMigrationsToV5(&$json_decode, $form_id)
    {
        foreach ($json_decode as $sectionKey => $section) {
            foreach ($section->fields as $rowIndex => $row) {
                foreach ($row as $colIndex => $field) {
                    /**
                     * in new version, we have added a tooltip field along with the description field
                     * So customer can use different messages in as description and tooltip
                     * Earlier desc_type was used to change the des type to tooltip or normal text
                     *
                     * Migrating description to tooltip in version, if the desc_type has set true.
                     * Otherwise keep it same
                     */
                    $field->tooltip = '';
                    if (isset($field->desc_type) && $field->desc_type) {
                        $field->tooltip     = isset($field->description) ? $field->description : '';
                        $field->description = '';
                    }
                    if (isset($field->date_pic_conf) && $field->date_pic_conf) {
                        $this->date($field);
                    }

                    /**
                     * convert text field with sub type email to email field
                     */
                    if ($field->type == 'text' && $field->subtype == "email") {
                        $field->type = 'email';
                    }
                    if ($field->type == 'text' && $field->subtype == "url") {
                        $field->type = 'url';
                    }
                    if (isset($field->helptext_change)) {
                        $field->show_selected_option = $field->helptext_change;
                    }


                    /** file extensions supported to array */

                    if (isset($field->exts_supported) && ! empty($field->exts_supported)) {
                        $extensions = preg_split("/[\s,]+/", $field->exts_supported);
                        if (is_array($extensions)) {
                            $extensions = array_filter(array_map('trim', $extensions));
                            if (count($extensions) == 0) {
                                $extensions = false;
                            }
                        } else {
                            $extensions = false;
                        }
                        $field->exts_supported = $extensions;
                        if ($extensions == false) {
                            $field->file_types = 'any';
                        }
                    }
                    if ($field->type == 'file') {
                        if ( ! isset($field->ajax_upload) || $field->ajax_upload == false) {
                            $field->upload_type = 'basic';
                        }

                        if (isset($v->droppable) && $v->droppable) {
                            $field->upload_type = 'droppable';
                        } else {
                            $field->upload_type = 'ajax';
                        }
                    }
                    if (in_array($field->type, [
                        'checkbox-group',
                        'radio-group',
                        'image-group',
                        'color-group',
                        'productGroup'
                    ])) {
                        if (isset($field->inline) && $field->inline) {
                            $field->layOut = 'inline';
                        } else {
                            $field->layOut = '';
                        }
                    }
                    if ($field->type == 'image-group') {
                        /** for image group. there were no value for options, setting the index as value now */
                        if (is_array($field->values)) {
                            foreach ($field->values as $_i => $opt) {
                                $opt->value = ''.$_i;
                            }
                        }
                    }


                    if ($field->type == 'paragraph') {
                        $field->type        = 'content';
                        $field->value       = $field->label;
                        $field->label       = '';
                        $field->name        = $field->elementId;
                        $field->contentType = 'plain';
                    }
                    if ($field->type == 'statictext') {
                        $field->type        = 'content';
                        $field->contentType = 'plain';
                    }


                    if ($field->type == 'color-group') {
                        if (isset($field->disp_size) && $field->disp_size > 0) {
                            $temp             = $field->disp_size;
                            $field->disp_size = (object) ['width' => $temp, 'height' => $temp];
                        }
                    }

                    if ($field->type == 'date' || $field->type == 'datetime-local') {
                        $field->picker_mode='single';
                    }
                    if ($field->type == 'image-group' || $field->type == 'productGroup') {
                        if (isset($field->disp_size_img) && $field->disp_size_img > 0) {
                            $temp                 = $field->disp_size_img;
                            $field->disp_size_img = (object) ['width' => $temp, 'height' => ''];
                        } else {
                            $field->disp_size_img = (object) ['width' => '', 'height' => ''];
                        }
                        if (isset($field->img_selection_type) && $field->img_selection_type=='shadow') {
                            $field->img_selection_type = 'outline';
                        }
                    }

                    if (isset($field->label)) {
                        $field->label = html_entity_decode($field->label);
                    }
                    if (isset($field->description)) {
                        $field->description = html_entity_decode($field->description);
                    }
                    if (isset($field->values)) {
                        foreach ($field->values as $v) {
                            if (isset($v->label)) {
                                $v->label = html_entity_decode($v->label);
                            }
                        }
                    }


                    if (isset($field->relations) && ! empty($field->relations)) {
                        foreach ($field->relations as $relation) {
                            if (is_array($relation->rules) && count($relation->rules)) {
                                foreach ($relation->rules as $val) {
                                    if ($val->rules) {
                                        /**
                                         * Typo corrections
                                         */
                                        if ($val->rules->cl_relation === 'is_greater_or_eqaul') {
                                            $val->rules->cl_relation = 'is_greater_or_equal';
                                        }
                                        if ($val->rules->cl_relation === 'is_lessthan_or_eqal') {
                                            $val->rules->cl_relation = 'is_lessthan_or_equal';
                                        }

                                        /** removing date_is comparison, as 'is' would be enough  */
                                        if ($val->rules->cl_relation === 'date_is') {
                                            $val->rules->cl_relation = 'is';
                                        }
                                        if ($val->rules->cl_relation === 'time_is') {
                                            $val->rules->cl_relation = 'is';
                                        }

                                        /**
                                         * Change attributes and Terms ids to slug
                                         */

                                        if ($val->rules->cl_field === 'attribute' && $val->rules->cl_relation) {
                                            $atr = wc_get_attribute($val->rules->cl_relation);
                                            if ($atr) {
                                                $term                    = get_term_by('id', $val->rules->cl_val->value,
                                                    $atr->slug);
                                                $val->rules->cl_val      = isset($term->slug) ? $term->slug : '';
                                                $val->rules->cl_relation = sanitize_title($atr->slug);
                                            }
                                        }

                                        /**
                                         * create new attr cl_field_sub, for variations and custom-fiels, where it stores the field name, it was stored as
                                         * cl_relation earlier
                                         * set cl_relation as 'is'
                                         */
                                        if (in_array($val->rules->cl_field, [
                                            'attribute',
                                            'custom_attribute',
                                            'custom_field'
                                        ])) {
                                            $val->rules->cl_field_sub = $val->rules->cl_relation;
                                            $val->rules->cl_relation  = 'is';
                                        }

                                        /**
                                         * comma separated product ids convert to array
                                         */
                                        if (in_array($val->rules->cl_field, ['product_ids'])) {
                                            $rel_val = (((isset($val->rules->cl_val->value)) ? $val->rules->cl_val->value : $val->rules->cl_val));
                                            $rel_val = preg_split('/[\ \n\,]+/', $rel_val);
                                            $rel_val = array_map('intval', $rel_val);
                                            if (is_array($rel_val) && count($rel_val) > 1) {
                                                $val->rules->cl_relation = 'is_in';
                                                $val->rules->cl_val      = $rel_val;
                                            } else {
                                                $val->rules->cl_val = $rel_val[0] ? $rel_val[0] : '';
                                            }
                                        }


                                        /**
                                         * in earlier version, it was storing option index as well for options/select type fields, changing this to value only.
                                         *For image field, it was even used this index for front end conditional logic,
                                         */
                                        if (isset($val->rules->cl_val) && is_object($val->rules->cl_val)) {
                                            if (substr($val->rules->cl_field, 0,
                                                    16) === "wcpa-image-group" && isset($val->rules->cl_val->i)) {
                                                $val->rules->cl_val = $val->rules->cl_val->i;
                                            } elseif (isset($val->rules->cl_val->value)) {
                                                $val->rules->cl_val = $val->rules->cl_val->value;
                                            } else {
                                                $val->rules->cl_val = '';
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }


                    /** formula replaces */

                    if (isset($field->enablePrice) && isset($v->pricingType) && $v->pricingType === 'custom' && isset($field->price)) {
//						$field->price
                        $field->price = str_replace(
                            [
                                '{days}',
                                '{seconds}',
                                '{today.days}',
                                '{today.seconds}',
                                '.days',
                                '.seconds',
                            ],
                            [
                                '{unixDays}',
                                '{unixDays}',
                                '{today.unixDays}',
                                '{today.unixSeconds}',
                                '.unixDays',
                                '.unixSeconds',

                            ], $field->price);
                    }


                    if ($field->type == 'content') {
                        /** old paragraph  */
                        if (isset($field->value)) {
                            if (preg_match('/\#\=(.+?)\=\#/', $field->value) === 1) {
                                $field->value = str_replace(
                                    [
                                        '{days}',
                                        '{seconds}',
                                        '{today.days}',
                                        '{today.seconds}',
                                        '.days',
                                        '.seconds',
                                    ],
                                    [
                                        '{unixDays}',
                                        '{unixDays}',
                                        '{today.unixDays}',
                                        '{today.unixSeconds}',
                                        '.unixDays',
                                        '.unixSeconds',

                                    ], $field->value);
                            }
                        }
                    }
                }
            }
        }
    }


    public
    function date(
        $field
    ) {
        /**
         * Date config migration
         */
        if (isset($field->date_pic_conf) && $field->date_pic_conf) {
            $date_pic_conf = json_decode(trim($field->date_pic_conf));
            if ($date_pic_conf) {
                /** if valid json */
                $format = get_option('date_format');
                if (isset($date_pic_conf->format)) {
                    $format = $date_pic_conf->format;
                }
                if (isset($date_pic_conf->formatDate)) {
                    $format = $date_pic_conf->formatDate;
                }
                $newConf = [];
                if (isset($date_pic_conf->startDate)) {
                    $newConf['defaultDate'] = $this->getUNIDate($date_pic_conf->startDate, $format);
                }
                if (isset($date_pic_conf->format)) {
                    $newConf['dateFormat'] = $date_pic_conf->format;
                }
                if (isset($date_pic_conf->step)) {
                    $newConf['minuteIncrement'] = $date_pic_conf->step;
                }
                if (isset($date_pic_conf->minDate)) {
                    $newConf['minDate'] = $this->minMaxDate($date_pic_conf->minDate, $format);
                }
                if (isset($date_pic_conf->maxDate)) {
                    $newConf['maxDate'] = $this->minMaxDate($date_pic_conf->maxDate, $format);
                }
                if (isset($date_pic_conf->minTime)) {
                    $newConf['minTime'] = $date_pic_conf->minTime;
                }
                if (isset($date_pic_conf->maxTime)) {
                    $newConf['maxTime'] = $date_pic_conf->maxTime;
                }

                if (isset($date_pic_conf->disabledWeekDays)) {
                    $newConf['weekNumbers'] = $date_pic_conf->disabledWeekDays;
                }
                if (isset($date_pic_conf->disabledDates)) {
                    $newConf['disable'] = [];
                    foreach ($date_pic_conf->disabledDates as $d) {
                        $newConf['disable'][] = $this->getUNIDate($d, $format);
                    }
                }

                if (isset($date_pic_conf->allowDates)) {
                    $newConf['enable'] = [];
                    foreach ($date_pic_conf->allowDates as $d) {
                        $newConf['enable'][] = $this->getUNIDate($d, $format);
                    }
                }

                $field->date_pic_conf = (object) $newConf;

                //startDate
                //format                        - dateFormat
                //value                 - defaultDate
                //formatDate Format date for minDate and maxDate  - dateFormat
                //formatTime
                //step  Step time  - minuteIncrement
                //minDate   - minDate
                //maxDate   - maxDate
                //startDate    -
                //defaultDate
                //defaultTime
                //minTime
                //maxTime
                //allowTimes
                //inline
                //hours12
                //yearStart
                //yearEnd
                //weekends
                //disabledDates Disbale all dates in list
                //disabledDates Disbale all dates in list  -  disable
                //allowDates Disbale all dates in list   - enable
                //disabledWeekDays  Disbale all dates in list [0, 3, 4]   -  weekNumbers
            }
        }
    }

    /**
     * Convert old version date conf dates to a unique format  (Y-m-d)
     *
     * @param $date
     * @param $format
     *
     * @return false|string
     */
    public
    function getUNIDate(
        $date,
        $format
    ) {
        $dt = date_create_from_format($format, $date);
        if ($dt) {
            return date_format($dt, 'Y-m-d');
        }

        return $date;
    }

    /**
     * Convert old version minDate and maxDate to new
     *
     * @param $date
     * @param $format
     *
     * @return false|string
     */
    public
    function minMaxDate(
        $date,
        $format
    ) {
        $date = trim($date);
        if (strlen($date) > 6) {
            /** check if date string or just digits */
            /** check if starts with +/- */
            if (str_starts_with($date, '+')) {
                /** get days sub stracting unix base time  */
                $date  = str_replace('+', '', $date);
                $date  = $this->getUNIDate($date, $format);
                $date1 = date_create(date('Y-m-d', 0));
                $date2 = date_create($date);

                return date_diff($date1, $date2);
            }
            if (str_starts_with($date, '-')) {
                /** get days sub stracting unix base time  */
                $date  = str_replace('-', '', $date);
                $date  = $this->getUNIDate($date, $format);
                $date1 = date_create(date('Y-m-d', 0));
                $date2 = date_create($date);

                return '-'.date_diff($date1, $date2);
            }

            return $this->getUNIDate($date, $format);
        }

        return $date;
    }
}
