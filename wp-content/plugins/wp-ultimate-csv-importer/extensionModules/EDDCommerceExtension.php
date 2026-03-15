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

class EDDCommerceExtension extends ExtensionHandler
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
     *
     * @param string $data - selected import type
     * @return array - mapping fields per module
     */
    public function processExtension($data)
    {

        $import_type = $this->import_name_as($data);

        $eddFields = [];
        $resultKey = '';

        switch ($import_type) {

            case 'EDD_DOWNLOADS':
                $eddFields = [
                    'Featured Image' => 'thumbnail_id',
                    'Price' => 'price',
                    'Variable Pricing' => 'variable_pricing',
                    'Variable Prices' => 'variable_prices',
                    'Default Price ID' => 'default_price_id',
                    'Price Options Mode' => 'price_options_mode',
                    'Download Sales' => 'download_sales',
                    'Download Earnings' => 'download_earnings',
                    'Download Limit' => 'download_limit',
                    'Refund Window' => 'refund_window',
                    'Hide Purchase Link' => 'hide_purchase_link',
                    'Button Behavior' => 'button_behavior',
                    'SKU' => 'sku',
                    'Product Type' => 'product_type',
                    'Featured' => 'featured',
                    'Feature Download' => 'feature_download',
                    'Product Notes' => 'product_notes',
                    'Download Files' => 'download_files',
                    'Bundled Products Conditions' => 'bundled_products_conditions',
                    'Bundled Products' => 'bundled_products',
                    'Edit Lock' => 'edit_lock',
                    'Edit Last' => 'edit_last',
                    'FAQ' => 'faq',
                    'Download Category' => 'download_category',
                    'Download Tag' => 'download_tag',
                ];
                $resultKey = 'EDD_DOWNLOADS';
                break;


            case 'EDD_CUSTOMERS':
                $eddFields = [
                    'Customer ID' => 'id',
                    'Customer Email' => 'email',
                    'Customer Name' => 'name',
                    'First Name' => 'first_name',
                    'Last Name' => 'last_name',
                    'User ID' => 'user_id',
                    'Username' => 'username',
                    'User Status' => 'user_status',
                    'Customer Status' => 'status',
                    'Phone Number' => 'phone',
                    'Address Line 1' => 'address',
                    'Address Line 2' => 'address2',
                    'City' => 'city',
                    'Region' => 'region',
                    'Postal Code' => 'postal_code',
                    'Country' => 'country',
                    'Purchase Count' => 'purchase_count',
                    'Lifetime Value' => 'purchase_value',
                    'Date Created' => 'date_created',
                    'Order IDs' => 'order_ids',
                    'Notes' => 'notes',
                ];

                $resultKey = 'EDD_CUSTOMERS';
                break;

            case 'EDD_DISCOUNTS':
                $eddFields = [
                    'Discount Code' => 'code',
                    'Discount Name' => 'name',
                    'Discount Amount' => 'amount',
                    'Discount Type' => 'type',
                    'Discount Status' => 'status',
                    'Start Date' => 'start_date',
                    'End Date' => 'end_date',
                    'Usage Limit' => 'max_uses',
                    'Use Count' => 'use_count',
                    'Product Requirements' => 'product_requirements',
                    'Product Condition' => 'product_condition',
                    'Excluded Products' => 'excluded_products',
                    'Category Requirements' => 'category_requirements',
                    'Min Price' => 'min_price',
                    'Max Uses Per User' => 'max_uses_per_user',
                    'Discount Notes' => 'notes'
                ];
                $resultKey = 'EDD_DISCOUNTS';
                break;

            default:
                return [];
        }

        $mapping_array = $this->convert_static_fields_to_array($eddFields);

        return [
            $resultKey => $mapping_array
        ];
    }

    /**
     * EDD extension supported import types
     *
     * @param string $import_type
     * @return boolean
     */
    public function extensionSupportedImportType($import_type)
    {

        if (!is_plugin_active('easy-digital-downloads/easy-digital-downloads.php')) {
            return false;
        }

        if ($import_type === 'nav_menu_item') {
            return false;
        }

        $import_type = $this->import_name_as($import_type);
        $supported = [
            'EDD_DOWNLOADS',
            'EDD_ORDERS',
            'EDD_CUSTOMERS',
            'EDD_DISCOUNTS'
        ];

        $is_supported = in_array($import_type, $supported, true);

        return $is_supported;
    }
}
