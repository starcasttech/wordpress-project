<?php
/**
 * CSV Processor Class
 * Handles the core CSV parsing and import logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class Starcast_CSV_Processor {
    
    /**
     * Process CSV file
     */
    public static function process_file($file_path, $post_type, $mapping, $options = array()) {
        $defaults = array(
            'update_existing' => true,
            'create_missing_providers' => true,
            'skip_duplicates' => false,
            'identifier_field' => 'title', // title, sku, or custom
            'identifier_custom' => '',
            'batch_size' => 50,
            'offset' => 0
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Open CSV file
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return array('error' => __('Could not open CSV file', 'starcast-csv-importer'));
        }
        
        // Get headers
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if (!$headers) {
            fclose($handle);
            return array('error' => __('Invalid CSV file - no headers found', 'starcast-csv-importer'));
        }
        
        // Clean headers (remove BOM, trim whitespace)
        $headers = array_map(function($header) {
            return trim(str_replace("\xEF\xBB\xBF", '', $header));
        }, $headers);
        
        // Skip to offset
        for ($i = 0; $i < $options['offset']; $i++) {
            fgetcsv($handle, 0, ',', '"', '\\');
        }
        
        $results = array(
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array(),
            'total_rows' => 0,
            'processed' => 0
        );
        
        $processed = 0;
        $rows_to_process = array();
        
        // Read batch of rows
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false && $processed < $options['batch_size']) {
            if (count($row) === count($headers)) {
                $rows_to_process[] = array_combine($headers, $row);
                $processed++;
            }
        }
        
        fclose($handle);
        
        // Process each row
        foreach ($rows_to_process as $row_index => $row) {
            $result = self::process_row($row, $post_type, $mapping, $options);
            
            if ($result['status'] === 'imported') {
                $results['imported']++;
            } elseif ($result['status'] === 'updated') {
                $results['updated']++;
            } elseif ($result['status'] === 'skipped') {
                $results['skipped']++;
            } elseif ($result['status'] === 'error') {
                $results['errors'][] = sprintf(
                    __('Row %d: %s', 'starcast-csv-importer'),
                    $options['offset'] + $row_index + 2,
                    $result['message']
                );
            }
            
            $results['processed']++;
            
            // Prevent timeout
            if ($options['batch_size'] > 10) {
                set_time_limit(30);
            }
        }
        
        // Check if there are more rows
        $results['has_more'] = $processed === $options['batch_size'];
        $results['next_offset'] = $options['offset'] + $processed;
        
        return $results;
    }
    
    /**
     * Process a single row
     */
    private static function process_row($row, $post_type, $mapping, $options) {
        // Get identifier value
        $identifier_value = '';
 // Get identifier value
$identifier_value = '';
if ($options['identifier_field'] === 'title') {
    // Check both possible mapping structures
    if (isset($mapping['post_title'])) {
        $identifier_value = self::get_mapped_value($row, $mapping['post_title']);
    } elseif (isset($mapping['basic']['post_title'])) {
        $identifier_value = self::get_mapped_value($row, $mapping['basic']['post_title']);
    }
} elseif ($options['identifier_field'] === 'sku') {
    if (isset($mapping['sku'])) {
        $identifier_value = self::get_mapped_value($row, $mapping['sku']);
    } elseif (isset($mapping['meta']['sku'])) {
        $identifier_value = self::get_mapped_value($row, $mapping['meta']['sku']);
    }
} elseif ($options['identifier_field'] === 'custom' && !empty($options['identifier_custom'])) {
    $identifier_value = self::get_mapped_value($row, $options['identifier_custom']);
}
        if (empty($identifier_value)) {
            return array(
                'status' => 'error',
                'message' => __('Identifier field is empty', 'starcast-csv-importer')
            );
        }
        
        // Check if post exists
        $existing_post_id = self::find_existing_post($identifier_value, $post_type, $options);
        
        if ($existing_post_id && !$options['update_existing']) {
            return array(
                'status' => 'skipped',
                'message' => __('Post already exists', 'starcast-csv-importer')
            );
        }
        
        // Prepare post data
        $post_data = array(
            'post_type' => $post_type,
            'post_status' => 'publish'
        );
        
        // Map basic fields
        if (isset($mapping['post_title'])) {
            $post_data['post_title'] = self::get_mapped_value($row, $mapping['post_title']);
        }
        
        if (isset($mapping['post_content'])) {
            $post_data['post_content'] = self::get_mapped_value($row, $mapping['post_content']);
        }
        
        if (isset($mapping['post_excerpt'])) {
            $post_data['post_excerpt'] = self::get_mapped_value($row, $mapping['post_excerpt']);
        }
        
        // Insert or update post
        if ($existing_post_id) {
            $post_data['ID'] = $existing_post_id;
            $post_id = wp_update_post($post_data, true);
            $action = 'updated';
        } else {
            $post_id = wp_insert_post($post_data, true);
            $action = 'imported';
        }
        
        if (is_wp_error($post_id)) {
            return array(
                'status' => 'error',
                'message' => $post_id->get_error_message()
            );
        }
        
        // Handle taxonomies
        if (isset($mapping['taxonomy'])) {
            foreach ($mapping['taxonomy'] as $taxonomy => $column) {
                $term_value = self::get_mapped_value($row, $column);
                if (!empty($term_value)) {
                    self::assign_taxonomy($post_id, $taxonomy, $term_value, $options);
                }
            }
        }
        
        // Handle custom fields
        if (isset($mapping['meta'])) {
            foreach ($mapping['meta'] as $meta_key => $column) {
                $meta_value = self::get_mapped_value($row, $column);
                
                // Special handling for specific fields
                if ($post_type === 'fibre_packages') {
                    // Clean speed values
                    if (in_array($meta_key, array('download', 'upload'))) {
                        $meta_value = self::clean_speed_value($meta_value);
                    }
                    // Clean price
                    if ($meta_key === 'price') {
                        $meta_value = self::clean_price_value($meta_value);
                    }
                } elseif ($post_type === 'lte_packages') {
                    // Clean data values
                    if ($meta_key === 'data') {
                        $meta_value = self::clean_data_value($meta_value);
                    }
                    // Clean speed
                    if ($meta_key === 'speed') {
                        $meta_value = self::clean_speed_value($meta_value);
                    }
                    // Clean price
                    if ($meta_key === 'price') {
                        $meta_value = self::clean_price_value($meta_value);
                    }
                }
                
                update_post_meta($post_id, $meta_key, $meta_value);
            }
        }
        
        // Trigger action for custom processing
        do_action('starcast_csv_after_import_row', $post_id, $row, $post_type, $mapping);
        
        return array(
            'status' => $action,
            'post_id' => $post_id,
            'message' => sprintf(__('Post %s successfully', 'starcast-csv-importer'), $action)
        );
    }
    
    /**
     * Get mapped value from row
     */
    private static function get_mapped_value($row, $column) {
        if (strpos($column, '||') !== false) {
            // Multiple columns with fallback
            $columns = explode('||', $column);
            foreach ($columns as $col) {
                $col = trim($col);
                if (isset($row[$col]) && !empty($row[$col])) {
                    return trim($row[$col]);
                }
            }
            return '';
        } elseif (strpos($column, '{{') !== false) {
            // Template with placeholders
            return preg_replace_callback('/\{\{([^}]+)\}\}/', function($matches) use ($row) {
                $col = trim($matches[1]);
                return isset($row[$col]) ? $row[$col] : '';
            }, $column);
        } else {
            // Single column
            return isset($row[$column]) ? trim($row[$column]) : '';
        }
    }
    
    /**
     * Find existing post
     */
    private static function find_existing_post($identifier_value, $post_type, $options) {
        global $wpdb;
        
        if ($options['identifier_field'] === 'title') {
            // Search by title
            $post = get_page_by_title($identifier_value, OBJECT, $post_type);
            return $post ? $post->ID : false;
        } elseif ($options['identifier_field'] === 'sku' || $options['identifier_field'] === 'custom') {
            // Search by meta value
            $meta_key = $options['identifier_field'] === 'sku' ? 'sku' : $options['identifier_custom'];
            
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = %s 
                AND pm.meta_value = %s
                AND p.post_type = %s
                AND p.post_status != 'trash'
                LIMIT 1",
                $meta_key,
                $identifier_value,
                $post_type
            ));
            
            return $post_id ? intval($post_id) : false;
        }
        
        return false;
    }
    
    /**
     * Assign taxonomy terms
     */
    private static function assign_taxonomy($post_id, $taxonomy, $term_value, $options) {
        // Handle multiple terms separated by comma
        $terms = array_map('trim', explode(',', $term_value));
        $term_ids = array();
        
        foreach ($terms as $term_name) {
            if (empty($term_name)) {
                continue;
            }
            
            // Check if term exists
            $term = term_exists($term_name, $taxonomy);
            
            if (!$term && $options['create_missing_providers']) {
                // Create term
                $term = wp_insert_term($term_name, $taxonomy);
                if (!is_wp_error($term)) {
                    $term_ids[] = intval($term['term_id']);
                }
            } elseif ($term) {
                $term_ids[] = intval($term['term_id']);
            }
        }
        
        if (!empty($term_ids)) {
            wp_set_object_terms($post_id, $term_ids, $taxonomy);
        }
    }
    
    /**
     * Clean speed value
     */
    private static function clean_speed_value($value) {
        // Remove common units and clean
        $value = str_ireplace(array('mbps', 'mb/s', 'mb', 'kbps', 'kb/s', 'kb'), '', $value);
        $value = trim($value);
        
        // Extract numeric value
        if (preg_match('/(\d+(?:\.\d+)?)/', $value, $matches)) {
            return $matches[1];
        }
        
        return $value;
    }
    
    /**
     * Clean price value
     */
    private static function clean_price_value($value) {
        // Remove currency symbols and clean
        $value = str_replace(array('R', 'r', '$', '€', '£', ',', ' '), '', $value);
        $value = trim($value);
        
        // Extract numeric value
        if (preg_match('/(\d+(?:\.\d+)?)/', $value, $matches)) {
            return round(floatval($matches[1]));
        }
        
        return $value;
    }
    
    /**
     * Clean data value
     */
    private static function clean_data_value($value) {
        // Standardize unlimited/uncapped
        if (preg_match('/unlimited|uncapped/i', $value)) {
            return 'Uncapped';
        }
        
        // Keep original value for specific data amounts
        return trim($value);
    }
    
    /**
     * Get CSV preview
     */
    public static function get_csv_preview($file_path, $rows = 5) {
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return false;
        }
        
        $preview = array();
        $count = 0;
        
        // Get headers
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if ($headers) {
            // Clean headers
            $headers = array_map(function($header) {
                return trim(str_replace("\xEF\xBB\xBF", '', $header));
            }, $headers);
            
            $preview['headers'] = $headers;
            $preview['rows'] = array();
            
            // Get sample rows
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false && $count < $rows) {
                if (count($row) === count($headers)) {
                    $preview['rows'][] = array_combine($headers, $row);
                    $count++;
                }
            }
            
            // Count total rows
            $total = $count;
            while (fgetcsv($handle, 0, ',', '"', '\\') !== false) {
                $total++;
            }
            
            $preview['total_rows'] = $total;
        }
        
        fclose($handle);
        
        return $preview;
    }
    
    /**
     * Validate CSV file
     */
    public static function validate_csv($file_path) {
        $errors = array();
        
        // Check file exists
        if (!file_exists($file_path)) {
            $errors[] = __('File does not exist', 'starcast-csv-importer');
            return $errors;
        }
        
        // Check file is readable
        if (!is_readable($file_path)) {
            $errors[] = __('File is not readable', 'starcast-csv-importer');
            return $errors;
        }
        
        // Check file size
        $file_size = filesize($file_path);
        if ($file_size === 0) {
            $errors[] = __('File is empty', 'starcast-csv-importer');
            return $errors;
        }
        
        // Try to open file
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            $errors[] = __('Cannot open file', 'starcast-csv-importer');
            return $errors;
        }
        
        // Check headers
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if (!$headers || count($headers) === 0) {
            $errors[] = __('No headers found in CSV file', 'starcast-csv-importer');
        }
        
        // Check for empty columns
        $empty_headers = array_filter($headers, function($header) {
            return empty(trim($header));
        });
        
        if (!empty($empty_headers)) {
            $errors[] = __('CSV contains empty column headers', 'starcast-csv-importer');
        }
        
        // Check for duplicate headers
        $header_counts = array_count_values($headers);
        $duplicates = array_filter($header_counts, function($count) {
            return $count > 1;
        });
        
        if (!empty($duplicates)) {
            $errors[] = sprintf(
                __('Duplicate column headers found: %s', 'starcast-csv-importer'),
                implode(', ', array_keys($duplicates))
            );
        }
        
        fclose($handle);
        
        return $errors;
    }
}