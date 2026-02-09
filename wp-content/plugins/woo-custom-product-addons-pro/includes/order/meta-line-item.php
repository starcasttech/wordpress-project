<?php

namespace Acowebs\WCPA;


class OrderMetaLineItem
{
    /**
     * @var false|mixed|string|void
     */
    private $item;
    private $meta_data;
    private $product;

    public function __construct($item, $product)
    {
        $this->item      = $item;
        $this->product   = $product;
        $this->meta_data = $item->get_meta(WCPA_ORDER_META_KEY);
    }

    public function render()
    {
        if (is_array($this->meta_data) && count($this->meta_data)) {
            ?>
            <div class="wcpa_order_meta" data-itemId="45"></div>
            <table>
                <tr>
                    <th><?php
                        _e('Options', 'woo-custom-product-addons-pro') ?></th>
                    <th><?php
                        _e('Value', 'woo-custom-product-addons-pro') ?></th>
                    <th><?php
                        _e('Cost', 'woo-custom-product-addons-pro') ?></th>
                    <th></th>
                </tr>
                <?php
                foreach ($this->meta_data as $sectionKey => $section) {
                    $form_rules = $section['extra']->form_rules;
                    foreach ($section['fields'] as $rowIndex => $row) {
                        foreach ($row as $colIndex => $field) {
                            if ( ! is_array($field)) {
                                continue;
                            }
                            switch ($field['type']) {
                                case 'checkbox-group':
                                case 'select':
                                case 'radio-group':
                                case 'image-group':
                                case 'color-group':
                                case 'productGroup':
                                    $this->group($field);

                                    break;
                                case 'file':
                                    $this->file($field);
                                    break;
                                case 'placeselector':
                                    $this->placeselector($field);
                                    break;
                                default:
                                    $this->default($field);
                                    break;
                            }
                        }
                    }
                }
                ?>
            </table>
            <?php
        }
    }

    public function group($field)
    {
        $label_printed = false;
        if (is_array($field['value'])) {
            foreach ($field['value'] as $k => $v) {
                ?>
                <tr class="item_wcpa">
                    <?php
                    if ( ! $label_printed) {
                        $label_printed = true;
                        echo "<td class='name' rowspan='".count($field['value'])."'>
                    ".$field["label"]."
                    </td>";
                    }

                    ?>

                    <td class="value">
                        <div class="view">
                            <?php
                            if ($field['type'] == 'productGroup') {
                                $edit_url = admin_url('post.php?post='.$v['value']).'&action=edit';
                                echo '<strong>'.__('Label:', 'woo-custom-product-addons-pro').'</strong>
                                    <a href="'.$edit_url.'" /> '.$v['label'].'</a>';
                            } else {
                                echo '<strong>'.__('Label:', 'woo-custom-product-addons-pro').'</strong> '.__($v['label'],
                                        'woo-custom-product-addons-pro');
                            }
                            if ((isset($v['quantity']) && ! empty($v['quantity']))) {
                                echo ' <span class="wcpa_cart_addon_quantity"><i>x</i> '.$v['quantity'].'</span> ';
                            }
                            echo '<br />';
                            if ($field['type'] == 'image-group' || $field['type'] == 'productGroup') {
                                if (isset($v['image']) && $v['image'] !== false) {
                                    $img_size_style = 'style="width:75px;height:auto"';
                                    echo '<img class="wcpa_img" '.$img_size_style.'  src="'.$v['image'].'" />';
                                } elseif (isset($v['value']) && $v['value'] !== false) {
                                    echo ' '.$v['value'];
                                }
                            } elseif ($field['type'] == 'color-group') {
                                echo '<strong>'.__('Value:',
                                        'woo-custom-product-addons-pro').'</strong> '.'<span style="color:'.$v['color'].';font-size: 20px;
                                                padding: 0;
                                        line-height: 0;">&#9632;</span>'.$v['value'];
                            } else {
                                echo '<strong>'.__('Value:', 'woo-custom-product-addons-pro').'</strong> '.$v['value'];
                            }

                            ?>

                        </div>
                        <div class="edit" style="display: none;">

                        </div>
                    </td>
                    <td class="item_cost" width="1%">
                        <?php
                        if (isset($field['form_data']->enablePrice) && $field['form_data']->enablePrice &&
                            ( ! isset($field['is_fee']) || $field['is_fee'] === false)) {
                            ?>
                            <div class="view">
                                <?php
                                echo isset($field['price'][$k]) ? $field['price'][$k] : '0'; ?>
                            </div>

                            <?php
                        }
                        ?>
                    </td>
                    <td class="wc-order-edit-line-item" width="1%">
                        <div class="wc-order-edit-line-item-actions edit" style="display: none;">
                            <a class="wcpa_delete-order-item tips" href="#"
                               data-tip="<?php
                               esc_attr_e('Delete item', 'woocommerce'); ?>"></a>
                        </div>
                    </td>
                </tr>
                <?php
            }
        }
    }

