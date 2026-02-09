<?php

if (!defined('WPINC')) {
    exit;
}

class Wt_Import_Export_For_Woo_Product_Bulk_Export {

    /**
     * Product Exporter
     */
    public static function do_export($post_type = 'product', $prod_ids = array()) {
        global $wpdb;

        if (!empty($prod_ids)) {
            $selected_product_ids = implode(', ', $prod_ids);
        } else {
            $selected_product_ids = '';
        }

        if (!empty($_POST['products'])) { // introduced specific product from export page
            $selected_product_ids = implode(', ', array_map('intval', $_POST['products']));
        }


        $prod_categories = apply_filters('wt_iew_product_bulk_export_product_categories', array()); // term_ids array
        $prod_tags = apply_filters('wt_iew_product_bulk_export_product_tags', array()); // term_ids array

        $prod_status = !empty($_POST['prod_status']) ? wc_clean($_POST['prod_status']) : array('publish', 'private', 'draft', 'pending', 'future');
        $export_limit = !empty($_POST['limit']) ? intval($_POST['limit']) : 999999999;
        $export_count = 0;
        $limit = apply_filters('wt_iew_product_bulk_export_limit_per_request', 100);

        if ($limit > $export_limit)
            $limit = $export_limit;

        $current_offset = !empty($_POST['offset']) ? intval($_POST['offset']) : 0;
        $sortcolumn = !empty($_POST['sortcolumn']) ? implode(', ', wc_clean($_POST['sortcolumn'])) : 'post_parent, ID';
        $prod_sort_order = !empty($_POST['wt_prod_sort_ord']) ? sanitize_text_field($_POST['wt_prod_sort_ord']) : 'ASC';
        $delimiter = !empty($_POST['delimiter']) ? $_POST['delimiter'] : ','; // WPCS: CSRF ok, input var ok.
        $delimitertxt = !empty($_POST['delimitertxt']) ? $_POST['delimitertxt'] : ','; // WPCS: CSRF ok, input var ok.
        $delimiter = self::wt_get_csv_delimiter($delimiter, $delimitertxt);

        $csv_columns = self::wt_iew_get_product_columns();

        $user_columns_name = !empty($_POST['columns_name']) ? wc_clean($_POST['columns_name']) : $csv_columns;
        $export_columns = !empty($_POST['columns']) ? wc_clean($_POST['columns']) : array();


        /* setting  $user_columns_name for bulk action or cron export. */
        if (!empty($prod_ids) || $enable_ftp_ie) {
            $user_columns_name = array_combine(array_keys($csv_columns), array_keys($csv_columns));
        }


        $product_taxonomies = self::wt_get_product_ptaxonomies();

        $include_hidden_meta = apply_filters('wt_iew_product_bulk_export_include_hidden_meta', true);
        $export_children_sku = true;
        $export_shortcodes = true;
        $export_images_zip = false;

        $wpdb->hide_errors();
        @set_time_limit(0);
        if (function_exists('apache_setenv'))
            @apache_setenv('no-gzip', 1);

        @ini_set('zlib.output_compression', 0);
        @ob_end_clean(); // to prevent issue that unidentified characters when opened in MS-Excel in some servers



        $file_name = apply_filters('wt_iew_product_bulk_export_product_filename', 'product_export_' . date('Y-m-d-h-i-s') . '.csv');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=' . $file_name);
        header('Pragma: no-cache');
        header('Expires: 0');

        $fp = fopen('php://output', 'w');

        // Headers
        $all_meta_pkeys = self::get_all_metakeys('product');
        $all_meta_vkeys = self::get_all_metakeys('product_variation');

        $all_meta_keys = array_merge($all_meta_pkeys, $all_meta_vkeys);
        $all_meta_keys = array_unique($all_meta_keys);

        $found_pattributes = self::get_all_product_attributes('product');
        $found_vattributes = self::get_all_product_attributes('product_variation');
        $found_attributes = array_merge($found_pattributes, $found_vattributes);
        $found_attributes = array_unique($found_attributes);

        $found_attributes = apply_filters('wt_iew_product_bulk_export_attribute_data', $found_attributes);
        // Loop products and load meta data
        $found_product_meta = array();
        // Some of the values may not be usable (e.g. arrays of arrays) but the worse
        // that can happen is we get an empty column.
        foreach ($all_meta_keys as $meta) {
            if (!$meta)
                continue;
            if ((!$include_hidden_meta && !in_array($meta, array_keys($csv_columns)) && substr((string) $meta, 0, 1) == '_' ) || in_array('meta:' . $meta, array_keys($csv_columns)))// chaged in repeating meta when that meta in Additional metadata
                continue;

            $found_product_meta[] = $meta;
        }

        $found_product_meta = array_diff($found_product_meta, array_keys($csv_columns));
        $found_product_meta = apply_filters('wt_iew_product_bulk_export_product_meta', $found_product_meta);

        // Variable to hold the CSV data we're exporting
        $row = array();

        // Export header rows       
        foreach ($csv_columns as $column => $value) {

            if (!isset($user_columns_name[$column])) {

                continue;
            }
            if (!$export_columns || in_array($value, $export_columns) || in_array($column, $export_columns)) {

                if ('meta' == $column) {
                    foreach ($found_product_meta as $product_meta) {
                        $row[] = 'meta:' . self::format_data($product_meta);
                    }
                    continue;
                }

                if ('attributes' == $column) {
                    foreach ($found_attributes as $attribute) {
                        $row[] = 'attribute:' . self::format_data($attribute);
                        $row[] = 'attribute_data:' . self::format_data($attribute);
                        $row[] = 'attribute_default:' . self::format_data($attribute);
                    }
                    continue;
                }

                $temp_head = esc_attr($user_columns_name[$column]);
                if (strpos($temp_head, 'yoast') === false) {
                    $temp_head = ltrim($temp_head, '_');
                }

                $row[] = $temp_head;
            }
        }

        $row = apply_filters('wt_iew_alter_product_bulk_export_csv_columns', $row);
        $row = array_map('Wt_Import_Export_For_Woo_Product_Bulk_Export::wrap_column', $row);

        fwrite($fp, implode($delimiter, $row) . "\n");
        $header_row = $row;
        unset($row);

        $csv_columns = self::wt_array_walk($csv_columns, 'meta:'); // Remove string 'meta:' from keys and values, YOAST support
        $export_columns = self::wt_array_walk($export_columns, 'meta:'); // Remove string 'meta:' from keys and values, YOAST support


        ini_set('max_execution_time', -1);
        ini_set('memory_limit', -1);
        while ($export_count < $export_limit) {

            $product_args = array(
                'numberposts' => $limit,
                'post_status' => $prod_status,
                'post_type' => array('product', 'product_variation'),
                'orderby' => $sortcolumn,
                'suppress_filters' => FALSE,
                'order' => $prod_sort_order,
                'offset' => $current_offset
            );

            if (apply_filters('wpml_setting', false, 'setup_complete')) {
                $product_args['suppress_filters'] = TRUE;
            }

            if ((!empty($prod_categories) ) || (!empty($prod_tags))) {


                //If only product categories has been selected
                if (!empty($prod_categories)) {
                    $product_args['tax_query'][] = array(
                        'taxonomy' => 'product_cat',
                        'field' => 'id',
                        'terms' => $prod_categories,
                        'operator' => 'IN',
                    );
                }

                if (!empty($prod_tags)) {
                    $product_args['tax_query'][] = array(
                        'taxonomy' => 'product_tag',
                        'field' => 'id',
                        'terms' => $prod_tags,
                        'operator' => 'IN',
                    );
                }
            }


            if ($selected_product_ids) {
                $parent_ids = array_map('intval', explode(',', $selected_product_ids));
                $child_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent IN (" . implode(',', $parent_ids) . ");");
                $sel_ids = array_merge($parent_ids, $child_ids);
                $product_args['post__in'] = $sel_ids;
                $product_args['order'] = 'ASC';
            }

            $product_args = apply_filters('wt_iew_product_bulk_export_args', $product_args);
            $products = get_posts($product_args);
            $product_status = array('private', 'draft', 'pending', 'future');
            $array_diff = array_diff($prod_status, $product_status);
            //if product type selected is variable or product categories selected , get variations of variable product and variations subscription of variable subscription product also    
            if (!empty($prod_categories) || (!empty(array_intersect($product_status, $prod_status)) && !in_array('publish', $array_diff))) {
                $products = self::get_childs_of_selected_parents($products);
            }
            if (!$products || is_wp_error($products))
                break;
            $products = apply_filters('wt_iew_product_bulk_export_before', $products);
            // Loop products
            foreach ($products as $product) {
                if ($product->post_parent == 0)
                    $product->post_parent = '';
                $row = array();

                // Pre-process data
                $meta_data = get_post_custom($product->ID);

                $product->meta = new stdClass;
                $product->attributes = new stdClass;
                // Meta data
                foreach ($meta_data as $meta => $value) {

                    if (!$meta) {
                        continue;
                    }

                    if (!$include_hidden_meta && !in_array($meta, array_keys($csv_columns)) && substr($meta, 0, 1) == '_') {   //skipping _wc_
                        continue;
                    }

                    $meta_value = maybe_unserialize(maybe_unserialize($value[0]));

                    if (is_array($meta_value)) {
                        $meta_value = json_encode($meta_value);
                    }

                    if (strstr($meta, 'attribute_pa_')) {
                        if ($meta_value) {
                            $get_name_by_slug = get_term_by('slug', $meta_value, str_replace('attribute_', '', $meta));
                            if ($get_name_by_slug) {
                                $product->meta->$meta = self::format_export_meta($get_name_by_slug->name, $meta);
                            } else {
                                $product->meta->$meta = self::format_export_meta($meta_value, $meta);
                            }
                        } else {
                            $product->meta->$meta = self::format_export_meta($meta_value, $meta);
                        }
                    } elseif ($meta == '_regular_price' || $meta == '_sale_price' || $meta == '_price') {
                        $product->meta->$meta = function_exists('wc_format_localized_price') ? wc_format_localized_price($meta_value) : $meta_value;
                    } else {
                        $product->meta->$meta = self::format_export_meta($meta_value, $meta);
                    }
                }
                // Product attributes
                if (isset($meta_data['_product_attributes'][0])) {

                    $attributes = maybe_unserialize(maybe_unserialize($meta_data['_product_attributes'][0]));
                    if (!empty($attributes) && is_array($attributes)) {
                        foreach ($attributes as $key => $attribute) {
                            if (!$key) {
                                continue;
                            }
                            if ($attribute['is_taxonomy'] == 1) {
                                $terms = wp_get_post_terms($product->ID, $attribute['name'], array("fields" => "names"));
                                if (!is_wp_error($terms)) {
                                    $attribute_value = implode('|', $terms);
                                } else {
                                    $attribute_value = '';
                                }
                            } else {
                                if (empty($attribute['name'])) {
                                    continue;
                                }
                                $key = $attribute['name'];
                                $attribute_value = $attribute['value'];
                            }

                            if (!isset($attribute['position'])) {
                                $attribute['position'] = 0;
                            }
                            if (!isset($attribute['is_visible'])) {
                                $attribute['is_visible'] = 0;
                            }
                            if (!isset($attribute['is_variation'])) {
                                $attribute['is_variation'] = 0;
                            }

                            $attribute_data = $attribute['position'] . '|' . $attribute['is_visible'] . '|' . $attribute['is_variation'];
                            $_default_attributes = isset($meta_data['_default_attributes'][0]) ? maybe_unserialize(maybe_unserialize($meta_data['_default_attributes'][0])) : '';

                            if (is_array($_default_attributes)) {
                                // $_default_attribute = isset($_default_attributes[$key]) ? $_default_attributes[$key] : '';
                                $_default_attribute = isset($_default_attributes[strtolower($key)]) ? $_default_attributes[strtolower($key)] : ''; // default_attribute key always in lowercase
                            } else {
                                $_default_attribute = '';
                            }

                            $product->attributes->$key = array(
                                'value' => $attribute_value,
                                'data' => $attribute_data,
                                'default' => $_default_attribute
                            );
                        }
                    }
                }

                foreach ($csv_columns as $column => $value) {
                    if (!$export_columns || in_array($value, $export_columns) || in_array($column, $export_columns)) {
                        if ('_regular_price' == $column && empty($product->meta->$column)) {
                            $column = '_price';
                        }
                        if (!self::wt_iew_is_woocommerce_prior_to('2.7')) {
                            if ('_visibility' == $column) {
                                $product_terms = get_the_terms($product->ID, 'product_visibility');
                                if (!empty($product_terms)) {
                                    if (!is_wp_error($product_terms)) {
                                        $term_slug = '';
                                        foreach ($product_terms as $i => $term) {
                                            $term_slug .= $term->slug . (isset($product_terms[$i + 1]) ? '|' : '');
                                        }
                                        $row[] = $term_slug;
                                    }
                                } else {
                                    $row[] = '';
                                }
                                continue;
                            }
                        }

                        if ('parent' == $column) {
                            if ($product->post_parent) {
                                $post_parent_title = get_the_title($product->post_parent);
                                if ($post_parent_title) {
                                    $row[] = self::format_data($post_parent_title);
                                } else {
                                    $row[] = '';
                                }
                            } else {
                                $row[] = '';
                            }
                            continue;
                        }

                        if ('parent_sku' == $column) {
                            if ($product->post_parent) {
                                $row[] = get_post_meta($product->post_parent, '_sku', true);
                            } else {
                                $row[] = '';
                            }
                            continue;
                        }

                        // Export images/gallery
                        if ('images' == $column) {

                            $export_image_metadata = apply_filters('wt_iew_product_bulk_export_image_metadata', TRUE); //filter for disable export image meta datas such as alt,title,content,caption...
                            $image_file_names = array();

                            // Featured image
                            if (( $featured_image_id = get_post_thumbnail_id($product->ID))) {
                                $image_object = get_post($featured_image_id);
                                $img_url = wp_get_attachment_image_src($featured_image_id, 'full');

                                $image_meta = '';
                                if ($image_object && $export_image_metadata) {
                                    $image_metadata = get_post_meta($featured_image_id);
                                    $image_meta = " ! alt : " . ( isset($image_metadata['_wp_attachment_image_alt'][0]) ? $image_metadata['_wp_attachment_image_alt'][0] : '' ) . " ! title : " . $image_object->post_title . " ! desc : " . $image_object->post_content . " ! caption : " . $image_object->post_excerpt;
                                }
                                if ($image_object && $image_object->guid) {
                                    $temp_images_export_to_csv = ($export_images_zip ? basename($img_url[0]) : $img_url[0]) . ($export_image_metadata ? $image_meta : '');
                                }
                                if (!empty($temp_images_export_to_csv)) {
                                    $image_file_names[] = $temp_images_export_to_csv;
                                }
                            }

                            // Images
                            $images = isset($meta_data['_product_image_gallery'][0]) ? explode(',', maybe_unserialize(maybe_unserialize($meta_data['_product_image_gallery'][0]))) : false;
                            $results = array();
                            if ($images) {
                                foreach ($images as $image_id) {
                                    if ($featured_image_id == $image_id) {
                                        continue;
                                    }
                                    $temp_gallery_images_export_to_csv = '';
                                    $gallery_image_meta = '';
                                    $gallery_image_object = get_post($image_id);
                                    $gallery_img_url = wp_get_attachment_image_src($image_id, 'full');

                                    if ($gallery_image_object && $export_image_metadata) {
                                        $gallery_image_metadata = get_post_meta($image_id);
                                        $gallery_image_meta = " ! alt : " . ( isset($gallery_image_metadata['_wp_attachment_image_alt'][0]) ? $gallery_image_metadata['_wp_attachment_image_alt'][0] : '' ) . " ! title : " . $gallery_image_object->post_title . " ! desc : " . $gallery_image_object->post_content . " ! caption : " . $gallery_image_object->post_excerpt;
                                    }
                                    if ($gallery_image_object && $gallery_image_object->guid) {
                                        $temp_gallery_images_export_to_csv = ($export_images_zip ? basename($gallery_img_url[0]) : $gallery_img_url[0]) . ($export_image_metadata ? $gallery_image_meta : '');
                                    }
                                    if (!empty($temp_gallery_images_export_to_csv)) {
                                        $image_file_names[] = $temp_gallery_images_export_to_csv;
                                    }
                                }
                            }


                            if (!empty($image_file_names)) {
                                $row[] = implode(' | ', $image_file_names);
                            } else {
                                $row[] = '';
                            }
                            continue;
                        }


                        // Downloadable files
                        if ('file_paths' == $column || 'downloadable_files' == $column) {
                            $file_paths_to_export = array();
                            if (!function_exists('wc_get_filename_from_url')) {
                                $file_paths = maybe_unserialize(maybe_unserialize($meta_data['_file_paths'][0]));

                                if ($file_paths) {
                                    foreach ($file_paths as $file_path) {
                                        $file_paths_to_export[] = $file_path;
                                    }
                                }

                                $file_paths_to_export = implode(' | ', $file_paths_to_export);
                                $row[] = self::format_data($file_paths_to_export);
                            } elseif (isset($meta_data['_downloadable_files'][0])) {
                                $file_paths = maybe_unserialize(maybe_unserialize($meta_data['_downloadable_files'][0]));

                                if (is_array($file_paths) || is_object($file_paths)) {
                                    foreach ($file_paths as $file_path) {
                                        $file_paths_to_export[] = (!empty($file_path['name']) ? $file_path['name'] : self::wt_iew_get_filename_from_url($file_path['file']) ) . '::' . $file_path['file'];
                                    }
                                }
                                $file_paths_to_export = implode(' | ', $file_paths_to_export);
                            }
                            if (!empty($file_paths_to_export)) {
                                $row[] = !empty($file_paths_to_export) ? self::format_data($file_paths_to_export) : '';
                            } else {
                                $row[] = '';
                            }
                            continue;
                        }


                        // Export taxonomies
//                        if ( 'taxonomies' == $column ) {
                        if (substr($column, 0, 4) === 'tax:') {

                            foreach ($product_taxonomies as $taxonomy) {

                                if (strstr($taxonomy->name, 'pa_'))
                                    continue; // Skip attributes

                                if ('tax:' . $taxonomy->name != $column)
                                    continue;

                                if (is_taxonomy_hierarchical($taxonomy->name)) {
                                    $terms = wp_get_post_terms($product->ID, $taxonomy->name, array("fields" => "all"));

                                    $formatted_terms = array();

                                    foreach ($terms as $term) {
                                        $ancestors = array_reverse(get_ancestors($term->term_id, $taxonomy->name));
                                        $formatted_term = array();

                                        foreach ($ancestors as $ancestor)
                                            $formatted_term[] = get_term($ancestor, $taxonomy->name)->name;

                                        $formatted_term[] = $term->name;

                                        $formatted_terms[] = implode(' > ', $formatted_term);
                                    }

                                    $row[] = self::format_data(implode('|', $formatted_terms));
                                } else {
                                    $terms = wp_get_post_terms($product->ID, $taxonomy->name, array("fields" => "names"));

                                    $row[] = self::format_data(implode('|', $terms));
                                }
                            }
                            continue;
                        }

                        // Export meta data
                        if ('meta' == $column) {
                            foreach ($found_product_meta as $product_meta) {
                                if (isset($product->meta->$product_meta)) {
                                    $row[] = self::format_data($product->meta->$product_meta);
                                } else {
                                    $row[] = '';
                                }
                            }
                            continue;
                        }

                        // Find and export attributes
                        if ('attributes' == $column) {
                            foreach ($found_attributes as $attribute) {
                                if (isset($product->attributes) && isset($product->attributes->$attribute)) {
                                    $values = $product->attributes->$attribute;
                                    $row[] = self::format_data($values['value']);
                                    $row[] = self::format_data($values['data']);
                                    $row[] = self::format_data($values['default']);
                                } else {
                                    $row[] = '';
                                    $row[] = '';
                                    $row[] = '';
                                }
                            }
                            continue;
                        }



                        // WF: Adding product permalink.
                        if ('product_page_url' == $column) {
                            $product_page_url = '';
                            if (!empty($product->ID)) {
                                $product_page_url = get_permalink($product->ID);
                            }
                            if (!empty($product->post_parent)) {
                                $product_page_url = get_permalink($product->post_parent);
                            }
                            $row[] = !empty($product_page_url) ? $product_page_url : '';
                            continue;
                        }

                        /**
                         * WPML
                         */
                        if (apply_filters('wpml_setting', false, 'setup_complete')) {
                            if (in_array($column, array('wpml:language_code', 'wpml:original_product_id', 'wpml:original_product_sku'))) {
                                if ('wpml:language_code' == $column) {
                                    $original_post_language_info = self::wt_iew_get_wpml_original_post_language_info($product->ID);
                                    $row[] = (isset($original_post_language_info->language_code) && !empty($original_post_language_info->language_code) ? $original_post_language_info->language_code : '');
                                    continue;
                                }

                                /*
                                 * To get the ID of the original product post 
                                 * https://wpml.org/forums/topic/translated-product-get-id-of-original-lang-for-custom-fields/             
                                 */

                                global $sitepress;
                                $original_product_id = icl_object_id($product->ID, 'product', false, $sitepress->get_default_language());
                                if ('wpml:original_product_id' == $column) {
                                    $row[] = ($original_product_id ? $original_product_id : '');
                                    continue;
                                }
                                if ('wpml:original_product_sku' == $column) {
                                    $sku = get_post_meta($original_product_id, '_sku', true);
                                    $row[] = ($sku ? $sku : '');
                                    continue;
                                }
                            }
                        }


                        if (isset($product->meta->$column)) {
                            if ('_children' == $column) {
                                if ($export_children_sku) {
                                    $children_sku = '';
                                    $children_id_array = str_replace('"', '', explode(',', trim($product->meta->$column, '[' . ']')));
                                    if (!empty($children_id_array) && $children_id_array[0] != '""') {
                                        foreach ($children_id_array as $children_id_array_key => $children_id) {
                                            $children_sku = !empty($children_sku) ? "{$children_sku}|" . get_post_meta($children_id, '_sku', TRUE) : get_post_meta($children_id, '_sku', TRUE);
                                        }
                                    }
                                    $row[] = !empty($children_sku) ? $children_sku : '';
                                } else {
                                    $row[] = str_replace('"', '', implode('|', explode(',', trim($product->meta->$column, '[' . ']'))));
                                }
                            } elseif ('_stock_status' == $column) {
                                $stock_status = self::format_data($product->meta->$column);

                                /* if( !function_exists('WC') && class_exists( 'Woocommerce' )){ // Lower version of WC
                                  if(function_exists('wc_get_product')){ // WC()->version < '3.0'
                                  $product_object = wc_get_product($product->ID);
                                  }elseif (function_exists('get_product')) { // Woocommerce 2.0.19 PIEPFW-407
                                  $product_object = get_product($product->ID);
                                  }
                                  if(!empty($product_object)){
                                  $product_type = $product_object->product_type;
                                  }else{
                                  $product_type = '';
                                  }
                                  }else{
                                  $product_type = WC_Product_Factory::get_product_type( $product->ID );
                                  } */
                                $term_product_type = wp_get_post_terms($product->ID, 'product_type', array('fields' => 'slugs'));
                                if (!is_wp_error($term_product_type) && isset($term_product_type[0])) {
                                    $product_type = $term_product_type[0];
                                } else {
                                    $product_type = '';
                                }
                                $row[] = !empty($stock_status) ? $stock_status : ( ( 'variable' == $product_type || 'variable-subscription' == $product_type ) ? '' : 'instock' );
                            } elseif ('_sku' == $column) {//PIEPFW-528 url decode replace + with space
                                $row[] = $product->meta->$column;
                            } else {
                                $row[] = self::format_data($product->meta->$column);
                            }
                        } elseif (isset($product->$column) && !is_array($product->$column)) {
                            if ($export_shortcodes && ( 'post_content' == $column || 'post_excerpt' == $column )) {
                                //Convert Shortcodes to html for Description and Short Description
                                $row[] = do_shortcode($product->$column);
                            } elseif ('post_title' === $column) {
                                $row[] = sanitize_text_field($product->$column);
                            } else {
                                $row[] = self::format_data($product->$column);
                            }
                        } else {
                            $row[] = '';
                        }
                    }
                }

                $row = apply_filters('wt_iew_product_bulk_export_csv_data', $row, $product->ID, $header_row);
                if (empty($row)) {//remove empty row export
                    continue;
                }

                // Add to csv
                $row = array_map('Wt_Import_Export_For_Woo_Product_Bulk_Export::wrap_column', $row);

                fwrite($fp, implode($delimiter, $row) . "\n");
                unset($row);
            }
            $current_offset += $limit;
            $export_count += $limit;
            unset($products);
        }


        fclose($fp);
        exit;
    }

