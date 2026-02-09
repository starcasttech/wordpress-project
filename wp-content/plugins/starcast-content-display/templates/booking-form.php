<?php if (!defined('ABSPATH')) exit;



?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<div class="booking-page">
    <div class="container">
        <div class="booking-card">
            <h2 class="booking-title">Tech-Support</h2>
            
            <?php
            $form_submitted = false;
            $submission_success = false;
            $error_message = '';
            $booking_ref = null;
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_submit'])) {
                $contact_name = sanitize_text_field($_POST['contact_name']);
                $contact_surname = sanitize_text_field($_POST['contact_surname']);
                $contact_email = sanitize_email($_POST['contact_email']);
                $contact_phone = sanitize_text_field($_POST['contact_phone']);
                $visit_address = sanitize_text_field($_POST['visit_address']);
                $preferred_date = sanitize_text_field($_POST['preferred_date']);
                $preferred_time = sanitize_text_field($_POST['preferred_time']);
                $special_instructions = sanitize_textarea_field($_POST['special_instructions']);
                
                $form_submitted = true;
                
                // Basic validation
                if (empty($contact_name) || empty($contact_surname) || empty($contact_email) || empty($contact_phone) || empty($visit_address) || empty($preferred_date)) {
                    $error_message = 'Please fill in all required fields.';
                } elseif (!is_email($contact_email)) {
                    $error_message = 'Please provide a valid email address.';
                } else {
                    // Check for a recent booking from the same email to prevent spam
                    $existing_bookings = wc_get_orders(array(
                        'meta_query' => array(
                            'relation' => 'AND',
                            array(
                                'key' => '_customer_email',
                                'value' => $contact_email,
                                'compare' => '='
                            ),
                            array(
                                'key' => '_booking_type',
                                'value' => 'technician_visit'
                            )
                        ),
                        'date_created' => '>' . (time() - 24 * 60 * 60), // in the last 24 hours
                        'status' => array('wc-pending', 'wc-processing', 'wc-on-hold')
                    ));

                    if (!empty($existing_bookings)) {
                        $error_message = 'You have already submitted a booking request recently. Please wait for our team to contact you.';
                    } else {
                        try {
                            // Create a WooCommerce order to track the booking
                            $order = wc_create_order();
                            
                            if (!$order || is_wp_error($order)) {
                                throw new Exception('Failed to create booking record.');
                            }
                            
                            $booking_ref = $order->get_id();
                            
                            // Store customer data as order meta
                            $customer_data = array(
                                'first_name' => $contact_name,
                                'last_name' => $contact_surname,
                                'email' => $contact_email,
                                'phone' => $contact_phone,
                                'address' => $visit_address
                            );
                            
                            // Add a line item for the booking
                            $item = new WC_Order_Item_Product();
                            $item->set_name('Technician Site Visit');
                            $item->set_quantity(1);
                            $item->set_subtotal(0); // No cost for booking
                            $item->set_total(0);
                            $order->add_item($item);
                            
                            // Set order status to "On Hold" to indicate it's a booking request
                            $order->set_status('on-hold');
                            
                            // Store booking details in meta fields
                            $order->update_meta_data('_customer_name', $contact_name . ' ' . $contact_surname);
                            $order->update_meta_data('_customer_email', $contact_email);
                            $order->update_meta_data('_customer_phone', $contact_phone);
                            $order->update_meta_data('_visit_address', $visit_address);
                            $order->update_meta_data('_preferred_date', $preferred_date);
                            $order->update_meta_data('_preferred_time', $preferred_time);
                            $order->update_meta_data('_special_instructions', $special_instructions);
                            $order->update_meta_data('_booking_type', 'technician_visit');
                            
                            // Save the order
                            $order->calculate_totals();
                            $order->save();
                            
                            // Add an order note
                            $order->add_order_note('Technician site visit requested. Awaiting confirmation from admin.');
                            
                            // Send confirmation emails
                            send_technician_booking_emails($order, $customer_data, $preferred_date, $preferred_time, $special_instructions);
                            
                            $submission_success = true;
                            
                        } catch (Exception $e) {
                            $error_message = 'There was an error processing your booking: ' . $e->getMessage();
                            error_log('Technician booking error: ' . $e->getMessage());
                        }
                    }
                }
            }
            
            if ($form_submitted && $submission_success) {
                ?>
                <div class="success-message">
                    <div class="success-icon">✓</div>
                    <h3>Booking Request Submitted!</h3>
                    <p>Thank you! Your request for a technician visit has been received. Your reference number is <strong>#<?php echo $booking_ref; ?></strong>.</p>
                    
                    <div class="next-steps">
                        <h4>What happens next?</h4>
                        <ol>
                            <li>Our team will review your request and contact you within 24 hours to confirm the date and time.</li>
                            <li>Please ensure someone is available at the address during the scheduled time.</li>
                        </ol>
                    </div>
                    
                    <div class="contact-info">
                        <p>If you have any urgent questions, please contact us at <a href="mailto:starcast.tech@gmail.com">starcast.tech@gmail.com</a>.</p>
                    </div>
                </div>
                
                <div class="return-actions">
                    <a href="<?php echo home_url(); ?>" class="btn-secondary">Return to Home</a>
                </div>
                <?php
            } else {
                if (!empty($error_message)) {
                    echo "<div class='error-message'>" . esc_html($error_message) . "</div>";
                }
                ?>
                <p class="booking-description">Need technical assistance or a site survey? Fill out the form below to request a visit from one of our qualified technicians.</p>
                
                <form method="POST" id="booking-form" class="booking-form">
                    <div class="form-section">
                        <h4>Your Details</h4>
                        <div class="form-row name-row">
                            <div class="form-group">
                                <label for="contact_name">First Name:</label>
                                <input type="text" name="contact_name" id="contact_name" required />
                            </div>
                            <div class="form-group">
                                <label for="contact_surname">Surname:</label>
                                <input type="text" name="contact_surname" id="contact_surname" required />
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact_email">Email Address:</label>
                                <input type="email" name="contact_email" id="contact_email" required />
                            </div>
                            <div class="form-group">
                                <label for="contact_phone">Phone Number:</label>
                                <input type="tel" name="contact_phone" id="contact_phone" required />
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="visit_address">Address for Site Visit:</label>
                            <input type="text" name="visit_address" id="visit_address" required placeholder="Full street address including suburb and postal code" />
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4>Preferred Date & Time</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="preferred_date">Preferred Date:</label>
                                <input type="date" name="preferred_date" id="preferred_date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" />
                            </div>
                            <div class="form-group">
                                <label for="preferred_time">Preferred Time:</label>
                                <select name="preferred_time" id="preferred_time" required>
                                    <option value="">Select a time slot</option>
                                    <option value="08:00-12:00">Morning (8:00 AM - 12:00 PM)</option>
                                    <option value="12:00-17:00">Afternoon (12:00 PM - 5:00 PM)</option>
                                    <option value="flexible">Any time</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="special_instructions">Reason for visit or special instructions (Optional):</label>
                            <textarea name="special_instructions" id="special_instructions" rows="3" placeholder="e.g., Poor signal, new setup query, gate access code..."></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="booking_submit" id="submit-button" class="btn-submit">Request Booking</button>
                    
                    <p class="terms-note">Please note that this is a request. Our team will contact you to confirm the final date and time for the technician's visit.</p>
                </form>

                <?php starcast_google_places_script('Booking', 'visit_address'); ?>
            <?php } ?>
        </div>
    </div>
