<?php
/**
 * Plugin Name: Starcast Signup Notifications
 * Description: Automated email notifications for fibre and LTE signups
 * Version: 1.0.0
 * Author: Starcast Technologies
 */

if (!defined('ABSPATH')) exit;

class Starcast_Signup_Notifications {
    
    public function __construct() {
        // Hook into WooCommerce checkout completion (fires after order data is saved)
        add_action('woocommerce_checkout_order_processed', array($this, 'send_order_notifications'), 10, 1);

        // Also hook into order status changes for manual orders
        add_action('woocommerce_order_status_pending', array($this, 'send_order_notifications'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'send_order_notifications_once'), 10, 1);

        // Hook into custom signup forms
        add_action('starcast_fibre_signup_complete', array($this, 'send_fibre_signup_notifications'), 10, 2);
        add_action('starcast_lte_signup_complete', array($this, 'send_lte_signup_notifications'), 10, 2);
    }

    /**
     * Send notifications only once (check if already processed)
     */
    public function send_order_notifications_once($order_id) {
        // Check if we already processed this order
        $already_processed = get_post_meta($order_id, '_starcast_notifications_sent', true);
        if ($already_processed) {
            return; // Already processed, don't send again
        }

        $this->send_order_notifications($order_id);
    }
    
    /**
     * Send notifications when WooCommerce order is created
     */
    public function send_order_notifications($order_id) {
        // Check if already processed
        $already_processed = get_post_meta($order_id, '_starcast_notifications_sent', true);
        if ($already_processed) {
            error_log("Starcast: Order #{$order_id} already processed, skipping");
            return;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            error_log("Starcast: Could not retrieve order #{$order_id}");
            return;
        }

        // Validate order has customer data
        $customer_email = $order->get_billing_email();
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $customer_phone = $order->get_billing_phone();

        // Skip if no email (placeholder orders, incomplete data)
        if (empty($customer_email)) {
            error_log("Starcast: Order #{$order_id} has no customer email, skipping");
            return;
        }

        // Skip if no customer name
        if (empty($customer_name)) {
            error_log("Starcast: Order #{$order_id} has no customer name, skipping");
            return;
        }
        $product_names = array();
        $product_ids = array();
        $package_type = 'fibre'; // Default

        foreach ($order->get_items() as $item) {
            $product_names[] = $item->get_name();
            $product_ids[] = $item->get_product_id();

            // Detect package type from product name
            $product_name_lower = strtolower($item->get_name());
            if (strpos($product_name_lower, 'lte') !== false) {
                $package_type = 'lte';
            }
        }

        $package_name = implode(', ', $product_names);
        $package_id = !empty($product_ids) ? $product_ids[0] : null;

        // Build customer address
        $customer_address = trim(
            $order->get_billing_address_1() . ' ' .
            $order->get_billing_address_2() . ', ' .
            $order->get_billing_city() . ', ' .
            $order->get_billing_state() . ' ' .
            $order->get_billing_postcode()
        );

        // Get installation address from order meta (if different)
        $installation_address = $order->get_meta('_installation_address');
        if (empty($installation_address)) {
            $installation_address = $customer_address;
        }

        // Insert signup record into database
        $signup_created = $this->create_signup_record($order_id, $customer_name, $customer_email, $customer_phone,
                                    $customer_address, $installation_address, $package_id,
                                    $package_type, $package_name);

        if (!$signup_created) {
            error_log("Starcast: Failed to create signup record for order #{$order_id}");
        }

        // Send customer email
        $this->send_customer_welcome_email($customer_email, $customer_name, $package_name);

        // Send admin notification
        $this->send_admin_notification($customer_name, $customer_email, $package_name, $order_id);

        // Mark order as processed to prevent duplicate notifications
        update_post_meta($order_id, '_starcast_notifications_sent', true);
        error_log("Starcast: Successfully processed order #{$order_id} for {$customer_email}");
    }

    /**
     * Create signup record in database
     */
    private function create_signup_record($order_id, $customer_name, $customer_email, $customer_phone,
                                         $customer_address, $installation_address, $package_id,
                                         $package_type, $package_name) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'starcast_signups';