    public static function wt_get_product_ptaxonomies() {
        $product_ptaxonomies = get_object_taxonomies('product', 'name');
        $product_vtaxonomies = get_object_taxonomies('product_variation', 'name');
        $product_taxonomies = array_merge($product_ptaxonomies, $product_vtaxonomies);
        return $product_taxonomies;
    }

    /**
     * To strip the specific string from the array key as well as value.
     * @param array $array.
     * @param string $data.
     * @return array.
     */
    public static function wt_array_walk($array, $data) {
        $new_array = array();
        foreach ($array as $key => $value) {
            $new_array[str_replace($data, '', $key)] = str_replace($data, '', $value);
        }
        return $new_array;
    }

    /**
     * Get File name by url
     * @param string $file_url URL of the file.
     * @return string the base name of the given URL (File name).
     */
    public static function wt_iew_get_filename_from_url($file_url) {
        $parts = parse_url($file_url);
        if (isset($parts['path'])) {
            return basename($parts['path']);
        }
    }

    /**
     * Get info like language code, parent product ID etc by product id.
     * @param int Product ID.
     * @return array/false.
     */
    public static function wt_iew_get_wpml_original_post_language_info($element_id) {
        $get_language_args = array('element_id' => $element_id, 'element_type' => 'post_product');
        $original_post_language_info = apply_filters('wpml_element_language_details', null, $get_language_args);
        return $original_post_language_info;
    }

