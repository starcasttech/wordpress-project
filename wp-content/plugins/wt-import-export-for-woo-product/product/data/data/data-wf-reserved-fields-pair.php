<?php
if (!defined('WPINC')) {
    exit;
}

// Reserved column names
$wc_weight_unit = get_option('woocommerce_weight_unit');
$wc_dimension_unit = get_option('woocommerce_dimension_unit');
// Reserved column names
$post_columns =  array(
                'post_title' => array('title'=>'Product name','description'=>'Product Title. ie Name of the product', 'field_type' => 'alternates', 'similar_fields' => array('Name', 'Title')),
                'post_name' => array('title'=>'Product permalink','description'=>'Unique part of the product URL', 'field_type' => 'alternates', 'similar_fields' => array('slug')),
                'ID' => array('title'=>'ID','description'=>'Product ID'),
                'post_parent' => array('title'=>'Parent ID','description'=>'Parent Product ID , if you are importing variation Product'),
                'post_status' => array('title'=>'Status','description'=>'Product Status ( published , draft ...)', 'field_type' => 'alternates', 'similar_fields' => array('Published')),
                'post_content' => array('title'=>'Description','description'=>'Description about the Product', 'field_type' => 'alternates', 'similar_fields' => array('Description')),
                'post_excerpt' => array('title'=>'Short description','description'=>'Short description about the Product', 'field_type' => 'alternates', 'similar_fields' => array('Short description')),
                'post_date' => array('title'=>'Post date','description'=>'Product posted date', 'type' => 'date'),
                'post_password' => array('title'=>'Post password','description'=>'To Protect a post with password'),
                'post_author' => array('title'=>'Product author','description'=>'Product Author ( 1 - Admin )'),
                'menu_order' => array('title'=>'Menu order','description'=>'If menu enabled , menu order'),
                'comment_status' => array('title'=>'Comment status','description'=>'Comment Status ( Open or Closed comments for this prodcut)', 'field_type' => 'alternates', 'similar_fields' => array('Allow customer reviews?')),
                //'post_date_gmt' => array('title'=>'Post Date GMT','description'=>'Tooltip data Status'),
                                
                'sku' => array('title'=>'SKU','description'=>'Product SKU - This will unique and Product identifier'),
                'parent_sku' => array('title'=>'Parent SKU','description'=>'Parent Product SKU , if you are importing variation Product'),                
				'children' => array('title'=>'Child product ID','description'=>'Linked Products id if you are importing Grouped products'),
                'downloadable' => array('title'=>'Type: Downloadable','description'=>'Is Product is downloadable eg:- Book'),
                'virtual' => array('title'=>'Type: Virtual','description'=>'Is Product is virtual'),
                //'visibility' => array('title'=>'Visibility: Visibility','description'=>'Visibility status ( hidden or visible)', 'field_type' => 'alternates', 'similar_fields' => array('Visibility in catalog')),  
                'featured' => array('title'=>'Visibility: Featured','description'=>'Featured Product'),
                'purchase_note' => array('title'=>'Purchase note','description'=>'Purchase note', 'field_type' => 'alternates', 'similar_fields' => array('Purchase note')),
                'stock' => array('title'=>'Inventory: Stock','description'=>'Stock quantity'),
                'stock_status' => array('title'=>'Inventory: Stock status','description'=>'InStock or OutofStock', 'field_type' => 'alternates', 'similar_fields' => array('In stock?')),
                'backorders' => array('title'=>'Inventory: Backorders','description'=>'Backorders', 'field_type' => 'alternates', 'similar_fields' => array('Backorders allowed?')),
                'sold_individually' => array('title'=>'Inventory: Sold individually','description'=>'Sold individually', 'field_type' => 'alternates', 'similar_fields' => array('Sold individually?')),
                'low_stock_amount' => array('title'=>'Inventory: Low stock amount','description'=>'Low stock amount', 'field_type' => 'alternates', 'similar_fields' => array('Low stock amount')),
                'manage_stock' => array('title'=>'Inventory: Manage stock','description'=>'yes to enable no to disable'),
                'sale_price' => array('title'=>'Price: sale price','description'=>'Sale Price', 'field_type' => 'alternates', 'similar_fields' => array('Sale price')),
                'regular_price' => array('title'=>'Price: regular price','description'=>'Regular Price', 'field_type' => 'alternates', 'similar_fields' => array('Regular price')),
                'sale_price_dates_from' => array('title'=>'Sale price dates: From','description'=>'Sale Price Dates effect from', 'type' => 'date', 'field_type' => 'alternates', 'similar_fields' => array('Date sale price starts')),
                'sale_price_dates_to' => array('title'=>'Sale price dates: To','description'=>'Sale Price Dates effect to', 'type' => 'date', 'field_type' => 'alternates', 'similar_fields' => array('Date sale price ends')),
                'weight' => array('title'=>'Dimensions: Weight','description'=>'Wight of product in LB , OZ , KG as of your woocommerce Unit', 'field_type' => 'alternates', 'similar_fields' => array("Weight ($wc_weight_unit)")),
                'length' => array('title'=>'Dimensions: Length','description'=>'Length', 'field_type' => 'alternates', 'similar_fields' => array("Length ($wc_dimension_unit)")),
                'width' => array('title'=>'Dimensions: Width','description'=>'Width', 'field_type' => 'alternates', 'similar_fields' => array("Width ($wc_dimension_unit)")),
                'height' => array('title'=>'Dimensions: Height','description'=>'Height', 'field_type' => 'alternates', 'similar_fields' => array("Height ($wc_dimension_unit)")),
                'tax_status' => array('title'=>'Tax: Tax status','description'=>'Taxable product or not', 'field_type' => 'alternates', 'similar_fields' => array('Tax status')),
                'tax_class' => array('title'=>'Tax: Tax class','description'=>'Tax class ( eg:- reduced rate)', 'field_type' => 'alternates', 'similar_fields' => array('Tax class')),
                'upsell_ids' => array('title'=>'Related products: Upsell IDs','description'=>'Upsell Product ids', 'field_type' => 'alternates', 'similar_fields' => array('Upsells')),
                'crosssell_ids' => array('title'=>'Related products: Crosssell IDs','description'=>'Crosssell Product ids', 'field_type' => 'alternates', 'similar_fields' => array('Cross-sells')),
                'file_paths' => array('title'=>'Downloads: File paths (WC 2.0.x)','description'=>'File Paths'),
                'downloadable_files' => array('title'=>'Downloads: Downloadable files (WC 2.1.x)','description'=>'Downloadable Files'),
                'download_limit' => array('title'=>'Downloads: Download limit','description'=>'Download Limit', 'field_type' => 'alternates', 'similar_fields' => array('Download limit')),
                'download_expiry' => array('title'=>'Downloads: Download expiry','description'=>'Download Expiry', 'field_type' => 'alternates', 'similar_fields' => array('Download expiry days')),
                'product_url' => array('title'=>'External: Product URL','description'=>'Product URL if the Product is external', 'field_type' => 'alternates', 'similar_fields' => array('External URL')),
                'button_text' => array('title'=>'External: Button text','description'=>'Buy button text for Product , if the Product is external', 'field_type' => 'alternates', 'similar_fields' => array('Button text')),
                'images' => array('title'=>'Images/Gallery','description'=>'Image URLs seperated with &#124;'),
                'product_page_url' => array('title'=>'Product page URL','description'=>'Product Page URL'),
//                'meta:total_sales' => array('title'=>'meta:total_sales','description'=>'Total sales for the Product'),
//                'tax:product_type' => array('title'=>'Product Type','description'=>'( eg:- simple , variable)'),
//                'tax:product_cat' => array('title'=>'Product Categories','description'=>'Product related categories'),
//                'tax:product_tag' => array('title'=>'Product Tags','description'=>'Product related tags'),
//                'tax:product_shipping_class' => array('title'=>'Product Shipping Class','description'=>'Allow you to group similar products for shipping'),
//                'tax:product_visibility' => array('title'=>'Product Visibility: Featured','description'=>'Featured Product'),

    
);


