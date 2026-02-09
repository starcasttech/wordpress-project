<?php
/**
 * Product section of the plugin
 *
 * @link          
 *
 * @package  Wt_Import_Export_For_Woo 
 */
if (!defined('ABSPATH')) {
    exit;
}

class Wt_Import_Export_For_Woo_Product {

    public $module_id = '';
    public static $module_id_static = '';
    public $module_base = 'product';
    public $module_name = 'Product Import Export for WooCommerce';
    public $min_base_version= '1.0.0'; /* Minimum `Import export plugin` required to run this add on plugin */

    private $importer = null;
    private $exporter = null;
    private $product_categories = null;
    private $product_tags = null;
    private $product_taxonomies = array();
    private $all_meta_keys = array();
    private $product_attributes = array();
    private $exclude_hidden_meta_columns = array();
    private $found_product_meta = array();
    private $found_product_hidden_meta = array();
    private $selected_column_names = null;

    public function __construct()
    {
        /**
        *   Checking the minimum required version of `Import export plugin` plugin available
        */
        if(!Wt_Import_Export_For_Woo_Common_Helper::check_base_version($this->module_base, $this->module_name, $this->min_base_version))
        {
            return;
        }
        if(!function_exists('is_plugin_active'))
        {
            include_once(ABSPATH.'wp-admin/includes/plugin.php');
        }
        if(!is_plugin_active('woocommerce/woocommerce.php'))
        {
            return;
        }

        $this->module_id = Wt_Import_Export_For_Woo::get_module_id($this->module_base);
        self::$module_id_static = $this->module_id;

        add_filter('wt_iew_exporter_post_types', array($this, 'wt_iew_exporter_post_types'), 10, 1);
        add_filter('wt_iew_importer_post_types', array($this, 'wt_iew_exporter_post_types'), 10, 1);

        add_filter('wt_iew_exporter_alter_filter_fields', array($this, 'exporter_alter_filter_fields'), 10, 3);
        
        add_filter('wt_iew_exporter_alter_mapping_fields', array($this, 'exporter_alter_mapping_fields'), 10, 3);        
        add_filter('wt_iew_importer_alter_mapping_fields', array($this, 'get_importer_post_columns'), 10, 3);  
        
        add_filter('wt_iew_exporter_alter_advanced_fields', array($this, 'exporter_alter_advanced_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_advanced_fields', array($this, 'importer_alter_advanced_fields'), 10, 3);

        add_filter('wt_iew_exporter_alter_meta_mapping_fields', array($this, 'exporter_alter_meta_mapping_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_meta_mapping_fields', array($this, 'importer_alter_meta_mapping_fields'), 10, 3);

        add_filter('wt_iew_exporter_alter_mapping_enabled_fields', array($this, 'exporter_alter_mapping_enabled_fields'), 10, 3);
        add_filter('wt_iew_importer_alter_mapping_enabled_fields', array($this, 'exporter_alter_mapping_enabled_fields'), 10, 3);

        add_filter('wt_iew_exporter_do_export', array($this, 'exporter_do_export'), 10, 7);
        add_filter('wt_iew_importer_do_import', array($this, 'importer_do_import'), 10, 8); 
        
        add_filter('wt_iew_exporter_do_image_export', array($this, 'exporter_do_export'), 10, 7);
        
        add_filter('wt_iew_importer_steps', array($this, 'importer_steps'), 10, 2);
        
        add_action('admin_footer-edit.php', array($this, 'wt_add_products_bulk_actions'));
        add_action('load-edit.php', array($this, 'wt_process_products_bulk_actions'));       
		
		add_filter('wt_add_woocommerce_debug_tools', array($this, 'wt_product_debug_tools'));
        
    }

	/**
	 * Add more tools options under WC status > tools
	 * 
	 * @param array $tools WC Tools items
	 */
	public function wt_product_debug_tools($wc_tools) {

		$wc_tools['wt_delete_products'] = array(
			'name' => __('Remove all products', 'wt-import-export-for-woo'),
			'button' => __('Delete all products', 'wt-import-export-for-woo'),
			'desc' => __('This tool will delete all products allowing you to start fresh.', 'wt-import-export-for-woo'),
			'callback' => array($this, 'wt_remove_all_products')
		);
		$wc_tools['wt_delete_variations'] = array(
			'name' => __('Remove all product variations', 'wt-import-export-for-woo'),
			'button' => __('Delete all variations', 'wt-import-export-for-woo'),
			'desc' => __('This tool will delete all the product variations.', 'wt-import-export-for-woo'),
			'callback' => array($this, 'wt_remove_all_variations')
		);
		$wc_tools['wt_delete_product_tags'] = array(
			'name' => __('Remove all product tags', 'wt-import-export-for-woo'),
			'button' => __('Delete  product tags', 'wt-import-export-for-woo'),
			'desc' => __('This tool will permanently delete all product tags.', 'wt-import-export-for-woo'),
			'callback' => array($this, 'wt_remove_all_product_tags')
		);
		$wc_tools['wt_delete_product_categories'] = array(
			'name' => __('Remove all product categories', 'wt-import-export-for-woo'),
			'button' => __('Delete product categories', 'wt-import-export-for-woo'),
			'desc' => __('This tool will permanently delete all product categories.', 'wt-import-export-for-woo'),
			'callback' => array($this, 'wt_remove_all_product_categories')
		);

		return $wc_tools;
	}

	/**
	 * Delete all products
	 * 
	 * @global object $wpdb
	 */
	public function wt_remove_all_products() {
		global $wpdb;

		// Delete products
		$result = absint($wpdb->delete($wpdb->posts, array('post_type' => 'product')));
		$result2 = absint($wpdb->delete($wpdb->posts, array('post_type' => 'product_variation')));

		// Delete meta and term relationships with no post
		$wpdb->query("DELETE pm
			FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id
			WHERE wp.ID IS NULL");
		$wpdb->query("DELETE tr
			FROM {$wpdb->term_relationships} tr
			LEFT JOIN {$wpdb->posts} wp ON wp.ID = tr.object_id
			WHERE wp.ID IS NULL");

		echo '<div class="updated"><p>' . sprintf(__('%d Products deleted', 'wt-import-export-for-woo'), ( $result + $result2)) . '</p></div>';
	}

	/**
	 * Delete all variations
	 * 
	 * @global object $wpdb
	 */
	public function wt_remove_all_variations() {
		global $wpdb;

		// Delete products
		$result = absint($wpdb->delete($wpdb->posts, array('post_type' => 'product_variation')));

		// Delete meta and term relationships with no post
		$wpdb->query("DELETE pm
			FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id
			WHERE wp.ID IS NULL");
		$wpdb->query("DELETE tr
			FROM {$wpdb->term_relationships} tr
			LEFT JOIN {$wpdb->posts} wp ON wp.ID = tr.object_id
			WHERE wp.ID IS NULL");

		echo '<div class="updated"><p>' . sprintf(__('%d Variations deleted', 'wt-import-export-for-woo'), $result) . '</p></div>';
	}

	/**
	 * Delete all product categories
	 * 
	 * @global object $wpdb
	 */
	public function wt_remove_all_product_categories() {
		global $wpdb;

		$wpdb->query("DELETE a,c FROM {$wpdb->prefix}terms AS a
                      LEFT JOIN {$wpdb->prefix}term_taxonomy AS c ON a.term_id = c.term_id
                      LEFT JOIN {$wpdb->prefix}term_relationships AS b ON b.term_taxonomy_id = c.term_taxonomy_id
                      WHERE c.taxonomy = 'product_cat'");
		echo '<div class="updated"><p>' . __('Product categories deleted', 'wt-import-export-for-woo') . '</p></div>';
	}

	/**
	 * Delete all product tags
	 * 
	 * @global object $wpdb
	 */
	public function wt_remove_all_product_tags() {
		global $wpdb;

		$wpdb->query("DELETE a,c FROM {$wpdb->prefix}terms AS a
                LEFT JOIN {$wpdb->prefix}term_taxonomy AS c ON a.term_id = c.term_id
                LEFT JOIN {$wpdb->prefix}term_relationships AS b ON b.term_taxonomy_id = c.term_taxonomy_id
                WHERE c.taxonomy = 'product_tag'");

		echo '<div class="updated"><p>' . __('Product tags deleted', 'wt-import-export-for-woo') . '</p></div>';
	}

	/**
     * Product list page bulk export action add to action list
     * 
     */
    public function wt_add_products_bulk_actions() {
        global $post_type, $post_status;

        if ($post_type == 'product' && $post_status != 'trash') {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    var $downloadProducts = $('<option>').val('wt_iew_download_products').text('<?php _e('Export as CSV', 'wt-import-export-for-woo') ?>');
                    $('select[name^="action"]').append($downloadProducts);
                });
            </script>
            <?php
        }
    }
    
    
    /**
     * Product page bulk export action
     * 
     */
    public function wt_process_products_bulk_actions() {
        global $typenow;
        if ($typenow == 'product') {
            // get the action list
            $wp_list_table = _get_list_table('WP_Posts_List_Table');
            $action = $wp_list_table->current_action();
            if (!in_array($action, array('wt_iew_download_products'))) {
                return;
            }
            // security check
            check_admin_referer('bulk-posts');

            if (isset($_REQUEST['post'])) {
                $prod_ids = array_map('absint', $_REQUEST['post']);
            }
            if (empty($prod_ids)) {
                return;
            }

            if ($action == 'wt_iew_download_products') {
                include_once( 'export/class-wt-prodimpexpcsv-exporter.php' );

                Wt_Import_Export_For_Woo_Product_Bulk_Export::do_export('product', $prod_ids);
            }
        }
    }

    
    /**
    *   Altering advanced step description
    */
    public function importer_steps($steps, $base)
    {
        if($this->module_base==$base)
        {
            $steps['advanced']['description']=__('Use options from below to decide updates to existing products, batch import count or schedule an import. You can also save the template file for future imports.', 'wt-import-export-for-woo');
        }
        return $steps;
    }

    public function importer_do_import($import_data, $base, $step, $form_data, $selected_template_data, $method_import, $batch_offset, $is_last_batch) {        
        if ($this->module_base != $base) {
            return $import_data;
        }
            
        if(0 == $batch_offset){                        
            $memory = size_format(wt_let_to_num(ini_get('memory_limit')));
            $wp_memory = size_format(wt_let_to_num(WP_MEMORY_LIMIT));                      
            Wt_Import_Export_For_Woo_Logwriter::write_log($this->module_base, 'import', '---[ New import started at '.date('Y-m-d H:i:s').' ] PHP Memory: ' . $memory . ', WP Memory: ' . $wp_memory);
        }
        
        include plugin_dir_path(__FILE__) . 'import/import.php';
        $import = new Wt_Import_Export_For_Woo_Product_Import($this);
        
        $response = $import->prepare_data_to_import($import_data,$form_data,$batch_offset,$is_last_batch);
        
        if($is_last_batch){
            Wt_Import_Export_For_Woo_Logwriter::write_log($this->module_base, 'import', '---[ Import ended at '.date('Y-m-d H:i:s').']---');
        }
        return $response;
    }

    public function exporter_do_export($export_data, $base, $step, $form_data, $selected_template_data, $method_export, $batch_offset) { 
		
        if ($this->module_base != $base) {
            return $export_data;
        }
        $form_data = apply_filters('wt_ier_product_exporter_form_data', $form_data);
               
        switch ($method_export) {
            case 'quick':
                $this->set_export_columns_for_quick_export($form_data);  
                break;

            case 'template':               
            case 'new':
                $this->set_selected_column_names($form_data);
                break;
            
            default:
                break;
        }
        
        include plugin_dir_path(__FILE__) . 'export/export.php';
        $export = new Wt_Import_Export_For_Woo_Product_Export($this);

        $header_row = $export->prepare_header();

        $data_row = $export->prepare_data_to_export($form_data, $batch_offset,$step);
        
        if('export_image' == $step){
            $export_data = array(            
                'total' => $data_row['total'],
                'images' => $data_row['images'],                
            );
        }else{
            $export_data = array(
                'head_data' => $header_row,
                'body_data' => $data_row['data'],
                'total' => $data_row['total'],
            ); 
        }
        if(isset($data_row['no_post'])){
            $export_data['no_post'] = $data_row['no_post'];
        }
        
        return $export_data;
    }
        
    /**
     * Adding current post type to export list
     *
     */
    public function wt_iew_exporter_post_types($arr) {
        $arr['product'] = __('Product', 'wt-import-export-for-woo');
        return $arr;
    }

    /**
     * Add/Remove steps in export section.
     * @param array $steps array of built in steps
     * @param string $base product, order etc
     * @return array $steps 
     */
    public function wt_iew_exporter_steps($steps, $base) {
        if ($base == $this->module_base) {
            foreach ($steps as $stepk => $stepv) {
                $out[$stepk] = $stepv;
                if ($stepk == 'filter') {
                    /*
                      $out['product']=array(
                      'title'=>'Product',
                      'description'=>'',
                      );
                     */
                }
            }
        } else {
            $out = $steps;
        }
        return $out;
    }
    
    
    /*
     * Setting default export columns for quick export
     */
    
    public function set_export_columns_for_quick_export($form_data) {

        $post_columns = self::get_product_post_columns();

        $this->selected_column_names = array_combine(array_keys($post_columns), array_keys($post_columns));
        
        if (isset($form_data['method_export_form_data']['mapping_enabled_fields']) && !empty($form_data['method_export_form_data']['mapping_enabled_fields'])) {
            foreach ($form_data['method_export_form_data']['mapping_enabled_fields'] as $value) {
                $additional_quick_export_fields[$value] = array('fields' => array());
            }

            $export_additional_columns = $this->exporter_alter_meta_mapping_fields($additional_quick_export_fields, $this->module_base, array());
            foreach ($export_additional_columns as $value) {
                $this->selected_column_names = array_merge($this->selected_column_names, $value['fields']);
            }
        }
    }


    /**
     * Get product categories
     * @return array $categories 
     */
    private function get_product_categories() {
        if (!is_null($this->product_categories)) {
            return $this->product_categories;
        }
        $out = array();
        $product_categories = get_terms('product_cat', array('hide_empty' => false) );
        if (!is_wp_error($product_categories)) {
            $version = get_bloginfo('version');
            foreach ($product_categories as $category) {
                $out[$category->slug] = (( $version < '4.8') ? $category->name : get_term_parents_list($category->term_id, 'product_cat', array('separator' => ' -> ')));
            }
        }
        $this->product_categories = $out;
        return $out;
    }

    private function get_product_tags() {
        if (!is_null($this->product_tags)) {
            return $this->product_tags;
        }
        $out = array();
        $product_tags = get_terms('product_tag');
        if (!is_wp_error($product_tags)) {
            foreach ($product_tags as $tag) {
                $out[$tag->slug] = $tag->name;
            }
        }
        $this->product_tags = $out;
        return $out;
    }

    public static function get_product_types() {
//        return include plugin_dir_path(__FILE__) . 'data/data-allowed-product-types.php';
        
        $product_types = array();
        foreach ( wc_get_product_types() as $value => $label ) {
            $product_types[esc_attr( $value )] = esc_html( $label );
        }
        return $product_types;
        return array_merge($product_types, array('variation' => 'Product variations'));
    }

    public static function get_product_statuses() {
        $product_statuses = array('publish', 'private', 'draft', 'pending', 'future');
        return apply_filters('wt_iew_allowed_product_statuses', array_combine($product_statuses, $product_statuses));
    }

    public static function get_product_sort_columns() {    
//        $sort_columns = array('post_parent', 'ID', 'post_author', 'post_date', 'post_title', 'post_name', 'post_modified', 'menu_order', 'post_modified_gmt', 'rand', 'comment_count');
        $sort_columns = array('ID'=>'Product ID', 'title'=>'Product name', 'type'=>'Product type', 'date'=>'Created date', 'modified'=>'Modified date');
        return apply_filters('wt_iew_allowed_product_sort_columns', $sort_columns);
    }

    public static function get_product_post_columns() {
        return include plugin_dir_path(__FILE__) . 'data/data-product-post-columns.php';
    }

    public function get_importer_post_columns($fields, $base, $step_page_form_data) {
        if ($base != $this->module_base) {
            return $fields;
        }
        $colunm = include plugin_dir_path(__FILE__) . 'data/data/data-wf-reserved-fields-pair.php';
//        $colunm = array_map(function($vl){ return array('title'=>$vl, 'description'=>$vl); }, $arr); 
        return $colunm;
    }

    public function exporter_alter_mapping_enabled_fields($mapping_enabled_fields, $base, $form_data_mapping_enabled_fields) {
        if ($base == $this->module_base) {
            $mapping_enabled_fields = array();
            $mapping_enabled_fields['taxonomies'] = array(__('Taxonomies (cat/tags/shipping-class)'), 1);
            $mapping_enabled_fields['meta'] = array(__('Meta (custom fields)'), 1);
            $mapping_enabled_fields['attributes'] = array(__('Attributes'), 1);
            $mapping_enabled_fields['hidden_meta'] = array(__('Hidden meta'), 0);
        }
        return $mapping_enabled_fields;
    }

    
    public function exporter_alter_meta_mapping_fields($fields, $base, $step_page_form_data) {
        if ($base != $this->module_base) {
            return $fields;
        }
        
        foreach ($fields as $key => $value) {
            switch ($key) {
                case 'taxonomies':
                    $product_taxonomies = $this->wt_get_product_taxonomies();
                    foreach ($product_taxonomies as $taxonomy) {
                        if (strstr($taxonomy->name, 'pa_'))
                            continue; // Skip attributes                        
                        $fields[$key]['fields']['tax:' . $taxonomy->name] = 'tax:' . $taxonomy->name;
                    }
                    break;
                    
                case 'meta':
                    $meta_attributes = array();
                    $found_product_meta = $this->wt_get_found_product_meta();
                    foreach ($found_product_meta as $product_meta) {
                        if('attribute_' == substr($product_meta, 0, 10)){ // Skipping attribute meta which will add on attribute section
                            $meta_attributes[] = $product_meta;
                            continue;
                        }
                        $fields[$key]['fields']['meta:' . $product_meta] = 'meta:' . $product_meta;
                    }

                    break;

                case 'attributes':
                    $found_attributes = $this->wt_get_product_attributes();
                    
                    if(!empty($meta_attributes) && apply_filters('wt_ier_product_attribute_listing', true)){  // adding meta attributes
                        foreach ($meta_attributes as $attribute_value) {
                            $fields[$key]['fields']['meta:' . $attribute_value] = 'meta:' . $attribute_value;
                        }
                    }
                    
                    foreach ($found_attributes as $attribute) {
                        $fields[$key]['fields']['attribute:' . $attribute] = 'attribute:' . $attribute;
						if(apply_filters('wt_ier_product_attribute_listing', true)){
							$fields[$key]['fields']['attribute_data:' . $attribute] = 'attribute_data:' . $attribute;
							$fields[$key]['fields']['attribute_default:' . $attribute] = 'attribute_default:' . $attribute;
						}
                    }
                    
                    break;

                case 'hidden_meta':
                    $found_product_hidden_meta = $this->wt_get_found_product_hidden_meta();
                    foreach ($found_product_hidden_meta as $product_meta) {
                        $fields[$key]['fields']['meta:' . $product_meta] = 'meta:' . $product_meta;
                    }
                    break;
                default:
                    break;
            }
        }

        return $fields;
    }
    
    public function importer_alter_meta_mapping_fields($fields, $base, $step_page_form_data) {
        if ($base != $this->module_base) {
            return $fields;
        }
        
        $fields=$this->exporter_alter_meta_mapping_fields($fields, $base, $step_page_form_data);

        $out=array();
        foreach ($fields as $key => $value) 
        {
                $value['fields'] = array_map(function($vl){ 
				$meta_mapping_temp = array('title'=>$vl, 'description'=>$vl);

				// For fileds other than default fields, the alternates select fields cannot be set as of now
				// Its called after loading the default fields so need to load head again in backend to set from similar array
				// Here user alternate field as single value. ( For defaults, its array )
				if( 'tax:product_type' === $vl){
							$meta_mapping_temp['field_type'] = 'alternates';
							$meta_mapping_temp['similar_fields'] = 'Type';
				}
				if( 'tax:product_tag' === $vl){
							$meta_mapping_temp['field_type'] = 'alternates';
							$meta_mapping_temp['similar_fields'] = 'Tags';
				}
				if( 'tax:product_cat' === $vl){
							$meta_mapping_temp['field_type'] = 'alternates';
							$meta_mapping_temp['similar_fields'] = 'Categories';
				}
				if( 'tax:product_shipping_class' === $vl){
							$meta_mapping_temp['field_type'] = 'alternates';
							$meta_mapping_temp['similar_fields'] = 'Shipping class';
				}					
				
				return $meta_mapping_temp; }, $value['fields']);
				
            $out[$key]=$value;
        }
        return $out;
    }
    
    public function wt_get_product_taxonomies() {

        if (!empty($this->product_taxonomies)) {
            return $this->product_taxonomies;
        }
        $product_ptaxonomies = get_object_taxonomies('product', 'name');
        $product_vtaxonomies = get_object_taxonomies('product_variation', 'name');
        $product_taxonomies = array_merge($product_ptaxonomies, $product_vtaxonomies);

        $this->product_taxonomies = $product_taxonomies;
        return $this->product_taxonomies;
    }

    public function wt_get_found_product_meta() {

        if (!empty($this->found_product_meta)) {
            return $this->found_product_meta;
        }

        // Loop products and load meta data
        $found_product_meta = array();
        // Some of the values may not be usable (e.g. arrays of arrays) but the worse
        // that can happen is we get an empty column.

        $all_meta_keys = $this->wt_get_all_meta_keys();
        $csv_columns = self::get_product_post_columns();
        $exclude_hidden_meta_columns = $this->wt_get_exclude_hidden_meta_columns();
        foreach ($all_meta_keys as $meta) {

            if (!$meta || (substr((string) $meta, 0, 1) == '_') || in_array($meta, $exclude_hidden_meta_columns) || in_array($meta, array_keys($csv_columns)) || in_array('meta:' . $meta, array_keys($csv_columns)))
                continue;

            $found_product_meta[] = $meta;
        }

        $found_product_meta = array_diff($found_product_meta, array_keys($csv_columns));
        $found_product_meta = array_map('rawurldecode', $found_product_meta);
        $this->found_product_meta = $found_product_meta;
        return $this->found_product_meta;
    }

    public function wt_get_found_product_hidden_meta() {

        if (!empty($this->found_product_hidden_meta)) {
            return $this->found_product_hidden_meta;
        }

        // Loop products and load meta data
        $found_product_meta = array();
        // Some of the values may not be usable (e.g. arrays of arrays) but the worse
        // that can happen is we get an empty column.

        $all_meta_keys = $this->wt_get_all_meta_keys();
        $csv_columns = self::get_product_post_columns();//$this->get_selected_column_names();
        $exclude_hidden_meta_columns = $this->wt_get_exclude_hidden_meta_columns();
        foreach ($all_meta_keys as $meta) {

            if (!$meta || (substr((string) $meta, 0, 1) != '_') || in_array($meta, $exclude_hidden_meta_columns) || in_array($meta, array_keys($csv_columns)) || in_array('meta:' . $meta, array_keys($csv_columns)))
                continue;

            $found_product_meta[] = $meta;
        }

        $found_product_meta = array_diff($found_product_meta, array_keys($csv_columns));

        $this->found_product_hidden_meta = $found_product_meta;
        return $this->found_product_hidden_meta;
    }

    public function wt_get_exclude_hidden_meta_columns() {

        if (!empty($this->exclude_hidden_meta_columns)) {
            return $this->exclude_hidden_meta_columns;
        }

        $exclude_hidden_meta_columns = include( plugin_dir_path(__FILE__) . 'data/data-wf-hidden-meta-columns.php' );

        $this->exclude_hidden_meta_columns = $exclude_hidden_meta_columns;
        return $this->exclude_hidden_meta_columns;
    }

    public function wt_get_all_meta_keys() {

        if (!empty($this->all_meta_keys)) {
            return $this->all_meta_keys;
        }

        $all_meta_keys = self::get_all_metakeys();

        $this->all_meta_keys = $all_meta_keys;
        return $this->all_meta_keys;
    }

    /**
     * Get a list of all the meta keys for a post type. This includes all public, private,
     * used, no-longer used etc. They will be sorted once fetched.
     */
    public static function get_all_metakeys() {  
        if(apply_filters('wt_iew_export_product_fetch_meta_keys', false) ){
            return array();
        }  
        global $wpdb;
        $limit_query_string = '';
        $limit = apply_filters('wt_ier_product_metakeys_fetch_limit',2000);
        if($limit){
            $limit_query_string = "LIMIT $limit";
        }
		$limit_query_string = apply_filters( 'wt_ier_product_metakeys_limit_query', $limit_query_string );
        
        $exclude_meta_keys = apply_filters( 'wt_ier_product_exclude_meta_keys', [
            // WP internals.
            '_edit_lock',
            '_edit_last',
            '_wp_old_date',
            // WC internals.
            '_downloadable_files',
            '_sku',
            '_weight',
            '_width',
            '_height',
            '_length',
            '_file_path',
            '_file_paths',
            '_sale_price',
            '_regular_price',
            '_virtual',
            '_visibility',
            '_stock_status',
            '_stock',
            '_sale_price_dates_from',
            '_price',
            '_manage_stock',
            '_backorders',
            '_upsell_ids',
            '_crosssell_ids',
        
        ]);

        $exclude_keys_sql = "'" . implode("', '", $exclude_meta_keys) . "'";
        $meta = $wpdb->get_col(
            "SELECT DISTINCT pm.meta_key
            FROM {$wpdb->postmeta} AS pm
            LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
            WHERE p.post_type IN ('product', 'product_variation')
            AND pm.meta_key NOT IN ($exclude_keys_sql)
            $limit_query_string"
        );
        
        sort($meta);
        return $meta;
    }

    public function set_selected_column_names($full_form_data) {   
        if (is_null($this->selected_column_names)) {
            if (isset($full_form_data['mapping_form_data']['mapping_selected_fields']) && !empty($full_form_data['mapping_form_data']['mapping_selected_fields'])) {
                $this->selected_column_names = $full_form_data['mapping_form_data']['mapping_selected_fields'];
            }
            if (isset($full_form_data['meta_step_form_data']['mapping_selected_fields']) && !empty($full_form_data['meta_step_form_data']['mapping_selected_fields'])) {
                $export_additional_columns = $full_form_data['meta_step_form_data']['mapping_selected_fields'];
                foreach ($export_additional_columns as $value) {
                    $this->selected_column_names = array_merge($this->selected_column_names, $value);
                }
            }
        }

        return $full_form_data;
    }

    public function get_selected_column_names() {
            
        return $this->selected_column_names;
    }

    public function wt_get_product_attributes() {
        if (!empty($this->product_attributes)) {
            return $this->product_attributes;
        }
        $found_pattributes = self::get_all_product_attributes('product');
        $found_vattributes = self::get_all_product_attributes('product_variation');
        $found_attributes = array_merge($found_pattributes, $found_vattributes);
        $found_attributes = array_unique($found_attributes);
        $found_attributes = array_map('rawurldecode', $found_attributes);
        $this->product_attributes = $found_attributes;
        return $this->product_attributes;
    }

    /**
     * Get a list of all the product attributes for a post type.
     * These require a bit more digging into the values.
     */
    public static function get_all_product_attributes($post_type = 'product') {
        if(apply_filters('wt_iew_export_product_fetch_attributes', false) ){
            return array();
        }
        global $wpdb;

        $results = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} AS pm
            LEFT JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status IN ( 'publish', 'pending', 'private', 'draft' )
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

    public function exporter_alter_mapping_fields($fields, $base, $mapping_form_data) {
        if ($base == $this->module_base) {
            $fields = self::get_product_post_columns();
        }
        return $fields;
    }

    public function exporter_alter_advanced_fields($fields, $base, $advanced_form_data) {
		
        if ($this->module_base != $base) {
            return $fields;
        }
        $out = array();
		
        $out['only_last_modified'] = array(
            'label' => __("Export products modified since last export", 'wt-import-export-for-woo'),
            'type' => 'checkbox',
			'merge_right' => true,
			'checkbox_fields' => array( 1 => __( 'Enable', 'wt-import-export-for-woo' ) ),
			'value' => 0,
            'field_name' => 'only_last_modified',            
        );

        $out['export_children_sku'] = array(
            'label' => __("Export grouped products, up-sells, and cross-sells", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                'No' => __('Export ID ', 'wt-import-export-for-woo'),                    
                'Yes' => __('Export SKU ', 'wt-import-export-for-woo'),
            ),
            'value' => 'No',
            'field_name' => 'export_children_sku',            
        );
        
        foreach ($fields as $fieldk => $fieldv) {
            $out[$fieldk] = $fieldv;
        }

        /* export images separately */
        $out['image_export'] = array(
            'label' => __("Export product images seperately", 'wt-import-export-for-woo'),
            'type' => 'image_export',
            'value' => 'No',
            'field_name' => 'image_export',
            'field_group'=>'advanced_field',
            'checkbox_fields' => array( 1 => __( 'Enable', 'wt-import-export-for-woo' ) ),
            'tip_description' => __( 'Downloads product images in a separate zip file. The exported CSV will contain the name of the images instead of their URL path.', 'wt-import-export-for-woo' ),
            'help_text' => sprintf(__('Enable this option if you have a large number of products to import or if you experience slowness during the import process. %sLearn More.%s', 'wt-import-export-for-woo'),'<a href="https://www.webtoffee.com/exporting-importing-woocommerce-products-images-with-zip-file/" target="_blank">','</a>'),
        ); 

        return $out;
    }
    
    public function importer_alter_advanced_fields($fields, $base, $advanced_form_data) {
        if ($this->module_base != $base) {
            return $fields;
        }
        $out = array(); 
        
		
        $out['found_action_merge'] = array(
            'label' => __("If the product exists in the store", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
//                'import' => __('Import as new item'),
                'skip' => __('Skip', 'wt-import-export-for-woo'),
                'update' => __('Update', 'wt-import-export-for-woo'),                
            ),
            'value' => 'skip',
            'field_name' => 'found_action',
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('This option will not update the existing products.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_found_action', 'value'=>'skip')
                    )
                ),
                array(
                    'help_text'=> __('This option will update the existing products as per the data from the input file.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_found_action', 'value'=>'update')
                    )
                )
            ),
            'form_toggler'=>array(
                'type'=>'parent',
                'target'=>'wt_iew_found_action'
            )
        );
		
        $out['merge_with'] = array(
            'label' => __("Match products by their", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                'id' => __('ID'),
                'sku' => __('SKU'),             
            ),
            'value' => 'id',
            'field_name' => 'merge_with',
            'help_text' => __('The products are either looked up based on their ID or SKU as per the selection.', 'wt-import-export-for-woo'),
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('If the post ID of the product being imported exists already(for any of the other post types like coupon, order, pages, media etc) skip the product from being updated into the store.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_merge_with', 'value'=>'id'),
                        'AND',
                        array('field'=>'wt_iew_skip_new', 'value'=>1)
                    )
                ),
                array(
                    'help_text'=> __('If the ID of a product in the input file is different from that of the product ID in site, then match products by SKU. If in case, the product has no SKU, it will be imported as a new item even if the file contains the correct ID.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_merge_with', 'value'=>'sku'),
                    )
                )
            )
        );
		
        $out['skip_new'] = array(
            'label' => __("Skip import of new products", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
				'0' => __('No', 'wt-import-export-for-woo'),
                '1' => __('Yes', 'wt-import-export-for-woo')
            ),
            'value' => '0',
            'field_name' => 'skip_new',
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('This option will not import the new products from the input file.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_skip_new', 'value'=>1)
                    )
                ),
                array(
                    'help_text'=> __('This option will import the new products from the input file.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_skip_new', 'value'=>0)
                    )
                )
            ),
            'form_toggler'=>array(
                'type'=>'parent',
                'target'=>'wt_iew_skip_new',
            )
        );    
        
        
        $out['conflict_with_existing_post'] = array(
            'label' => __("If product ID conflicts with an existing Post ID", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(                
                'skip' => __('Skip item', 'wt-import-export-for-woo'),
                'import' => __('Import as new item', 'wt-import-export-for-woo'),                
            ),
            'value' => 'skip',
            'field_name' => 'id_conflict',
            'help_text' => __('Every post in the WooCommerce store is assigned a unique Post ID on creation. The post types could be: product, coupon, order, pages, media etc.', 'wt-import-export-for-woo'),
            'help_text_conditional'=>array(
                array(
                    'help_text'=> __('Skips the import of that particular product if there is a conflict in Post ID with an existing post.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_id_conflict', 'value'=>'skip')
                    )
                ),
                array(
                    'help_text'=> __('Imports product with a new ID.', 'wt-import-export-for-woo'),
                    'condition'=>array(
                        array('field'=>'wt_iew_id_conflict', 'value'=>'import')
                    )
                )
            ),
            'form_toggler'=>array(
                'type'=>'child',
                'id'=>'wt_iew_skip_new',
                'val'=>'0',
                'depth'=>1, /* indicates the left margin of fields */
            )
        );  
        
        $out['merge_empty_cells'] = array(
            'label' => __("Update even if empty values", 'wt-import-export-for-woo'),
            'type' => 'radio',
            'radio_fields' => array(
                '1' => __('Yes', 'wt-import-export-for-woo'),
                '0' => __('No', 'wt-import-export-for-woo')
            ),
            'value' => '0',
            'field_name' => 'merge_empty_cells',
            'help_text' => __('Updates the product data respectively even if some of the columns in the input file contains empty value.', 'wt-import-export-for-woo'),
            'form_toggler'=>array(
                'type'=>'child',
                'id'=>'wt_iew_found_action',
                'val'=>'update',
            )
        );
        
        $out['use_sku_upsell_crosssell'] = array(
            'label' => __("Use SKU to link up-sells, cross-sells and grouped products", 'wt-import-export-for-woo'),
            'type' => 'checkbox',
            'checkbox_fields' => array(
                1 => __('Enable', 'wt-import-export-for-woo')
            ),
            'value' => 0,
            'field_name' => 'use_sku_upsell_crosssell',            
        );
        
        
        $out['delete_existing'] = array(
            'label' => __("Delete non-matching products from store", 'wt-import-export-for-woo'),
            'type' => 'checkbox',
            'checkbox_fields' => array( 1 => __( 'Enable' ) ),
            'value' => 0,
            'field_name' => 'delete_existing',
            'tip_description' => __('E.g: If you have product A in your store and your import file has products B, C; then product A will get deleted from the store prior to importing B and C.', 'wt-import-export-for-woo'),
            'help_text' => __('Select this if you need to remove products from your store which are not present in the input file.', 'wt-import-export-for-woo'),
        );
        
        foreach ($fields as $fieldk => $fieldv) {
            $out[$fieldk] = $fieldv;
        }
        return $out;
    }

    /**
     *  Customize the items in filter export page
     */
    public function exporter_alter_filter_fields($fields, $base, $filter_form_data) {
        if ($this->module_base != $base) {
            return $fields;
        }

        /* altering help text of default fields */
        $fields['limit']['label']=__('Total number of products to export', 'wt-import-export-for-woo'); 
	$fields['limit']['help_text']=__('Exports specified number of products.', 'wt-import-export-for-woo');
        $fields['limit']['tip_description']=__('E.g.: Entering 500 with a skip count of 10 will export products from 11th to 510th position.', 'wt-import-export-for-woo');
        
        $fields['offset']['label']=__('Skip first <i>n</i> products', 'wt-import-export-for-woo');
	$fields['offset']['help_text']=__('Skips specified number of products from the beginning.', 'wt-import-export-for-woo');
        $fields['offset']['tip_description']=__('E.g.: Enter 10 to skip first 10 products during export.', 'wt-import-export-for-woo');        
        
        $fields['product_types'] = array(
            'label' => __('Export products by their type', 'wt-import-export-for-woo'),
            'placeholder' => __('All types', 'wt-import-export-for-woo'),
            'field_name' => 'product_types',
            'sele_vals' => self::get_product_types(),
            'help_text' => __('Filter products by their type. You can export multiple types together.', 'wt-import-export-for-woo'),
            'type' => 'multi_select',
            'css_class' => 'wc-enhanced-select',
            'validation_rule' => array('type'=>'text_arr')
            
        );

        $fields['product'] = array(
            'label' => __('Products to include', 'wt-import-export-for-woo'),
            'placeholder' => __('All products', 'wt-import-export-for-woo'),
            'field_name' => 'product',
            'sele_vals' =>  array(),
            'help_text' => __('Export specific products. Keyin the product names to export to export multiple products.', 'wt-import-export-for-woo'),
            'type' => 'multi_select',
            'css_class' => 'wc-product-search',
            'validation_rule' => array('type'=>'text_arr')
        );
        $fields['stock_status'] = array(
            'label' => __( 'Export based on stock status', 'wt-import-export-for-woo' ),
            'placeholder' => __( 'All status', 'wt-import-export-for-woo' ),
            'field_name' => 'stock_status',
            'sele_vals' => array( '' => __( 'All status', 'wt-import-export-for-woo' ), 'instock' => __( 'In Stock', 'wt-import-export-for-woo' ), 'outofstock' => __( 'Out of Stock', 'wt-import-export-for-woo' ), 'onbackorder' => __( 'On backorder', 'wt-import-export-for-woo' ) ),
            'help_text' => __( 'Export products based on stock status.', 'wt-import-export-for-woo' ),
            'type' => 'select',
            'validation_rule' => array('type'=>'text_arr')
        );         

        $fields['product_categories'] = array(
            'label' => __('Export specific product categories', 'wt-import-export-for-woo'),
            'placeholder' => __('Any category', 'wt-import-export-for-woo'),
            'field_name' => 'product_categories',
            'sele_vals' => $this->get_product_categories(),
            'help_text' => __('Export products belonging to a particular or from multiple categories. Just select the respective categories.', 'wt-import-export-for-woo'),
            'type' => 'multi_select',
            'css_class' => 'wc-enhanced-select',
            'validation_rule' => array('type'=>'sanitize_title_with_dashes_arr')
        );


        $fields['product_tags'] = array(
            'label' => __('Export specific product tags', 'wt-import-export-for-woo'),
            'placeholder' => __('Any tag', 'wt-import-export-for-woo'),
            'field_name' => 'product_tags',
            'sele_vals' => $this->get_product_tags(),
            'help_text' => __('Enter the product tags to export only the respective products that have been tagged accordingly.', 'wt-import-export-for-woo'),
            'type' => 'multi_select',
            'css_class' => 'wc-enhanced-select',
            'validation_rule' => array('type'=>'sanitize_title_with_dashes_arr')
        );

        $fields['product_status'] = array(
            'label' => __('Export based on product status', 'wt-import-export-for-woo'),
            'placeholder' => __('All status', 'wt-import-export-for-woo'),
            'field_name' => 'product_status',
            'sele_vals' => self::get_product_statuses(),
            'help_text' => __('Filter products by their status.', 'wt-import-export-for-woo'),
            'type' => 'multi_select',
            'css_class' => 'wc-enhanced-select',
            'validation_rule' => array('type'=>'text_arr')
        );

        $fields['exclude_product'] = array(
            'label' => __('Exclude Products', 'wt-import-export-for-woo'),
            'placeholder' => __('Exclude Products', 'wt-import-export-for-woo'),
            'field_name' => 'exclude_product',
            'sele_vals' => array(),
            'help_text' => __('Use this if you need to exclude a specific or multiple products from your export list.', 'wt-import-export-for-woo'),
            'type' => 'multi_select',
            'css_class' => 'wc-product-search',
            'validation_rule' => array('type'=>'text_arr')
        );        
        
        $sort_columns = self::get_product_sort_columns();
        $fields['sort_columns'] = array(
            'label' => __('Sort Columns', 'wt-import-export-for-woo'),
            'placeholder' => __('ID'),
            'field_name' => 'sort_columns',
            'sele_vals' => $sort_columns,
            'help_text' => __('Sort the exported data based on the selected columns in order specified. Defaulted to ID.', 'wt-import-export-for-woo'),
            'type' => 'select',
//            'css_class' => 'wc-enhanced-select',
            'validation_rule' => array('type'=>'text_arr')
        );
        $fields['order_by'] = array(
                'label' => __('Sort By', 'wt-import-export-for-woo'),
                'placeholder' => __('ASC'),
                'field_name' => 'order_by',
                'sele_vals' => array('ASC' => 'Ascending', 'DESC' => 'Descending'),
                'help_text' => __('Defaulted to Ascending. Applicable to above selected columns in the order specified.', 'wt-import-export-for-woo'),
                'type' => 'select',
            );

        return $fields;
    }

    /**
     * Get File name by url
     * @param string $file_url URL of the file.
     * @return string the base name of the given URL (File name).
     */
    public static function xa_wc_get_filename_from_url($file_url) {
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
    public static function wt_get_wpml_original_post_language_info($element_id) {
        $get_language_args = array('element_id' => $element_id, 'element_type' => 'post_product');
        $original_post_language_info = apply_filters('wpml_element_language_details', null, $get_language_args);
        return $original_post_language_info;
    }

    public static function wt_get_product_id_by_sku($sku) {
        global $wpdb;
        $post_exists_sku = $wpdb->get_var($wpdb->prepare("
	    		SELECT $wpdb->posts.ID
	    		FROM $wpdb->posts
	    		LEFT JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id )
	    		WHERE $wpdb->posts.post_status IN ( 'publish', 'private', 'draft', 'pending', 'future' )
	    		AND $wpdb->postmeta.meta_key = '_sku' AND $wpdb->postmeta.meta_value = '%s'
	    		", $sku));
        if ($post_exists_sku) {
            return $post_exists_sku;
        }
        return false;
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
    
    public function get_item_by_id($id) {
        $post['title'] = get_the_title($id);
        $product = wc_get_product($id);
		if (is_object($product) && $product->is_type('variation')) {
			$id = $product->get_parent_id();
		}
        $post['edit_url']=get_edit_post_link($id);
        
        return $post; 
    }
	
    public static function get_item_link_by_id($id) {
        $post['title'] = get_the_title($id);
        $product = wc_get_product($id);
		if (is_object($product) && $product->is_type('variation')) {
			$id = $product->get_parent_id();
		}
        $post['edit_url']=get_edit_post_link($id);

        return $post; 
    }	

}

new Wt_Import_Export_For_Woo_Product();
// Add category/tag/review import export addon
include_once( __DIR__ . "/../product_categories/product_categories.php" );
include_once( __DIR__ . "/../product_tags/product_tags.php" );
include_once( __DIR__ . "/../product_review/product_review.php" );
