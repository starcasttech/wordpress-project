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

use Error;
use SureCart\Models\Product;
use SureCart\Models\Price;
use SureCart\Models\Order;
use SureCart\Models\Customer;
use SureCart\Models\Coupon;
use SureCart\Models\Subscription;

class SureCartImport
{

    private static $surecart_instance = null;

    public static function getInstance()
    {
        if (SureCartImport::$surecart_instance === null) {
            SureCartImport::$surecart_instance = new SureCartImport;
        }
        return SureCartImport::$surecart_instance;
    }


    /**
     * Set SureCart values and process import
     *
     * @param array $header_array CSV headers
     * @param array $value_array CSV row values
     * @param array $map Field mapping
     * @param int $post_id Post ID
     * @param string $type Import type
     * @param string $hash_key Import hash key
     * @param string $gmode Import mode
     * @param string $templatekey Template key
     * @param int $line_number Line number
     * @return void
     */
    public function set_surecart_values(
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

        $this->surecart_import_function(
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

    /**
     * Main SureCart import function
     *
     * @param array $data_array Mapped data
     * @param string $importas Import type
     * @param int $pID Post ID
     * @param array $header_array CSV headers
     * @param array $value_array CSV row values
     * @param string $hash_key Import hash key
     * @param string $gmode Import mode
     * @param string $templatekey Template key
     * @param int $line_number Line number
     * @return array Created fields
     */
    public function surecart_import_function(
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
        global $wpdb;

        if (!is_plugin_active('surecart/surecart.php')) {
            return [];
        }

        $createdFields = array_keys($data_array);
        $surecart = [];

        // Map common fields
        $mapping = [
            'product_id' => ['product_id'],
            'product_name' => ['product_name', 'post_title'],
            'post_title' => ['post_title', 'product_name'],
            'post_content' => ['post_content', 'product_description', 'description'],
            'post_excerpt' => ['post_excerpt', 'short_description'],
            'post_status' => ['post_status', 'status'],
            'sku' => ['sku'],
            'price' => ['price'],
            'sale_price' => ['sale_price'],
            'stock_enabled' => ['stock_enabled'],
            'stock_quantity' => ['stock_quantity'],
            'allow_purchase_out_of_stock' => ['allow_purchase_out_of_stock', 'allow_backorders'],
            'tax_enabled' => ['tax_enabled'],
            'tax_status' => ['tax_status'],
            'featured_image' => ['featured_image', 'thumbnail_id'],
            'gallery_images' => ['gallery_images'],
            'product_type' => ['product_type'],
            'recurring_interval' => ['recurring_interval'],
            'recurring_period' => ['recurring_period'],
            'recurring_price' => ['recurring_price'],
            'variants' => ['variants'],
            'product_categories' => ['product_categories'],
            'product_tags' => ['product_tags'],
            'download_files' => ['download_files'],
            'product_meta' => ['product_meta']
        ];

        foreach ($mapping as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($data_array[$alias]) && !empty($data_array[$alias])) {
                    $surecart[$key] = $data_array[$alias];
                    break;
                }
            }
        }

        // Handle product creation/update
        if ($importas === 'SURECART_PRODUCTS') {
            $this->import_product($surecart, $pID, $hash_key, $line_number);
        }

        return $createdFields;
    }