// if (class_exists('WPSEO_Options')) {
//     /* Yoast is active */

//     $post_columns['meta:_yoast_wpseo_focuskw'] = array('title' => 'meta:_yoast_wpseo_focuskw', 'description' => 'yoast SEO');
//     $post_columns['meta:_yoast_wpseo_canonical'] = array('title' => 'meta:_yoast_wpseo_canonical', 'description' => 'yoast SEO');
//     $post_columns['meta:_yoast_wpseo_bctitle'] = array('title' => 'meta:_yoast_wpseo_bctitle', 'description' => 'yoast SEO');
//     $post_columns['meta:_yoast_wpseo_meta-robots-adv'] = array('title' => 'meta:_yoast_wpseo_meta-robots-adv', 'description' => 'yoast SEO');
//     $post_columns['meta:_yoast_wpseo_is_cornerstone'] = array('title' => 'meta:_yoast_wpseo_is_cornerstone', 'description' => 'yoast SEO');
//     $post_columns['meta:_yoast_wpseo_metadesc'] = array('title' => 'meta:_yoast_wpseo_metadesc', 'description' => 'yoast SEO');
//     $post_columns['meta:_yoast_wpseo_linkdex'] = array('title' => 'meta:_yoast_wpseo_linkdex', 'description' => 'yoast SEO');
//     $post_columns['meta:_yoast_wpseo_estimated-reading-time-minutes'] = array('title' => 'meta:yoast_wpseo_estimated-reading-time-minutes', 'description' => 'yoast SEO');
//     $post_columns['meta:_yoast_wpseo_content_score'] = array('title' => 'meta:_yoast_wpseo_content_score', 'description' => 'yoast SEO');
//     $post_columns['meta:_yoast_wpseo_title'] = array('title' => 'meta:_yoast_wpseo_title', 'description' => 'yoast SEO');
//     $post_columns['meta:_yoast_wpseo_metadesc'] = array('title' => 'meta:_yoast_wpseo_metadesc', 'description' => 'yoast SEO');
//     $post_columns['meta:_yoast_wpseo_metakeywords'] = array('title' => 'meta:_yoast_wpseo_metakeywords', 'description' => 'yoast SEO');
// }