</div>

<?php
function send_technician_booking_emails($order, $customer_data, $preferred_date, $preferred_time, $special_instructions) {
    $order_id = $order->get_id();
    $customer_name = $customer_data['first_name'];
    $customer_email = $customer_data['email'];
    
    $formatted_date = date('l, F j, Y', strtotime($preferred_date));
    $time_display = str_replace(['-', '_'], [' to ', ' '], $preferred_time);
    
    // Admin notification
    $admin_to = get_option('admin_email');
    $admin_subject = 'New Technician Booking Request #' . $order_id;
    $headers = array('Content-Type: text/html; charset=UTF-8', 'From: Starcast <info@localhost>');
    $admin_url = admin_url('post.php?post=' . $order_id . '&action=edit');
    
    $admin_message = "
    <html><body>
        <h2>New Technician Booking Request (#{$order_id})</h2>
        <p>A new request for a technician site visit has been submitted.</p>
        <h3>Customer Details:</h3>
        <ul>
            <li><strong>Name:</strong> {$customer_data['first_name']} {$customer_data['last_name']}</li>
            <li><strong>Email:</strong> {$customer_data['email']}</li>
            <li><strong>Phone:</strong> {$customer_data['phone']}</li>
            <li><strong>Address:</strong> {$customer_data['address']}</li>
        </ul>
        <h3>Booking Details:</h3>
        <ul>
            <li><strong>Preferred Date:</strong> {$formatted_date}</li>
            <li><strong>Preferred Time:</strong> {$time_display}</li>
            <li><strong>Instructions:</strong> " . nl2br(esc_html($special_instructions)) . "</li>
        </ul>
        <p><a href='{$admin_url}'>View and confirm the booking in the admin panel.</a></p>
    </body></html>";
    
    wp_mail($admin_to, $admin_subject, $admin_message, $headers);
    
    // Customer confirmation
    $customer_subject = 'Your Technician Booking Request has been Received (Ref: #' . $order_id . ')';
    $customer_message = "
    <html><body>
        <h2>We've Received Your Booking Request!</h2>
        <p>Hi {$customer_name},</p>
        <p>Thank you for submitting a request for a technician visit. Your reference number is <strong>#{$order_id}</strong>.</p>
        <p>We will review your preferred date and time and contact you shortly to confirm the appointment.</p>
        <h3>Your Request Details:</h3>
        <ul>
            <li><strong>Address:</strong> {$customer_data['address']}</li>
            <li><strong>Preferred Date:</strong> {$formatted_date}</li>
            <li><strong>Preferred Time:</strong> {$time_display}</li>
        </ul>
        <p>If you have any questions, please reply to this email.</p>
        <p>Best regards,<br>The Starcast Team</p>
    </body></html>";
    
    wp_mail($customer_email, $customer_subject, $customer_message, $headers);
}
?>

<style>
/* Reset and Base Styles */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --primary-color: #d67d3e;
    --primary-color-dark: #c56d31;
    --text-dark: #2d2823;
    --text-light: #6b6355;
    --bg-light: #faf7f4;
    --bg-gradient: linear-gradient(135deg, #faf7f4 0%, #f0ebe3 30%, #f7f2eb 70%, #faf7f4 100%);
    --border-color: #ede8e1;
    --white: #ffffff;
    --font-family: 'Poppins', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --card-shadow: 0 8px 32px rgba(74, 69, 63, 0.1);
    --card-border: 1px solid rgba(232, 227, 219, 0.3);
}
.booking-page {
    background: var(--bg-gradient);
    background-attachment: fixed;
    padding: 120px 20px;
    font-family: var(--font-family);
    color: var(--text-dark);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    overflow-x: hidden;
    line-height: 1.6;
    position: relative;
    min-height: 100vh;
}

.booking-page::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 20%, rgba(214, 125, 62, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(242, 237, 230, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 40% 60%, rgba(232, 227, 219, 0.2) 0%, transparent 50%);
    pointer-events: none;
}

.container {
    max-width: 700px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.booking-card {
    background: rgba(250, 247, 244, 0.9);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 48px;
    box-shadow: 
        var(--card-shadow),
        inset 0 1px 0 rgba(255, 255, 255, 0.5);
    border: var(--card-border);
}
.booking-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-dark);
    text-align: center;
    margin-bottom: 16px;
    line-height: 1.1;
    letter-spacing: -0.02em;
}