    private function import_product($data, $pID, $hash_key, $line_number)
    {
        global $wpdb;

        if (empty($pID)) {
            return;
        }

        // Prepare product data for SureCart Model
        $product_api_data = [
            'name' => !empty($data['post_title']) ? sanitize_text_field($data['post_title']) : get_the_title($pID),
            'description' => !empty($data['post_content']) ? wp_kses_post($data['post_content']) : '',
        ];

        if (!empty($data['sku'])) {
            $product_api_data['sku'] = sanitize_text_field($data['sku']);
        }

        $sc_id = get_post_meta($pID, 'sc_id', true);
        $product_model = null;
        $response = null;

        try {
            if (!empty($sc_id)) {
                // Update existing SureCart product via manual PATCH
                $response = \SureCart::request("products/{$sc_id}", [
                    'method' => 'PATCH',
                    'body' => [
                        'product' => $product_api_data
                    ]
                ]);
                if (!is_wp_error($response)) {
                    $product_model = new Product($response);
                }
            } else {
                // Create new SureCart product via manual POST
                $response = \SureCart::request('products', [
                    'method' => 'POST',
                    'body' => [
                        'product' => $product_api_data
                    ]
                ]);
                if (!is_wp_error($response)) {
                    $product_model = new Product($response);
                    $sc_id = $product_model->id;
                    update_post_meta($pID, 'sc_id', $sc_id);
                }
            }

            // Sync the model back to the post to ensure all SureCart meta is populated
            if ($product_model && !is_wp_error($product_model)) {
                \SureCart::sync()->product()->sync($product_model);
            }
        } catch (\Exception $e) {
        }

        // If product creation/update failed, log the error details
        if (isset($response) && is_wp_error($response)) {
            if (isset($response->get_error_data()['validation_errors'])) {
            }
        }

        // If product creation/update failed, fall back to basic post updates
        if (!$product_model || is_wp_error($product_model)) {

            if (!empty($data['post_title']) || !empty($data['post_content']) || !empty($data['post_status'])) {
                $update_args = ['ID' => $pID];
                if (!empty($data['post_title']))
                    $update_args['post_title'] = sanitize_text_field($data['post_title']);
                if (!empty($data['post_content']))
                    $update_args['post_content'] = wp_kses_post($data['post_content']);
                if (!empty($data['post_status']))
                    $update_args['post_status'] = sanitize_text_field($data['post_status']);
                wp_update_post($update_args);
            }
        }

        // Handle SKU and other meta
        if (!empty($data['sku'])) {
            update_post_meta($pID, 'sc_sku', sanitize_text_field($data['sku']));
            update_post_meta($pID, 'sku', sanitize_text_field($data['sku']));
        }

        // Handle Price via Model if sc_id exists
        if (!empty($sc_id) && isset($data['price'])) {
            try {
                $amount = intval(floatval($data['price']) * 100); // SureCart uses cents
                $price_api_data = [
                    'product' => $sc_id,
                    'amount' => $amount,
                    'currency' => strtolower(function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'usd'),
                    'name' => 'Default Price',
                ];

                if (!empty($data['recurring_interval']) && !empty($data['recurring_period'])) {
                    $price_api_data['recurring_interval'] = sanitize_text_field($data['recurring_period']);
                    $price_api_data['recurring_interval_count'] = intval($data['recurring_interval']);
                    if (isset($data['recurring_price'])) {
                        $price_api_data['amount'] = intval(floatval($data['recurring_price']) * 100);
                    }
                }

                // Check if we already have prices for this product
                $existing_prices = [];
                if ($product_model && isset($product_model->prices->data)) {
                    $existing_prices = $product_model->prices->data;
                }

                $price_response = null;
                if (!empty($existing_prices)) {
                    // Update the first existing price
                    $price_to_update = $existing_prices[0];
                    $price_response = \SureCart::request("prices/{$price_to_update->id}", [
                        'method' => 'PATCH',
                        'body' => [
                            'price' => $price_api_data
                        ]
                    ]);
                } else {
                    // Create new Price via manual POST
                    $price_response = \SureCart::request('prices', [
                        'method' => 'POST',
                        'body' => [
                            'price' => $price_api_data
                        ]
                    ]);
                }

                if ($price_response && !is_wp_error($price_response)) {
                    // Final sync of the product to make sure everything is linked in WP
                    if ($product_model) {
                        \SureCart::sync()->product()->sync($product_model);
                    }
                }
            } catch (\Exception $e) {
            }
            if (isset($price_response) && is_wp_error($price_response)) {
            }
        }

        // Regular meta updates for compatibility
        if (isset($data['price'])) {
            update_post_meta($pID, 'sc_price', floatval($data['price']));
        }
        if (isset($data['sale_price'])) {
            update_post_meta($pID, 'sc_sale_price', floatval($data['sale_price']));
        }

        // Stock and Tax
        if (isset($data['stock_enabled'])) {
            $stock_enabled = in_array(strtolower($data['stock_enabled']), ['yes', '1', 'true', 'on']) ? 1 : 0;
            update_post_meta($pID, 'sc_stock_enabled', $stock_enabled);
            update_post_meta($pID, 'stock_enabled', $stock_enabled);
        }
        if (isset($data['stock_quantity'])) {
            update_post_meta($pID, 'sc_stock_quantity', intval($data['stock_quantity']));
            update_post_meta($pID, 'stock_quantity', intval($data['stock_quantity']));
        }
        if (isset($data['tax_enabled'])) {
            $tax_enabled = in_array(strtolower($data['tax_enabled']), ['yes', '1', 'true', 'on']) ? 1 : 0;
            update_post_meta($pID, 'sc_tax_enabled', $tax_enabled);
        }

        // Featured Image - Fixed to use proper import type
        if (!empty($data['featured_image'])) {
            $media_instance = MediaHandling::getInstance();
            $attachment_id = $media_instance->image_meta_table_entry(
                $line_number, 
                $data, 
                $pID, 
                '_thumbnail_id', 
                $data['featured_image'], 
                $hash_key, 
                'Featured', 
                'SURECART_PRODUCTS', 
                ''
            );
            if ($attachment_id) {
                set_post_thumbnail($pID, $attachment_id);
            }
        }

        // Gallery Images
        if (!empty($data['gallery_images'])) {
            $gallery_images = array_filter(array_map('trim', explode(',', $data['gallery_images'])));
            $gallery_ids = [];
            $media_instance = MediaHandling::getInstance();
            foreach ($gallery_images as $image_url) {
                $attachment_id = $media_instance->image_meta_table_entry($line_number, '', $pID, 'sc_gallery_images', $image_url, $hash_key, 'surecart', 'post', '');
                if ($attachment_id)
                    $gallery_ids[] = $attachment_id;
            }
            if (!empty($gallery_ids))
                update_post_meta($pID, 'sc_gallery_images', $gallery_ids);
        }

        // Categories and Tags
        if (!empty($data['product_categories'])) {
            $categories = array_map('trim', explode(',', $data['product_categories']));
            wp_set_object_terms($pID, $categories, 'sc_product_category', false);
        }
        if (!empty($data['product_tags'])) {
            $tags = array_map('trim', explode(',', $data['product_tags']));
            wp_set_object_terms($pID, $tags, 'sc_product_tag', false);
        }

        // Download Files
        if (!empty($data['download_files'])) {
            $files = [];
            $file_entries = explode('|', $data['download_files']);
            foreach ($file_entries as $entry) {
                $parts = array_map('trim', explode(',', $entry));
                if (count($parts) >= 2) {
                    $files[] = ['name' => $parts[0], 'file' => $parts[1]];
                }
            }
            if (!empty($files))
                update_post_meta($pID, 'sc_download_files', $files);
        }

        // Custom Meta
        if (!empty($data['product_meta'])) {
            $meta_pairs = explode('|', $data['product_meta']);
            foreach ($meta_pairs as $pair) {
                $parts = array_map('trim', explode(':', $pair, 2));
                if (count($parts) === 2) {
                    update_post_meta($pID, 'sc_' . $parts[0], $parts[1]);
                }
            }
        }

        // CRITICAL FIX: Ensure post status is set to publish after all SureCart operations
        // SureCart sync may override the status to draft, so we explicitly set it here
        // Note: post_status comes from CORE fields, not SURECART_PRODUCTS fields, so we need to get it from the post
        $post = get_post($pID);
        if ($post) {
            // Get the intended status from the post (which was set by CoreFieldsImport)
            // If it's currently draft but was supposed to be publish, fix it
            $current_status = $post->post_status;
            
            // Check if there's a post_status in the data array (from CSV mapping)
            $desired_status = !empty($data['post_status']) ? sanitize_text_field($data['post_status']) : null;
            
            // If no explicit status in data, check what CoreFieldsImport set
            // CoreFieldsImport defaults to 'publish' if no status is provided
            if ($desired_status === null) {
                // If current status is draft, it means SureCart changed it, so set to publish
                if ($current_status === 'draft') {
                    $desired_status = 'publish';
                }
            }
            
            // Apply the fix if needed
            if ($desired_status && $current_status !== $desired_status) {
                $wpdb->update(
                    $wpdb->posts,
                    ['post_status' => $desired_status],
                    ['ID' => $pID],
                    ['%s'],
                    ['%d']
                );
                // Clear post cache to ensure the status change is reflected
                clean_post_cache($pID);
            }
        }
    }