if (function_exists( 'aioseo' )) {
        
    /* All in One SEO is active */
    
    $post_columns['meta:_aioseo_title'] = array('title' => 'meta:_aioseo_title', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_description'] = array('title' => 'meta:_aioseo_description', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_keywords'] = array('title' => 'meta:_aioseo_keywords', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_og_title'] = array('title' => 'meta:_aioseo_og_title', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_og_description'] = array('title' => 'meta:_aioseo_og_description', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_twitter_title'] = array('title' => 'meta:_aioseo_twitter_title', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_og_article_tags'] = array('title' => 'meta:_aioseo_og_article_tags', 'description' => 'All in One SEO');
    $post_columns['meta:_aioseo_twitter_description'] = array('title' => 'meta:_aioseo_twitter_description', 'description' => 'All in One SEO');
}


if (apply_filters('wpml_setting', false, 'setup_complete')) {

    $post_columns['wpml:language_code'] = array('title'=>'wpml:language_code','description'=>'WPML language code');
    $post_columns['wpml:original_product_id'] = array('title'=>'wpml:original_product_id','description'=>'WPML Original Product ID');
    $post_columns['wpml:original_product_sku'] = array('title'=>'wpml:original_product_sku','description'=>'WPML Original Product SKU');
}

return apply_filters('woocommerce_csv_product_import_reserved_fields_pair', $post_columns);