.booking-title span {
    color: var(--primary-color);
}

.booking-description {
    text-align: center;
    color: var(--text-light);
    margin-bottom: 40px;
    font-size: 1.2rem;
    line-height: 1.6;
    font-weight: 400;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}
.success-message { 
    text-align: center; 
    padding: 32px; 
    background: rgba(240, 253, 244, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(187, 247, 208, 0.3); 
    color: #166534; 
    border-radius: 16px; 
    box-shadow: 0 4px 16px rgba(22, 101, 52, 0.1);
}
.success-icon { 
    font-size: 3rem; 
    margin-bottom: 16px; 
    color: var(--primary-color); 
}
.success-message h3 { 
    font-size: 1.5rem; 
    font-weight: 700;
    margin-bottom: 12px; 
    color: var(--text-dark); 
}
.return-actions { 
    margin-top: 16px; 
    text-align: center; 
}
.btn-secondary { 
    display: inline-block; 
    padding: 16px 32px; 
    background: var(--primary-color); 
    color: var(--white); 
    text-decoration: none; 
    border-radius: 8px; 
    font-weight: 600; 
    font-size: 1rem;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px 0 rgba(214, 125, 62, 0.25);
}
.btn-secondary:hover { 
    background: var(--primary-color-dark);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px 0 rgba(214, 125, 62, 0.35);
}
.error-message { 
    text-align: center; 
    padding: 16px; 
    background: rgba(255, 241, 242, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 221, 224, 0.3); 
    color: #be123c; 
    border-radius: 12px; 
    margin-bottom: 20px;
    box-shadow: 0 4px 16px rgba(190, 18, 60, 0.1);
}
.form-section { 
    margin-bottom: 32px; 
}
.form-section h4 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary-color);
    padding-bottom: 12px;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--border-color);
}
.form-row { 
    display: grid; 
    grid-template-columns: 1fr 1fr; 
    gap: 20px; 
}
.form-group { 
    margin-bottom: 20px; 
}
.form-group label { 
    display: block; 
    font-weight: 600; 
    color: var(--text-light); 
    margin-bottom: 8px; 
    font-size: 0.95rem; 
}
.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 16px;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    font-size: 1rem;
    font-family: var(--font-family);
    transition: all 0.2s ease-in-out;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
}
.form-group select {
    color: var(--text-dark);
}
.form-group select:invalid {
    color: var(--text-light);
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(214, 125, 62, 0.2);
    background: rgba(255, 255, 255, 0.95);
}
.btn-submit {
    width: 100%;
    padding: 20px;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--white);
    background: var(--primary-color);
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px 0 rgba(214, 125, 62, 0.25);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.btn-submit:hover { 
    background: var(--primary-color-dark);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px 0 rgba(214, 125, 62, 0.35);
}
.terms-note { 
    text-align: center; 
    font-size: 0.9rem; 
    color: var(--text-light); 
    margin-top: 24px;
    line-height: 1.5;
}
@media (max-width: 768px) {
    .booking-page {
        padding: 80px 20px;
    }
    .form-row { 
        grid-template-columns: 1fr; 
        gap: 16px;
    }
    .booking-card { 
        padding: 32px; 
    }
    .booking-title { 
        font-size: 2rem; 
    }
    .booking-description {
        font-size: 1.1rem;
    }
    .form-group input, .form-group select, .form-group textarea {
        padding: 14px;
    }
    .btn-submit {
        padding: 18px;
        font-size: 1rem;
    }
}
</style>

<?php get_footer(); ?>
