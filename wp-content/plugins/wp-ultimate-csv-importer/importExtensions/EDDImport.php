<?php
/******************************************************************************************
 * Copyright (C) Smackcoders. - All Rights Reserved under Smackcoders Proprietary License
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * You can contact Smackcoders at email address info@smackcoders.com.
 *******************************************************************************************/

namespace Smackcoders\FCSV;

if (!defined('ABSPATH'))
    exit;

class EDDImport
{

    private static $edd_instance = null;

    public static function getInstance()
    {
        if (EDDImport::$edd_instance === null) {
            EDDImport::$edd_instance = new EDDImport;
        }
        return EDDImport::$edd_instance;
    }
    private function is_edd_3_0_plus()
    {
        if (!class_exists('Easy_Digital_Downloads')) {
            return false;
        }

        // Check for EDD 3.0+ Order class
        if (class_exists('\\EDD\\Orders\\Order')) {
            return true;
        }


        // Check for Order_Query class
        if (class_exists('\\EDD\\Orders\\Order_Query')) {
            return true;
        }


        // Check version if function exists
        if (function_exists('edd_get_version')) {
            $version = edd_get_version();
            if ($version && version_compare($version, '3.0', '>=')) {
                return true;
            }
        }


        return false;
    }



    public function set_edd_values(
        $header_array,
        $value_array,
        $map,
        $post_id,
        $type,
        $hash_key,
        $gmode,
        $templatekey,
        $line_number
    ) {
        $helpers_instance = ImportHelpers::getInstance();

        $post_values = $helpers_instance->get_header_values(
            $map,
            $header_array,
            $value_array
        );

        return $this->edd_import_function(
            $post_values,
            $type,
            $post_id,
            $header_array,
            $value_array,
            $hash_key,
            $gmode,
            $templatekey,
            $line_number
        );
    }