return array(
    
    'id' => array('title'=>'Product ID','description'=>'Product ID'),
    'type' => array('title'=>'Product Type','description'=>'( eg:- simple , variable)'),
    'sku' => array('title'=>'Product SKU','description'=>'Product SKU - This will unique and Product identifier'),
    'name' => array('title'=>'Product Title','description'=>'Product Title. ie Name of the product'),
    'slug' => array('title'=>'Product Permalink','description'=>'Unique part of the product URL'),
    'date_created' => array('title'=>'Date','description'=>'Product posted date'),
                'published' => array('title'=>'Published','description'=>''), 
    'featured' => array('title'=>'Visibility: Featured','description'=>'Featured Product'),
    'catalog_visibility' =>  array('title'=>'Visibility: Visibility','description'=>'Visibility status ( hidden or visible)'),
    'short_description' => array('title'=>'Product Short Description','description'=>'Short description about the Product'),
    'description' => array('title'=>'Product Description','description'=>'Description about the Product'),
    'date_on_sale_from' =>  array('title'=>'Sale Price Dates: From','description'=>'Sale Price Dates effect from'),
    'date_on_sale_to' => array('title'=>'Sale Price Dates: To','description'=>'Sale Price Dates effect to'),
    'tax_status' => array('title'=>'Tax: Tax Status','description'=>'Taxable product or not'),
    'tax_class' => array('title'=>'Tax: Tax Class','description'=>'Tax class ( eg:- reduced rate)'),
    'stock_status' => array('title'=>'Inventory: Stock Status','description'=>'InStock or OutofStock'),
    'stock' => array('title'=>'Inventory: Stock','description'=>'Stock quantity'),
                'low_stock_amount' => array('title'=>'Low Stock Amount','description'=>''),
    'backorders' => array('title'=>'Inventory: Backorders','description'=>'Backorders'),
                'sold_individually' => array('title'=>'Sold Individually','description'=>''),
    'weight' => array('title'=>'Dimensions: Weight','description'=>'Wight of product in LB , OZ , KG as of your woocommerce Unit'),
    'length' => array('title'=>'Dimensions: length','description'=>'Length'),
    'width' => array('title'=>'Dimensions: width','description'=>'Width'),
    'height' => array('title'=>'Dimensions: height','description'=>'Height'),
    'reviews_allowed' => array('title'=>'Comment Status','description'=>'Comment Status ( Open or Closed comments for this prodcut)'),
                'purchase_note' => array('title'=>'Purchase note','description'=>''),
    'sale_price' => array('title'=>'Price: Sale Price','description'=>'Sale Price'),
    'regular_price' => array('title'=>'Price: Regular Price','description'=>'Regular Price'),
                'category_ids' => array('title'=>'Categories','description'=>''),
                'tag_ids' => array('title'=>'Tags','description'=>''),
                'shipping_class_id' => array('title'=>'Shipping class','description'=>''),
    'images' => array('title'=>'Images/Gallery','description'=>'Image URLs seperated with &#124;'),
    'download_limit' => array('title'=>'Downloads: Download Limit','description'=>'Download Limit'),
    'download_expiry' => array('title'=>'Downloads: Download Expiry','description'=>'Download Expiry'),
    'parent_id' => array('title'=>'Parent ID','description'=>'Parent Product ID , if you are importing variation Product'),
                'grouped_products' => array('title'=>'Grouped products','description'=>''),
    'upsell_ids' => array('title'=>'Related Products: Upsell IDs','description'=>'Upsell Product ids'),
    'cross_sell_ids' => array('title'=>'Related Products: Crosssell IDs','description'=>'Crosssell Product ids'),
    'product_url' => array('title'=>'External: Product URL','description'=>'Product URL if the Product is external'),
    'button_text' => array('title'=>'External: Button Text','description'=>'Buy button text for Product , if the Product is external'),
    'menu_order' => array('title'=>'Menu Order','description'=>'If menu enabled , menu order'),
);

array(
		'name'               => '',
		'slug'               => '',
		'date_created'       => null,
		'date_modified'      => null,
		'status'             => false,
		'featured'           => false,
		'catalog_visibility' => 'visible',
		'description'        => '',
		'short_description'  => '',
		'sku'                => '',
		'price'              => '',
		'regular_price'      => '',
		'sale_price'         => '',
		'date_on_sale_from'  => null,
		'date_on_sale_to'    => null,
		'total_sales'        => '0',
		'tax_status'         => 'taxable',
		'tax_class'          => '',
		'manage_stock'       => false,
		'stock_quantity'     => null,
		'stock_status'       => 'instock',
		'backorders'         => 'no',
		'low_stock_amount'   => '',
		'sold_individually'  => false,
		'weight'             => '',
		'length'             => '',
		'width'              => '',
		'height'             => '',
		'upsell_ids'         => array(),
		'cross_sell_ids'     => array(),
		'parent_id'          => 0,
		'reviews_allowed'    => true,
		'purchase_note'      => '',
		'attributes'         => array(),
		'default_attributes' => array(),
		'menu_order'         => 0,
		'post_password'      => '',
		'virtual'            => false,
		'downloadable'       => false,
		'category_ids'       => array(),
		'tag_ids'            => array(),
		'shipping_class_id'  => 0,
		'downloads'          => array(),
		'image_id'           => '',
		'gallery_image_ids'  => array(),
		'download_limit'     => -1,
		'download_expiry'    => -1,
		'rating_counts'      => array(),
		'average_rating'     => 0,
		'review_count'       => 0,
	);