    public function file($field)
    {
        ?>

        <tr class="item_wcpa">

            <td class="name"><?php
                echo $field['label']; ?></td>
            <td class="value">
                <div class="view">
                    <?php
                    if (isset($field['value']) && is_array($field['value'])) {
                        foreach ($field['value'] as $dt) {
                            if (isset($dt['url'])) {
                                $display = '<a href="'.$dt['url'].'"  target="_blank" download="'.$dt['file_name'].'">';
                                if (in_array($dt['type'], array('image/jpg', 'image/png', 'image/gif', 'image/jpeg'))) {
                                    $display .= '<img class="wcpa_img" style="width:75px;height:auto" src="'.$dt['url'].'" />';
                                } else {
                                    $display .= '<img class="wcpa_icon" src="'.wp_mime_type_icon($dt['type']).'" />';
                                }
                                $display .= $dt['file_name'].'</a>';
                                echo $display;
                            } else {
                                echo $dt;
                            }
                        }
                    }
                    if ((isset($field['quantity']) && ! empty($field['quantity']))) {
                        echo ' <span class="wcpa_cart_addon_quantity"><i>x</i> '.$field['quantity'].'</span> ';
                    }
                    ?>
                </div>
            </td>
        </tr>

        <?php
    }

    public function placeselector($field)
    {
        $display = '';
        $strings = [
            'street'    => Config::get_config('place_selector_street'),
            'city'      => Config::get_config('place_selector_city'),
            'state'     => Config::get_config('place_selector_state'),
            'zip'       => Config::get_config('place_selector_zip'),
            'country'   => Config::get_config('place_selector_country'),
            'latitude'  => Config::get_config('place_selector_latitude'),
            'longitude' => Config::get_config('place_selector_longitude'),
        ];

        ?>
        <tr class="item_wcpa">

            <td class="name">
                <?php

                echo $field['label'];

                ?>
            </td>
            <td class="value">
                <div class="view">
                    <?php
                    $value = $field['value'];
                    if ( ! empty($value['value'])) {
                        $display = $value['value'].'<br>';
                        if ( ! empty($value['split']['street_number'])) {
                            $display .= $strings['street'].' '.$value['split']['street_number'].' '.$value['split']['route'].' <br>';
                        }
                        if ( ! empty($value['split']['locality'])) {
                            $display .= $strings['city'].' '.$value['split']['locality'].'<br>';
                        }
                        if ( ! empty($value['split']['administrative_area_level_1'])) {
                            $display .= $strings['state'].' '.$value['split']['administrative_area_level_1'].'<br>';
                        }
                        if ( ! empty($value['split']['postal_code'])) {
                            $display .= $strings['zip'].' '.$value['split']['postal_code'].'<br>';
                        }
                        if ( ! empty($value['split']['country'])) {
                            $display .= $strings['country'].' '.$value['split']['country'].'<br>';
                        }
                        if (isset($value['cords']['lat']) && ! empty($value['cords']['lat'])) {
                            $display .= $strings['latitude'].' '.$value['cords']['lat'].'<br>';
                            $display .= $strings['longitude'].' '.$value['cords']['lng'].'<br>';
                            $display .= '<a href="https://www.google.com/maps/?q='.$value['cords']['lat'].','.$value['cords']['lng'].'" target="_blank">'.__(
                                    'View on map',
                                    'woo-custom-product-addons-pro'
                                ).'</a> <br>';
                            //TODO view on map text field in settings
                        }
                    }
                    echo $display;
                    ?>
                </div>
            </td>
        </tr>
        <?php
    }

    public function default($field)
    {
        ?>
        <tr class="item_wcpa">

            <td class="name">
                <?php
                if ($field['type'] == 'hidden' && empty($field['label'])) {
                    echo $field['label'].'[hidden]';
                } else {
                    echo $field['label'];
                }
                ?>
            </td>
            <td class="value">
                <div class="view">
                    <?php
                    if ($field['type'] == 'color') {
                        echo '<span style = "color:'.$field['value'].';font-size: 20px;
            padding: 0;
    line-height: 0;">&#9632;</span>'.$field['value'];
                    } else {
                        echo nl2br($field['value']);
                    }
                    if ((isset($field['quantity']) && ! empty($field['quantity']))) {
                        echo ' <span class="wcpa_cart_addon_quantity"><i>x</i> '.$field['quantity'].'</span> ';
                    }
                    ?>
                </div>
            </td>
            <td class="item_cost" width="1%">
                <?php
                if (isset($field['form_data']->enablePrice) && $field['form_data']->enablePrice &&
                    ( ! isset($field['is_fee']) || $field['is_fee'] === false)) {
                    ?>
                    <div class="view">
                        <?php
                        echo isset($field['price']) ? $field['price'] : '0'; ?>
                    </div>

                    <?php
                }
                ?>
            </td>
        </tr>
        <?php
    }
}



