<?php
/**
 * Plugin Name: Starcast Customer Dashboard
 * Description: Customer dashboard for ISP package + billing allocation summary.
 * Version: 0.1.0
 * Author: Starcast
 */

if (!defined('ABSPATH')) {
    exit;
}

class StarcastCustomerDashboard {
    private $meta_keys = [
        'scd_package_post_id',
        'scd_package_label',
        'scd_provider',
        'scd_monthly_amount',
        'scd_once_off_amount',
        'scd_outstanding_amount',
        'scd_next_billing_date',
        'scd_account_status',
    ];

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);

        add_action('show_user_profile', [$this, 'render_user_fields']);
        add_action('edit_user_profile', [$this, 'render_user_fields']);
        add_action('personal_options_update', [$this, 'save_user_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_fields']);

        add_shortcode('starcast_customer_dashboard', [$this, 'render_dashboard_shortcode']);
    }

    public function enqueue_admin() {
        wp_enqueue_style(
            'scd-admin',
            plugin_dir_url(__FILE__) . 'assets/css/customer-dashboard.css',
            [],
            '0.1.0'
        );
    }

    public function enqueue_frontend() {
        wp_enqueue_style(
            'scd-frontend',
            plugin_dir_url(__FILE__) . 'assets/css/customer-dashboard.css',
            [],
            '0.1.0'
        );
    }

    public function render_user_fields($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        wp_nonce_field('scd_save_user_fields', 'scd_user_fields_nonce');

        $package_id = get_user_meta($user->ID, 'scd_package_post_id', true);
        $package_label = get_user_meta($user->ID, 'scd_package_label', true);
        $provider = get_user_meta($user->ID, 'scd_provider', true);
        $monthly = get_user_meta($user->ID, 'scd_monthly_amount', true);
        $once_off = get_user_meta($user->ID, 'scd_once_off_amount', true);
        $outstanding = get_user_meta($user->ID, 'scd_outstanding_amount', true);
        $next_date = get_user_meta($user->ID, 'scd_next_billing_date', true);
        $status = get_user_meta($user->ID, 'scd_account_status', true) ?: 'active';
        ?>
        <h2>Customer Dashboard Allocation</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="scd_package_post_id">Package Post ID</label></th>
                <td><input type="number" name="scd_package_post_id" id="scd_package_post_id" value="<?php echo esc_attr($package_id); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="scd_package_label">Package Label</label></th>
                <td><input type="text" name="scd_package_label" id="scd_package_label" value="<?php echo esc_attr($package_label); ?>" class="regular-text" placeholder="e.g. Openserve 100/50" /></td>
            </tr>
            <tr>
                <th><label for="scd_provider">Provider</label></th>
                <td><input type="text" name="scd_provider" id="scd_provider" value="<?php echo esc_attr($provider); ?>" class="regular-text" placeholder="e.g. Openserve" /></td>
            </tr>
            <tr>
                <th><label for="scd_monthly_amount">Monthly Amount (R)</label></th>
                <td><input type="number" step="0.01" min="0" name="scd_monthly_amount" id="scd_monthly_amount" value="<?php echo esc_attr($monthly); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="scd_once_off_amount">Once-off Amount (R)</label></th>
                <td><input type="number" step="0.01" min="0" name="scd_once_off_amount" id="scd_once_off_amount" value="<?php echo esc_attr($once_off); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="scd_outstanding_amount">Outstanding Amount (R)</label></th>
                <td><input type="number" step="0.01" min="0" name="scd_outstanding_amount" id="scd_outstanding_amount" value="<?php echo esc_attr($outstanding); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="scd_next_billing_date">Next Billing Date</label></th>
                <td><input type="date" name="scd_next_billing_date" id="scd_next_billing_date" value="<?php echo esc_attr($next_date); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="scd_account_status">Account Status</label></th>
                <td>
                    <select name="scd_account_status" id="scd_account_status">
                        <option value="active" <?php selected($status, 'active'); ?>>Active</option>
                        <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                        <option value="suspended" <?php selected($status, 'suspended'); ?>>Suspended</option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>>Cancelled</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_user_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if (!isset($_POST['scd_user_fields_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scd_user_fields_nonce'])), 'scd_save_user_fields')) {
            return;
        }

        $map = [
            'scd_package_post_id' => 'absint',
            'scd_package_label' => 'sanitize_text_field',
            'scd_provider' => 'sanitize_text_field',
            'scd_monthly_amount' => [$this, 'sanitize_decimal'],
            'scd_once_off_amount' => [$this, 'sanitize_decimal'],
            'scd_outstanding_amount' => [$this, 'sanitize_decimal'],
            'scd_next_billing_date' => [$this, 'sanitize_date'],
            'scd_account_status' => [$this, 'sanitize_status'],
        ];

        foreach ($map as $key => $sanitizer) {
            $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
            $val = is_callable($sanitizer) ? call_user_func($sanitizer, $raw) : '';
            update_user_meta($user_id, $key, $val);
        }
    }

    public function render_dashboard_shortcode() {
        if (!is_user_logged_in()) {
            return '<div class="scd-login-required">Please log in to view your dashboard.</div>';
        }

        $user_id = get_current_user_id();
        $data = $this->get_user_dashboard_data($user_id);

        ob_start();
        ?>
        <div class="scd-dashboard">
            <h2>My Service Dashboard</h2>
            <div class="scd-grid">
                <div class="scd-card">
                    <h3>Active Package</h3>
                    <p><strong><?php echo esc_html($data['package_label'] ?: 'Not allocated'); ?></strong></p>
                    <p>Provider: <?php echo esc_html($data['provider'] ?: '—'); ?></p>
                    <p>Status: <span class="scd-status scd-status-<?php echo esc_attr($data['account_status']); ?>"><?php echo esc_html(ucfirst($data['account_status'])); ?></span></p>
                </div>

                <div class="scd-card">
                    <h3>Billing Summary</h3>
                    <p>Monthly: <strong>R<?php echo esc_html(number_format((float)$data['monthly_amount'], 2)); ?></strong></p>
                    <p>Once-off: R<?php echo esc_html(number_format((float)$data['once_off_amount'], 2)); ?></p>
                    <p>Outstanding: <strong>R<?php echo esc_html(number_format((float)$data['outstanding_amount'], 2)); ?></strong></p>
                </div>

                <div class="scd-card">
                    <h3>Next Billing</h3>
                    <p>Date: <strong><?php echo esc_html($data['next_billing_date'] ?: 'Not set'); ?></strong></p>
                    <p>Amount due now: <strong>R<?php echo esc_html(number_format((float)$data['amount_due_now'], 2)); ?></strong></p>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    private function get_user_dashboard_data($user_id) {
        $monthly = (float) get_user_meta($user_id, 'scd_monthly_amount', true);
        $once_off = (float) get_user_meta($user_id, 'scd_once_off_amount', true);
        $outstanding = (float) get_user_meta($user_id, 'scd_outstanding_amount', true);

        return [
            'package_post_id' => (int) get_user_meta($user_id, 'scd_package_post_id', true),
            'package_label' => (string) get_user_meta($user_id, 'scd_package_label', true),
            'provider' => (string) get_user_meta($user_id, 'scd_provider', true),
            'monthly_amount' => $monthly,
            'once_off_amount' => $once_off,
            'outstanding_amount' => $outstanding,
            'next_billing_date' => (string) get_user_meta($user_id, 'scd_next_billing_date', true),
            'account_status' => (string) (get_user_meta($user_id, 'scd_account_status', true) ?: 'active'),
            'amount_due_now' => max(0, $outstanding + $once_off),
        ];
    }

    public function sanitize_decimal($val) {
        $val = preg_replace('/[^0-9.\-]/', '', (string) $val);
        return is_numeric($val) ? number_format((float)$val, 2, '.', '') : '0.00';
    }

    public function sanitize_date($val) {
        $val = sanitize_text_field((string) $val);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $val) ? $val : '';
    }

    public function sanitize_status($val) {
        $allowed = ['active', 'pending', 'suspended', 'cancelled'];
        $val = sanitize_text_field((string) $val);
        return in_array($val, $allowed, true) ? $val : 'active';
    }
}

new StarcastCustomerDashboard();
