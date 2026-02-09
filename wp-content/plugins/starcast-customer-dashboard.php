<?php
/**
 * Plugin Name: Starcast Customer Dashboard
 * Plugin URI: http://localhost
 * Description: Customer dashboard for managing fibre/LTE packages, billing, and support
 * Version: 1.0.0
 * Author: Starcast
 * License: GPL v2 or later
 * Text Domain: starcast-customer-dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Starcast_Customer_Dashboard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Add custom endpoints to My Account
        add_action('init', array($this, 'add_endpoints'));
        
        // Add menu items to My Account
        add_filter('woocommerce_account_menu_items', array($this, 'add_menu_items'));
        
        // Register endpoint content
        add_action('woocommerce_account_dashboard_endpoint', array($this, 'dashboard_content'));
        add_action('woocommerce_account_my-package_endpoint', array($this, 'package_content'));
        add_action('woocommerce_account_billing_endpoint', array($this, 'billing_content'));
        add_action('woocommerce_account_account-details_endpoint', array($this, 'account_details_content'));
        add_action('woocommerce_account_support_endpoint', array($this, 'support_content'));
        
        // Handle form submissions
        add_action('template_redirect', array($this, 'handle_account_update'));
        
        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_header_nav_styles'), 20);

        // Login page styling
        add_action('login_enqueue_scripts', array($this, 'enqueue_login_styles'));

        // Hide Shop page from frontend menus
        add_filter('wp_nav_menu_objects', array($this, 'filter_shop_menu_item'), 10, 2);
        
        // Flush rewrite rules on activation
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function activate() {
        $this->add_endpoints();
        flush_rewrite_rules();
    }
    
    public function add_endpoints() {
        add_rewrite_endpoint('my-package', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('billing', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('account-details', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('support', EP_ROOT | EP_PAGES);
    }
    
    public function add_menu_items($items) {
        // Remove default dashboard and edit-account
        unset($items['dashboard']);
        unset($items['edit-account']);
        
        // Add custom items
        $new_items = array(
            'dashboard' => __('Dashboard', 'starcast-customer-dashboard'),
            'my-package' => __('My Package', 'starcast-customer-dashboard'),
            'billing' => __('Billing & Payments', 'starcast-customer-dashboard'),
            'account-details' => __('Account Details', 'starcast-customer-dashboard'),
            'support' => __('Support', 'starcast-customer-dashboard'),
        );
        
        // Insert custom items at the beginning
        $items = array_merge($new_items, $items);
        
        return $items;
    }
    
    public function handle_account_update() {
        if (!isset($_POST['starcast_update_account']) || !isset($_POST['_wpnonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'starcast_update_account')) {
            return;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        
        // Update email
        if (!empty($_POST['account_email'])) {
            $email = sanitize_email($_POST['account_email']);
            if (is_email($email)) {
                wp_update_user(array(
                    'ID' => $user_id,
                    'user_email' => $email
                ));
            }
        }
        
        // Update phone
        if (isset($_POST['billing_phone'])) {
            update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['billing_phone']));
        }
        
        // Update address in subscription
        if (!empty($_POST['installation_address'])) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'starcast_subscriptions';
            $installation_address = sanitize_text_field($_POST['installation_address']);

            update_user_meta($user_id, 'starcast_installation_address', $installation_address);
            update_user_meta($user_id, 'billing_address_1', $installation_address);
            
            $wpdb->update(
                $table_name,
                array('installation_address' => $installation_address),
                array('user_id' => $user_id),
                array('%s'),
                array('%d')
            );
        }
        
        wc_add_notice(__('Account details updated successfully.', 'starcast-customer-dashboard'), 'success');
        wp_safe_redirect(wc_get_account_endpoint_url('account-details'));
        exit;
    }
    
    public function account_details_content() {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        $profile = $this->get_user_profile_data($user_id);
        
        $phone = $profile['customer_phone'];
        $address = $profile['installation_address'];
        $id_number = $profile['id_number'];
        $display_name = $profile['customer_name'];
        
        ?>
        <div class="starcast-account-details">
            <h2><?php _e('Account Details', 'starcast-customer-dashboard'); ?></h2>
            
            <form method="post" class="account-details-form">
                <?php wp_nonce_field('starcast_update_account'); ?>
                
                <p class="form-row">
                    <label for="account_name"><?php _e('Full Name', 'starcast-customer-dashboard'); ?></label>
                    <input type="text" id="account_name" value="<?php echo esc_attr($display_name); ?>" readonly>
                </p>

                <p class="form-row">
                    <label for="account_email"><?php _e('Email Address', 'starcast-customer-dashboard'); ?> <span class="required">*</span></label>
                    <input type="email" name="account_email" id="account_email" 
                           value="<?php echo esc_attr($user->user_email); ?>" required>
                </p>
                
                <p class="form-row">
                    <label for="billing_phone"><?php _e('Phone Number', 'starcast-customer-dashboard'); ?></label>
                    <input type="tel" name="billing_phone" id="billing_phone" 
                           value="<?php echo esc_attr($phone); ?>">
                </p>
                
                <p class="form-row">
                    <label for="installation_address"><?php _e('Installation Address', 'starcast-customer-dashboard'); ?></label>
                    <textarea name="installation_address" id="installation_address" 
                              rows="3"><?php echo esc_textarea($address); ?></textarea>
                </p>

                <p class="form-row">
                    <label for="id_number"><?php _e('ID Number', 'starcast-customer-dashboard'); ?></label>
                    <input type="text" id="id_number" value="<?php echo esc_attr($id_number); ?>" readonly>
                </p>
                
                <p class="form-row">
                    <button type="submit" name="starcast_update_account" class="button button-primary">
                        <?php _e('Update Details', 'starcast-customer-dashboard'); ?>
                    </button>
                </p>
            </form>
            <?php if (function_exists('starcast_google_places_script')): ?>
                <?php starcast_google_places_script('Account', 'installation_address'); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function dashboard_content() {
        $user_id = get_current_user_id();
        $subscription = $this->get_user_subscription($user_id);
        $profile = $this->get_user_profile_data($user_id);
        
        if (!$subscription && empty($profile['package_id'])) {
            echo '<div class="woocommerce-message">No active subscription found.</div>';
            return;
        }
        
        $package_id = $subscription ? $subscription->package_id : $profile['package_id'];
        $package_type = $subscription ? $subscription->package_type : $profile['package_type'];
        $monthly_price = $subscription ? $subscription->monthly_price : $profile['monthly_price'];
        $installation_address = $subscription ? $subscription->installation_address : $profile['installation_address'];
        $status = $subscription ? $subscription->status : 'approved';
        $next_billing_date = ($subscription && !empty($subscription->next_billing_date)) ? $subscription->next_billing_date : null;
        $start_date = ($subscription && !empty($subscription->start_date)) ? $subscription->start_date : null;

        $package = $package_id ? get_post($package_id) : null;
        $provider = '';
        $speed_down = '';
        $speed_up = '';
        
        if ($package) {
            // Get package metadata
            if ($package_type === 'fibre') {
                $terms = get_the_terms($package->ID, 'fibre_provider');
                if ($terms && !is_wp_error($terms)) {
                    $provider = $terms[0]->name;
                }
            }
            
            // Get speed from title or ACF fields
            $speed_down = get_post_meta($package->ID, 'download_speed', true) ?: '50';
            $speed_up = get_post_meta($package->ID, 'upload_speed', true) ?: '25';
        }
        
        // Calculate days until next billing
        $days_until = null;
        if ($next_billing_date) {
            $next_billing = new DateTime($next_billing_date);
            $today = new DateTime();
            $days_until = $today->diff($next_billing)->days;
        }
        
        ?>
        <div class="starcast-dashboard">
            <h2><?php _e('Welcome to Your Dashboard', 'starcast-customer-dashboard'); ?></h2>
            
            <div class="package-card">
                <div class="package-header">
                    <h3><?php echo esc_html($package ? $package->post_title : 'Package'); ?></h3>
                    <span class="status-badge status-<?php echo esc_attr($status); ?>">
                        <?php echo esc_html(ucfirst($status)); ?>
                    </span>
                </div>
                
                <div class="package-details">
                    <?php if ($provider): ?>
                    <div class="detail-item">
                        <span class="label">Provider:</span>
                        <span class="value"><?php echo esc_html($provider); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="detail-item">
                        <span class="label">Speed:</span>
                        <span class="value">
                            ↓ <?php echo esc_html($speed_down); ?>Mbps / 
                            ↑ <?php echo esc_html($speed_up); ?>Mbps
                        </span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="label">Monthly Price:</span>
                        <span class="value">R<?php echo number_format((float) $monthly_price, 2); ?>/month</span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="label">Next Billing:</span>
                        <span class="value">
                            <?php echo $next_billing_date ? date('F j, Y', strtotime($next_billing_date)) : 'Due now'; ?>
                            <?php if ($days_until !== null): ?>
                                <span class="days-count">(<?php echo $days_until; ?> days)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="label">Installation Address:</span>
                        <span class="value"><?php echo esc_html($installation_address); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="label">Service Started:</span>
                        <span class="value"><?php echo $start_date ? date('F j, Y', strtotime($start_date)) : 'Pending'; ?></span>
                    </div>
                </div>
                
                <div class="package-actions">
                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('my-package')); ?>" class="button">
                        Manage Package
                    </a>
                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('billing')); ?>" class="button button-primary">
                        Make Payment
                    </a>
                </div>
            </div>
            
            <div class="quick-stats">
                <div class="stat-box">
                    <div class="stat-value">R<?php echo number_format((float) $monthly_price, 2); ?></div>
                    <div class="stat-label">Current Balance</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $days_until !== null ? $days_until : '-'; ?></div>
                    <div class="stat-label">Days Until Next Billing</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo ucfirst($status); ?></div>
                    <div class="stat-label">Account Status</div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function package_content() {
        $user_id = get_current_user_id();
        $subscription = $this->get_user_subscription($user_id);
        $profile = $this->get_user_profile_data($user_id);
        
        ?>
        <div class="starcast-package-management">
            <h2><?php _e('My Package', 'starcast-customer-dashboard'); ?></h2>
            
            <?php if ($subscription || !empty($profile['package_id'])): ?>
            <div class="current-package">
                <h3>Current Package Details</h3>
                <?php
                $package_id = $subscription ? $subscription->package_id : $profile['package_id'];
                $package_title = $package_id ? get_the_title($package_id) : 'Package';
                $monthly_price = $subscription ? $subscription->monthly_price : $profile['monthly_price'];
                $status = $subscription ? $subscription->status : 'approved';
                ?>
                <p><strong>Package:</strong> <?php echo esc_html($package_title); ?></p>
                <p><strong>Monthly Price:</strong> R<?php echo number_format((float) $monthly_price, 2); ?></p>
                <p><strong>Status:</strong> <?php echo esc_html(ucfirst($status)); ?></p>
            </div>

            <div class="current-package">
                <h3>Customer Details</h3>
                <p><strong>Name:</strong> <?php echo esc_html($profile['customer_name']); ?></p>
                <p><strong>Cell:</strong> <?php echo esc_html($profile['customer_phone']); ?></p>
                <p><strong>Installation Address:</strong> <?php echo esc_html($profile['installation_address']); ?></p>
                <p><strong>ID Number:</strong> <?php echo esc_html($profile['id_number']); ?></p>
            </div>
            
            <div class="package-options">
                <h3>Package Management</h3>
                <p>Request changes to your package:</p>
                
                <div class="action-buttons">
                    <button class="button" disabled>Upgrade Package</button>
                    <button class="button" disabled>Downgrade Package</button>
                    <button class="button button-secondary" disabled>Cancel Subscription</button>
                    <a href="<?php echo esc_url(wc_get_account_endpoint_url('billing')); ?>" class="button button-primary">Pay Now</a>
                </div>
                
                <p class="notice">
                    <em>Package management features coming soon. For assistance, please contact support.</em>
                </p>
            </div>
            <?php else: ?>
            <p>No active subscription found.</p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function billing_content() {
        $user_id = get_current_user_id();
        $subscription = $this->get_user_subscription($user_id);
        $profile = $this->get_user_profile_data($user_id);
        
        if (!$subscription && empty($profile['package_id'])) {
            echo '<div class="woocommerce-message">No active subscription found.</div>';
            return;
        }

        $monthly_price = $subscription ? $subscription->monthly_price : $profile['monthly_price'];
        $next_billing_date = ($subscription && !empty($subscription->next_billing_date)) ? $subscription->next_billing_date : null;
        
        ?>
        <div class="starcast-billing">
            <h2><?php _e('Billing & Payments', 'starcast-customer-dashboard'); ?></h2>
            
            <div class="billing-summary">
                <div class="billing-amount">
                    <h3>Current Amount Due</h3>
                    <div class="amount">R<?php echo number_format((float) $monthly_price, 2); ?></div>
                    <p>Due: <?php echo $next_billing_date ? date('F j, Y', strtotime($next_billing_date)) : 'Due now'; ?></p>
                </div>
            </div>
            
            <div class="payment-methods">
                <h3>Choose Payment Method</h3>
                
                <div class="payment-options">
                    <div class="payment-card payfast-card">
                        <div class="payment-logo">
                            <h4>PayFast</h4>
                        </div>
                        <p>Secure payment via PayFast (Credit Card, EFT, SnapScan)</p>
                        <form action="<?php echo esc_url(home_url('/process-payfast-payment')); ?>" method="post">
                            <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription ? $subscription->id : 0); ?>">
                            <input type="hidden" name="amount" value="<?php echo esc_attr($monthly_price); ?>">
                            <button type="submit" class="button button-primary button-large">
                                Pay with PayFast
                            </button>
                        </form>
                    </div>
                    
                    <div class="payment-card ozow-card">
                        <div class="payment-logo">
                            <h4>Ozow</h4>
                        </div>
                        <p>Instant EFT payment via Ozow</p>
                        <form action="<?php echo esc_url(home_url('/process-ozow-payment')); ?>" method="post">
                            <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription ? $subscription->id : 0); ?>">
                            <input type="hidden" name="amount" value="<?php echo esc_attr($monthly_price); ?>">
                            <button type="submit" class="button button-primary button-large">
                                Pay with Ozow
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="payment-history">
                <h3>Payment History</h3>
                <?php if ($subscription && $subscription->last_payment_date): ?>
                <table class="shop_table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo date('F j, Y', strtotime($subscription->last_payment_date)); ?></td>
                            <td>R<?php echo number_format((float) $monthly_price, 2); ?></td>
                            <td><span class="status-paid">Paid</span></td>
                        </tr>
                    </tbody>
                </table>
                <?php else: ?>
                <p>No payment history available.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function support_content() {
        ?>
        <div class="starcast-support">
            <h2><?php _e('Support', 'starcast-customer-dashboard'); ?></h2>
            
            <div class="support-info">
                <p>Need help? Contact our support team:</p>
                <ul>
                    <li><strong>Email:</strong> <a href="mailto:starcast.tech@gmail.com">starcast.tech@gmail.com</a></li>
                    <li><strong>Phone:</strong> Available during business hours</li>
                </ul>
            </div>
            
            <div class="support-ticket-form">
                <h3>Submit a Support Request</h3>
                <p><em>Support ticket system coming soon. For now, please email us directly at starcast.tech@gmail.com</em></p>
                
                <form class="support-form" disabled>
                    <p>
                        <label for="support-subject">Subject</label>
                        <input type="text" id="support-subject" disabled>
                    </p>
                    <p>
                        <label for="support-message">Message</label>
                        <textarea id="support-message" rows="6" disabled></textarea>
                    </p>
                    <p>
                        <button type="submit" class="button" disabled>Submit Ticket</button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    private function get_user_profile_data($user_id) {
        $profile = array(
            'customer_name' => '',
            'customer_email' => '',
            'customer_phone' => '',
            'customer_address' => '',
            'installation_address' => '',
            'id_number' => '',
            'package_id' => null,
            'package_type' => '',
            'order_id' => null,
            'monthly_price' => null
        );

        $user = get_userdata($user_id);
        if ($user) {
            $profile['customer_name'] = $user->display_name;
            $profile['customer_email'] = $user->user_email;
        }

        $profile['customer_phone'] = get_user_meta($user_id, 'billing_phone', true);
        $profile['installation_address'] = get_user_meta($user_id, 'starcast_installation_address', true);
        if (empty($profile['installation_address'])) {
            $profile['installation_address'] = get_user_meta($user_id, 'billing_address_1', true);
        }
        $profile['id_number'] = get_user_meta($user_id, 'starcast_id_number', true);
        $profile['package_id'] = (int) get_user_meta($user_id, 'starcast_package_id', true);
        $profile['package_type'] = get_user_meta($user_id, 'starcast_package_type', true);
        $profile['order_id'] = (int) get_user_meta($user_id, 'starcast_order_id', true);
        $profile['monthly_price'] = get_user_meta($user_id, 'starcast_monthly_price', true);

        $order = $this->get_latest_application_order($user_id, $profile['customer_email']);
        if ($order) {
            if (empty($profile['order_id'])) {
                $profile['order_id'] = $order->get_id();
            }
            if (empty($profile['monthly_price'])) {
                $profile['monthly_price'] = (float) $order->get_total();
            }
            if (empty($profile['installation_address'])) {
                $profile['installation_address'] = $order->get_meta('_installation_address');
            }

            $application_data = $order->get_meta('_customer_application_data');
            if (is_array($application_data)) {
                if (empty($profile['customer_phone']) && !empty($application_data['phone'])) {
                    $profile['customer_phone'] = $application_data['phone'];
                }
                if (empty($profile['installation_address']) && !empty($application_data['installation_address'])) {
                    $profile['installation_address'] = $application_data['installation_address'];
                }
                if (empty($profile['id_number']) && !empty($application_data['id_number'])) {
                    $profile['id_number'] = $application_data['id_number'];
                }
                if (empty($profile['customer_address']) && !empty($application_data['customer_address'])) {
                    $profile['customer_address'] = $application_data['customer_address'];
                }
            }

            if (empty($profile['package_id'])) {
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
                $profile['package_id'] = $package_id ? (int) $package_id : null;
            }

            if (empty($profile['package_type'])) {
                $application_type = $order->get_meta('_application_type');
                $profile['package_type'] = (is_string($application_type) && strpos($application_type, 'lte') !== false)
                    ? 'lte'
                    : 'fibre';
            }
        }

        if (!empty($profile['customer_email'])) {
            global $wpdb;
            $signups_table = $wpdb->prefix . 'starcast_signups';
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $signups_table
            ));

            if ($table_exists) {
                $signup = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$signups_table} WHERE customer_email = %s ORDER BY updated_at DESC LIMIT 1",
                    $profile['customer_email']
                ));

                if ($signup) {
                    if (empty($profile['package_id']) && !empty($signup->package_id)) {
                        $profile['package_id'] = (int) $signup->package_id;
                    }
                    if (empty($profile['package_type']) && !empty($signup->package_type)) {
                        $profile['package_type'] = $signup->package_type;
                    }
                    if (empty($profile['installation_address']) && !empty($signup->installation_address)) {
                        $profile['installation_address'] = $signup->installation_address;
                    }
                    if (empty($profile['customer_phone']) && !empty($signup->customer_phone)) {
                        $profile['customer_phone'] = $signup->customer_phone;
                    }
                    if (empty($profile['customer_name']) && !empty($signup->customer_name)) {
                        $profile['customer_name'] = $signup->customer_name;
                    }
                    if (empty($profile['order_id']) && !empty($signup->notes) && preg_match('/Order ID:\s*(\d+)/', $signup->notes, $matches)) {
                        $profile['order_id'] = (int) $matches[1];
                    }
                }
            }
        }

        return $profile;
    }

    private function get_latest_application_order($user_id, $email) {
        if (!function_exists('wc_get_orders')) {
            return null;
        }

        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        if (empty($orders) && $email) {
            $orders = wc_get_orders(array(
                'customer' => $email,
                'limit' => 1,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
        }

        if (empty($orders) && $email) {
            $orders = wc_get_orders(array(
                'limit' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => '_customer_application_data',
                        'value' => $email,
                        'compare' => 'LIKE'
                    )
                )
            ));
        }

        return !empty($orders) ? $orders[0] : null;
    }

    private function get_user_subscription($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'starcast_subscriptions';
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));

        if (!$table_exists) {
            return null;
        }
        
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND status = 'active' LIMIT 1",
            $user_id
        ));
        
        return $subscription;
    }
    
    public function enqueue_styles() {
        if (is_account_page()) {
            wp_register_style('starcast-account-ui', false);
            wp_enqueue_style('starcast-account-ui');
            wp_add_inline_style('starcast-account-ui', $this->get_custom_css());
        }
    }

    public function enqueue_header_nav_styles() {
        wp_register_style('starcast-header-nav-ui', false);
        wp_enqueue_style('starcast-header-nav-ui');
        wp_add_inline_style('starcast-header-nav-ui', $this->get_header_nav_css());
    }

    public function enqueue_login_styles() {
        wp_register_style('starcast-login-ui', false);
        wp_enqueue_style('starcast-login-ui');
        wp_add_inline_style('starcast-login-ui', $this->get_login_css());
    }

    public function filter_shop_menu_item($items, $args) {
        $shop_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
        $filtered = array();

        foreach ($items as $item) {
            $is_shop_id = $shop_id > 0 && (int) $item->object_id === (int) $shop_id;
            $title = isset($item->title) ? strtolower(trim($item->title)) : '';
            $is_shop_title = $title === 'shop';
            $is_shop_url = isset($item->url) && strpos($item->url, '/shop') !== false;

            if ($is_shop_id || $is_shop_title || $is_shop_url) {
                continue;
            }

            if ($title === 'on site tech' || $title === 'on-site tech') {
                $item->title = 'Support';
            }

            $filtered[] = $item;
        }

        return $filtered;
    }
    
    private function get_custom_css() {
        return "
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap');

        :root {
            --starcast-ink: #0f172a;
            --starcast-muted: #5b6475;
            --starcast-line: #e6e9ef;
            --starcast-surface: #ffffff;
            --starcast-soft: #f6f7fb;
            --starcast-accent: #2563eb;
            --starcast-accent-ink: #ffffff;
            --starcast-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            --starcast-radius: 16px;
        }

        .woocommerce-account {
            background: radial-gradient(1200px 800px at 15% -10%, #f2f7ff 0%, #f8f4ff 35%, #ffffff 70%);
        }

        .woocommerce-account .woocommerce {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 20px 60px;
            font-family: 'IBM Plex Sans', sans-serif;
            color: var(--starcast-ink);
        }

        .woocommerce-account .woocommerce-MyAccount-navigation {
            background: var(--starcast-surface);
            border: 1px solid var(--starcast-line);
            border-radius: var(--starcast-radius);
            padding: 16px;
            box-shadow: var(--starcast-shadow);
        }

        .woocommerce-account .woocommerce-MyAccount-navigation ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 8px;
        }

        .woocommerce-account .woocommerce-MyAccount-navigation li a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-radius: 12px;
            color: var(--starcast-ink);
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .woocommerce-account .woocommerce-MyAccount-navigation li a:hover {
            background: var(--starcast-soft);
            transform: translateY(-1px);
        }

        .woocommerce-account .woocommerce-MyAccount-navigation li.is-active a {
            background: rgba(37, 99, 235, 0.12);
            color: var(--starcast-accent);
        }

        .woocommerce-account .woocommerce-MyAccount-content {
            background: var(--starcast-surface);
            border: 1px solid var(--starcast-line);
            border-radius: var(--starcast-radius);
            padding: 28px 28px 32px;
            box-shadow: var(--starcast-shadow);
            min-height: 420px;
        }

        .woocommerce-account h2,
        .woocommerce-account h3 {
            font-family: 'Space Grotesk', sans-serif;
            letter-spacing: -0.01em;
        }

        .woocommerce-account h2 {
            font-size: 28px;
            margin-bottom: 18px;
        }

        .woocommerce-account h3 {
            font-size: 20px;
            margin-top: 8px;
        }

        .woocommerce-account .woocommerce-message,
        .woocommerce-account .woocommerce-error,
        .woocommerce-account .woocommerce-info {
            border-radius: 12px;
            border: 1px solid var(--starcast-line);
            background: #f9fbff;
            padding: 14px 16px;
        }

        .woocommerce-account .button,
        .woocommerce-account .button.button-primary,
        .woocommerce-account .woocommerce-button {
            background: var(--starcast-accent);
            color: var(--starcast-accent-ink);
            border-radius: 12px;
            border: none;
            padding: 12px 18px;
            font-weight: 600;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .woocommerce-account .button:hover,
        .woocommerce-account .woocommerce-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(37, 99, 235, 0.25);
        }

        .woocommerce-account .button.button-secondary {
            background: #eef2ff;
            color: #1e40af;
            box-shadow: none;
        }

        .woocommerce-account input[type='email'],
        .woocommerce-account input[type='tel'],
        .woocommerce-account input[type='text'],
        .woocommerce-account textarea {
            border-radius: 12px;
            border: 1px solid var(--starcast-line);
            padding: 12px 14px;
            background: #fbfcff;
        }

        .woocommerce-account .shop_table {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--starcast-line);
        }

        .starcast-dashboard,
        .starcast-package-management,
        .starcast-billing,
        .starcast-account-details,
        .starcast-support {
            padding: 20px;
        }
        
        .account-details-form .form-row {
            margin-bottom: 20px;
        }
        
        .account-details-form label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .account-details-form input[type='email'],
        .account-details-form input[type='tel'],
        .account-details-form textarea {
            width: 100%;
            max-width: 500px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .account-details-form .required {
            color: red;
        }
        
        .package-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--starcast-shadow);
        }
        
        .package-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--starcast-line);
        }
        
        .package-header h3 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .package-details {
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid var(--starcast-line);
        }
        
        .detail-item .label {
            font-weight: 600;
            color: #666;
            width: 180px;
            flex-shrink: 0;
        }
        
        .detail-item .value {
            color: #333;
        }
        
        .days-count {
            color: #0073aa;
            font-weight: 600;
        }
        
        .package-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 55%, #3b82f6 100%);
            color: #f8fafc;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .payment-card {
            border: 1px solid var(--starcast-line);
            border-radius: 14px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
            background: var(--starcast-surface);
        }
        
        .payment-card:hover {
            border-color: rgba(37, 99, 235, 0.45);
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.12);
        }
        
        .payment-logo h4 {
            font-size: 24px;
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .payfast-card {
            background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%);
        }
        
        .ozow-card {
            background: linear-gradient(to bottom, #fff 0%, #f0f8ff 100%);
        }
        
        .billing-summary {
            background: #f4f7ff;
            border-left: 4px solid var(--starcast-accent);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .billing-amount .amount {
            font-size: 36px;
            font-weight: 700;
            color: var(--starcast-accent);
            margin: 10px 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }
        
        .notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 12px;
            border-radius: 4px;
            margin-top: 15px;
        }
        
        .status-paid {
            color: #28a745;
            font-weight: 600;
        }

        @media (max-width: 900px) {
            .woocommerce-account .woocommerce {
                padding: 24px 16px 40px;
            }

            .woocommerce-account .woocommerce-MyAccount-navigation {
                margin-bottom: 18px;
            }
        }
        ";
    }

    private function get_login_css() {
        return "
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap');

        :root {
            --starcast-ink: #0f172a;
            --starcast-muted: #5b6475;
            --starcast-line: #e6e9ef;
            --starcast-surface: #ffffff;
            --starcast-soft: #f6f7fb;
            --starcast-accent: #2563eb;
            --starcast-accent-ink: #ffffff;
            --starcast-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
            --starcast-radius: 18px;
        }

        body.login {
            font-family: 'IBM Plex Sans', sans-serif;
            color: var(--starcast-ink);
            background: radial-gradient(1200px 800px at 18% -10%, #f2f7ff 0%, #f8f4ff 35%, #ffffff 70%);
        }

        body.login #login {
            width: min(260px, 80vw) !important;
            max-width: 260px !important;
            padding: 24px 0 0 !important;
        }

        body.login #login h1 a {
            background-image: none;
            height: auto;
            width: auto;
            text-indent: 0;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--starcast-ink);
            letter-spacing: -0.02em;
            margin-bottom: 18px;
        }

        body.login #login h1 a::after {
            content: 'Starcast Technologies';
        }

        body.login .login form {
            background: var(--starcast-surface);
            border: 1px solid var(--starcast-line);
            border-radius: var(--starcast-radius);
            padding: 18px;
            box-shadow: var(--starcast-shadow);
        }

        body.login .login form p {
            margin-bottom: 12px;
        }

        body.login .login form .input,
        body.login .login input[type='text'],
        body.login .login input[type='password'] {
            border-radius: 12px;
            border: 1px solid var(--starcast-line);
            padding: 12px 14px;
            background: #fbfcff;
            font-size: 14px;
        }

        body.login .login form .input:focus,
        body.login .login input[type='text']:focus,
        body.login .login input[type='password']:focus {
            border-color: var(--starcast-accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        body.login .wp-core-ui .button-primary {
            background: var(--starcast-accent);
            border: none;
            box-shadow: 0 10px 22px rgba(37, 99, 235, 0.25);
            border-radius: 12px;
            font-weight: 600;
            padding: 10px 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        body.login .wp-core-ui .button-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 26px rgba(37, 99, 235, 0.3);
        }

        body.login #backtoblog a,
        body.login #nav a {
            color: var(--starcast-muted);
            text-decoration: none;
        }

        body.login #backtoblog a:hover,
        body.login #nav a:hover {
            color: var(--starcast-accent);
        }
        ";
    }

    private function get_header_nav_css() {
        return "
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap');

        .kadence-header .main-navigation a,
        .kadence-header .primary-menu a,
        .kadence-header .menu a,
        .kadence-header .header-navigation a,
        .site-header .main-navigation a {
            font-family: 'IBM Plex Sans', sans-serif;
            font-weight: 600;
            letter-spacing: -0.01em;
            text-transform: none;
        }

        .kadence-header .main-navigation ul li,
        .kadence-header .primary-menu li,
        .kadence-header .menu li {
            margin: 0 8px;
        }

        .kadence-header .main-navigation a:hover,
        .kadence-header .primary-menu a:hover,
        .kadence-header .menu a:hover {
            color: #2563eb;
        }
        ";
    }
}

// Initialize plugin
add_action('plugins_loaded', array('Starcast_Customer_Dashboard', 'get_instance'));