    /**
     * Import SureCart Products
     *
     * @param array $header_array CSV headers
     * @param array $value_array CSV row values
     * @param array $map Field mapping
     * @param int $post_id Post ID
     * @param string $hash_key Import hash key
     * @param string $gmode Import mode
     * @param string $templatekey Template key
     * @param int $line_number Line number
     * @return void
     */
    public function import_products(
        $header_array,
        $value_array,
        $map,
        $post_id,
        $hash_key,
        $gmode,
        $templatekey,
        $line_number
    ) {
        $this->set_surecart_values(
            $header_array,
            $value_array,
            $map,
            $post_id,
            'SURECART_PRODUCTS',
            $hash_key,
            $gmode,
            $templatekey,
            $line_number
        );
    }

    /**
     * Import SureCart Customers
     *
     * @param array $header_array CSV headers
     * @param array $value_array CSV row values
     * @param array $map Field mapping
     * @param string $hash_key Import hash key
     * @param string $gmode Import mode
     * @param string $templatekey Template key
     * @param int $line_number Line number
     * @return void
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
        global $wpdb, $core_instance;

        if (!is_plugin_active('surecart/surecart.php')) {
            return;
        }

        $helpers_instance = ImportHelpers::getInstance();
        $log_table_name = $wpdb->prefix . "import_detail_log";

        // Get mapped values (pass hash_key for proper column resolution)
        $customer_data = $helpers_instance->get_header_values($map, $header_array, $value_array, $hash_key);

        // Normalize: map may use 'customer_email' or 'Customer Email' as key
        $customer_data = $this->normalize_customer_map_keys($customer_data);

        $customer_email = isset($customer_data['customer_email']) ? sanitize_email($customer_data['customer_email']) : '';
        if (empty($customer_email)) {
            if (isset($core_instance)) {
                $core_instance->detailed_log[$line_number] = [
                    'Message' => 'Skipped: Customer email is required',
                    'state' => 'Skipped'
                ];
            }
            return;
        }

        // Use our helper to get or create SureCart customer (Model-based)
        $customer_post_id = $this->get_or_create_surecart_customer($customer_email, $customer_data);

        if (!$customer_post_id) {
            if (isset($core_instance)) {
                $core_instance->detailed_log[$line_number] = [
                    'Message' => 'Failed: Could not create customer',
                    'state' => 'Failed'
                ];
            }
            return;
        }

        // Update additional customer meta for CSV importer compatibility
        $meta_fields = [
            'first_name' => 'sc_first_name',
            'last_name' => 'sc_last_name',
            'phone_number' => 'sc_phone_number',
            'purchase_count' => 'sc_purchase_count',
            'lifetime_value' => 'sc_lifetime_value',
            'notes' => 'sc_notes'
        ];

        foreach ($meta_fields as $key => $meta_key) {
            if (isset($customer_data[$key])) {
                update_post_meta($customer_post_id, $meta_key, sanitize_text_field($customer_data[$key]));
            }
        }

        // Handle addresses
        foreach (['billing'] as $type) {
            foreach (['address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country'] as $field) {
                $data_key = $type . '_' . $field;
                if (!empty($customer_data[$data_key])) {
                    update_post_meta($customer_post_id, 'sc_' . $data_key, sanitize_text_field($customer_data[$data_key]));
                }
            }
        }

        // Update import log
        $update_count = $helpers_instance->update_count($hash_key, 'hash_key');
        $wpdb->query($wpdb->prepare(
            "UPDATE $log_table_name SET created = %d WHERE hash_key = %s",
            $update_count,
            $hash_key
        ));

        // Update detailed log for UI feedback
        if (isset($core_instance)) {
            $core_instance->detailed_log[$line_number] = [
                'Message' => 'Created SureCart Customer ID: ' . $customer_post_id . ' (' . $customer_email . ')',
                'state' => 'Created',
                'id' => $customer_post_id,
                'status' => 'publish',
                'adminLink' => get_edit_post_link($customer_post_id, true)
            ];
        }

    }

    /**
     * Import SureCart Coupons
     *
     * @param array $header_array CSV headers
     * @param array $value_array CSV row values
     * @param array $map Field mapping
     * @param string $hash_key Import hash key
     * @param string $gmode Import mode
     * @param string $templatekey Template key
     * @param int $line_number Line number
     * @return void
     */
    public function import_coupons(
        $header_array,
        $value_array,
        $map,
        $hash_key,
        $gmode,
        $templatekey,
        $line_number
    ) {
        global $wpdb, $core_instance;

        if (!is_plugin_active('surecart/surecart.php')) {
            return;
        }

        $helpers_instance = ImportHelpers::getInstance();
        $log_table_name = $wpdb->prefix . "import_detail_log";

        // Get mapped values
        $coupon_data = $helpers_instance->get_header_values($map, $header_array, $value_array);

        $coupon_code = isset($coupon_data['coupon_code']) ? sanitize_text_field($coupon_data['coupon_code']) : '';

        // Fallback: If coupon_code is empty but promotion_codes is present, use it as the code.
        if (empty($coupon_code) && !empty($coupon_data['promotion_codes'])) {
            $coupon_code = sanitize_text_field($coupon_data['promotion_codes']);
        }

        if (empty($coupon_code)) {
            if (isset($core_instance)) {
                $core_instance->detailed_log[$line_number] = [
                    'Message' => 'Skipped: Coupon code is required',
                    'state' => 'Skipped'
                ];
            }
            return;
        }


        // Prepare data for SureCart Model
        $coupon_api_data = [
            'name' => $coupon_code,
            'code' => $coupon_code,
        ];

        if (!empty($coupon_data['promotion_codes'])) {
            $promo_code = sanitize_text_field($coupon_data['promotion_codes']);
            $coupon_api_data['promotion_code'] = $promo_code;
            // If promotion_codes is explicit, use it as the main code as well
            $coupon_api_data['code'] = $promo_code;
        }


        // Map discount type
        $discount_type = isset($coupon_data['discount_type']) ? $coupon_data['discount_type'] : 'percentage';
        if ($discount_type === 'fixed') {
            $coupon_api_data['promotion_type'] = 'fixed_amount';
            $coupon_api_data['amount_off'] = isset($coupon_data['discount_amount']) ? intval(floatval($coupon_data['discount_amount']) * 100) : 0;
            $coupon_api_data['fixed_amount_currency'] = strtolower(function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'usd');
        } else {
            $coupon_api_data['promotion_type'] = 'percent'; // NOTE: API might expect 'percent' not 'percentage' for type too? Let's check error log again.
            $coupon_api_data['percent_off'] = isset($coupon_data['discount_amount']) ? floatval($coupon_data['discount_amount']) : 0;
        }

        // Additional Model fields
        if (isset($coupon_data['usage_limit'])) {
            $coupon_api_data['max_redemptions'] = intval($coupon_data['usage_limit']);
        }
        if (!empty($coupon_data['end_date'])) {
            $coupon_api_data['redeem_by'] = strtotime($coupon_data['end_date']);
        }
        if (!empty($coupon_data['promotion_codes'])) {
            $coupon_api_data['promotion_code'] = sanitize_text_field($coupon_data['promotion_codes']);
        }
        if (isset($coupon_data['usage_limit_per_user'])) {
            $coupon_api_data['max_redemptions_per_customer'] = intval($coupon_data['usage_limit_per_user']);
        }
        if (!empty($coupon_data['minimum_amount'])) {
            $coupon_api_data['min_subtotal_amount'] = intval(floatval($coupon_data['minimum_amount']) * 100);
            $currency = function_exists('get_woocommerce_currency') ? \get_woocommerce_currency() : 'USD';
            $coupon_api_data['min_subtotal_amount_currency'] = strtolower($currency);
        }
        if (!empty($coupon_data['maximum_amount'])) {
            $coupon_api_data['max_subtotal_amount'] = intval(floatval($coupon_data['maximum_amount']) * 100);
            $currency = function_exists('get_woocommerce_currency') ? \get_woocommerce_currency() : 'USD';
            $coupon_api_data['max_subtotal_amount_currency'] = strtolower($currency);
        }

        $sc_coupon = null;
        try {
            $sc_coupon = Coupon::create($coupon_api_data);
            if (is_wp_error($sc_coupon)) {
            } else {
            }
        } catch (\Exception $e) {
        }

        // Create coupon post
        $coupon_post = [
            'post_type' => 'sc_coupon',
            'post_status' => 'publish',
            'post_title' => $coupon_code
        ];

        $coupon_id = wp_insert_post($coupon_post);

        if (is_wp_error($coupon_id) || !$coupon_id) {
            if (isset($core_instance)) {
                $core_instance->detailed_log[$line_number] = [
                    'Message' => 'Failed: Could not create coupon post',
                    'state' => 'Failed'
                ];
            }
            return;
        }

        // Store SureCart ID
        if (!is_wp_error($sc_coupon) && isset($sc_coupon->id)) {
            update_post_meta($coupon_id, 'sc_id', $sc_coupon->id);
        }

        // Update coupon meta for compatibility
        $meta_fields = [
            'coupon_code' => 'sc_coupon_code',
            'discount_type' => 'sc_discount_type',
            'discount_amount' => 'sc_discount_amount',
            'status' => 'sc_status',
            'usage_limit' => 'sc_usage_limit',
            'usage_count' => 'sc_usage_count',
            'usage_limit_per_user' => 'sc_usage_limit_per_user',
            'start_date' => 'sc_start_date',
            'end_date' => 'sc_end_date',
            'minimum_amount' => 'sc_minimum_amount',
            'maximum_amount' => 'sc_maximum_amount',
            'first_order_only' => 'sc_first_order_only',
            'promotion_codes' => 'sc_promotion_code'
        ];

        foreach ($meta_fields as $key => $meta_key) {
            if (isset($coupon_data[$key])) {
                update_post_meta($coupon_id, $meta_key, sanitize_text_field($coupon_data[$key]));
            }
        }

        // Update import log
        $update_count = $helpers_instance->update_count($hash_key, 'hash_key');
        $wpdb->query($wpdb->prepare(
            "UPDATE $log_table_name SET created = %d WHERE hash_key = %s",
            $update_count,
            $hash_key
        ));

        // Update detailed log for UI feedback
        if (isset($core_instance)) {
            $core_instance->detailed_log[$line_number] = [
                'Message' => 'Created SureCart Coupon ID: ' . $coupon_id . ' (' . $coupon_code . ')',
                'state' => 'Created',
                'id' => $coupon_id,
                'status' => 'publish',
                'adminLink' => get_edit_post_link($coupon_id, true)
            ];
        }

    }

