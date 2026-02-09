<?php
/**
 * Plugin Name: Starcast WhatsApp Business
 * Description: Complete WhatsApp Business integration - Send welcome messages, status updates, and enable two-way customer chat via Twilio
 * Version: 1.0.0
 * Author: Starcast Technologies
 */

if (!defined('ABSPATH')) exit;

class Starcast_WhatsApp_Business {

    private $twilio_sid;
    private $twilio_token;
    private $twilio_whatsapp_number;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'starcast_whatsapp_messages';

        // Load Twilio credentials from WordPress options
        $this->twilio_sid = get_option('starcast_twilio_sid');
        $this->twilio_token = get_option('starcast_twilio_token');
        $this->twilio_whatsapp_number = get_option('starcast_twilio_whatsapp_number');

        // Hooks for automatic messages
        add_action('woocommerce_checkout_order_processed', array($this, 'send_welcome_message'), 20, 1);
        add_action('starcast_signup_status_changed', array($this, 'send_status_update_message'), 10, 3);

        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handlers for admin interface
        add_action('wp_ajax_starcast_send_whatsapp', array($this, 'ajax_send_message'));
        add_action('wp_ajax_starcast_get_whatsapp_messages', array($this, 'ajax_get_messages'));
        add_action('wp_ajax_starcast_mark_message_read', array($this, 'ajax_mark_read'));