$post_columns = array(
    'post_title' => 'name',
    'post_name' => 'slug',
    'post_parent' => 'parent_id',
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
    '_visibility' => 'visibility',
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
    // YOAST
    // 'meta:_yoast_wpseo_focuskw' => 'meta:_yoast_wpseo_focuskw',
    // 'meta:_yoast_wpseo_title' => 'meta:_yoast_wpseo_title',
    // 'meta:_yoast_wpseo_metadesc' => 'meta:_yoast_wpseo_metadesc',
    // 'meta:_yoast_wpseo_metakeywords' => 'meta:_yoast_wpseo_metakeywords',

    'images' => 'Images (featured and gallery)',
    "$file_path_header" => 'Downloadable file paths',
    'product_page_url' => 'Product Page URL',
    //'taxonomies' => 'Taxonomies (cat/tags/shipping-class)',
    //'meta' => 'Meta (custom fields)',
    //'attributes' => 'Attributes',
);

if (apply_filters('wpml_setting', false, 'setup_complete')) {

    $post_columns['wpml:language_code'] = 'wpml:language_code';
    $post_columns['wpml:original_product_id'] = 'wpml:original_product_id';
    $post_columns['wpml:original_product_sku'] = 'wpml:original_product_sku';
}






$default_export_columns = array(
    'id' => __('ID', 'woocommerce'),
    'type' => __('Type', 'woocommerce'),
    'sku' => __('SKU', 'woocommerce'),
    'name' => __('Name', 'woocommerce'),
    'published' => __('Published', 'woocommerce'),
    'featured' => __('Is featured?', 'woocommerce'),
    'catalog_visibility' => __('Visibility in catalog', 'woocommerce'),
    'short_description' => __('Short description', 'woocommerce'),
    'description' => __('Description', 'woocommerce'),
    'date_on_sale_from' => __('Date sale price starts', 'woocommerce'),
    'date_on_sale_to' => __('Date sale price ends', 'woocommerce'),
    'tax_status' => __('Tax status', 'woocommerce'),
    'tax_class' => __('Tax class', 'woocommerce'),
    'stock_status' => __('In stock?', 'woocommerce'),
    'stock' => __('Stock', 'woocommerce'),
    'low_stock_amount' => __('Low stock amount', 'woocommerce'),
    'backorders' => __('Backorders allowed?', 'woocommerce'),
    'sold_individually' => __('Sold individually?', 'woocommerce'),
    'weight' => sprintf(__('Weight (%s)', 'woocommerce'), get_option('woocommerce_weight_unit')),
    'length' => sprintf(__('Length (%s)', 'woocommerce'), get_option('woocommerce_dimension_unit')),
    'width' => sprintf(__('Width (%s)', 'woocommerce'), get_option('woocommerce_dimension_unit')),
    'height' => sprintf(__('Height (%s)', 'woocommerce'), get_option('woocommerce_dimension_unit')),
    'reviews_allowed' => __('Allow customer reviews?', 'woocommerce'),
    'purchase_note' => __('Purchase note', 'woocommerce'),
    'sale_price' => __('Sale price', 'woocommerce'),
    'regular_price' => __('Regular price', 'woocommerce'),
    'category_ids' => __('Categories', 'woocommerce'),
    'tag_ids' => __('Tags', 'woocommerce'),
    'shipping_class_id' => __('Shipping class', 'woocommerce'),
    'images' => __('Images', 'woocommerce'),
    'download_limit' => __('Download limit', 'woocommerce'),
    'download_expiry' => __('Download expiry days', 'woocommerce'),
    'parent_id' => __('Parent', 'woocommerce'),
    'grouped_products' => __('Grouped products', 'woocommerce'),
    'upsell_ids' => __('Upsells', 'woocommerce'),
    'cross_sell_ids' => __('Cross-sells', 'woocommerce'),
    'product_url' => __('External URL', 'woocommerce'),
    'button_text' => __('Button text', 'woocommerce'),
    'menu_order' => __('Position', 'woocommerce'),
);


