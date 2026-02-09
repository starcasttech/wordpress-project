<?php
/**
 * Plugin Name: Starcast Signup Manager  
 * Plugin URI: http://localhost
 * Description: Admin panel for managing customer signups, verifying details, and approving/rejecting fibre/LTE applications
 * Version: 1.0.0
 * Author: Starcast Technologies
 * License: GPL v2 or later
 * Text Domain: starcast-signup-manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class StarcastSignupManager {

    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'starcast_signups';

        $this->maybe_create_table();

        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers
        add_action('wp_ajax_starcast_update_signup_status', array($this, 'ajax_update_signup_status'));
        add_action('wp_ajax_starcast_add_signup_note', array($this, 'ajax_add_signup_note'));
        add_action('wp_ajax_starcast_cleanup_rejected_signups', array($this, 'ajax_cleanup_rejected_signups'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Signup Management',
            'Signups',
            'manage_options',
            'starcast-signups',
            array($this, 'render_signups_page'),
            'dashicons-groups',
            25
        );

        add_submenu_page(
            'starcast-signups',
            'All Signups',
            'All Signups',
            'manage_options',
            'starcast-signups',
            array($this, 'render_signups_page')
        );

        add_submenu_page(
            'starcast-signups',
            'Pending Approval',
            'Pending Approval',
            'manage_options',
            'starcast-signups-pending',
            array($this, 'render_pending_signups_page')
        );

        add_submenu_page(
            'starcast-signups',
            'Approved',
            'Approved',
            'manage_options',
            'starcast-signups-approved',
            array($this, 'render_approved_signups_page')
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'starcast-signups') === false) {
            return;
        }

        wp_register_style('starcast-signup-manager-css', false);
        wp_enqueue_style('starcast-signup-manager-css');
        wp_add_inline_style('starcast-signup-manager-css', $this->get_admin_css());

        wp_register_script('starcast-signup-manager-js', '', array('jquery'), '1.0', true);
        wp_enqueue_script('starcast-signup-manager-js');
        wp_add_inline_script('starcast-signup-manager-js', $this->get_admin_js());

        wp_localize_script('starcast-signup-manager-js', 'starcastSignups', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('starcast_signups_nonce')
        ));
    }

    public function render_signups_page() {
        $this->sync_pending_orders_to_signups();
        $this->sync_approved_signups_to_users();
        $signups = $this->get_signups();
        $this->render_signups_list($signups, 'All Signups');
    }

    public function render_pending_signups_page() {
        $this->sync_pending_orders_to_signups();
        $this->sync_approved_signups_to_users();
        $signups = $this->get_signups('pending');
        $this->render_signups_list($signups, 'Pending Approval');
    }

    public function render_approved_signups_page() {
        $this->sync_pending_orders_to_signups();
        $this->sync_approved_signups_to_users();
        $signups = $this->get_signups('approved');
        $this->render_signups_list($signups, 'Approved Signups');
    }

    private function maybe_create_table() {
        global $wpdb;

        $table_name = $this->table_name;
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));

        if ($table_exists) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(20) NOT NULL,
            customer_address text NOT NULL,
            package_id mediumint(9),
            package_type enum('fibre', 'lte') NOT NULL,
            installation_address text,
            preferred_contact_method enum('email', 'phone', 'sms') DEFAULT 'email',
            notes text,
            status enum('pending', 'processing', 'approved', 'rejected') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email (customer_email),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) $charset_collate;";

        dbDelta($sql);
    }

    private function sync_pending_orders_to_signups() {
        if (!function_exists('wc_get_orders')) {
            return;
        }

        $orders = wc_get_orders(array(
            'limit' => 200,
            'status' => array('pending', 'on-hold', 'processing'),
            'type' => 'shop_order',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_requires_manual_approval',
                    'value' => 'yes',
                    'compare' => '='
                ),
                array(
                    'key' => '_application_type',
                    'value' => array('fibre_subscription', 'lte_subscription'),
                    'compare' => 'IN'
                )
            )
        ));

        if (empty($orders)) {
            return;
        }

        global $wpdb;
        foreach ($orders as $order) {
            $order_id = $order->get_id();

            $existing_signup = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE notes LIKE %s",
                '%Order ID: ' . $order_id . '%'
            ));
            if ($existing_signup) {
                continue;
            }

            $application_data = $order->get_meta('_customer_application_data');
            $first_name = is_array($application_data) && isset($application_data['first_name'])
                ? $application_data['first_name']
                : $order->get_billing_first_name();
            $last_name = is_array($application_data) && isset($application_data['last_name'])
                ? $application_data['last_name']
                : $order->get_billing_last_name();
            $customer_name = trim($first_name . ' ' . $last_name);
            $customer_email = is_array($application_data) && isset($application_data['email'])
                ? $application_data['email']
                : $order->get_billing_email();
            $customer_phone = is_array($application_data) && isset($application_data['phone'])
                ? $application_data['phone']
                : $order->get_billing_phone();
            $installation_address = is_array($application_data) && isset($application_data['installation_address'])
                ? $application_data['installation_address']
                : $order->get_meta('_installation_address');

            $customer_address = '';
            if (is_array($application_data) && isset($application_data['customer_address'])) {
                $customer_address = $application_data['customer_address'];
            } else {
                $customer_address = trim(
                    $order->get_billing_address_1() . ' ' .
                    $order->get_billing_address_2() . ', ' .
                    $order->get_billing_city() . ', ' .
                    $order->get_billing_state() . ' ' .
                    $order->get_billing_postcode()
                );
            }

            $package_id = $order->get_meta('_fibre_package_id');
            if (!$package_id) {
                $package_id = $order->get_meta('_lte_package_id');
            }
            if (!$package_id) {
                foreach ($order->get_items() as $item) {
                    $package_id = $item->get_product_id();
                    break;
                }
            }

            $application_type = $order->get_meta('_application_type');
            $package_type = (is_string($application_type) && strpos($application_type, 'lte') !== false)
                ? 'lte'
                : 'fibre';

            $notes = 'WooCommerce Order ID: ' . $order_id;

            $wpdb->insert(
                $this->table_name,
                array(
                    'customer_name' => $customer_name,
                    'customer_email' => $customer_email,
                    'customer_phone' => $customer_phone,
                    'customer_address' => $customer_address,
                    'installation_address' => $installation_address ?: $customer_address,
                    'package_id' => $package_id ?: null,
                    'package_type' => $package_type,
                    'status' => 'pending',
                    'notes' => $notes
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
            );
        }
    }

    private function sync_approved_signups_to_users() {
        global $wpdb;

        $approved = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE status = 'approved' ORDER BY updated_at DESC LIMIT 200"
        );

        if (empty($approved)) {
            return;
        }

        foreach ($approved as $signup) {
            $user = get_user_by('email', $signup->customer_email);
            if (!$user) {
                continue;
            }

            $user_id = $user->ID;
            $order_id = $this->get_order_id_from_signup($signup->id);
            $order = $order_id ? wc_get_order($order_id) : null;

            if ($order_id) {
                update_post_meta($order_id, '_customer_user', $user_id);
                if ($order) {
                    $order->set_customer_id($user_id);
                    $order->save();
                }
                update_user_meta($user_id, 'starcast_order_id', $order_id);
            }

            if (!empty($signup->customer_phone)) {
                update_user_meta($user_id, 'billing_phone', $signup->customer_phone);
            }
            if (!empty($signup->installation_address)) {
                update_user_meta($user_id, 'starcast_installation_address', $signup->installation_address);
            }
            if (!empty($signup->package_id)) {
                update_user_meta($user_id, 'starcast_package_id', $signup->package_id);
            }
            if (!empty($signup->package_type)) {
                update_user_meta($user_id, 'starcast_package_type', $signup->package_type);
            }

            if ($order) {
                update_user_meta($user_id, 'starcast_monthly_price', (float) $order->get_total());
                $application_data = $order->get_meta('_customer_application_data');
                if (is_array($application_data) && !empty($application_data['id_number'])) {
                    update_user_meta($user_id, 'starcast_id_number', $application_data['id_number']);
                }
            }
        }
    }

    private function get_signups($status = null) {
        global $wpdb;

        if ($status) {
            $signups = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE status = %s ORDER BY created_at DESC",
                $status
            ));
        } else {
            $signups = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY created_at DESC");
        }

        return $signups;
    }

    private function render_signups_list($signups, $title) {
        ?>
        <div class="wrap starcast-signups-manager">
            <h1><?php echo esc_html($title); ?></h1>

            <?php if ($title === 'Approved Signups'): ?>
            <div class="cleanup-actions" style="margin-bottom: 20px;">
                <button type="button" class="button button-primary" id="cleanup-rejected-signups">
                    🗑️ Delete All Rejected Signups
                </button>
                <p class="description">Remove all rejected signup records from the database. This allows customers to reapply in the future.</p>
            </div>
            <?php endif; ?>

            <div class="signup-stats">
                <div class="stat-card pending-stat">
                    <div class="stat-number"><?php echo $this->get_count_by_status('pending'); ?></div>
                    <div class="stat-label">Pending Approval</div>
                </div>
                <div class="stat-card approved-stat">
                    <div class="stat-number"><?php echo $this->get_count_by_status('approved'); ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card processing-stat">
                    <div class="stat-number"><?php echo $this->get_count_by_status('processing'); ?></div>
                    <div class="stat-label">Processing</div>
                </div>
                <div class="stat-card rejected-stat">
                    <div class="stat-number"><?php echo $this->get_count_by_status('rejected'); ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <?php if (empty($signups)): ?>
                <div class="no-signups">
                    <p>No signups found.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Package Type</th>
                            <th>Package</th>
                            <th>Installation Address</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($signups as $signup): ?>
                        <tr data-signup-id="<?php echo esc_attr($signup->id); ?>">
                            <td><?php echo esc_html($signup->id); ?></td>
                            <td><strong><?php echo esc_html($signup->customer_name); ?></strong></td>
                            <td><a href="mailto:<?php echo esc_attr($signup->customer_email); ?>"><?php echo esc_html($signup->customer_email); ?></a></td>
                            <td><a href="tel:<?php echo esc_attr($signup->customer_phone); ?>"><?php echo esc_html($signup->customer_phone); ?></a></td>
                            <td>
                                <span class="package-type-badge <?php echo esc_attr($signup->package_type); ?>">
                                    <?php echo esc_html(ucfirst($signup->package_type)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($this->get_package_name($signup->package_id)); ?></td>
                            <td><?php echo esc_html(substr($signup->installation_address, 0, 50)) . (strlen($signup->installation_address) > 50 ? '...' : ''); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($signup->status); ?>">
                                    <?php echo esc_html(ucfirst($signup->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($signup->created_at))); ?></td>
                            <td>
                                <button type="button" class="button view-details" data-signup-id="<?php echo esc_attr($signup->id); ?>">
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div id="signup-details-modal" class="signup-modal" style="display: none;">
                <div class="modal-content">
                    <span class="close-modal">&times;</span>
                    <div class="modal-body">
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_count_by_status($status) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
            $status
        ));
    }

    private function get_package_name($package_id) {
        if (!$package_id) {
            return 'N/A';
        }

        $package = get_post($package_id);
        return $package ? $package->post_title : 'Package #' . $package_id;
    }

    public function ajax_update_signup_status() {
        check_ajax_referer('starcast_signups_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $signup_id = isset($_POST['signup_id']) ? intval($_POST['signup_id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$signup_id || !in_array($new_status, array('pending', 'processing', 'approved', 'rejected'))) {
            wp_send_json_error('Invalid parameters');
        }

        global $wpdb;
        $result = $wpdb->update(
            $this->table_name,
            array('status' => $new_status, 'updated_at' => current_time('mysql')),
            array('id' => $signup_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result !== false) {
            // Get signup data for email/WhatsApp
            $signup = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $signup_id
            ), ARRAY_A);

            $order_id = $this->get_order_id_from_signup($signup_id);
            if ($order_id) {
                $this->sync_order_application_status($order_id, $new_status);
            }

            if ($new_status === 'approved') {
                $this->send_approval_email($signup_id);
            } elseif ($new_status === 'rejected') {
                $this->send_rejection_email($signup_id);
            }

            // Trigger WhatsApp status update (will be caught by WhatsApp plugin)
            if ($signup) {
                do_action('starcast_signup_status_changed', $signup_id, $new_status, $signup);
            }

            wp_send_json_success('Status updated successfully');
        } else {
            wp_send_json_error('Failed to update status');
        }
    }

    public function ajax_add_signup_note() {
        check_ajax_referer('starcast_signups_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $signup_id = isset($_POST['signup_id']) ? intval($_POST['signup_id']) : 0;
        $note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';

        if (!$signup_id || !$note) {
            wp_send_json_error('Invalid parameters');
        }

        global $wpdb;
        $existing_notes = $wpdb->get_var($wpdb->prepare(
            "SELECT notes FROM {$this->table_name} WHERE id = %d",
            $signup_id
        ));

        $timestamp = current_time('mysql');
        $current_user = wp_get_current_user();
        $new_note = sprintf("[%s by %s]\n%s\n\n", $timestamp, $current_user->display_name, $note);
        $updated_notes = $new_note . ($existing_notes ?: '');

        $result = $wpdb->update(
            $this->table_name,
            array('notes' => $updated_notes, 'updated_at' => $timestamp),
            array('id' => $signup_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success('Note added successfully');
        } else {
            wp_send_json_error('Failed to add note');
        }
    }

    public function ajax_cleanup_rejected_signups() {
        check_ajax_referer('starcast_signups_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;

        // Get count of rejected signups before deletion
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
            'rejected'
        ));

        if ($count == 0) {
            wp_send_json_error('No rejected signups found to delete');
            return;
        }

        // Delete all rejected signups from the table
        $deleted = $wpdb->delete(
            $this->table_name,
            array('status' => 'rejected'),
            array('%s')
        );

        if ($deleted !== false) {
            wp_send_json_success(array(
                'message' => sprintf('Successfully deleted %d rejected signup(s)', $count),
                'count' => $count
            ));
        } else {
            wp_send_json_error('Failed to delete rejected signups');
        }
    }

    private function send_approval_email($signup_id) {
        global $wpdb;

        $signup = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $signup_id
        ));

        if (!$signup) {
            error_log("Starcast: Signup #{$signup_id} not found for approval");
            return false;
        }

        // Check if user already exists
        $user = get_user_by('email', $signup->customer_email);

        if ($user) {
            // User already exists, just send notification
            error_log("Starcast: User already exists for {$signup->customer_email}, sending notification only");
            $username = $user->user_login;
            $password = null; // Don't regenerate password
            $user_id = $user->ID;
        } else {
            // Create new WordPress user account
            $username = $signup->customer_email;
            $password = wp_generate_password(12, true, true);

            $user_id = wp_create_user($username, $password, $signup->customer_email);

            if (is_wp_error($user_id)) {
                error_log("Starcast: Failed to create user for {$signup->customer_email}: " . $user_id->get_error_message());
                return false;
            }

            // Update user meta
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => explode(' ', $signup->customer_name)[0],
                'last_name' => implode(' ', array_slice(explode(' ', $signup->customer_name), 1)),
                'display_name' => $signup->customer_name,
                'role' => 'customer'
            ));

            // Link user to WooCommerce order if exists
            $order_id = $this->get_order_id_from_signup($signup_id);
            if ($order_id) {
                update_post_meta($order_id, '_customer_user', $user_id);
            }

            error_log("Starcast: Created user account #{$user_id} for {$signup->customer_email}");
        }

        // Send approval email with credentials
        $order_id = $this->get_order_id_from_signup($signup_id);
        $order = null;
        if ($order_id) {
            update_post_meta($order_id, '_customer_user', $user_id);
            $order = wc_get_order($order_id);
            if ($order) {
                $order->set_customer_id($user_id);
                $order->save();
            }
        }

        $application_data = array();
        if ($order) {
            $application_data = $order->get_meta('_customer_application_data');
        }

        $installation_address = $signup->installation_address;
        if (empty($installation_address) && is_array($application_data) && isset($application_data['installation_address'])) {
            $installation_address = $application_data['installation_address'];
        }

        $id_number = '';
        if (is_array($application_data) && isset($application_data['id_number'])) {
            $id_number = $application_data['id_number'];
        }

        $customer_address = '';
        if (is_array($application_data) && isset($application_data['customer_address'])) {
            $customer_address = $application_data['customer_address'];
        }

        $monthly_price = $order ? (float) $order->get_total() : null;

        if (!empty($signup->customer_phone)) {
            update_user_meta($user_id, 'billing_phone', $signup->customer_phone);
        }
        if (!empty($installation_address)) {
            update_user_meta($user_id, 'starcast_installation_address', $installation_address);
        }
        if (!empty($customer_address)) {
            update_user_meta($user_id, 'billing_address_1', $customer_address);
        }
        if (!empty($id_number)) {
            update_user_meta($user_id, 'starcast_id_number', $id_number);
        }
        if (!empty($signup->package_id)) {
            update_user_meta($user_id, 'starcast_package_id', $signup->package_id);
        }
        if (!empty($signup->package_type)) {
            update_user_meta($user_id, 'starcast_package_type', $signup->package_type);
        }
        if (!empty($order_id)) {
            update_user_meta($user_id, 'starcast_order_id', $order_id);
        }
        if (!empty($monthly_price)) {
            update_user_meta($user_id, 'starcast_monthly_price', $monthly_price);
        }

        $to = $signup->customer_email;
        $subject = 'Your Starcast Application Has Been Approved!';
        $package = $this->get_package_name($signup->package_id);

        $message = $this->get_approval_email_template($signup->customer_name, $username, $password,
                                                      $package, ucfirst($signup->package_type),
                                                      $installation_address);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Starcast Technologies <info@starcast.co.za>'
        );

        $result = wp_mail($to, $subject, $message, $headers);

        if ($result) {
            error_log("Starcast: Approval email sent to {$to} with" . ($password ? " credentials" : "out credentials (existing user)"));
        } else {
            error_log("Starcast: Failed to send approval email to {$to}");
        }

        return $result;
    }

    private function get_order_id_from_signup($signup_id) {
        global $wpdb;

        // Try to find order ID from notes
        $notes = $wpdb->get_var($wpdb->prepare(
            "SELECT notes FROM {$this->table_name} WHERE id = %d",
            $signup_id
        ));

        if ($notes && preg_match('/Order ID:\s*(\d+)/', $notes, $matches)) {
            return intval($matches[1]);
        }

        return null;
    }

    private function sync_order_application_status($order_id, $new_status) {
        if (!$order_id) {
            return;
        }

        if ($new_status === 'approved') {
            update_post_meta($order_id, '_application_approved', current_time('mysql'));
            delete_post_meta($order_id, '_application_declined');
            return;
        }

        if ($new_status === 'rejected') {
            update_post_meta($order_id, '_application_declined', current_time('mysql'));
            delete_post_meta($order_id, '_application_approved');
            return;
        }

        delete_post_meta($order_id, '_application_approved');
        delete_post_meta($order_id, '_application_declined');
    }

    private function get_approval_email_template($customer_name, $username, $password, $package, $package_type, $installation_address) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #28a745 0%, #20803a 100%); color: white; padding: 40px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { background: #ffffff; padding: 40px 30px; border: 1px solid #e0e0e0; }
                .success-badge { background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; text-align: center; font-weight: 600; margin: 20px 0; }
                .credentials-box { background: #f8f9fa; border-left: 4px solid #28a745; padding: 20px; margin: 20px 0; }
                .credentials-box h3 { margin-top: 0; color: #28a745; }
                .credential-item { margin: 10px 0; }
                .credential-label { font-weight: 600; color: #666; }
                .credential-value { font-family: 'Courier New', monospace; background: #fff; padding: 8px 12px; border-radius: 4px; display: inline-block; margin-left: 10px; }
                .button { display: inline-block; background: #d67d3e; color: white; padding: 15px 40px; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: 600; }
                .info-section { background: #faf7f4; padding: 20px; border-radius: 6px; margin: 20px 0; }
                .footer { background: #2d2823; color: white; padding: 30px 20px; text-align: center; border-radius: 0 0 8px 8px; }
                .footer a { color: #d67d3e; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Application Approved!</h1>
                </div>

                <div class="content">
                    <div class="success-badge">
                        ✓ Your Application Has Been Approved
                    </div>

                    <p>Hi <?php echo esc_html($customer_name); ?>,</p>

                    <p>Great news! Your application for <strong><?php echo esc_html($package_type); ?></strong> service has been approved.</p>

                    <div class="info-section">
                        <strong>Package:</strong> <?php echo esc_html($package); ?><br>
                        <strong>Installation Address:</strong> <?php echo esc_html($installation_address); ?>
                    </div>

                    <?php if ($password): ?>
                    <div class="credentials-box">
                        <h3>Your Account Credentials</h3>
                        <p>We've created your customer account. Use these credentials to access your account:</p>

                        <div class="credential-item">
                            <span class="credential-label">Username:</span>
                            <span class="credential-value"><?php echo esc_html($username); ?></span>
                        </div>
                        <div class="credential-item">
                            <span class="credential-label">Password:</span>
                            <span class="credential-value"><?php echo esc_html($password); ?></span>
                        </div>

                        <p style="margin-top: 15px;"><small><strong>Important:</strong> Please save these credentials in a secure location. You can change your password after logging in.</small></p>
                    </div>

                    <div style="text-align: center;">
                        <a href="https://starcast.co.za/my-account/" class="button">Login to Your Account</a>
                    </div>

                    <p><strong>What You Can Do in Your Account:</strong></p>
                    <ul>
                        <li>View your package details and pricing</li>
                        <li>Make payments for your service</li>
                        <li>Update your contact information</li>
                        <li>Track installation progress</li>
                        <li>Access billing history</li>
                    </ul>
                    <?php else: ?>
                    <p>You can access your account using your existing credentials at:</p>
                    <div style="text-align: center;">
                        <a href="https://starcast.co.za/my-account/" class="button">Login to Your Account</a>
                    </div>
                    <?php endif; ?>

                    <div class="info-section">
                        <h3 style="margin-top: 0;">Next Steps:</h3>
                        <ol>
                            <li>Our installation team will contact you within 24-48 hours</li>
                            <li>Schedule your installation appointment</li>
                            <li>Our technician will install and activate your service</li>
                            <li>Complete your first payment to activate billing</li>
                        </ol>
                    </div>

                    <p><strong>Need Help?</strong></p>
                    <ul>
                        <li>Email: <a href="mailto:info@starcast.co.za">info@starcast.co.za</a></li>
                        <li>WhatsApp: Available via our website</li>
                        <li>Support Hours: 24/7</li>
                    </ul>

                    <p>Welcome to Starcast Technologies! We're excited to get you connected.</p>

                    <p>Best regards,<br>
                    <strong>The Starcast Team</strong></p>
                </div>

                <div class="footer">
                    <p><strong>Starcast Technologies</strong></p>
                    <p>Fast, Reliable Internet Made Simple</p>
                    <p><a href="https://starcast.co.za">www.starcast.co.za</a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private function send_rejection_email($signup_id) {
        global $wpdb;

        $signup = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $signup_id
        ));

        if (!$signup) {
            return false;
        }

        $to = $signup->customer_email;
        $subject = 'Update on Your Starcast Application';

        $message = $this->get_rejection_email_template($signup->customer_name, ucfirst($signup->package_type));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Starcast Technologies <info@starcast.co.za>'
        );

        $result = wp_mail($to, $subject, $message, $headers);

        if ($result) {
            error_log("Starcast: Rejection email sent to {$to}");
        }

        return $result;
    }

    private function get_rejection_email_template($customer_name, $package_type) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #d67d3e 0%, #c06b32 100%); color: white; padding: 40px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { background: #ffffff; padding: 40px 30px; border: 1px solid #e0e0e0; }
                .info-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0; }
                .footer { background: #2d2823; color: white; padding: 30px 20px; text-align: center; border-radius: 0 0 8px 8px; }
                .footer a { color: #d67d3e; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Application Update</h1>
                </div>

                <div class="content">
                    <p>Hi <?php echo esc_html($customer_name); ?>,</p>

                    <p>Thank you for your interest in Starcast Technologies <?php echo esc_html($package_type); ?> service.</p>

                    <div class="info-box">
                        <p><strong>Service Availability Notice</strong></p>
                        <p>Unfortunately, we are currently unable to provide service at your location. This may be due to:</p>
                        <ul>
                            <li>Coverage limitations in your area</li>
                            <li>Infrastructure availability</li>
                            <li>Technical constraints</li>
                        </ul>
                    </div>

                    <p>We're constantly expanding our network and adding new coverage areas. We encourage you to check back with us in the future as your location may become serviceable.</p>

                    <p><strong>What You Can Do:</strong></p>
                    <ul>
                        <li>Contact us to be notified when service becomes available in your area</li>
                        <li>Inquire about alternative service options</li>
                        <li>Check if a different installation address might work</li>
                    </ul>

                    <p><strong>Questions? We're Here to Help</strong></p>
                    <ul>
                        <li>Email: <a href="mailto:info@starcast.co.za">info@starcast.co.za</a></li>
                        <li>WhatsApp: Available via our website</li>
                        <li>Support Hours: 24/7</li>
                    </ul>

                    <p>We apologize for any inconvenience and appreciate your understanding.</p>

                    <p>Best regards,<br>
                    <strong>The Starcast Team</strong></p>
                </div>

                <div class="footer">
                    <p><strong>Starcast Technologies</strong></p>
                    <p>Fast, Reliable Internet Made Simple</p>
                    <p><a href="https://starcast.co.za">www.starcast.co.za</a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private function get_admin_css() {
        return "
        .starcast-signups-manager {padding: 20px;}
        .signup-stats {display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;}
        .stat-card {background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 8px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);}
        .pending-stat {background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);}
        .approved-stat {background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);}
        .processing-stat {background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);}
        .rejected-stat {background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);}
        .stat-number {font-size: 42px; font-weight: 700; margin-bottom: 8px;}
        .stat-label {font-size: 14px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;}
        .package-type-badge {display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase;}
        .package-type-badge.fibre {background: #e3f2fd; color: #1976d2;}
        .package-type-badge.lte {background: #f3e5f5; color: #7b1fa2;}
        .status-badge {display: inline-block; padding: 6px 15px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase;}
        .status-pending {background: #fff3cd; color: #856404;}
        .status-processing {background: #cce5ff; color: #004085;}
        .status-approved {background: #d4edda; color: #155724;}
        .status-rejected {background: #f8d7da; color: #721c24;}
        .view-details {background: #0073aa; color: white; border: none; cursor: pointer;}
        .view-details:hover {background: #005177;}
        .signup-modal {position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);}
        .modal-content {background-color: #fefefe; margin: 5% auto; padding: 0; border: none; width: 90%; max-width: 800px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); max-height: 80vh; overflow-y: auto;}
        .close-modal {color: #aaa; float: right; font-size: 28px; font-weight: bold; padding: 10px 20px; cursor: pointer;}
        .close-modal:hover, .close-modal:focus {color: black;}
        .modal-body {padding: 20px 30px;}
        .signup-detail-section {margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e0e0e0;}
        .signup-detail-section:last-child {border-bottom: none;}
        .signup-detail-section h3 {margin-top: 0; color: #0073aa;}
        .detail-grid {display: grid; grid-template-columns: 150px 1fr; gap: 15px; margin: 15px 0;}
        .detail-label {font-weight: 600; color: #666;}
        .detail-value {color: #333;}
        .action-buttons {display: flex; gap: 10px; margin-top: 20px;}
        .action-buttons button {padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; transition: all 0.3s;}
        .btn-approve {background: #28a745; color: white;}
        .btn-approve:hover {background: #218838;}
        .btn-reject {background: #dc3545; color: white;}
        .btn-reject:hover {background: #c82333;}
        .btn-processing {background: #17a2b8; color: white;}
        .btn-processing:hover {background: #138496;}
        .notes-section textarea {width: 100%; min-height: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit;}
        .notes-history {background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 15px; max-height: 200px; overflow-y: auto;}
        .no-signups {text-align: center; padding: 60px 20px; color: #666;}
        ";
    }

    private function get_admin_js() {
        return "
        jQuery(document).ready(function($) {
            // Cleanup rejected signups button handler
            $('#cleanup-rejected-signups').on('click', function() {
                if (!confirm('Are you sure you want to delete ALL rejected signups?\\n\\nThis will permanently remove rejected signup records from the database, allowing those customers to reapply.\\n\\nWooCommerce orders will be kept for record-keeping.')) {
                    return;
                }

                var button = $(this);
                button.prop('disabled', true).text('Deleting...');

                $.ajax({
                    url: starcastSignups.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'starcast_cleanup_rejected_signups',
                        nonce: starcastSignups.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                            button.prop('disabled', false).text('🗑️ Delete All Rejected Signups');
                        }
                    },
                    error: function() {
                        alert('Failed to delete rejected signups. Please try again.');
                        button.prop('disabled', false).text('🗑️ Delete All Rejected Signups');
                    }
                });
            });

            $('.view-details').on('click', function() {
                var signupId = $(this).data('signup-id');
                loadSignupDetails(signupId);
            });
            $('.close-modal').on('click', function() {
                $('#signup-details-modal').hide();
            });
            $(window).on('click', function(event) {
                if (event.target.id === 'signup-details-modal') {
                    $('#signup-details-modal').hide();
                }
            });

            function loadSignupDetails(signupId) {
                $.ajax({
                    url: starcastSignups.ajax_url,
                    type: 'POST',
                    data: {action: 'starcast_get_signup_details', nonce: starcastSignups.nonce, signup_id: signupId},
                    success: function(response) {
                        if (response.success) {
                            renderSignupDetails(response.data);
                            $('#signup-details-modal').show();
                        } else {
                            alert('Error loading signup details');
                        }
                    }
                });
            }

            function renderSignupDetails(signup) {
                var html = '<h2>Signup Details #' + signup.id + '</h2>';
                html += '<div class=\"signup-detail-section\"><h3>Customer Information</h3><div class=\"detail-grid\">';
                html += '<div class=\"detail-label\">Name:</div><div class=\"detail-value\">' + signup.customer_name + '</div>';
                html += '<div class=\"detail-label\">Email:</div><div class=\"detail-value\"><a href=\"mailto:' + signup.customer_email + '\">' + signup.customer_email + '</a></div>';
                html += '<div class=\"detail-label\">Phone:</div><div class=\"detail-value\"><a href=\"tel:' + signup.customer_phone + '\">' + signup.customer_phone + '</a></div>';
                html += '<div class=\"detail-label\">Preferred Contact:</div><div class=\"detail-value\">' + signup.preferred_contact_method + '</div></div></div>';
                html += '<div class=\"signup-detail-section\"><h3>Package Information</h3><div class=\"detail-grid\">';
                html += '<div class=\"detail-label\">Package Type:</div><div class=\"detail-value\"><span class=\"package-type-badge ' + signup.package_type + '\">' + signup.package_type.toUpperCase() + '</span></div>';
                html += '<div class=\"detail-label\">Package:</div><div class=\"detail-value\">' + (signup.package_name || 'N/A') + '</div>';
                html += '<div class=\"detail-label\">Address:</div><div class=\"detail-value\">' + signup.customer_address + '</div>';
                html += '<div class=\"detail-label\">Installation Address:</div><div class=\"detail-value\">' + (signup.installation_address || 'Same as above') + '</div></div></div>';
                html += '<div class=\"signup-detail-section\"><h3>Status & Timeline</h3><div class=\"detail-grid\">';
                html += '<div class=\"detail-label\">Current Status:</div><div class=\"detail-value\"><span class=\"status-badge status-' + signup.status + '\">' + signup.status.toUpperCase() + '</span></div>';
                html += '<div class=\"detail-label\">Created:</div><div class=\"detail-value\">' + signup.created_at + '</div>';
                html += '<div class=\"detail-label\">Last Updated:</div><div class=\"detail-value\">' + signup.updated_at + '</div></div></div>';
                html += '<div class=\"signup-detail-section notes-section\"><h3>Internal Notes</h3>';
                html += '<textarea id=\"new-note-text\" placeholder=\"Add a note about fibre availability, verification status, etc...\"></textarea>';
                html += '<button type=\"button\" class=\"button button-primary\" onclick=\"addNote(' + signup.id + ')\">Add Note</button>';
                if (signup.notes) {
                    html += '<div class=\"notes-history\"><strong>Previous Notes:</strong><br>' + signup.notes.replace(/\\n/g, '<br>') + '</div>';
                }
                html += '</div><div class=\"signup-detail-section\"><h3>Actions</h3><div class=\"action-buttons\">';
                if (signup.status !== 'approved') {
                    html += '<button type=\"button\" class=\"btn-approve\" onclick=\"updateStatus(' + signup.id + ', \\'approved\\')\">Approve Signup</button>';
                }
                if (signup.status !== 'processing') {
                    html += '<button type=\"button\" class=\"btn-processing\" onclick=\"updateStatus(' + signup.id + ', \\'processing\\')\">Mark as Processing</button>';
                }
                if (signup.status !== 'rejected') {
                    html += '<button type=\"button\" class=\"btn-reject\" onclick=\"updateStatus(' + signup.id + ', \\'rejected\\')\">Reject Signup</button>';
                }
                html += '</div></div>';
                $('#signup-details-modal .modal-body').html(html);
            }

            window.updateStatus = function(signupId, newStatus) {
                if (!confirm('Are you sure you want to change this signup to ' + newStatus + '?')) {
                    return;
                }
                $.ajax({
                    url: starcastSignups.ajax_url,
                    type: 'POST',
                    data: {action: 'starcast_update_signup_status', nonce: starcastSignups.nonce, signup_id: signupId, status: newStatus},
                    success: function(response) {
                        if (response.success) {
                            alert('Status updated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            };

            window.addNote = function(signupId) {
                var note = $('#new-note-text').val();
                if (!note) {alert('Please enter a note'); return;}
                $.ajax({
                    url: starcastSignups.ajax_url,
                    type: 'POST',
                    data: {action: 'starcast_add_signup_note', nonce: starcastSignups.nonce, signup_id: signupId, note: note},
                    success: function(response) {
                        if (response.success) {
                            alert('Note added successfully!');
                            loadSignupDetails(signupId);
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            };
        });
        ";
    }
}

add_action('wp_ajax_starcast_get_signup_details', function() {
    check_ajax_referer('starcast_signups_nonce', 'nonce');
    if (!current_user_can('manage_options')) {wp_send_json_error('Unauthorized');}
    $signup_id = isset($_POST['signup_id']) ? intval($_POST['signup_id']) : 0;
    if (!$signup_id) {wp_send_json_error('Invalid signup ID');}
    global $wpdb;
    $table_name = $wpdb->prefix . 'starcast_signups';
    $signup = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $signup_id), ARRAY_A);
    if (!$signup) {wp_send_json_error('Signup not found');}
    if ($signup['package_id']) {
        $package = get_post($signup['package_id']);
        $signup['package_name'] = $package ? $package->post_title : 'Package #' . $signup['package_id'];
    } else {
        $signup['package_name'] = 'N/A';
    }
    wp_send_json_success($signup);
});

add_action('plugins_loaded', array('StarcastSignupManager', 'get_instance'));