    /**
     * Normalize order_data keys - map may use 'Customer Email' (label) or 'customer_email' (field key)
     *
     * @param array $data Raw data from get_header_values
     * @return array Normalized with field keys
     */
    private function normalize_order_map_keys($data)
    {
        if (!is_array($data)) {
            return [];
        }
        $label_to_key = [
            'SC ID' => 'sc_id',
            'Order ID' => 'order_id',
            'Order Number' => 'order_number',
            'Order Status' => 'order_status',
            'Order Date' => 'order_date',
            'Customer Email' => 'customer_email',
            'Customer ID' => 'customer_id',
            'Customer Name' => 'customer_name',
            'Billing First Name' => 'billing_first_name',
            'Billing Last Name' => 'billing_last_name',
            'Billing Address Line 1' => 'billing_address_line_1',
            'Billing Address Line 2' => 'billing_address_line_2',
            'Billing City' => 'billing_city',
            'Billing State' => 'billing_state',
            'Billing Postal Code' => 'billing_postal_code',
            'Billing Country' => 'billing_country',
            'Shipping First Name' => 'shipping_first_name',
            'Shipping Last Name' => 'shipping_last_name',
            'Shipping Address Line 1' => 'shipping_address_line_1',
            'Shipping Address Line 2' => 'shipping_address_line_2',
            'Shipping City' => 'shipping_city',
            'Shipping State' => 'shipping_state',
            'Shipping Postal Code' => 'shipping_postal_code',
            'Shipping Country' => 'shipping_country',
            'Order Total' => 'total',
            'Currency' => 'currency',
            'Subtotal' => 'subtotal',
            'Tax Amount' => 'tax_amount',
            'Discount Amount' => 'discount_amount',
            'Shipping Amount' => 'shipping_amount',
            'Payment Method' => 'payment_method',
            'Payment Status' => 'payment_status',
            'Transaction ID' => 'transaction_id',
            'Line Items' => 'line_items',
            'Order Meta' => 'order_meta',
            'Subscription ID' => 'subscription_id',
            'Coupon Code' => 'coupon_code',
        ];
        $normalized = [];
        foreach ($label_to_key as $label => $field_key) {
            $value = $data[$field_key] ?? $data[$label] ?? null;
            if ($value !== '' && $value !== null) {
                $normalized[$field_key] = $value;
            }
        }
        $known_keys = array_merge(array_keys($label_to_key), array_values($label_to_key));
        foreach ($data as $key => $value) {
            if (!in_array($key, $known_keys) && ($value !== '' && $value !== null)) {
                $normalized[$key] = $value;
            }
        }
        return $normalized;
    }