$default_import_columns = array(
    'ID' => 'id',
    'Type' => 'type',
    'SKU' => 'sku',
    'Name' => 'name',
    'Published' => 'published',
    'Is featured?' => 'featured',
    'Visibility in catalog' => 'catalog_visibility',
    'Short description' => 'short_description',
    'Description' => 'description',
    'Date sale price starts' => 'date_on_sale_from',
    'Date sale price ends' => 'date_on_sale_to',
    'Tax status' => 'tax_status',
    'Tax class' => 'tax_class',
    'In stock?' => 'stock_status',
    'Stock' => 'stock_quantity',
    'Backorders allowed?' => 'backorders',
    'Low stock amount' => 'low_stock_amount',
    'Sold individually?' => 'sold_individually',
    sprintf('Weight (%s)', get_option('woocommerce_weight_unit')) => 'weight',
    sprintf('Length (%s)', get_option('woocommerce_dimension_unit')) => 'length',
    sprintf('Width (%s)', get_option('woocommerce_dimension_unit')) => 'width',
    sprintf('Height (%s)', get_option('woocommerce_dimension_unit')) => 'height',
    'Allow customer reviews?' => 'reviews_allowed',
    'Purchase note' => 'purchase_note',
    'Sale price' => 'sale_price',
    'Regular price' => 'regular_price',
    'Categories' => 'category_ids',
    'Tags' => 'tag_ids',
    'Shipping class' => 'shipping_class_id',
    'Images' => 'images',
    'Download limit' => 'download_limit',
    'Download expiry days' => 'download_expiry',
    'Parent' => 'parent_id',
    'Upsells' => 'upsell_ids',
    'Cross-sells' => 'cross_sell_ids',
    'Grouped products' => 'grouped_products',
    'External URL' => 'product_url',
    'Button text' => 'button_text',
    'Position' => 'menu_order',
);


$default_import_columns_1 = array(
    __('ID', 'woocommerce') => 'id',
    __('Type', 'woocommerce') => 'type',
    __('SKU', 'woocommerce') => 'sku',
    __('Name', 'woocommerce') => 'name',
    __('Published', 'woocommerce') => 'published',
    __('Is featured?', 'woocommerce') => 'featured',
    __('Visibility in catalog', 'woocommerce') => 'catalog_visibility',
    __('Short description', 'woocommerce') => 'short_description',
    __('Description', 'woocommerce') => 'description',
    __('Date sale price starts', 'woocommerce') => 'date_on_sale_from',
    __('Date sale price ends', 'woocommerce') => 'date_on_sale_to',
    __('Tax status', 'woocommerce') => 'tax_status',
    __('Tax class', 'woocommerce') => 'tax_class',
    __('In stock?', 'woocommerce') => 'stock_status',
    __('Stock', 'woocommerce') => 'stock_quantity',
    __('Backorders allowed?', 'woocommerce') => 'backorders',
    __('Low stock amount', 'woocommerce') => 'low_stock_amount',
    __('Sold individually?', 'woocommerce') => 'sold_individually',
    /* translators: %s: Weight unit */
    sprintf(__('Weight (%s)', 'woocommerce'), $weight_unit) => 'weight',
    /* translators: %s: Length unit */
    sprintf(__('Length (%s)', 'woocommerce'), $dimension_unit) => 'length',
    /* translators: %s: Width unit */
    sprintf(__('Width (%s)', 'woocommerce'), $dimension_unit) => 'width',
    /* translators: %s: Height unit */
    sprintf(__('Height (%s)', 'woocommerce'), $dimension_unit) => 'height',
    __('Allow customer reviews?', 'woocommerce') => 'reviews_allowed',
    __('Purchase note', 'woocommerce') => 'purchase_note',
    __('Sale price', 'woocommerce') => 'sale_price',
    __('Regular price', 'woocommerce') => 'regular_price',
    __('Categories', 'woocommerce') => 'category_ids',
    __('Tags', 'woocommerce') => 'tag_ids',
    __('Shipping class', 'woocommerce') => 'shipping_class_id',
    __('Images', 'woocommerce') => 'images',
    __('Download limit', 'woocommerce') => 'download_limit',
    __('Download expiry days', 'woocommerce') => 'download_expiry',
    __('Parent', 'woocommerce') => 'parent_id',
    __('Upsells', 'woocommerce') => 'upsell_ids',
    __('Cross-sells', 'woocommerce') => 'cross_sell_ids',
    __('Grouped products', 'woocommerce') => 'grouped_products',
    __('External URL', 'woocommerce') => 'product_url',
    __('Button text', 'woocommerce') => 'button_text',
    __('Position', 'woocommerce') => 'menu_order',
);