        // Webhook endpoint for receiving messages
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));

        // Create database table on activation
        register_activation_hook(__FILE__, array($this, 'create_database_table'));
    }

    /**
     * Create database table for storing WhatsApp messages
     */
    public function create_database_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_phone varchar(50) NOT NULL,
            customer_name varchar(255) DEFAULT NULL,
            customer_email varchar(255) DEFAULT NULL,
            message_sid varchar(100) DEFAULT NULL,
            direction varchar(20) NOT NULL,
            message_body text NOT NULL,
            message_status varchar(50) DEFAULT NULL,
            media_url text DEFAULT NULL,
            is_read tinyint(1) DEFAULT 0,
            order_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_phone (customer_phone),
            KEY order_id (order_id),
            KEY created_at (created_at),
            KEY is_read (is_read)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        error_log("Starcast WhatsApp: Database table created successfully");
    }

    /**
     * Send WhatsApp message via Twilio API
     */
    private function send_whatsapp_message($to_phone, $message_body, $order_id = null, $customer_name = null, $customer_email = null) {
        if (empty($this->twilio_sid) || empty($this->twilio_token) || empty($this->twilio_whatsapp_number)) {
            error_log("Starcast WhatsApp: Twilio credentials not configured");
            return array('success' => false, 'error' => 'Twilio credentials not configured');
        }

        // Format phone number for WhatsApp
        $to_whatsapp = $this->format_whatsapp_number($to_phone);
        $from_whatsapp = $this->format_whatsapp_number($this->twilio_whatsapp_number);

        // Twilio API endpoint
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilio_sid}/Messages.json";

        // API request data
        $data = array(
            'From' => $from_whatsapp,
            'To' => $to_whatsapp,
            'Body' => $message_body
        );

        // Send request
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->twilio_sid . ':' . $this->twilio_token)
            ),
            'body' => $data,
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            error_log("Starcast WhatsApp: Failed to send message - " . $response->get_error_message());
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['sid'])) {
            // Store message in database
            $this->store_message(
                $to_phone,
                $customer_name,
                $customer_email,
                $body['sid'],
                'outbound',
                $message_body,
                $body['status'],
                $order_id
            );

            error_log("Starcast WhatsApp: Message sent successfully to {$to_phone} (SID: {$body['sid']})");
            return array('success' => true, 'sid' => $body['sid'], 'status' => $body['status']);
        } else {
            $error = isset($body['message']) ? $body['message'] : 'Unknown error';
            error_log("Starcast WhatsApp: Failed to send message - " . $error);
            return array('success' => false, 'error' => $error);
        }
    }

    /**
     * Format phone number for WhatsApp (add whatsapp: prefix)
     */
    private function format_whatsapp_number($phone) {
        // Remove any existing whatsapp: prefix
        $phone = str_replace('whatsapp:', '', $phone);

        // Remove spaces and dashes
        $phone = preg_replace('/[\s\-]/', '', $phone);

        // Add + if not present
        if (substr($phone, 0, 1) !== '+') {
            // Assume South African number if no country code
            if (substr($phone, 0, 2) === '27') {
                $phone = '+' . $phone;
            } elseif (substr($phone, 0, 1) === '0') {
                $phone = '+27' . substr($phone, 1);
            } else {
                $phone = '+27' . $phone;
            }
        }

        // Add whatsapp: prefix
        return 'whatsapp:' . $phone;
    }

    /**
     * Store message in database
     */
    private function store_message($customer_phone, $customer_name, $customer_email, $message_sid, $direction, $message_body, $message_status, $order_id = null, $media_url = null) {
        global $wpdb;

        $data = array(
            'customer_phone' => $customer_phone,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'message_sid' => $message_sid,
            'direction' => $direction,
            'message_body' => $message_body,
            'message_status' => $message_status,
            'media_url' => $media_url,
            'order_id' => $order_id,
            'is_read' => ($direction === 'outbound') ? 1 : 0
        );

        $wpdb->insert($this->table_name, $data);

        return $wpdb->insert_id;
    }

    /**
     * Send welcome message when customer signs up
     */
    public function send_welcome_message($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $customer_phone = $order->get_billing_phone();
        $customer_email = $order->get_billing_email();

        if (empty($customer_phone)) {
            error_log("Starcast WhatsApp: No phone number for order #{$order_id}");
            return;
        }

        // Get package name
        $package_names = array();
        foreach ($order->get_items() as $item) {
            $package_names[] = $item->get_name();
        }
        $package_name = implode(', ', $package_names);

        // Welcome message template
        $message = "🎉 *Welcome to Starcast Technologies!*\n\n";
        $message .= "Hi {$customer_name},\n\n";
        $message .= "Thank you for choosing Starcast! Your application has been received.\n\n";
        $message .= "📦 *Package:* {$package_name}\n\n";
        $message .= "⏱️ *Next Steps:*\n";
        $message .= "1. Our team will review your application within 24 hours\n";
        $message .= "2. We'll check coverage at your location\n";
        $message .= "3. You'll receive a status update soon\n\n";
        $message .= "💬 *Need help?* Just reply to this message and our support team will assist you!\n\n";
        $message .= "Best regards,\n";
        $message .= "The Starcast Team";

        $this->send_whatsapp_message($customer_phone, $message, $order_id, $customer_name, $customer_email);
    }

    /**
     * Send status update message (approved/declined/processing)
     */
    public function send_status_update_message($signup_id, $new_status, $signup_data) {
        $customer_phone = $signup_data['customer_phone'];
        $customer_name = $signup_data['customer_name'];
        $customer_email = $signup_data['customer_email'];
        $order_id = isset($signup_data['order_id']) ? $signup_data['order_id'] : null;

        if (empty($customer_phone)) {
            error_log("Starcast WhatsApp: No phone number for signup #{$signup_id}");
            return;
        }

        $message = '';

        switch ($new_status) {
            case 'approved':
                $message = "✅ *Application Approved!*\n\n";
                $message .= "Hi {$customer_name},\n\n";
                $message .= "Great news! Your Starcast application has been approved.\n\n";
                $message .= "📧 We've sent your account details to {$customer_email}\n\n";
                $message .= "🔑 *Login:* https://starcast.co.za/my-account/\n\n";
                $message .= "📞 Our team will contact you within 24-48 hours to schedule installation.\n\n";
                $message .= "💬 Questions? Just reply to this message!\n\n";
                $message .= "Welcome aboard! 🚀";
                break;

            case 'rejected':
                $message = "📋 *Application Update*\n\n";
                $message .= "Hi {$customer_name},\n\n";
                $message .= "Thank you for your interest in Starcast Technologies.\n\n";
                $message .= "Unfortunately, we're unable to provide service at your location at this time due to coverage limitations.\n\n";
                $message .= "🔍 *Alternatives:*\n";
                $message .= "• We're constantly expanding - we'll notify you when available\n";
                $message .= "• Check our coverage map for updates\n\n";
                $message .= "📧 More details sent to {$customer_email}\n\n";
                $message .= "💬 Questions? Reply to this message anytime.\n\n";
                $message .= "Thank you,\n";
                $message .= "The Starcast Team";
                break;

            case 'processing':
                $message = "⏳ *Application Being Processed*\n\n";
                $message .= "Hi {$customer_name},\n\n";
                $message .= "Your Starcast application is currently being reviewed by our team.\n\n";
                $message .= "We're checking:\n";
                $message .= "✓ Coverage availability\n";
                $message .= "✓ Infrastructure requirements\n";
                $message .= "✓ Installation logistics\n\n";
                $message .= "⏱️ You'll receive an update within 24-48 hours.\n\n";
                $message .= "💬 Need help? Just reply to this message!\n\n";
                $message .= "The Starcast Team";
                break;

            default:
                // Don't send message for other status changes
                return;
        }

        $this->send_whatsapp_message($customer_phone, $message, $order_id, $customer_name, $customer_email);
    }

    /**
     * Register webhook endpoint for receiving messages
     */
    public function register_webhook_endpoint() {
        register_rest_route('starcast/v1', '/whatsapp/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_incoming_message'),
            'permission_callback' => '__return_true' // Twilio will send webhooks
        ));
    }

    /**
     * Handle incoming WhatsApp messages from Twilio webhook
     */
    public function handle_incoming_message($request) {
        $params = $request->get_params();

        error_log("Starcast WhatsApp: Received webhook - " . print_r($params, true));

        // Extract message data
        $from = isset($params['From']) ? str_replace('whatsapp:', '', $params['From']) : '';
        $body = isset($params['Body']) ? sanitize_textarea_field($params['Body']) : '';
        $message_sid = isset($params['MessageSid']) ? sanitize_text_field($params['MessageSid']) : '';
        $media_url = isset($params['MediaUrl0']) ? esc_url_raw($params['MediaUrl0']) : null;

        if (empty($from) || empty($body)) {
            return new WP_REST_Response(array('error' => 'Invalid message'), 400);
        }

        // Try to find customer by phone number
        $customer_name = null;
        $customer_email = null;
        $order_id = null;

        // Search in previous messages
        global $wpdb;
        $previous_message = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_name, customer_email, order_id FROM {$this->table_name}
             WHERE customer_phone = %s AND customer_name IS NOT NULL
             ORDER BY created_at DESC LIMIT 1",
            $from
        ));

        if ($previous_message) {
            $customer_name = $previous_message->customer_name;
            $customer_email = $previous_message->customer_email;
            $order_id = $previous_message->order_id;
        }

        // Store incoming message
        $this->store_message(
            $from,
            $customer_name,
            $customer_email,
            $message_sid,
            'inbound',
            $body,
            'received',
            $order_id,
            $media_url
        );

        // Send auto-reply for support messages
        $auto_reply = "👋 Thanks for your message! Our support team has been notified and will respond shortly.\n\n";
        $auto_reply .= "🕐 Support hours: 24/7\n";
        $auto_reply .= "⏱️ Average response time: 1-2 hours\n\n";
        $auto_reply .= "For urgent matters, call us directly.\n\n";
        $auto_reply .= "The Starcast Team";

        // Send auto-reply (don't store it, admin will send proper reply)
        $this->send_whatsapp_message($from, $auto_reply, $order_id, $customer_name, $customer_email);

        return new WP_REST_Response(array('success' => true), 200);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'WhatsApp Messages',
            'WhatsApp',
            'manage_options',
            'starcast-whatsapp',
            array($this, 'admin_page'),
            'dashicons-whatsapp',
            25
        );

        add_submenu_page(
            'starcast-whatsapp',
            'WhatsApp Settings',
            'Settings',
            'manage_options',
            'starcast-whatsapp-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'starcast-whatsapp') === false) {
            return;
        }

        wp_enqueue_style('starcast-whatsapp-admin', plugins_url('assets/whatsapp-admin.css', __FILE__), array(), '1.0.0');
        wp_enqueue_script('starcast-whatsapp-admin', plugins_url('assets/whatsapp-admin.js', __FILE__), array('jquery'), '1.0.0', true);

        wp_localize_script('starcast-whatsapp-admin', 'starcastWhatsApp', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('starcast_whatsapp_nonce')
        ));
    }

    /**
     * Admin page - View and reply to messages
     */
    public function admin_page() {
        global $wpdb;

        // Get all conversations (grouped by phone number)
        $conversations = $wpdb->get_results(
            "SELECT customer_phone, customer_name, customer_email,
                    MAX(created_at) as last_message_time,
                    SUM(CASE WHEN direction = 'inbound' AND is_read = 0 THEN 1 ELSE 0 END) as unread_count
             FROM {$this->table_name}
             GROUP BY customer_phone
             ORDER BY last_message_time DESC"
        );

        ?>
        <div class="wrap starcast-whatsapp-wrap">
            <h1>WhatsApp Messages</h1>

            <div class="whatsapp-container">
                <!-- Conversations List -->
                <div class="conversations-list">
                    <h2>Conversations (<?php echo count($conversations); ?>)</h2>

                    <?php if (empty($conversations)): ?>
                        <p class="no-messages">No conversations yet</p>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <div class="conversation-item" data-phone="<?php echo esc_attr($conv->customer_phone); ?>">
                                <div class="conversation-header">
                                    <strong><?php echo esc_html($conv->customer_name ?: $conv->customer_phone); ?></strong>
                                    <?php if ($conv->unread_count > 0): ?>
                                        <span class="unread-badge"><?php echo $conv->unread_count; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-meta">
                                    <span class="phone"><?php echo esc_html($conv->customer_phone); ?></span>
                                    <span class="time"><?php echo human_time_diff(strtotime($conv->last_message_time), current_time('timestamp')) . ' ago'; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Messages View -->
                <div class="messages-view">
                    <div id="messages-container">
                        <p class="select-conversation">← Select a conversation to view messages</p>
                    </div>

                    <div class="reply-form" style="display:none;">
                        <textarea id="reply-message" placeholder="Type your message..." rows="3"></textarea>
                        <button id="send-reply" class="button button-primary">Send Message</button>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .starcast-whatsapp-wrap { max-width: 1400px; }
            .whatsapp-container { display: flex; gap: 20px; margin-top: 20px; }
            .conversations-list { flex: 0 0 350px; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px; max-height: 600px; overflow-y: auto; }
            .conversation-item { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s; }
            .conversation-item:hover, .conversation-item.active { background: #f5f5f5; }
            .conversation-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
            .unread-badge { background: #25D366; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
            .conversation-meta { display: flex; justify-content: space-between; font-size: 12px; color: #666; }
            .messages-view { flex: 1; background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px; }
            #messages-container { max-height: 450px; overflow-y: auto; margin-bottom: 20px; padding: 10px; background: #f9f9f9; border-radius: 4px; }
            .message { margin-bottom: 15px; padding: 10px 15px; border-radius: 8px; max-width: 70%; }
            .message.inbound { background: #e5e5ea; margin-right: auto; }
            .message.outbound { background: #25D366; color: white; margin-left: auto; }
            .message-meta { font-size: 11px; margin-top: 5px; opacity: 0.7; }
            .reply-form textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; resize: vertical; }
            #send-reply { margin-top: 10px; }
            .select-conversation { text-align: center; color: #666; padding: 50px 20px; }
            .no-messages { text-align: center; padding: 30px; color: #666; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Handle conversation selection
            $('.conversation-item').on('click', function() {
                var phone = $(this).data('phone');

                $('.conversation-item').removeClass('active');
                $(this).addClass('active');

                // Load messages for this phone number
                loadMessages(phone);

                // Show reply form
                $('.reply-form').show().data('phone', phone);

                // Mark as read
                $.post(ajaxurl, {
                    action: 'starcast_mark_message_read',
                    phone: phone,
                    nonce: '<?php echo wp_create_nonce('starcast_whatsapp_nonce'); ?>'
                });

                // Remove unread badge
                $(this).find('.unread-badge').remove();
            });

            // Send reply
            $('#send-reply').on('click', function() {
                var phone = $('.reply-form').data('phone');
                var message = $('#reply-message').val();

                if (!message.trim()) {
                    alert('Please enter a message');
                    return;
                }

                $(this).prop('disabled', true).text('Sending...');

                $.post(ajaxurl, {
                    action: 'starcast_send_whatsapp',
                    phone: phone,
                    message: message,
                    nonce: '<?php echo wp_create_nonce('starcast_whatsapp_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#reply-message').val('');
                        loadMessages(phone);
                    } else {
                        alert('Failed to send message: ' + (response.data || 'Unknown error'));
                    }
                    $('#send-reply').prop('disabled', false).text('Send Message');
                });
            });

            function loadMessages(phone) {
                $('#messages-container').html('<p style="text-align:center;">Loading messages...</p>');

                $.post(ajaxurl, {
                    action: 'starcast_get_whatsapp_messages',
                    phone: phone,
                    nonce: '<?php echo wp_create_nonce('starcast_whatsapp_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        var html = '';
                        if (response.data.length === 0) {
                            html = '<p class="select-conversation">No messages</p>';
                        } else {
                            response.data.forEach(function(msg) {
                                html += '<div class="message ' + msg.direction + '">';
                                html += '<div>' + msg.message_body + '</div>';
                                html += '<div class="message-meta">' + msg.created_at + '</div>';
                                html += '</div>';
                            });
                        }
                        $('#messages-container').html(html);
                        $('#messages-container').scrollTop($('#messages-container')[0].scrollHeight);
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['save_settings'])) {
            check_admin_referer('starcast_whatsapp_settings');

            update_option('starcast_twilio_sid', sanitize_text_field($_POST['twilio_sid']));
            update_option('starcast_twilio_token', sanitize_text_field($_POST['twilio_token']));
            update_option('starcast_twilio_whatsapp_number', sanitize_text_field($_POST['twilio_whatsapp_number']));

            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }

        $webhook_url = rest_url('starcast/v1/whatsapp/webhook');

        ?>
        <div class="wrap">
            <h1>WhatsApp Settings</h1>

            <form method="post">
                <?php wp_nonce_field('starcast_whatsapp_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="twilio_sid">Twilio Account SID</label></th>
                        <td>
                            <input type="text" id="twilio_sid" name="twilio_sid" value="<?php echo esc_attr($this->twilio_sid); ?>" class="regular-text" />
                            <p class="description">Get this from your <a href="https://console.twilio.com/" target="_blank">Twilio Console</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="twilio_token">Twilio Auth Token</label></th>
                        <td>
                            <input type="password" id="twilio_token" name="twilio_token" value="<?php echo esc_attr($this->twilio_token); ?>" class="regular-text" />
                            <p class="description">Your Twilio Auth Token (keep this secret!)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="twilio_whatsapp_number">Twilio WhatsApp Number</label></th>
                        <td>
                            <input type="text" id="twilio_whatsapp_number" name="twilio_whatsapp_number" value="<?php echo esc_attr($this->twilio_whatsapp_number); ?>" class="regular-text" placeholder="+14155238886" />
                            <p class="description">Format: +14155238886 (include country code, no spaces)</p>
                        </td>
                    </tr>
                </table>

                <h2>Webhook Configuration</h2>
                <p>Configure this webhook URL in your Twilio WhatsApp Sandbox or Business Profile:</p>
                <table class="form-table">
                    <tr>
                        <th>Webhook URL (Incoming Messages)</th>
                        <td>
                            <code style="background:#f5f5f5;padding:8px;display:inline-block;"><?php echo esc_html($webhook_url); ?></code>
                            <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($webhook_url); ?>')">Copy</button>
                            <p class="description">
                                <strong>Setup Instructions:</strong><br>
                                1. Go to <a href="https://console.twilio.com/us1/develop/sms/settings/whatsapp-sandbox" target="_blank">Twilio WhatsApp Sandbox Settings</a><br>
                                2. Under "Sandbox Configuration", paste this URL in "When a message comes in"<br>
                                3. Set HTTP method to POST<br>
                                4. Click Save
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="save_settings" class="button button-primary">Save Settings</button>
                </p>
            </form>

            <hr>

            <h2>Testing</h2>
            <p>Test your WhatsApp integration:</p>
            <ol>
                <li>Join your Twilio WhatsApp Sandbox by sending the join code to your Twilio number</li>
                <li>Send a test message from your WhatsApp</li>
                <li>Check the <a href="<?php echo admin_url('admin.php?page=starcast-whatsapp'); ?>">WhatsApp Messages</a> page</li>
            </ol>
        </div>
        <?php
    }

    /**
     * AJAX: Send WhatsApp message
     */
    public function ajax_send_message() {
        check_ajax_referer('starcast_whatsapp_nonce', 'nonce');

        $phone = sanitize_text_field($_POST['phone']);
        $message = sanitize_textarea_field($_POST['message']);

        if (empty($phone) || empty($message)) {
            wp_send_json_error('Phone and message are required');
        }

        $result = $this->send_whatsapp_message($phone, $message);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['error']);
        }
    }

    /**
     * AJAX: Get messages for a phone number
     */
    public function ajax_get_messages() {
        check_ajax_referer('starcast_whatsapp_nonce', 'nonce');

        $phone = sanitize_text_field($_POST['phone']);

        global $wpdb;
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE customer_phone = %s
             ORDER BY created_at ASC",
            $phone
        ), ARRAY_A);

        wp_send_json_success($messages);
    }

    /**
     * AJAX: Mark messages as read
     */
    public function ajax_mark_read() {
        check_ajax_referer('starcast_whatsapp_nonce', 'nonce');

        $phone = sanitize_text_field($_POST['phone']);

        global $wpdb;
        $wpdb->update(
            $this->table_name,
            array('is_read' => 1),
            array('customer_phone' => $phone, 'direction' => 'inbound')
        );

        wp_send_json_success();
    }
}

// Initialize the plugin
new Starcast_WhatsApp_Business();