    /**
     * Normalize customer_data keys - map may use 'Customer Email' (label) or 'customer_email' (field key)
     *
     * @param array $data Raw data from get_header_values
     * @return array Normalized with lowercase field keys
     */
    private function normalize_customer_map_keys($data)
    {
        if (!is_array($data)) {
            return [];
        }
        $label_to_key = [
            'SC ID' => 'sc_id',
            'Customer ID' => 'customer_id',
            'Customer Email' => 'customer_email',
            'Customer Name' => 'customer_name',
            'First Name' => 'first_name',
            'Last Name' => 'last_name',
            'User ID' => 'user_id',
            'User Login' => 'user_login',
            'User Email' => 'user_email',
            'Billing Address Line 1' => 'billing_address_line_1',
            'Billing Address Line 2' => 'billing_address_line_2',
            'Billing City' => 'billing_city',
            'Billing State' => 'billing_state',
            'Billing Postal Code' => 'billing_postal_code',
            'Billing Country' => 'billing_country',
            'Phone Number' => 'phone_number',
            'Purchase Count' => 'purchase_count',
            'Lifetime Value' => 'lifetime_value',
            'Date Created' => 'date_created',
            'Customer Meta' => 'customer_meta',
            'Order IDs' => 'order_ids',
            'Notes' => 'notes',
        ];
        $normalized = [];
        foreach ($label_to_key as $label => $field_key) {
            $value = $data[$field_key] ?? $data[$label] ?? null;
            if ($value !== '' && $value !== null) {
                $normalized[$field_key] = $value;
            }
        }
        $known_keys = array_merge(array_keys($label_to_key), array_values($label_to_key));
        foreach ($data as $key => $value) {
            if (!in_array($key, $known_keys) && ($value !== '' && $value !== null)) {
                $normalized[$key] = $value;
            }
        }
        return $normalized;
    }