        // Check if signup already exists for this order
        $existing_signup = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE notes LIKE %s",
            '%Order ID: ' . $order_id . '%'
        ));

        if ($existing_signup) {
            error_log("Starcast: Signup already exists (#{$existing_signup}) for order #{$order_id}, skipping duplicate");
            return true; // Return true since the signup exists
        }

        $data = array(
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'customer_address' => $customer_address,
            'installation_address' => $installation_address,
            'package_id' => $package_id,
            'package_type' => $package_type,
            'status' => 'pending',
            'notes' => 'WooCommerce Order ID: ' . $order_id . ' | Package: ' . $package_name
        );

        $result = $wpdb->insert($table_name, $data);

        if ($result) {
            $signup_id = $wpdb->insert_id;

            // Store signup ID in order meta for future reference
            update_post_meta($order_id, '_starcast_signup_id', $signup_id);

            error_log("Starcast: Created signup record #{$signup_id} for order #{$order_id}");
        } else {
            error_log("Starcast: Failed to create signup record for order #{$order_id}. Error: " . $wpdb->last_error);
        }

        return $result;
    }
    
    /**
     * Send customer welcome email
     */
    private function send_customer_welcome_email($to_email, $customer_name, $package_name) {
        $subject = 'Welcome to Starcast Technologies - Your Application is Received';
        
        $message = $this->get_customer_email_template($customer_name, $package_name);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Starcast Technologies <info@starcast.co.za>',
            'Reply-To: info@starcast.co.za'
        );
        
        wp_mail($to_email, $subject, $message, $headers);
        
        // Log email
        error_log("Starcast: Welcome email sent to {$to_email}");
    }
    
    /**
     * Send admin notification
     */
    private function send_admin_notification($customer_name, $customer_email, $package_name, $order_id = null) {
        $subject = '🔔 New Customer Signup - ' . $customer_name;
        
        $message = $this->get_admin_email_template($customer_name, $customer_email, $package_name, $order_id);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Starcast Notifications <info@starcast.co.za>',
            'Reply-To: ' . $customer_email
        );
        
        // Send to both admin emails
        $admin_emails = array('info@starcast.co.za', 'starcast.tech@gmail.com');
        
        foreach ($admin_emails as $admin_email) {
            wp_mail($admin_email, $subject, $message, $headers);
        }
        
        error_log("Starcast: Admin notification sent for {$customer_name}");
    }
    
    /**
     * Customer email template
     */
    private function get_customer_email_template($customer_name, $package_name) {
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
                .highlight { background: #faf7f4; padding: 20px; border-left: 4px solid #d67d3e; margin: 20px 0; }
                .next-steps { background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .next-steps h3 { color: #d67d3e; margin-top: 0; }
                .next-steps ol { padding-left: 20px; }
                .next-steps li { margin-bottom: 10px; }
                .footer { background: #2d2823; color: white; padding: 30px 20px; text-align: center; border-radius: 0 0 8px 8px; }
                .footer a { color: #d67d3e; text-decoration: none; }
                .button { display: inline-block; background: #d67d3e; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Welcome to Starcast Technologies!</h1>
                </div>
                
                <div class="content">
                    <p>Hi <?php echo esc_html($customer_name); ?>,</p>
                    
                    <p>Thank you for choosing Starcast Technologies! We're excited to get you connected.</p>
                    
                    <div class="highlight">
                        <strong>Package Selected:</strong> <?php echo esc_html($package_name); ?>
                    </div>
                    
                    <div class="next-steps">
                        <h3>What Happens Next?</h3>
                        <ol>
                            <li><strong>Application Review</strong> - Our team will review your application within 24 hours</li>
                            <li><strong>Coverage Check</strong> - We'll verify service availability at your location</li>
                            <li><strong>Installation Scheduling</strong> - You'll receive a call to schedule installation (typically within 7 days)</li>
                            <li><strong>Activation</strong> - Our technician will install and activate your service</li>
                        </ol>
                    </div>
                    
                    <p><strong>Need Help?</strong></p>
                    <p>Our support team is here to help:</p>
                    <ul>
                        <li>📧 Email: <a href="mailto:info@starcast.co.za">info@starcast.co.za</a></li>
                        <li>📱 WhatsApp: Available via our website</li>
                        <li>🕐 Support Hours: 24/7</li>
                    </ul>
                    
                    <p>We look forward to providing you with fast, reliable internet!</p>
                    
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
    
    /**
     * Admin email template
     */
    private function get_admin_email_template($customer_name, $customer_email, $package_name, $order_id) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
                .header { background: #d67d3e; color: white; padding: 20px; text-align: center; }
                .content { background: white; padding: 30px; margin-top: 20px; border-radius: 8px; }
                .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .info-table th { background: #faf7f4; padding: 12px; text-align: left; border-bottom: 2px solid #d67d3e; }
                .info-table td { padding: 12px; border-bottom: 1px solid #eee; }
                .action-button { display: inline-block; background: #d67d3e; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>🔔 New Customer Signup</h2>
                </div>
                
                <div class="content">
                    <h3>New Application Received</h3>
                    
                    <table class="info-table">
                        <tr>
                            <th>Customer Name</th>
                            <td><?php echo esc_html($customer_name); ?></td>
                        </tr>
                        <tr>
                            <th>Email Address</th>
                            <td><a href="mailto:<?php echo esc_attr($customer_email); ?>"><?php echo esc_html($customer_email); ?></a></td>
                        </tr>
                        <tr>
                            <th>Package</th>
                            <td><?php echo esc_html($package_name); ?></td>
                        </tr>
                        <?php if ($order_id): ?>
                        <tr>
                            <th>Order ID</th>
                            <td>#<?php echo esc_html($order_id); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Date & Time</th>
                            <td><?php echo date('F j, Y g:i A'); ?></td>
                        </tr>
                    </table>
                    
                    <?php if ($order_id): ?>
                    <p>
                        <a href="<?php echo admin_url('post.php?post=' . $order_id . '&action=edit'); ?>" class="action-button">
                            View Order Details →
                        </a>
                    </p>
                    <?php endif; ?>
                    
                    <p><strong>Action Required:</strong></p>
                    <ol>
                        <li>Review customer application details</li>
                        <li>Check coverage availability at customer location</li>
                        <li>Contact customer within 24 hours to schedule installation</li>
                    </ol>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Fibre signup notifications
     */
    public function send_fibre_signup_notifications($customer_data, $package_data) {
        $this->send_customer_welcome_email(
            $customer_data['email'],
            $customer_data['name'],
            $package_data['name']
        );
        
        $this->send_admin_notification(
            $customer_data['name'],
            $customer_data['email'],
            $package_data['name']
        );
    }
    
    /**
     * LTE signup notifications
     */
    public function send_lte_signup_notifications($customer_data, $package_data) {
        $this->send_fibre_signup_notifications($customer_data, $package_data);
    }
}

// Initialize the plugin
new Starcast_Signup_Notifications();
