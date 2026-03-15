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

class SureCartCommerceExtension extends ExtensionHandler
{

    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Process SureCart extension and return field mappings
     *
     * @param string $data - selected import type
     * @return array - mapping fields per module
     */
    public function processExtension($data)
    {

        $import_type = $this->import_name_as($data);
        // error_log(PHP_EOL.'data : '.$data.PHP_EOL,3,WP_CONTENT_DIR.'/free.log');


        $surecartFields = [];
        $resultKey = '';

        switch ($import_type) {

            case 'SURECART_PRODUCTS':
                $surecartFields = [
                    'Product ID' => 'product_id',
                    'Product Name' => 'product_name',
                    'Product Title' => 'post_title',
                    'Product Description' => 'post_content',
                    'Product Excerpt' => 'post_excerpt',
                    'Product Status' => 'post_status',
                    'SKU' => 'sku',
                    'Price' => 'price',
                    'Sale Price' => 'sale_price',
                    'Stock Enabled' => 'stock_enabled',
                    'Stock Quantity' => 'stock_quantity',
                    'Allow Backorders' => 'allow_purchase_out_of_stock',
                    'Tax Enabled' => 'tax_enabled',
                    'Tax Status' => 'tax_status',
                    'Featured Image' => 'featured_image',
                    'Gallery Images' => 'gallery_images',
                    'Product Type' => 'product_type',
                    'Recurring Interval' => 'recurring_interval',
                    'Recurring Period' => 'recurring_period',
                    'Recurring Price' => 'recurring_price',
                    'Variants' => 'variants',
                    'Product Categories' => 'product_categories',
                    'Product Tags' => 'product_tags',
                    'Short Description' => 'short_description',
                    'Purchase Link Text' => 'purchase_link_text',
                    'Purchase Link URL' => 'purchase_link_url',
                    'Download Files' => 'download_files',
                    'Product Meta' => 'product_meta'
                ];
                $resultKey = 'SURECART_PRODUCTS';
                break;

            case 'SURECART_CUSTOMERS':
                $surecartFields = [
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
                    'Notes' => 'notes'
                ];
                $resultKey = 'SURECART_CUSTOMERS';
                break;

            case 'SURECART_COUPONS':
                $surecartFields = [
                    'Coupon ID' => 'coupon_id',
                    'Coupon Code' => 'coupon_code',
                    'Promotion Codes' => 'promotion_codes',
                    'Discount Type' => 'discount_type',
                    'Discount Amount' => 'discount_amount',
                    'Status' => 'status',
                    'Usage Limit' => 'usage_limit',
                    'Usage Count' => 'usage_count',
                    'Usage Limit Per User' => 'usage_limit_per_user',
                    'Start Date' => 'start_date',
                    'End Date' => 'end_date',
                    'Minimum Amount' => 'minimum_amount',
                    'Maximum Amount' => 'maximum_amount',
                    'First Order Only' => 'first_order_only',
                    'Applies To' => 'applies_to',
                    'Duration' => 'duration',
                    'Duration In Months' => 'duration_in_months',
                    'Product Requirements' => 'product_requirements',
                    'Excluded Products' => 'excluded_products',
                    'Category Requirements' => 'category_requirements',
                    'Excluded Categories' => 'excluded_categories',
                    'Currency' => 'currency',
                    'Archived' => 'archived',
                    'Coupon Meta' => 'coupon_meta'
                ];
                $resultKey = 'SURECART_COUPONS';
                break;


            default:
                return [];
        }

        $mapping_array = $this->convert_static_fields_to_array($surecartFields);

        return [
            $resultKey => $mapping_array
        ];
    }

    /**
     * SureCart extension supported import types
     *
     * @param string $import_type
     * @return boolean
     */
    public function extensionSupportedImportType($import_type)
    {

        if (!is_plugin_active('surecart/surecart.php')) {
            return false;
        }

        if ($import_type === 'nav_menu_item') {
            return false;
        }

        $import_type = $this->import_name_as($import_type);
        $supported = [
            'SURECART_PRODUCTS',
            'SURECART_CUSTOMERS',
            'SURECART_COUPONS'
        ];
        // error_log('import_type : '.$import_type,3,WP_CONTENT_DIR.'/free.log');

        $is_supported = in_array($import_type, $supported, true);

        return $is_supported;
    }
}