    /**
     * Get or create SureCart customer
     *
     * @param string $email Customer email
     * @param array $customer_data Additional customer data
     * @return int Customer ID
     */
    private function get_or_create_surecart_customer($email, $customer_data = [])
    {
        global $wpdb;

        if (empty($email)) {
            return 0;
        }

        $local_post_id = 0;
        // Try to find existing customer by UUID (sc_id) if provided in CSV
        if (!empty($customer_data['customer_id'])) {
            $local_post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'sc_id' AND meta_value = %s LIMIT 1",
                sanitize_text_field($customer_data['customer_id'])
            ));
        }

        // Fallback to email lookup if ID lookup found nothing
        if (!$local_post_id) {
            $local_post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'sc_customer_email' AND meta_value = %s LIMIT 1",
                $email
            ));
        }

        if ($local_post_id) {
            $current_sc_id = get_post_meta($local_post_id, 'sc_id', true);
            // If we have sc_id locally, verify cloud customer still exists (SureCart UI fetches from API)
            if (!empty($current_sc_id)) {
                $cloud_customer = null;
                try {
                    $cloud_customer = Customer::find($current_sc_id);
                } catch (\Throwable $e) {
                    // Ignore - will create new
                }
                if ($cloud_customer && !is_wp_error($cloud_customer)) {
                    return intval($local_post_id);
                }
                // Cloud customer missing (deleted?) - fall through to create
            }
        }

        // Prepare data for SureCart Model
        $customer_api_data = [
            'email' => $email,
            'first_name' => !empty($customer_data['first_name']) ? sanitize_text_field($customer_data['first_name']) : '',
            'last_name' => !empty($customer_data['last_name']) ? sanitize_text_field($customer_data['last_name']) : '',
        ];

        if (!empty($customer_data['customer_name'])) {
            $customer_api_data['name'] = sanitize_text_field($customer_data['customer_name']);
            $names = explode(' ', $customer_data['customer_name'], 2);
            if (empty($customer_api_data['first_name']))
                $customer_api_data['first_name'] = $names[0];
            if (empty($customer_api_data['last_name']) && isset($names[1]))
                $customer_api_data['last_name'] = $names[1];
        }

        $sc_customer = null;
        try {
            $sc_customer = Customer::create($customer_api_data);
        } catch (\Exception $e) {
            $sc_customer = new \WP_Error('exception', $e->getMessage());
        }

        // Fallback: If creation fails, try to find existing customer in SureCart
        if (is_wp_error($sc_customer)) {
            try {
                $query_args = ['email' => $email, 'limit' => 50];
                $existing_customers = Customer::get($query_args);
                if (is_array($existing_customers)) {
                    foreach ($existing_customers as $cust) {
                        if (isset($cust->email) && strtolower($cust->email) === strtolower($email)) {
                            $sc_customer = $cust;
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
            }
        }

        // Handle WordPress Post (Create or Update)
        if ($local_post_id) {
            $customer_id = $local_post_id;
            // Optionally update title if current is email-based but name is available
            $post_data = ['ID' => $customer_id];
            if (!empty($customer_data['customer_name'])) {
                $post_data['post_title'] = $customer_data['customer_name'];
                wp_update_post($post_data);
            }
        } else {
            $customer_post = [
                'post_type' => 'sc_customer',
                'post_status' => 'publish',
                'post_title' => !empty($customer_data['customer_name']) ? $customer_data['customer_name'] : $email
            ];
            $customer_id = wp_insert_post($customer_post);
        }

        if ($customer_id && !is_wp_error($customer_id)) {
            update_post_meta($customer_id, 'sc_customer_email', $email);
            if (!empty($customer_data['customer_name'])) {
                update_post_meta($customer_id, 'sc_customer_name', $customer_data['customer_name']);
            }

            // Store SureCart ID if returned by API
            if (!is_wp_error($sc_customer) && isset($sc_customer->id)) {
                update_post_meta($customer_id, 'sc_id', $sc_customer->id);
            } elseif (!empty($customer_data['customer_id'])) {
                // Final fallback: Use the ID provided in the CSV
                update_post_meta($customer_id, 'sc_id', sanitize_text_field($customer_data['customer_id']));
            }

            // Set first and last names if available
            if (!empty($customer_api_data['first_name'])) {
                update_post_meta($customer_id, 'sc_first_name', $customer_api_data['first_name']);
            }
            if (!empty($customer_api_data['last_name'])) {
                update_post_meta($customer_id, 'sc_last_name', $customer_api_data['last_name']);
            }

            // Addresses
            foreach (['billing', 'shipping'] as $type) {
                if (!empty($customer_data[$type . '_address_line_1'])) {
                    update_post_meta($customer_id, 'sc_' . $type . '_address', [
                        'first_name' => $customer_data[$type . '_first_name'] ?? $customer_api_data['first_name'],
                        'last_name' => $customer_data[$type . '_last_name'] ?? $customer_api_data['last_name'],
                        'line_1' => $customer_data[$type . '_address_line_1'],
                        'line_2' => $customer_data[$type . '_address_line_2'] ?? '',
                        'city' => $customer_data[$type . '_city'] ?? '',
                        'state' => $customer_data[$type . '_state'] ?? '',
                        'postal_code' => $customer_data[$type . '_postal_code'] ?? '',
                        'country' => $customer_data[$type . '_country'] ?? 'US',
                    ]);
                }
            }
        }

        return $customer_id ? intval($customer_id) : 0;
    }

    /**
     * Extract cloud order ID from manually_pay API response.
     * Orders appear in SureCart Orders UI only when they have a cloud order ID.
     *
     * @param object $response manually_pay API response (checkout with possible order relation)
     * @param string $checkout_id Fallback checkout ID if order not found
     * @return string|null Order ID or null
     */
    private function extract_order_id_from_manually_pay_response($response, $checkout_id = '')
    {
        if (empty($response)) {
            return null;
        }
        // Order can be nested: response->order->id or response->order (string id)
        if (!empty($response->order)) {
            $order = $response->order;
            if (is_object($order) && !empty($order->id)) {
                return $order->id;
            }
            if (is_string($order)) {
                return $order;
            }
        }
        if (!empty($response->order_id)) {
            return $response->order_id;
        }
        // Checkout ID is not the order ID - but if API returns checkout as paid with order embedded
        if (!empty($response->attributes['order'])) {
            $order = $response->attributes['order'];
            return is_object($order) ? ($order->id ?? null) : $order;
        }
        return null;
    }

    /**
     * Get Price ID by SKU
     * 
     * @param string $sku Product SKU
     * @return string|false Price ID or false if not found
     */
    public function get_price_id_by_sku($sku)
    {
        $result = $this->get_price_id_and_currency_by_sku($sku);
        return $result ? $result['price_id'] : false;
    }

    /**
     * Get Price ID and Currency by SKU
     * 
     * @param string $sku Product SKU
     * @return array|false Array with 'price_id' and 'currency', or false if not found
     */
    public function get_price_id_and_currency_by_sku($sku)
    {
        static $price_cache = [];
        if (isset($price_cache[$sku])) {
            return $price_cache[$sku];
        }

        global $wpdb;
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key IN ('sc_sku', 'sku') AND meta_value = %s LIMIT 1",
            $sku
        ));

        // Fallback: If SKU resolution fails and it looks like price_N, try to treat N as a Post ID
        if (!$product_id && strpos($sku, 'price_') === 0) {
            $numeric_id = substr($sku, 6);
            if (is_numeric($numeric_id)) {
                $product_id = intval($numeric_id);
                // Verify it is actually a product
                $post_type = get_post_type($product_id);
                if ($post_type !== 'sc_product') {
                    $product_id = 0;
                }
            }
        }

        if (!$product_id) {
            return false;
        }

        $sc_product_id = get_post_meta($product_id, 'sc_id', true);
        if (!$sc_product_id) {
            return false;
        }

        try {
            // Verify class exists before calling
            if (class_exists('\SureCart\Models\Product')) {
                $product = \SureCart\Models\Product::with(['prices'])->find($sc_product_id);
                if ($product && !is_wp_error($product) && !empty($product->prices->data)) {
                    $price_data = $product->prices->data[0];
                    $result = [
                        'price_id' => $price_data->id,
                        'currency' => $price_data->currency ?? ''
                    ];
                    $price_cache[$sku] = $result;
                    return $result;
                }
            }
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * Get first available price from store (for order import fallback when no line items)
     * Ensures imported orders can be created via SureCart API and appear in Orders UI
     *
     * @return array|false Array with 'price_id', 'currency', 'amount' or false
     */
    private function get_first_available_price_for_import()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        global $wpdb;

        // Prefer "Simple" products (no variants) - they are purchasable for manually_pay
        $product_post_id = $wpdb->get_var(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'sc_id'
             WHERE p.post_type = 'sc_product' AND p.post_status = 'publish'
             AND (p.post_title LIKE '%Simple Physical%' OR p.post_title LIKE '%Simple Installment%')
             LIMIT 1"
        );
        if (!$product_post_id) {
            $product_post_id = $wpdb->get_var(
                "SELECT p.ID FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'sc_id'
                 WHERE p.post_type = 'sc_product' AND p.post_status = 'publish'
                 LIMIT 1"
            );
        }

        if (!$product_post_id) {
            return false;
        }

        $sc_product_id = get_post_meta($product_post_id, 'sc_id', true);
        if (!$sc_product_id) {
            return false;
        }

        try {
            if (!class_exists('\SureCart\Models\Product')) {
                return false;
            }
            $product = \SureCart\Models\Product::with(['prices'])->find($sc_product_id);
            if (!$product || is_wp_error($product) || empty($product->prices->data)) {
                return false;
            }

            $price_data = $product->prices->data[0];
            $result = [
                'price_id' => $price_data->id,
                'currency' => $price_data->currency ?? 'usd',
                'amount' => $price_data->amount ?? 0,
            ];
            $cached = $result;
            return $result;
        } catch (\Exception $e) {
        }

        return false;
    }
}

