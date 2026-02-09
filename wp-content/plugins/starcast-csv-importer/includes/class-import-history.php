<?php
/**
 * Import History Class
 * Handles database operations for import history
 */

if (!defined('ABSPATH')) {
    exit;
}

class Starcast_CSV_Import_History {
    
    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'starcast_csv_imports';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            file_name varchar(255) NOT NULL,
            post_type varchar(50) NOT NULL,
            total_rows int(11) NOT NULL DEFAULT 0,
            imported int(11) NOT NULL DEFAULT 0,
            updated int(11) NOT NULL DEFAULT 0,
            skipped int(11) NOT NULL DEFAULT 0,
            errors int(11) NOT NULL DEFAULT 0,
            error_messages longtext,
            status varchar(20) NOT NULL DEFAULT 'processing',
            user_id bigint(20) NOT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY post_type (post_type),
            KEY user_id (user_id),
            KEY status (status),
            KEY started_at (started_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create new import entry
     */
    public static function create_entry($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'starcast_csv_imports';
        
        $wpdb->insert(
            $table_name,
            array(
                'file_name' => $data['file_name'],
                'post_type' => $data['post_type'],
                'total_rows' => $data['total_rows'],
                'user_id' => $data['user_id'],
                'status' => 'processing',
                'started_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%d', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update import entry with results
     */
    public static function update_entry($id, $results) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'starcast_csv_imports';
        
        // Get current values
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT imported, updated, skipped, errors FROM $table_name WHERE id = %d",
            $id
        ));
        
        if (!$current) {
            return false;
        }
        
        // Update with new values
        $data = array(
            'imported' => $current->imported + $results['imported'],
            'updated' => $current->updated + $results['updated'],
            'skipped' => $current->skipped + $results['skipped'],
            'errors' => $current->errors + count($results['errors'])
        );
        
        // Handle error messages
        if (!empty($results['errors'])) {
            $existing_errors = $wpdb->get_var($wpdb->prepare(
                "SELECT error_messages FROM $table_name WHERE id = %d",
                $id
            ));
            
            $all_errors = array();
            if ($existing_errors) {
                $all_errors = json_decode($existing_errors, true) ?: array();
            }
            
            $all_errors = array_merge($all_errors, $results['errors']);
            $data['error_messages'] = json_encode($all_errors);
        }
        
        $wpdb->update(
            $table_name,
            $data,
            array('id' => $id),
            array('%d', '%d', '%d', '%d', '%s'),
            array('%d')
        );
        
        return true;
    }
    
    /**
     * Mark import as complete
     */
    public static function complete_entry($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'starcast_csv_imports';
        
        $wpdb->update(
            $table_name,
            array(
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get import entries
     */
    public static function get_entries($page = 1, $per_page = 20) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'starcast_csv_imports';
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Get entries
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, u.display_name as user_name 
            FROM $table_name i
            LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
            ORDER BY i.started_at DESC
            LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        // Process entries
        foreach ($entries as &$entry) {
            if ($entry->error_messages) {
                $entry->error_messages = json_decode($entry->error_messages, true);
            }
            
            // Calculate duration
            if ($entry->completed_at) {
                $start = new DateTime($entry->started_at);
                $end = new DateTime($entry->completed_at);
                $diff = $start->diff($end);
                $entry->duration = $diff->format('%H:%I:%S');
            } else {
                $entry->duration = '-';
            }
            
            // Format dates
            $entry->started_at_formatted = get_date_from_gmt($entry->started_at, 'Y-m-d H:i:s');
            $entry->completed_at_formatted = $entry->completed_at ? get_date_from_gmt($entry->completed_at, 'Y-m-d H:i:s') : '-';
        }
        
        return array(
            'entries' => $entries,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page
        );
    }
    
    /**
     * Get single entry
     */
    public static function get_entry($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'starcast_csv_imports';
        
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, u.display_name as user_name 
            FROM $table_name i
            LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
            WHERE i.id = %d",
            $id
        ));
        
        if ($entry && $entry->error_messages) {
            $entry->error_messages = json_decode($entry->error_messages, true);
        }
        
        return $entry;
    }
    
    /**
     * Delete old entries
     */
    public static function cleanup_old_entries($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'starcast_csv_imports';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name 
            WHERE started_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    /**
     * Get statistics
     */
    public static function get_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'starcast_csv_imports';
        
        $stats = array();
        
        // Total imports
        $stats['total_imports'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Successful imports
        $stats['successful_imports'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name WHERE status = 'completed' AND errors = 0"
        );
        
        // Total rows processed
        $stats['total_rows_processed'] = $wpdb->get_var(
            "SELECT SUM(imported + updated + skipped) FROM $table_name"
        );
        
        // Imports by post type
        $stats['by_post_type'] = $wpdb->get_results(
            "SELECT post_type, COUNT(*) as count 
            FROM $table_name 
            GROUP BY post_type"
        );
        
        // Recent imports
        $stats['recent_imports'] = $wpdb->get_results(
            "SELECT * FROM $table_name 
            ORDER BY started_at DESC 
            LIMIT 5"
        );
        
        return $stats;
    }
}