    public function edd_import_function(
        $data_array,
        $importas,
        $pID,
        $header_array,
        $value_array,
        $hash_key,
        $gmode,
        $templatekey,
        $line_number
    ) {
        $createdFields = array_keys($data_array);

        /* ---------------------------------------------------------
         * HEADER → NORMALIZED KEYS
         * --------------------------------------------------------- */
        $mapping = array(
            'thumbnail_id' => ['thumbnail_id'],
            'price' => ['price'],
            'variable_pricing' => ['variable_pricing'],
            'variable_prices' => ['variable_prices'],
            'default_price_id' => ['default_price_id'],
            'price_options_mode' => ['price_options_mode'],

            'download_sales' => ['download_sales'],
            'download_earnings' => ['download_earnings'],
            'download_limit' => ['download_limit'],
            'refund_window' => ['refund_window'],

            'hide_purchase_link' => ['hide_purchase_link'],
            'button_behavior' => ['button_behavior'],

            'sku' => ['sku'],
            'product_type' => ['product_type'],
            'featured' => ['featured'],
            'feature_download' => ['feature_download'],

            'product_notes' => ['product_notes'],
            'download_files' => ['download_files'],
            'bundled_products_conditions' => ['bundled_products_conditions'],
            'bundled_products' => ['bundled_products'],

            'download_category' => ['download_category', 'download_categories'],
            'download_tag' => ['download_tag', 'download_tags'],
            'edit_lock' => ['edit_lock'],
            'edit_last' => ['edit_last'],
            'faq' => ['faq']
        );

        $edd = [];
        foreach ($mapping as $key => $aliases) {
            $edd[$key] = null;
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $data_array)) {
                    $edd[$key] = urldecode($data_array[$alias]);
                    break;
                }
            }
        }

        /* ---------------------------------------------------------
         * FEATURED IMAGE (MERGED LOGIC)
         * --------------------------------------------------------- */
        if (isset($edd['thumbnail_id']) && !empty($edd['thumbnail_id'])) {
            $img = $edd['thumbnail_id'];
            if (is_numeric($img) && (int) $img > 0) {
                set_post_thumbnail($pID, (int) $img);
            } else {
                // Handle URL using MediaHandling
                $media_instance = MediaHandling::getInstance();
                $media_instance->media_handling(
                    $img,
                    $pID,
                    $data_array,
                    'EDD',
                    'EDD_DOWNLOADS',
                    null,
                    $templatekey,
                    null,
                    null,
                    $header_array,
                    $value_array,
                    null,
                    null,
                    $line_number,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    true // Set as Featured Image
                );
            }
        }

        /* ---------------------------------------------------------
         * BASIC PRICE (STRING)
         * --------------------------------------------------------- */
        update_post_meta($pID, 'edd_price', (string) $edd['price']);

        /* ---------------------------------------------------------
         * VARIABLE PRICING
         * --------------------------------------------------------- */
        update_post_meta($pID, '_variable_pricing', (int) $edd['variable_pricing']);

        if ((int) $edd['variable_pricing'] === 1 && !empty($edd['variable_prices'])) {
            $prices = [];
            foreach (explode('|', $edd['variable_prices']) as $item) {
                $item = trim($item);
                if ($item === '')
                    continue;

                $row = [];
                foreach (explode(',', $item) as $pair) {
                    if (strpos($pair, ':') !== false) {
                        [$k, $v] = array_map('trim', explode(':', $pair, 2));
                        $row[$k] = $v;
                    }
                }

                if (isset($row['price_id']) && $row['price_id'] !== '') {
                    $index = (string) $row['price_id'];
                    $prices[$index] = [
                        'index' => $index,
                        'name' => $row['name'] ?? '',
                        'amount' => $row['amount'] ?? '',
                        'sale_price' => $row['sale_price'] ?? '',
                    ];
                }
            }
            if ($prices) {
                update_post_meta($pID, 'edd_variable_prices', $prices);
            }
        }

        update_post_meta($pID, '_edd_default_price_id', (int) $edd['default_price_id']);
        update_post_meta($pID, '_edd_price_options_mode', (int) $edd['price_options_mode']);

        /* ---------------------------------------------------------
         * LIMITS & CONTROLS (INT)
         * --------------------------------------------------------- */
        update_post_meta($pID, '_edd_download_limit', (int) $edd['download_limit']);
        update_post_meta($pID, '_edd_refund_window', (int) $edd['refund_window']);
        update_post_meta($pID, '_edd_hide_purchase_link', (int) $edd['hide_purchase_link']);
        update_post_meta($pID, '_edd_button_behavior', (string) $edd['button_behavior']);

        /* ---------------------------------------------------------
         * IDENTITY (SANITIZED PRODUCT TYPE)
         * --------------------------------------------------------- */
        update_post_meta($pID, 'edd_sku', (string) $edd['sku']);

        $product_type = !empty($edd['product_type']) ? strtolower(trim($edd['product_type'])) : 'default';
        update_post_meta($pID, '_edd_product_type', $product_type);

        update_post_meta($pID, '_edd_featured', (int) $edd['featured']);
        update_post_meta($pID, 'edd_feature_download', (int) $edd['feature_download']);

        /* ---------------------------------------------------------
         * PRODUCT NOTES
         * --------------------------------------------------------- */
        update_post_meta($pID, 'edd_product_notes', (string) $edd['product_notes']);

        /* ---------------------------------------------------------
         * DOWNLOAD FILES
         * --------------------------------------------------------- */
        if (!empty($edd['download_files'])) {
            $files = [];
            foreach (explode('|', $edd['download_files']) as $i => $item) {
                $row = [];
                foreach (explode(',', $item) as $pair) {
                    if (strpos($pair, ':') !== false) {
                        [$k, $v] = array_map('trim', explode(':', $pair, 2));
                        $row[$k] = $v;
                    }
                }

                if (!empty($row['file_url'])) {
                    $files[$i] = [
                        'name' => $row['file_name'] ?? '',
                        'file' => $row['file_url'],
                    ];
                }
            }
            update_post_meta($pID, 'edd_download_files', $files);
        }

        /* ---------------------------------------------------------
         * BUNDLE CONDITIONS
         * --------------------------------------------------------- */
        if (!empty($edd['bundled_products_conditions'])) {
            $raw_bundled_products_conditions = explode('|', $edd['bundled_products_conditions']);
            $bundled_products_conditions = [];
            $i = 1;
            foreach ($raw_bundled_products_conditions as $product_contion) {
                $bundled_products_conditions[$i++] = trim($product_contion);
            }
            update_post_meta($pID, '_edd_bundled_products_conditions', $bundled_products_conditions);
        }

        if (!empty($edd['edit_last'])) {
            $edit_last = $edd['edit_last'];
            update_post_meta($pID, '_edit_last', $edit_last);
        }

        if (!empty($edd['edit_lock'])) {
            $edit_lock = $edd['edit_lock'];
            update_post_meta($pID, '_edit_lock', $edit_lock);
        }

        /* ---------------------------------------------------------
         * TAXONOMIES
         * --------------------------------------------------------- */
        foreach (['download_category', 'download_tag'] as $tax) {
            if (isset($edd[$tax]) && $edd[$tax] !== '') {
                wp_set_object_terms(
                    $pID,
                    array_map('trim', explode(',', $edd[$tax])),
                    $tax,
                    false
                );
            }
        }

        /* ---------------------------------------------------------
         * SALES & EARNINGS (INT + STRING)
         * --------------------------------------------------------- */
        update_post_meta($pID, '_edd_download_sales', (int) $edd['download_sales']);
        update_post_meta($pID, '_edd_download_earnings', (string) $edd['download_earnings']);

        if (!empty($edd['bundled_products'])) {
            $raw_products = explode('|', $edd['bundled_products']);
            error_log("started to update the bundled_products");
            $bundled_products = [];
            $i = 1;
            foreach ($raw_products as $product) {
                $bundled_products[$i++] = trim($product);
            }
            update_post_meta($pID, '_edd_bundled_products', $bundled_products);
        }

        /* ---------------------------------------------------------
         * FAQ (MY ENHANCEMENT)
         * --------------------------------------------------------- */
        if (!empty($edd['faq'])) {
            $faq_items = [];
            $raw_faqs = explode('|', $edd['faq']);
            foreach ($raw_faqs as $item) {
                $item = trim($item);
                if ($item === '')
                    continue;

                $question = '';
                $answer = '';

                // Robust parsing for "question:Q,answer:A"
                $answer_start_pos = strpos($item, ',answer:');
                if ($answer_start_pos !== false && strpos($item, 'question:') === 0) {
                    $question = substr($item, 9, $answer_start_pos - 9);
                    $answer = substr($item, $answer_start_pos + 8);
                    $faq_items[] = ['question' => $question, 'answer' => $answer];
                } elseif (strpos($item, ':') !== false) {
                    $row = [];
                    foreach (explode(',', $item) as $pair) {
                        if (strpos($pair, ':') !== false) {
                            list($k, $v) = array_map('trim', explode(':', $pair, 2));
                            $row[$k] = $v;
                        }
                    }
                    if (isset($row['question']) && isset($row['answer'])) {
                        $faq_items[] = ['question' => $row['question'], 'answer' => $row['answer']];
                    }
                }
            }
            if (!empty($faq_items)) {
                update_post_meta($pID, 'edd_faq', $faq_items);
            }
        }

        error_log("this is the log: " . print_r($edd, true));

        return array('ID' => $pID);
    }

    /**
     * Import EDD Downloads
     * 
     * @param array $header_array CSV headers
     * @param array $value_array CSV row values
     * @param array $map Field mapping
     * @param int $post_id Post ID
     * @param string $hash_key Import hash key
     * @param string $gmode Import mode
     * @param string $templatekey Template key
     * @param int $line_number Line number
     * @return array Array containing the record ID
     */
    public function import_downloads(
        $header_array,
        $value_array,
        $map,
        $post_id,
        $hash_key,
        $gmode,
        $templatekey,
        $line_number
    ) {
        return $this->set_edd_values(
            $header_array,
            $value_array,
            $map,
            $post_id,
            'EDD_DOWNLOADS',
            $hash_key,
            $gmode,
            $templatekey,
            $line_number
        );
    }


    /**
     * Import EDD Customers
     * 
     * @param array $header_array CSV headers
     * @param array $value_array CSV row values
     * @param array $map Field mapping
     * @param string $hash_key Import hash key
     * @param string $gmode Import mode
     * @param string $templatekey Template key
     * @param int $line_number Line number
     * @return array Array containing the record ID
     */
    public function import_customers(
        $header_array,
        $value_array,
        $map,
        $hash_key,
        $gmode,
        $templatekey,
        $line_number
    ) {
        global $wpdb;

        if (!class_exists('Easy_Digital_Downloads')) {
            return array('ID' => 0);
        }

        $helpers_instance = ImportHelpers::getInstance();
        $core_instance = CoreFieldsImport::getInstance();
        $log_table_name = $wpdb->prefix . "import_detail_log";
        $customers_table = $wpdb->prefix . "edd_customers";

        // Get mapped values
        $customer_map = isset($map['EDD_CUSTOMERS']) ? $map['EDD_CUSTOMERS'] : $map;
        $customer_data = $helpers_instance->get_header_values($customer_map, $header_array, $value_array);

        // Extract customer fields
        $customer_id = isset($customer_data['id']) ? intval($customer_data['id']) : 0;
        $email = isset($customer_data['email']) ? sanitize_email($customer_data['email']) : '';
        $name = isset($customer_data['name']) ? sanitize_text_field($customer_data['name']) : '';
        $first_name = isset($customer_data['first_name']) ? sanitize_text_field($customer_data['first_name']) : '';
        $last_name = isset($customer_data['last_name']) ? sanitize_text_field($customer_data['last_name']) : '';
        $user_id = isset($customer_data['user_id']) ? intval($customer_data['user_id']) : 0;
        $username = isset($customer_data['username']) ? sanitize_text_field($customer_data['username']) : '';
        $customer_status = isset($customer_data['status']) ? sanitize_text_field($customer_data['status']) : '';
        $phone = isset($customer_data['phone']) ? sanitize_text_field($customer_data['phone']) : '';
        $address = isset($customer_data['address']) ? sanitize_text_field($customer_data['address']) : '';
        $address2 = isset($customer_data['address2']) ? sanitize_text_field($customer_data['address2']) : '';
        $city = isset($customer_data['city']) ? sanitize_text_field($customer_data['city']) : '';
        $region = isset($customer_data['region']) ? sanitize_text_field($customer_data['region']) : '';
        $postal_code = isset($customer_data['postal_code']) ? sanitize_text_field($customer_data['postal_code']) : '';
        $country = isset($customer_data['country']) ? sanitize_text_field($customer_data['country']) : '';
        $purchase_count = isset($customer_data['purchase_count']) ? intval($customer_data['purchase_count']) : 0;
        $purchase_value = isset($customer_data['purchase_value']) ? floatval($customer_data['purchase_value']) : 0;
        $date_created = isset($customer_data['date_created']) ? $customer_data['date_created'] : current_time('mysql');
        $notes = isset($customer_data['notes']) ? $customer_data['notes'] : '';
        $order_ids = isset($customer_data['order_ids']) ? $customer_data['order_ids'] : '';

        // Combine first_name and last_name if provided
        if (!empty($first_name) || !empty($last_name)) {
            $name = trim($first_name . ' ' . $last_name);
        }

        // Validate required fields
        if (empty($email)) {
            $core_instance->detailed_log[$line_number]['Message'] = "Skipped: Missing required field (email)";
            $core_instance->detailed_log[$line_number]['state'] = 'Skipped';
            $skipped_count = $helpers_instance->update_count($hash_key, 'hash_key')['skipped'];
            $wpdb->query($wpdb->prepare("UPDATE $log_table_name SET skipped = %d WHERE hash_key = %s", $skipped_count, $hash_key));
            return array('ID' => 0);
        }

        // Get WordPress user ID if not provided
        if (empty($user_id)) {
            // Try to get user by username first
            if (!empty($username)) {
                $user = get_user_by('login', $username);
                $user_id = $user ? $user->ID : 0;
            }
            // Fall back to email
            if (empty($user_id)) {
                $user = get_user_by('email', $email);
                $user_id = $user ? $user->ID : 0;
            }
        }

        // Check if customer exists
        $existing_customer = null;
        if ($customer_id > 0) {
            $existing_customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $customers_table WHERE id = %d", $customer_id));
        }

        if (!$existing_customer) {
            $existing_customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $customers_table WHERE email = %s", $email));
        }

        if ($existing_customer) {
            // Update existing customer
            $update_data = array(
                'email' => $email,
                'name' => $name ?: $existing_customer->name,
                'user_id' => $user_id ?: $existing_customer->user_id,
                'status' => $customer_status ?: $existing_customer->status,
                'purchase_count' => $purchase_count ?: $existing_customer->purchase_count,
                'purchase_value' => $purchase_value ?: $existing_customer->purchase_value,
                'date_created' => $date_created ?: $existing_customer->date_created
            );

            $wpdb->update(
                $customers_table,
                $update_data,
                array('id' => $existing_customer->id),
                array('%s', '%s', '%d', '%s', '%d', '%f', '%s'),
                array('%d')
            );

            $customer_id = $existing_customer->id;
            $mode = 'Updated';
        } else {
            // Insert new customer
            $insert_data = array(
                'email' => $email,
                'name' => $name,
                'user_id' => $user_id,
                'status' => $customer_status,
                'purchase_count' => $purchase_count,
                'purchase_value' => $purchase_value,
                'date_created' => $date_created
            );

            $wpdb->insert(
                $customers_table,
                $insert_data,
                array('%s', '%s', '%d', '%s', '%d', '%f', '%s')
            );

            $customer_id = $wpdb->insert_id;
            $mode = 'Inserted';
        }

        // Handle phone number
        if (!empty($phone)) {
            $meta_table = $wpdb->prefix . "edd_customermeta";
            $wpdb->replace(
                $meta_table,
                array(
                    'edd_customer_id' => $customer_id,
                    'meta_key' => 'phone',
                    'meta_value' => $phone
                ),
                array('%d', '%s', '%s')
            );
        }

        // Handle notes - use edd_add_note for EDD 3.0+
        if (!empty($notes)) {
            if (function_exists('edd_add_note')) {
                // EDD 3.0+ - Add notes to wp_edd_notes table
                $notes_arr = explode('|', $notes);
                foreach ($notes_arr as $note_content) {
                    if (!empty(trim($note_content))) {
                        edd_add_note(array(
                            'object_id' => $customer_id,
                            'object_type' => 'customer',
                            'content' => sanitize_textarea_field($note_content),
                            'user_id' => get_current_user_id() ?: 1
                        ));
                    }
                }
            } else {
                // Legacy EDD 2.x - Store in customermeta
                $meta_table = $wpdb->prefix . "edd_customermeta";
                $wpdb->replace(
                    $meta_table,
                    array(
                        'edd_customer_id' => $customer_id,
                        'meta_key' => 'notes',
                        'meta_value' => $notes
                    ),
                    array('%d', '%s', '%s')
                );
            }
        }

        // Handle customer address
        if (!empty($address) || !empty($city) || !empty($region) || !empty($postal_code) || !empty($country)) {
            $addresses_table = $wpdb->prefix . "edd_customer_addresses";

            // Check if address already exists
            $existing_address = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $addresses_table WHERE customer_id = %d AND type = 'billing' AND is_primary = 1",
                $customer_id
            ));

            $address_data = array(
                'customer_id' => $customer_id,
                'name' => $name,
                'address' => $address,
                'address2' => $address2,
                'city' => $city,
                'region' => $region,
                'postal_code' => $postal_code,
                'country' => $country,
                'type' => 'billing',
                'is_primary' => 1,
                'status' => 'active'
            );

            if ($existing_address) {
                $wpdb->update(
                    $addresses_table,
                    $address_data,
                    array('id' => $existing_address->id),
                    array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'),
                    array('%d')
                );
            } else {
                $wpdb->insert(
                    $addresses_table,
                    $address_data,
                    array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
                );
            }
        }

        // Handle order IDs
        if (!empty($order_ids)) {
            $order_id_array = array_map('trim', explode(',', $order_ids));
            foreach ($order_id_array as $order_id) {
                if (is_numeric($order_id)) {
                    update_post_meta($order_id, '_edd_payment_customer_id', $customer_id);
                }
            }
        }

        // Log success
        if ($mode === 'Inserted') {
            $created_count = $helpers_instance->update_count($hash_key, 'hash_key')['created'];
            $core_instance->detailed_log[$line_number]['Message'] = 'Inserted EDD Customer ID: ' . $customer_id;
            $core_instance->detailed_log[$line_number]['id'] = $customer_id;
            $core_instance->detailed_log[$line_number]['post_type'] = 'EDD_CUSTOMERS';
            $core_instance->detailed_log[$line_number]['adminLink'] = admin_url('edit.php?post_type=download&page=edd-customers&view=overview&id=' . $customer_id);
            $core_instance->detailed_log[$line_number]['state'] = 'Inserted';
            $wpdb->query($wpdb->prepare("UPDATE $log_table_name SET created = %d WHERE hash_key = %s", $created_count, $hash_key));
        } else {
            $updated_count = $helpers_instance->update_count($hash_key, 'hash_key')['updated'];
            $core_instance->detailed_log[$line_number]['Message'] = 'Updated EDD Customer ID: ' . $customer_id;
            $core_instance->detailed_log[$line_number]['id'] = $customer_id;
            $core_instance->detailed_log[$line_number]['post_type'] = 'EDD_CUSTOMERS';
            $core_instance->detailed_log[$line_number]['adminLink'] = admin_url('edit.php?post_type=download&page=edd-customers&view=overview&id=' . $customer_id);
            $core_instance->detailed_log[$line_number]['state'] = 'Updated';
            $wpdb->query($wpdb->prepare("UPDATE $log_table_name SET updated = %d WHERE hash_key = %s", $updated_count, $hash_key));
        }

        return array('ID' => $customer_id);
    }

    /**
     * Import EDD Discounts
     * 
     * @param array $header_array CSV headers
     * @param array $value_array CSV row values
     * @param array $map Field mapping
     * @param string $hash_key Import hash key
     * @param string $gmode Import mode
     * @param string $templatekey Template key
     * @param int $line_number Line number
     * @return array Array containing the record ID
     */
    public function import_discounts(
        $header_array,
        $value_array,
        $map,
        $hash_key,
        $gmode,
        $templatekey,
        $line_number
    ) {
        global $wpdb;
        $log_file = '/var/www/html/test/wordpress/wp-content/plugins/wp-ultimate-csv-importer-pro/edd_debug_log.txt';
        file_put_contents($log_file, "--- Import Discounts Start (Row $line_number) ---\n", FILE_APPEND);
        file_put_contents($log_file, "is_edd_3_0_plus: " . ($this->is_edd_3_0_plus() ? 'YES' : 'NO') . "\n", FILE_APPEND);

        if (!class_exists('Easy_Digital_Downloads')) {
            return array('ID' => 0);
        }

        $helpers_instance = ImportHelpers::getInstance();
        $core_instance = CoreFieldsImport::getInstance();
        $log_table_name = $wpdb->prefix . "import_detail_log";

        // Get mapped values
        $discount_map = isset($map['EDD_DISCOUNTS']) ? $map['EDD_DISCOUNTS'] : $map;
        $discount_data = $helpers_instance->get_header_values($discount_map, $header_array, $value_array);

        file_put_contents($log_file, "Map Type: " . (isset($map['EDD_DISCOUNTS']) ? 'Nested' : 'Flat') . "\n", FILE_APPEND);
        file_put_contents($log_file, "Discount Data: " . print_r($discount_data, true) . "\n", FILE_APPEND);

        file_put_contents('/var/www/html/test/wordpress/wp-content/plugins/wp-ultimate-csv-importer-pro/edd_debug_log.txt', "User Discount Data: " . print_r($discount_data, true) . "\n", FILE_APPEND);

        // Extract discount fields
        $code = isset($discount_data['code']) ? sanitize_text_field($discount_data['code']) : '';
        $name = isset($discount_data['name']) ? sanitize_text_field($discount_data['name']) : $code; // Default to code if name not provided
        $amount = isset($discount_data['amount']) ? floatval($discount_data['amount']) : 0;
        $type = isset($discount_data['type']) ? sanitize_text_field($discount_data['type']) : 'flat';
        $status = isset($discount_data['status']) ? sanitize_text_field($discount_data['status']) : 'active';
        $start_date = isset($discount_data['start_date']) ? $discount_data['start_date'] : '';
        $end_date = isset($discount_data['end_date']) ? $discount_data['end_date'] : '';
        $max_uses = isset($discount_data['max_uses']) ? intval($discount_data['max_uses']) : 0;
        $product_requirements = isset($discount_data['product_requirements']) ? $discount_data['product_requirements'] : '';
        $excluded_products = isset($discount_data['excluded_products']) ? $discount_data['excluded_products'] : '';
        $category_requirements = isset($discount_data['category_requirements']) ? $discount_data['category_requirements'] : '';
        $min_price = isset($discount_data['min_price']) ? floatval($discount_data['min_price']) : 0;
        $max_uses_per_user = isset($discount_data['max_uses_per_user']) ? intval($discount_data['max_uses_per_user']) : 0;
        $use_count = isset($discount_data['use_count']) && $discount_data['use_count'] !== '' ? intval($discount_data['use_count']) : null;
        $product_condition = isset($discount_data['product_condition']) ? sanitize_text_field($discount_data['product_condition']) : 'all';
        $notes = isset($discount_data['notes']) ? $discount_data['notes'] : '';
        $max_price = isset($discount_data['max_price']) ? floatval($discount_data['max_price']) : 0;
        $excluded_categories = isset($discount_data['excluded_categories']) ? $discount_data['excluded_categories'] : '';

        // Validate required fields
        if (empty($code)) {
            $core_instance->detailed_log[$line_number]['Message'] = "Skipped: Missing required field (code)";
            $core_instance->detailed_log[$line_number]['state'] = 'Skipped';
            $skipped_count = $helpers_instance->update_count($hash_key, 'hash_key')['skipped'];
            $wpdb->query($wpdb->prepare("UPDATE $log_table_name SET skipped = %d WHERE hash_key = %s", $skipped_count, $hash_key));
            return array('ID' => 0);
        }

        // Normalize discount type
        $type = strtolower($type);
        if ($type === 'fixed') {
            $type = 'flat'; // EDD 3.0+ uses 'flat'
        }
        if ($type === 'percentage' || $type === 'percent') {
            $type = 'percent'; // EDD 3.0+ uses 'percent'
        }

        // Validate discount type
        if (!in_array($type, array('flat', 'percent'))) {
            $type = 'flat';
        }

        // Validate status
        if (!in_array($status, array('active', 'inactive', 'expired'))) {
            $status = 'active';
        }

        // Format dates
        if (!empty($start_date) && strtotime($start_date)) {
            $start_date = date('Y-m-d H:i:s', strtotime($start_date));
        } else {
            $start_date = current_time('mysql');
        }

        if (!empty($end_date) && strtotime($end_date)) {
            $end_date = date('Y-m-d H:i:s', strtotime($end_date));
        } else {
            $end_date = '';
        }

        // Check if EDD 3.0+ and use appropriate API
        if ($this->is_edd_3_0_plus() && function_exists('edd_add_discount')) {
            // EDD 3.0+ - Use discount API

            // Check if discount exists
            $existing_discount = edd_get_discount_by('code', $code);

            file_put_contents('/var/www/html/test/wordpress/wp-content/plugins/wp-ultimate-csv-importer-pro/edd_debug_log.txt', "Existing Discount: " . ($existing_discount ? $existing_discount->id : 'None') . "\n", FILE_APPEND);

            $discount_args = array(
                'code' => $code,
                'name' => $name,
                'amount' => $amount,
                'type' => $type,
                'status' => $status,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'max_uses' => $max_uses,
                'use_count' => is_numeric($use_count) ? $use_count : ($existing_discount ? $existing_discount->use_count : 0),
                'product_condition' => $product_condition,
                'is_not_global' => false,
                'once_per_customer' => $max_uses_per_user > 0 ? 1 : 0,
            );

            // Add product requirements
            if (!empty($product_requirements)) {
                $product_ids = array_map('trim', explode(',', $product_requirements));
                $product_ids = array_filter(array_map('intval', $product_ids));
                $discount_args['product_reqs'] = $product_ids;
            }

            // Add excluded products
            if (!empty($excluded_products)) {
                $excluded_ids = array_map('trim', explode(',', $excluded_products));
                $excluded_ids = array_filter(array_map('intval', $excluded_ids));
                $discount_args['excluded_products'] = $excluded_ids;
            }

            // Add category requirements
            if (!empty($category_requirements)) {
                $category_ids = array_map('trim', explode(',', $category_requirements));
                $category_ids = array_filter(array_map('intval', $category_ids));
                // EDD 3.0+ stores this in adjustmentmeta as 'categories'
                // But we need to set it via the discount object after creation
            }

            // Add excluded categories
            if (!empty($excluded_categories)) {
                $excluded_cat_ids = array_map('trim', explode(',', $excluded_categories));
                $excluded_cat_ids = array_filter(array_map('intval', $excluded_cat_ids));
                $discount_args['excluded_categories'] = $excluded_cat_ids;
            }

            // Add min/max price
            if ($min_price > 0) {
                $discount_args['min_charge_amount'] = $min_price;
            }
            if ($max_price > 0) {
                $discount_args['max_charge_amount'] = $max_price;
            }

            // Note: once_per_customer is already set above in the main args
            // max_uses_per_user is not a direct EDD 3.0+ field, it's stored as once_per_customer (boolean)

            file_put_contents('/var/www/html/test/wordpress/wp-content/plugins/wp-ultimate-csv-importer-pro/edd_debug_log.txt', "Discount Args: " . print_r($discount_args, true) . "\n", FILE_APPEND);

            if ($existing_discount) {
                file_put_contents($log_file, "Attempting Update (ID: " . $existing_discount->id . ")\n", FILE_APPEND);
                $result = edd_update_discount($existing_discount->id, $discount_args);
                if ($result) {
                    $discount_id = $existing_discount->id;
                    $mode = 'Updated';
                    file_put_contents($log_file, "Update SUCCESS\n", FILE_APPEND);
                } else {
                    file_put_contents($log_file, "Update FAILED\n", FILE_APPEND);
                    $core_instance->detailed_log[$line_number]['Message'] = "Failed to update discount: " . $code;
                    $core_instance->detailed_log[$line_number]['state'] = 'Skipped';
                    $skipped_count = $helpers_instance->update_count($hash_key, 'hash_key')['skipped'];
                    $wpdb->query($wpdb->prepare("UPDATE $log_table_name SET skipped = %d WHERE hash_key = %s", $skipped_count, $hash_key));
                    return array('ID' => 0);
                }
            } else {
                file_put_contents($log_file, "Attempting Insert\n", FILE_APPEND);
                $discount_id = edd_add_discount($discount_args);
                if ($discount_id) {
                    $mode = 'Inserted';
                    file_put_contents($log_file, "Insert SUCCESS (ID: $discount_id)\n", FILE_APPEND);
                } else {
                    file_put_contents($log_file, "Insert FAILED\n", FILE_APPEND);
                    $core_instance->detailed_log[$line_number]['Message'] = "Failed to create discount: " . $code;
                    $core_instance->detailed_log[$line_number]['state'] = 'Skipped';
                    $skipped_count = $helpers_instance->update_count($hash_key, 'hash_key')['skipped'];
                    $wpdb->query($wpdb->prepare("UPDATE $log_table_name SET skipped = %d WHERE hash_key = %s", $skipped_count, $hash_key));
                    return array('ID' => 0);
                }
            }

            // Handle category requirements manually via adjustmentmeta for EDD 3.0+
            if (!empty($category_requirements)) {
                $category_ids = array_map('trim', explode(',', $category_requirements));
                $category_ids = array_filter(array_map('intval', $category_ids));
                if (!empty($category_ids)) {
                    $meta_table = $wpdb->prefix . 'edd_adjustmentmeta';
                    $wpdb->replace(
                        $meta_table,
                        array(
                            'edd_adjustment_id' => $discount_id,
                            'meta_key' => 'categories',
                            'meta_value' => maybe_serialize($category_ids)
                        ),
                        array('%d', '%s', '%s')
                    );
                }
            }

            // Handle notes for EDD 3.0+
            if (!empty($notes)) {
                $notes_arr = explode('|', $notes);
                foreach ($notes_arr as $note_content) {
                    if (function_exists('edd_add_note')) {
                        edd_add_note(array(
                            'object_id' => $discount_id,
                            'object_type' => 'discount',
                            'content' => sanitize_textarea_field($note_content),
                            'user_id' => get_current_user_id() ?: 1
                        ));
                    }
                }
            }
        } else {
            // Legacy EDD 2.x - Use direct database queries
            $discounts_table = $wpdb->prefix . "edd_discounts";

            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$discounts_table'");
            if (!$table_exists) {
                $core_instance->detailed_log[$line_number]['Message'] = "Skipped: EDD discounts table not found. Please ensure EDD is properly installed.";
                $core_instance->detailed_log[$line_number]['state'] = 'Skipped';
                $skipped_count = $helpers_instance->update_count($hash_key, 'hash_key')['skipped'];
                $wpdb->query($wpdb->prepare("UPDATE $log_table_name SET skipped = %d WHERE hash_key = %s", $skipped_count, $hash_key));
                return array('ID' => 0);
            }

            // Check if discount exists
            $existing_discount = $wpdb->get_row($wpdb->prepare("SELECT * FROM $discounts_table WHERE code = %s", $code));

            if ($existing_discount) {
                // Update existing discount
                $update_data = array(
                    'amount' => $amount,
                    'type' => $type,
                    'status' => $status,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'max_uses' => $max_uses
                );

                $wpdb->update(
                    $discounts_table,
                    $update_data,
                    array('code' => $code),
                    array('%f', '%s', '%s', '%s', '%s', '%d'),
                    array('%s')
                );

                $discount_id = $existing_discount->id;
                $mode = 'Updated';
            } else {
                // Insert new discount
                $insert_data = array(
                    'code' => $code,
                    'amount' => $amount,
                    'type' => $type,
                    'status' => $status,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'max_uses' => $max_uses,
                    'use_count' => 0
                );

                $wpdb->insert(
                    $discounts_table,
                    $insert_data,
                    array('%s', '%f', '%s', '%s', '%s', '%s', '%d', '%d')
                );

                $discount_id = $wpdb->insert_id;
                $mode = 'Inserted';
            }

            // Handle discount restrictions and rules for EDD 2.x
            $meta_table = $wpdb->prefix . 'edd_discountmeta';

            if (!empty($product_requirements)) {
                $product_ids = array_map('trim', explode(',', $product_requirements));
                $product_ids = array_filter(array_map('intval', $product_ids));
                if (!empty($product_ids)) {
                    $wpdb->replace(
                        $meta_table,
                        array(
                            'discount_id' => $discount_id,
                            'meta_key' => '_edd_discount_product_reqs',
                            'meta_value' => maybe_serialize($product_ids)
                        ),
                        array('%d', '%s', '%s')
                    );
                }
            }

            if (!empty($excluded_products)) {
                $excluded_ids = array_map('trim', explode(',', $excluded_products));
                $excluded_ids = array_filter(array_map('intval', $excluded_ids));
                if (!empty($excluded_ids)) {
                    $wpdb->replace(
                        $meta_table,
                        array(
                            'discount_id' => $discount_id,
                            'meta_key' => '_edd_discount_excluded_products',
                            'meta_value' => maybe_serialize($excluded_ids)
                        ),
                        array('%d', '%s', '%s')
                    );
                }
            }

            if (!empty($category_requirements)) {
                $category_ids = array_map('trim', explode(',', $category_requirements));
                $category_ids = array_filter(array_map('intval', $category_ids));
                if (!empty($category_ids)) {
                    $wpdb->replace(
                        $meta_table,
                        array(
                            'discount_id' => $discount_id,
                            'meta_key' => '_edd_discount_category_reqs',
                            'meta_value' => maybe_serialize($category_ids)
                        ),
                        array('%d', '%s', '%s')
                    );
                }
            }

            if (!empty($excluded_categories)) {
                $excluded_cat_ids = array_map('trim', explode(',', $excluded_categories));
                $excluded_cat_ids = array_filter(array_map('intval', $excluded_cat_ids));
                if (!empty($excluded_cat_ids)) {
                    $wpdb->replace(
                        $meta_table,
                        array(
                            'discount_id' => $discount_id,
                            'meta_key' => '_edd_discount_excluded_categories',
                            'meta_value' => maybe_serialize($excluded_cat_ids)
                        ),
                        array('%d', '%s', '%s')
                    );
                }
            }

            if ($min_price > 0) {
                $wpdb->replace(
                    $meta_table,
                    array(
                        'discount_id' => $discount_id,
                        'meta_key' => '_edd_discount_min_price',
                        'meta_value' => $min_price
                    ),
                    array('%d', '%s', '%f')
                );
            }

            if ($max_price > 0) {
                $wpdb->replace(
                    $meta_table,
                    array(
                        'discount_id' => $discount_id,
                        'meta_key' => '_edd_discount_max_price',
                        'meta_value' => $max_price
                    ),
                    array('%d', '%s', '%f')
                );
            }

            if ($max_uses_per_user > 0) {
                $wpdb->replace(
                    $meta_table,
                    array(
                        'discount_id' => $discount_id,
                        'meta_key' => '_edd_discount_max_uses_per_user',
                        'meta_value' => $max_uses_per_user
                    ),
                    array('%d', '%s', '%d')
                );
            }

            if (!empty($notes)) {
                $wpdb->replace(
                    $meta_table,
                    array(
                        'discount_id' => $discount_id,
                        'meta_key' => '_edd_discount_notes',
                        'meta_value' => $notes
                    ),
                    array('%d', '%s', '%s')
                );
            }
        }

        // Log success
        if ($mode === 'Inserted') {
            $created_count = $helpers_instance->update_count($hash_key, 'hash_key')['created'];
            $core_instance->detailed_log[$line_number]['Message'] = 'Inserted EDD Discount ID: ' . $discount_id . ' (Code: ' . $code . ')';
            $core_instance->detailed_log[$line_number]['id'] = $discount_id;
            $core_instance->detailed_log[$line_number]['post_type'] = 'edd_discount';
            $core_instance->detailed_log[$line_number]['adminLink'] = admin_url('edit.php?post_type=download&page=edd-discounts&edd-action=edit_discount&discount=' . $discount_id);
            $core_instance->detailed_log[$line_number]['state'] = 'Inserted';
            $wpdb->query($wpdb->prepare("UPDATE $log_table_name SET created = %d WHERE hash_key = %s", $created_count, $hash_key));
        } else {
            $updated_count = $helpers_instance->update_count($hash_key, 'hash_key')['updated'];
            $core_instance->detailed_log[$line_number]['Message'] = 'Updated EDD Discount ID: ' . $discount_id . ' (Code: ' . $code . ')';
            $core_instance->detailed_log[$line_number]['id'] = $discount_id;
            $core_instance->detailed_log[$line_number]['post_type'] = 'edd_discount';
            $core_instance->detailed_log[$line_number]['adminLink'] = admin_url('edit.php?post_type=download&page=edd-discounts&edd-action=edit_discount&discount=' . $discount_id);
            $core_instance->detailed_log[$line_number]['state'] = 'Updated';
            $wpdb->query($wpdb->prepare("UPDATE $log_table_name SET updated = %d WHERE hash_key = %s", $updated_count, $hash_key));
        }

        return array('ID' => $discount_id);
    }

    /**
     * Get or create EDD customer by email
     * 
     * @param string $email Customer email
     * @return int Customer ID
     */
    private function get_or_create_edd_customer($email)
    {
        global $wpdb;
        $customers_table = $wpdb->prefix . "edd_customers";

        // Check if customer exists
        $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $customers_table WHERE email = %s", $email));

        if ($customer) {
            return $customer->id;
        }

        // Get WordPress user if exists
        $user = get_user_by('email', $email);
        $user_id = $user ? $user->ID : 0;
        $name = $user ? $user->display_name : '';

        // Create new customer
        $wpdb->insert(
            $customers_table,
            array(
                'email' => $email,
                'name' => $name,
                'user_id' => $user_id,
                'purchase_count' => 0,
                'purchase_value' => 0,
                'date_created' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%d', '%f', '%s')
        );

        return $wpdb->insert_id;
    }

    /**
     * Update customer purchase statistics
     * 
     * @param int $customer_id Customer ID
     * @param float $amount Purchase amount
     * @return void
     */
    private function update_customer_stats($customer_id, $amount)
    {
        global $wpdb;
        $customers_table = $wpdb->prefix . "edd_customers";

        $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $customers_table WHERE id = %d", $customer_id));

        if ($customer) {
            $new_count = $customer->purchase_count + 1;
            $new_value = floatval($customer->purchase_value) + floatval($amount);

            $wpdb->update(
                $customers_table,
                array(
                    'purchase_count' => $new_count,
                    'purchase_value' => $new_value
                ),
                array('id' => $customer_id),
                array('%d', '%f'),
                array('%d')
            );
        }
    }

}