    public static function wt_iew_is_woocommerce_prior_to($version) {

        $woocommerce_is_pre_version = (!defined('WC_VERSION') || version_compare(WC_VERSION, $version, '<')) ? true : false;
        return $woocommerce_is_pre_version;
    }

    public static function wt_iew_get_product_columns() {
        if (function_exists('wc_get_filename_from_url')) {
            $file_path_header = 'downloadable_files';
        } else {
            $file_path_header = 'file_paths';
        }


        $post_columns = array(
            'post_title' => 'Product Name',
            'post_name' => 'Product Slug',
            'post_parent' => 'Parent ID',
            'ID' => 'ID',
            'post_excerpt' => 'Short Description',
            'post_content' => 'Description',
            'post_status' => 'Status',
            'post_password' => 'post_password',
            'menu_order' => 'menu_order',
            'post_date' => 'post_date',
            'post_author' => 'post_author',
            'comment_status' => 'comment_status',
            // Meta
            '_sku' => 'sku',
            'parent_sku' => 'parent_sku',
            'parent' => 'Parent Title',
            '_children' => 'children', //For Grouped products
            '_downloadable' => 'downloadable',
            '_virtual' => 'virtual',
            '_stock' => 'stock',
            '_regular_price' => 'Regular Price',
            '_sale_price' => 'Sale Price',
            '_weight' => 'weight',
            '_length' => 'length',
            '_width' => 'width',
            '_height' => 'height',
            '_tax_class' => 'tax_class',
//    '_visibility' => 'visibility',
            '_stock_status' => 'stock_status',
            '_backorders' => 'backorders',
            '_manage_stock' => 'manage_stock',
            '_tax_status' => 'tax_status',
            '_upsell_ids' => 'upsell_ids',
            '_crosssell_ids' => 'crosssell_ids',
            '_featured' => 'featured',
            '_sale_price_dates_from' => 'sale_price_dates_from',
            '_sale_price_dates_to' => 'sale_price_dates_to',
            // Downloadable products
            '_download_limit' => 'download_limit',
            '_download_expiry' => 'download_expiry',
            // Virtual products
            '_product_url' => 'product_url',
            '_button_text' => 'button_text',
            'images' => 'Images (featured and gallery)',
            "$file_path_header" => 'Downloadable file paths',
            'product_page_url' => 'Product Page URL',
        );

        if (class_exists('WPSEO_Options')) {
            /* Yoast is active */

            $post_columns['meta:_yoast_wpseo_focuskw'] = 'meta:_yoast_wpseo_focuskw';
            $post_columns['meta:_yoast_wpseo_canonical'] = 'meta:_yoast_wpseo_canonical';
            $post_columns['meta:_yoast_wpseo_bctitle'] = 'meta:_yoast_wpseo_bctitle';
            $post_columns['meta:_yoast_wpseo_meta-robots-adv'] = 'meta:_yoast_wpseo_meta-robots-adv';
            $post_columns['meta:_yoast_wpseo_is_cornerstone'] = 'meta:_yoast_wpseo_is_cornerstone';
            $post_columns['meta:_yoast_wpseo_metadesc'] = 'meta:_yoast_wpseo_metadesc';
            $post_columns['meta:_yoast_wpseo_linkdex'] = 'meta:_yoast_wpseo_linkdex';
            $post_columns['meta:_yoast_wpseo_estimated-reading-time-minutes'] = 'meta:yoast_wpseo_estimated-reading-time-minutes';
            $post_columns['meta:_yoast_wpseo_content_score'] = 'meta:_yoast_wpseo_focuskw';
            $post_columns['meta:_yoast_wpseo_title'] = 'meta:_yoast_wpseo_title';
            $post_columns['meta:_yoast_wpseo_metadesc'] = 'meta:_yoast_wpseo_metadesc';
            $post_columns['meta:_yoast_wpseo_metakeywords'] = 'meta:_yoast_wpseo_metakeywords';
        }

        if (function_exists('aioseo')) {
            /* All in One SEO is active */

            $post_columns['meta:_aioseo_title'] = 'meta:_aioseo_title';
            $post_columns['meta:_aioseo_description'] = 'meta:_aioseo_description';
            $post_columns['meta:_aioseo_keywords'] = 'meta:_aioseo_keywords';
            $post_columns['meta:_aioseo_og_title'] = 'meta:_aioseo_og_title';
            $post_columns['meta:_aioseo_og_description'] = 'meta:_aioseo_og_description';
            $post_columns['meta:_aioseo_twitter_title'] = 'meta:_aioseo_twitter_title';
            $post_columns['meta:_aioseo_og_article_tags'] = 'meta:_aioseo_og_article_tags';
            $post_columns['meta:_aioseo_twitter_description'] = 'meta:_aioseo_twitter_description';
        }

        $taxonomies = array();
        $product_taxonomies = self::wt_get_product_ptaxonomies();
        foreach ($product_taxonomies as $taxonomy) {
            if (strstr($taxonomy->name, 'pa_'))
                continue; // Skip attributes

            $taxonomies['tax:' . self::format_data($taxonomy->name)] = self::format_data($taxonomy->name);
        }
        $post_columns = array_merge($post_columns, $taxonomies);

        $post_columns['meta'] = 'Meta (custom fields)';
        $post_columns['attributes'] = 'Attributes';

        if (WC()->version < '2.7.0') {
            $post_columns['_visibility'] = 'visibility';
        }
        if (apply_filters('wpml_setting', false, 'setup_complete')) {

            $post_columns['wpml:language_code'] = 'wpml:language_code';
            $post_columns['wpml:original_product_id'] = 'wpml:original_product_id';
            $post_columns['wpml:original_product_sku'] = 'wpml:original_product_sku';
        }

        return apply_filters('wt_iew_woocommerce_csv_product_bulk_product_columns', $post_columns);
    }

