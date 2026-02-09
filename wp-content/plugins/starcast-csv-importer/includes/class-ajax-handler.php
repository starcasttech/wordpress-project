<?php
/**
 * AJAX Handler Class
 * Handles all AJAX requests for the importer
 */

if (!defined('ABSPATH')) {
    exit;
}

class Starcast_CSV_Ajax_Handler {
    
    /**
     * Handle file upload
     */
    public static function handle_upload() {
        // Verify nonce
        if (!check_ajax_referer('starcast_csv_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'starcast-csv-importer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'starcast-csv-importer'));
        }
        
        // Check file upload
        if (empty($_FILES['csv_file'])) {
            wp_send_json_error(__('No file uploaded', 'starcast-csv-importer'));
        }
        
        $uploaded_file = $_FILES['csv_file'];
        
        // Validate file type
        $file_type = wp_check_filetype($uploaded_file['name']);
        if ($file_type['ext'] !== 'csv') {
            wp_send_json_error(__('Please upload a CSV file', 'starcast-csv-importer'));
        }
        
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $csv_dir = $upload_dir['basedir'] . '/starcast-csv-imports';
        
        if (!file_exists($csv_dir)) {
            wp_mkdir_p($csv_dir);
        }
        
        // Generate unique filename
        $filename = 'import_' . time() . '_' . sanitize_file_name($uploaded_file['name']);
        $file_path = $csv_dir . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
            wp_send_json_error(__('Failed to save uploaded file', 'starcast-csv-importer'));
        }
        
        // Validate CSV
        $validation_errors = Starcast_CSV_Processor::validate_csv($file_path);
        if (!empty($validation_errors)) {
            unlink($file_path);
            wp_send_json_error(array(
                'message' => __('CSV validation failed', 'starcast-csv-importer'),
                'errors' => $validation_errors
            ));
        }
        
        // Get CSV preview
        $preview = Starcast_CSV_Processor::get_csv_preview($file_path);
        if (!$preview) {
            unlink($file_path);
            wp_send_json_error(__('Failed to read CSV file', 'starcast-csv-importer'));
        }
        
        // Store file info in transient
        $file_info = array(
            'path' => $file_path,
            'name' => $uploaded_file['name'],
            'size' => $uploaded_file['size'],
            'headers' => $preview['headers'],
            'total_rows' => $preview['total_rows']
        );
        
        $transient_key = 'starcast_csv_file_' . get_current_user_id();
        set_transient($transient_key, $file_info, HOUR_IN_SECONDS);
        
        wp_send_json_success(array(
            'message' => __('File uploaded successfully', 'starcast-csv-importer'),
            'file' => array(
                'name' => $uploaded_file['name'],
                'size' => size_format($uploaded_file['size']),
                'rows' => $preview['total_rows']
            ),
            'preview' => $preview
        ));
    }
    
    /**
     * Get CSV columns for mapping
     */
    public static function get_columns() {
        // Verify nonce
        if (!check_ajax_referer('starcast_csv_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'starcast-csv-importer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'starcast-csv-importer'));
        }
        
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        
        if (!in_array($post_type, array('fibre_packages', 'lte_packages'))) {
            wp_send_json_error(__('Invalid post type', 'starcast-csv-importer'));
        }
        
        // Get file info from transient
        $transient_key = 'starcast_csv_file_' . get_current_user_id();
        $file_info = get_transient($transient_key);
        
        if (!$file_info) {
            wp_send_json_error(__('File session expired. Please upload again.', 'starcast-csv-importer'));
        }
        
        // Get preview
        $preview = Starcast_CSV_Processor::get_csv_preview($file_info['path'], 3);
        
        // Build field mapping options
        $mapping_fields = self::get_mapping_fields($post_type);
        
        // Get saved mappings
        $saved_mappings = get_option('starcast_csv_saved_mappings', array());
        $type_mappings = isset($saved_mappings[$post_type]) ? $saved_mappings[$post_type] : array();
        
        wp_send_json_success(array(
            'columns' => $file_info['headers'],
            'preview' => $preview,
            'fields' => $mapping_fields,
            'saved_mappings' => $type_mappings
        ));
    }
    
    /**
     * Process import batch
     */
    public static function process_batch() {
        // Verify nonce
        if (!check_ajax_referer('starcast_csv_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'starcast-csv-importer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'starcast-csv-importer'));
        }
        
        // Get parameters
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        $mapping = isset($_POST['mapping']) ? json_decode(stripslashes($_POST['mapping']), true) : array();
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $import_id = isset($_POST['import_id']) ? sanitize_text_field($_POST['import_id']) : '';
        
        // Get options
        $options = array(
            'update_existing' => isset($_POST['update_existing']) && $_POST['update_existing'] === 'true',
            'create_missing_providers' => isset($_POST['create_providers']) && $_POST['create_providers'] === 'true',
            'skip_duplicates' => isset($_POST['skip_duplicates']) && $_POST['skip_duplicates'] === 'true',
            'identifier_field' => isset($_POST['identifier_field']) ? sanitize_text_field($_POST['identifier_field']) : 'title',
            'identifier_custom' => isset($_POST['identifier_custom']) ? sanitize_text_field($_POST['identifier_custom']) : '',
            'batch_size' => Starcast_CSV_Importer::get_option('batch_size', 50),
            'offset' => $offset
        );
        
        // Get file info
        $transient_key = 'starcast_csv_file_' . get_current_user_id();
        $file_info = get_transient($transient_key);
        
        if (!$file_info || !file_exists($file_info['path'])) {
            wp_send_json_error(__('File not found. Please upload again.', 'starcast-csv-importer'));
        }
        
        // Process batch
        $results = Starcast_CSV_Processor::process_file(
            $file_info['path'],
            $post_type,
            $mapping,
            $options
        );
        
        // Update history if first batch
        if ($offset === 0 && Starcast_CSV_Importer::get_option('log_imports', true)) {
            $import_id = Starcast_CSV_Import_History::create_entry(array(
                'file_name' => $file_info['name'],
                'post_type' => $post_type,
                'total_rows' => $file_info['total_rows'],
                'user_id' => get_current_user_id()
            ));
        }
        
        // Update history with results
        if (!empty($import_id) && Starcast_CSV_Importer::get_option('log_imports', true)) {
            Starcast_CSV_Import_History::update_entry($import_id, $results);
        }
        
        // Calculate progress
        $progress = array(
            'current' => $results['next_offset'],
            'total' => $file_info['total_rows'],
            'percentage' => round(($results['next_offset'] / $file_info['total_rows']) * 100)
        );
        
        // Prepare response
        $response = array(
            'results' => $results,
            'progress' => $progress,
            'import_id' => $import_id,
            'has_more' => $results['has_more']
        );
        
        // Clean up if import is complete
        if (!$results['has_more']) {
            // Mark import as complete
            if (!empty($import_id)) {
                Starcast_CSV_Import_History::complete_entry($import_id);
            }
            
            // Clean up transient
            delete_transient($transient_key);
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Save field mapping
     */
    public static function save_mapping() {
        // Verify nonce
        if (!check_ajax_referer('starcast_csv_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'starcast-csv-importer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'starcast-csv-importer'));
        }
        
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
        $mapping_name = isset($_POST['mapping_name']) ? sanitize_text_field($_POST['mapping_name']) : '';
        $mapping = isset($_POST['mapping']) ? json_decode(stripslashes($_POST['mapping']), true) : array();
        
        if (empty($post_type) || empty($mapping_name) || empty($mapping)) {
            wp_send_json_error(__('Invalid mapping data', 'starcast-csv-importer'));
        }
        
        // Get existing mappings
        $saved_mappings = get_option('starcast_csv_saved_mappings', array());
        
        // Add new mapping
        if (!isset($saved_mappings[$post_type])) {
            $saved_mappings[$post_type] = array();
        }
        
        $saved_mappings[$post_type][$mapping_name] = array(
            'name' => $mapping_name,
            'mapping' => $mapping,
            'created' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        
        // Save mappings
        update_option('starcast_csv_saved_mappings', $saved_mappings);
        
        wp_send_json_success(array(
            'message' => __('Mapping saved successfully', 'starcast-csv-importer'),
            'mappings' => $saved_mappings[$post_type]
        ));
    }
    
    /**
     * Get import history
     */
    public static function get_history() {
        // Verify nonce
        if (!check_ajax_referer('starcast_csv_nonce', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'starcast-csv-importer'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'starcast-csv-importer'));
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        
        $history = Starcast_CSV_Import_History::get_entries($page, $per_page);
        
        wp_send_json_success($history);
    }
    
    /**
     * Get mapping fields for post type
     */
    private static function get_mapping_fields($post_type) {
        $fields = array(
            'basic' => array(
                'label' => __('Basic Fields', 'starcast-csv-importer'),
                'fields' => array(
                    'post_title' => __('Package Title', 'starcast-csv-importer'),
                    'post_content' => __('Description', 'starcast-csv-importer'),
                    'post_excerpt' => __('Short Description', 'starcast-csv-importer')
                )
            )
        );
        
        // Add taxonomy fields
        if ($post_type === 'fibre_packages') {
            $fields['taxonomies'] = array(
                'label' => __('Categories', 'starcast-csv-importer'),
                'fields' => array(
                    'fibre_provider' => __('Provider', 'starcast-csv-importer')
                )
            );
            
            $fields['custom_fields'] = array(
                'label' => __('Package Details', 'starcast-csv-importer'),
                'fields' => array(
                    'download' => __('Download Speed', 'starcast-csv-importer'),
                    'upload' => __('Upload Speed', 'starcast-csv-importer'),
                    'price' => __('Price', 'starcast-csv-importer')
                )
            );
        } elseif ($post_type === 'lte_packages') {
            $fields['taxonomies'] = array(
                'label' => __('Categories', 'starcast-csv-importer'),
                'fields' => array(
                    'lte_provider' => __('Provider', 'starcast-csv-importer')
                )
            );
            
            $fields['custom_fields'] = array(
                'label' => __('Package Details', 'starcast-csv-importer'),
                'fields' => array(
                    'speed' => __('Speed', 'starcast-csv-importer'),
                    'data' => __('Data Allowance', 'starcast-csv-importer'),
                    'aup' => __('AUP Limit', 'starcast-csv-importer'),
                    'throttle' => __('Throttle Speed', 'starcast-csv-importer'),
                    'price' => __('Price', 'starcast-csv-importer'),
                    'package_type' => __('Package Type', 'starcast-csv-importer'),
                    'display_order' => __('Display Order', 'starcast-csv-importer')
                )
            );
        }
        
        // Add identifier fields
        $fields['identifiers'] = array(
            'label' => __('Unique Identifiers', 'starcast-csv-importer'),
            'fields' => array(
                'sku' => __('SKU', 'starcast-csv-importer')
            )
        );
        
        // Allow plugins to add custom fields
        $fields = apply_filters('starcast_csv_mapping_fields', $fields, $post_type);
        
        return $fields;
    }
}