    /**
     * Format the data if required
     * function gets childs of products and psuh to products array
     * @param type $products
     */
    public static function get_childs_of_selected_parents($products) {
        global $wpdb;
        $var_ar_ids = array();
        foreach ($products as $product) {
            $post_type = $product->post_type;
            if ('product_variation' === $post_type) {
                $product_type = 'variation';
            } elseif ('product' === $post_type) {
                $terms = get_the_terms($product->ID, 'product_type');
                $product_type = !empty($terms) ? sanitize_title(current($terms)->name) : 'simple';
            } else {
                $product_type = false;
            }
            if ($product_type == 'variable' || $product_type == 'variable-subscription') {
                $get_type = 'product_variation';
                $child_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_parent = $product->ID AND post_type = '$get_type'");
                if (!empty($child_ids)) {
                    $var_ar_ids = array_merge($var_ar_ids, $child_ids);
                }
            }
        }
        if (!empty($var_ar_ids)) {
            $product_args = array(
                'numberposts' => -1,
                'post_type' => array('product_variation'),
                'post__in' => $var_ar_ids,
                'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
            );
            $product_variations = get_posts($product_args);
            $products = array_merge($products, $product_variations);
        }
        return $products;
    }

    /**
     * Format the data if required
     * @param  string $meta_value
     * @param  string $meta name of meta key
     * @return string
     */
    public static function format_export_meta($meta_value, $meta) {
        switch ($meta) {
            case '_sale_price_dates_from' :
            case '_sale_price_dates_to' :
                return $meta_value ? date('Y-m-d', $meta_value) : '';
                break;
            case '_upsell_ids' :
            case '_crosssell_ids' :
                return implode('|', array_filter((array) json_decode($meta_value)));
                break;
            default :
                return $meta_value;
                break;
        }
    }

    public static function format_data($data) {
        if (!is_array($data))
            ;
        $data = (string) urldecode($data);
//        $enc = mb_detect_encoding($data, 'UTF-8, ISO-8859-1', true);        
        $use_mb = function_exists('mb_detect_encoding');
        $enc = '';
        if ($use_mb) {
            $enc = mb_detect_encoding($data, 'UTF-8, ISO-8859-1', true);
        }
        $data = ( $enc == 'UTF-8' ) ? $data : utf8_encode($data);

        return $data;
    }

    /**
     * Wrap a column in quotes for the CSV
     * @param  string data to wrap
     * @return string wrapped data
     */
    public static function wrap_column($data) {
        return '"' . str_replace('"', '""', $data) . '"';
    }

    /**
     * Get a list of all the meta keys for a post type. This includes all public, private,
     * used, no-longer used etc. They will be sorted once fetched.
     */
    public static function get_all_metakeys($post_type = 'product') {
        global $wpdb;

        $meta = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT pm.meta_key
            FROM {$wpdb->postmeta} AS pm
            LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status IN ( 'publish', 'private', 'draft', 'pending', 'future' )", $post_type
        ));

        sort($meta);

        return $meta;
    }

    /**
     * Get a list of all the product attributes for a post type.
     * These require a bit more digging into the values.
     */
    public static function get_all_product_attributes($post_type = 'product') {
        global $wpdb;

        $results = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} AS pm
            LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status IN ( 'publish', 'private', 'draft', 'pending', 'future' )
            AND pm.meta_key = '_product_attributes'", $post_type
        ));

        // Go through each result, and look at the attribute keys within them.
        $result = array();

        if (!empty($results)) {
            foreach ($results as $_product_attributes) {
                $attributes = maybe_unserialize(maybe_unserialize($_product_attributes));
                if (!empty($attributes) && is_array($attributes)) {
                    foreach ($attributes as $key => $attribute) {
                        if (!$key) {
                            continue;
                        }
                        if (!strstr($key, 'pa_')) {
                            if (empty($attribute['name'])) {
                                continue;
                            }
                            $key = $attribute['name'];
                        }

                        $result[$key] = $key;
                    }
                }
            }
        }

        sort($result);

        return $result;
    }

    public static function wt_get_csv_delimiter($delemiter = ',', $other_delimiter = ',') {
        $delemiter = strtolower($delemiter);
        $delemiter_pass = $delemiter;
        switch ($delemiter) {
            case 'tab':
                $delemiter_pass = "\t";
                break;

            case 'space':
                $delemiter_pass = " ";
                break;

            case 'other':
                $delemiter_pass = $other_delimiter;
                break;
        }
        return $delemiter_pass;
    }

}
