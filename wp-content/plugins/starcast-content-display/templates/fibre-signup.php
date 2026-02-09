<?php if (!defined('ABSPATH')) exit;



?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/mobile-optimization.css">


<div class="signup-page">
    <div class="container">
        <div class="signup-card">
            <h2 class="signup-title">Complete Your <span>Fibre Application</span></h2>
            
            <?php
            $form_submitted = false;
            $submission_success = false;
            $error_message = '';
            $order_id = null;
            
            // Get package ID from URL
            $package_id = isset($_GET['package_id']) ? intval($_GET['package_id']) : 0;
            
            // FIXED: Process fibre application without creating account
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup_submit'])) {
                // Create upload directory if it doesn't exist
                $upload_base_dir = ABSPATH . 'wp-content/uploads/ID-Documents';
                $upload_dir = $upload_base_dir;
                
                if (!file_exists($upload_dir)) {
                    wp_mkdir_p($upload_dir);
                    // Create .htaccess to protect uploads
                    $htaccess_content = "Order Allow,Deny\nDeny from all";
                    file_put_contents($upload_base_dir . '/.htaccess', $htaccess_content);
                }
                
                // PROMO CODE VALIDATION - NEW ADDITION
                $promo_code = isset($_POST['promo_code']) ? sanitize_text_field($_POST['promo_code']) : '';
                $promo_price = null;
                $promo_discount = 0;
                
                if ($promo_code) {
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'starcast_promo_deals';
                    
                    $package_id = intval($_POST['package_id']);
                    
                    $promo = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $table_name WHERE promo_code = %s AND package_id = %d AND package_type = %s AND status = 'active'",
                        $promo_code,
                        $package_id,
                        'fibre'
                    ));
                    
                    if ($promo) {
                        $now = current_time('mysql');
                        if ($now >= $promo->start_date && $now <= $promo->end_date) {
                            if ($promo->usage_limit == 0 || $promo->times_used < $promo->usage_limit) {
                                $promo_price = $promo->promo_price;
                                $promo_discount = $promo->original_price - $promo->promo_price;
                                
                                if (!session_id()) {
                                    session_start();
                                }
                                $_SESSION['active_promo'] = $promo;
                            }
                        }
                    }
                }
                // END PROMO CODE VALIDATION
                
                $package_id = intval($_POST['package_id']);
                $contact_name = sanitize_text_field($_POST['contact_name']);
                $contact_surname = sanitize_text_field($_POST['contact_surname']);
                $contact_email = sanitize_email($_POST['contact_email']);
                $contact_phone = sanitize_text_field($_POST['contact_phone']);
                $id_number = sanitize_text_field($_POST['id_number']);
                $installation_address = isset($_POST['installation_address'])
                    ? sanitize_text_field($_POST['installation_address'])
                    : '';
                $postal_code = sanitize_text_field($_POST['postal_code']);
                
                $form_submitted = true;
                
                // Validate package exists
                $package_post = get_post($package_id);
                if (!$package_post || $package_post->post_type !== 'fibre_packages') {
                    $error_message = 'Invalid package selected. Please go back and select a package.';
                } else {
                    // Check if email already has a TRULY PENDING application (not approved/declined)
                    // FIXED: Exclude placeholder/draft orders
                    $existing_orders = wc_get_orders(array(
                        'meta_query' => array(
                            'relation' => 'AND',
                            array(
                                'key' => '_customer_application_data',
                                'value' => $contact_email,
                                'compare' => 'LIKE'
                            ),
                            array(
                                'key' => '_application_type',
                                'value' => 'fibre_subscription'
                            ),
                            array(
                                'key' => '_requires_manual_approval',
                                'value' => 'yes',
                                'compare' => '='
                            ),
                            array(
                                'relation' => 'AND',
                                array(
                                    'key' => '_application_approved',
                                    'compare' => 'NOT EXISTS'
                                ),
                                array(
                                    'key' => '_application_declined',
                                    'compare' => 'NOT EXISTS'
                                )
                            )
                        ),
                        'status' => array('pending', 'on-hold', 'processing'), // Only real orders, not draft/placeholder
                        'type' => 'shop_order', // Exclude shop_order_placehold
                        'limit' => 10
                    ));

                    // Additional filter to ensure we have real orders with customer data
                    $has_pending_order = false;
                    $pending_order = null;
                    if (!empty($existing_orders)) {
                        foreach ($existing_orders as $order) {
                            $order_data = $order->get_meta('_customer_application_data');
                            // Only count it if it has actual customer data (not empty placeholder)
                            if (!empty($order_data) && !empty($order_data['email']) && !empty($order_data['first_name'])) {
                                $has_pending_order = true;
                                $pending_order = $order;
                                break;
                            }
                        }
                    }

                    if ($has_pending_order && $pending_order) {
                        $error_message = 'You already have a pending fibre application (Order #' . $pending_order->get_id() . '). Please wait for it to be processed or contact support if you need assistance.';
                    } else {
                        // Process ID document upload
                        $id_document_path = '';
                        $id_document_url = '';
                        
                        if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
                            $file = $_FILES['id_document'];
                            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                            
                            // Validate file type
                            if (!in_array($file['type'], $allowed_types)) {
                                $error_message = 'Invalid file type. Please upload a JPG, PNG, or PDF file.';
                            } 
                            // Validate file size (5MB max)
                            elseif ($file['size'] > 5242880) {
                                $error_message = 'File too large. Maximum size is 5MB.';
                            } 
                            else {
                                // Generate filename: name-surname-idnr.ext
                                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                                $safe_first_name = preg_replace('/[^a-zA-Z]/', '', $contact_name);
                                $safe_last_name = preg_replace('/[^a-zA-Z]/', '', $contact_surname);
                                $safe_id_number = preg_replace('/[^0-9]/', '', $id_number);
                                $unique_filename = $safe_first_name . '-' . $safe_last_name . '-' . $safe_id_number . '.' . $file_extension;
                                $target_path = $upload_dir . '/' . $unique_filename;
                                
                                // Move uploaded file
                                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                                    $id_document_path = $target_path;
                                    $id_document_url = content_url('uploads/ID-Documents/' . $unique_filename);
                                } else {
                                    $error_message = 'Failed to upload ID document. Please try again.';
                                }
                            }
                        } else {
                            $error_message = 'ID document is required. Please upload a copy of your ID.';
                        }
                        
                        if (empty($error_message)) {
                            // Get package details with promotional pricing
                            $package_regular_price = get_field('price', $package_id);
                            $package_effective_price = get_effective_price($package_id);
                            $package_price = $package_effective_price ?: $package_regular_price;
                            $package_download = get_field('download', $package_id);
                            $package_upload = get_field('upload', $package_id);
                            $provider_terms = wp_get_post_terms($package_id, 'fibre_provider');
                            $provider_name = !empty($provider_terms) ? $provider_terms[0]->name : 'Unknown';
                            
                            // Fix provider name display
                            if (strtolower($provider_name) === 'vumatel') {
                                $provider_name = 'Vuma';
                            }
                            
                            try {
                                // Create WooCommerce order WITHOUT creating customer account
                                $order = wc_create_order();
                                
                                if (!$order || is_wp_error($order)) {
                                    throw new Exception('Failed to create order');
                                }
                                
                                // NO CUSTOMER ACCOUNT CREATED - Store data as order meta only
                                $customer_data = array(
                                    'first_name' => $contact_name,
                                    'last_name' => $contact_surname,
                                    'email' => $contact_email,
                                    'phone' => $contact_phone,
                                    'id_number' => $id_number,
                                    'id_document_path' => $id_document_path,
                                    'id_document_url' => $id_document_url,
                                    'installation_address' => $installation_address,
                                    'postal_code' => $postal_code
                                );
                                
                                // Add the fibre package as a line item (manual)
                                $item = new WC_Order_Item_Product();
                                $item->set_name($provider_name . ' - ' . $package_download . '/' . $package_upload);
                                $item->set_quantity(1);
                                
                                // PROMO PRICING - MODIFIED
                                if ($promo_price !== null) {
                                    $item->set_subtotal($promo_price);
                                    $item->set_total($promo_price);
                                } else {
                                    $item->set_subtotal($package_price);
                                    $item->set_total($package_price);
                                }
                                // END PROMO PRICING
                                
                                $order->add_item($item);
                                
                                // Set order status to pending for manual review
                                $order->set_status('pending');
                                
                                // Store all data as order meta (NO ACCOUNT YET)
                                $order->update_meta_data('_customer_application_data', $customer_data);
                                $order->update_meta_data('_fibre_package_id', $package_id);
                                $order->update_meta_data('_fibre_provider', $provider_name);
                                $order->update_meta_data('_installation_address', $installation_address);
                                $order->update_meta_data('_requires_manual_approval', 'yes');
                                $order->update_meta_data('_application_type', 'fibre_subscription');
                                $order->update_meta_data('_customer_account_created', 'no');
                                
                                // ADD PROMO META
                                if ($promo_code && $promo_discount > 0) {
                                    $order->update_meta_data('_promo_code', $promo_code);
                                    $order->update_meta_data('_promo_discount', $promo_discount);
                                    $order->update_meta_data('_promo_price', $promo_price);
                                }
                                // END PROMO META
                                
                                // Calculate totals and save
                                $order->calculate_totals();
                                $order->save();
                                
                                $order_id = $order->get_id();
                                
                                // Add order note
                                $order->add_order_note('Fibre application submitted. Awaiting admin approval before account creation.');
                                
                                // Send ONLY confirmation emails (no account details)
                                send_application_confirmation_emails($order, $customer_data, $package_post, $promo_code, $promo_discount);
                                
                                $submission_success = true;
                                
                            } catch (Exception $e) {
                                $error_message = 'There was an error processing your application: ' . $e->getMessage();
                                error_log('Fibre signup error: ' . $e->getMessage());
                            }
                        } // End of error check
                    }
                }
            }
            
            if ($form_submitted && $submission_success) {
                // Show success message
                ?>
                <div class="success-message">
                    <div class="success-icon">✓</div>
                    <h3>Application Submitted Successfully!</h3>
                    <p>Thank you for choosing our fibre service! Your application has been received and assigned reference number <strong>#<?php echo $order_id; ?></strong>.</p>
                    
                    <div class="next-steps">
                        <h4>What happens next?</h4>
                        <ol>
                            <li>Our team will review your application within 24 hours</li>
                            <li>We'll check fibre availability in your area</li>
                            <li>You'll receive an email with account access once approved</li>
                            <li>Professional installation typically completed within 7 days</li>
                        </ol>
                    </div>
                    
                    <div class="contact-info">
                        <p>Questions? Contact us at <a href="mailto:starcast.tech@gmail.com">starcast.tech@gmail.com</a></p>
                    </div>
                </div>
                
                <div class="return-actions">
                    <a href="<?php echo home_url(); ?>" class="btn-secondary">Return to Home</a>
                    <a href="<?php echo home_url('/fibre'); ?>" class="btn-primary">Browse More Packages</a>
                </div>
                <?php
            } elseif ($form_submitted && !empty($error_message)) {
                // Show error message
                echo "<div class='error-message'>" . esc_html($error_message) . "</div>";
                // Include the form again so they can retry
            }
            
            if (!$form_submitted || !$submission_success) {
                // Show package summary and form
                ?>
                <div id="package-summary" class="package-summary" <?php echo $package_id ? 'style="display: block;"' : ''; ?>>
                    <div id="package-details" class="package-details">
                        <?php if ($package_id): 
                            $package_post = get_post($package_id);
                            if ($package_post && $package_post->post_type === 'fibre_packages'):
                                $regular_price = get_field('price', $package_id);
                                $download = get_field('download', $package_id);
                                $upload = get_field('upload', $package_id);
                                $provider_terms = wp_get_post_terms($package_id, 'fibre_provider');
                                $provider_name = !empty($provider_terms) ? $provider_terms[0]->name : 'Unknown';
                                
                                // Get promotional pricing
                                $has_promo = is_promo_active($package_id);
                                $promo_price = get_promo_price($package_id);
                                $effective_price = get_effective_price($package_id);
                                $display_price = $has_promo && $promo_price ? $promo_price : $regular_price;
                                
                                // Fix provider name display
                                if (strtolower($provider_name) === 'vumatel') {
                                    $provider_name = 'Vuma';
                                }
                        ?>
                            <?php 
                        // Try to get provider logo
                        $provider_logo = '';
                        if (!empty($provider_terms)) {
                            $provider_logo = function_exists('get_field') ? get_field('logo', $provider_terms[0]) : null;
                        }
                        ?>
                        <?php if ($provider_logo): ?>
                            <img src="<?php echo esc_url($provider_logo); ?>" alt="<?php echo esc_attr($provider_name); ?>" class="provider-logo" style="max-width: 160px; max-height: 60px; margin-bottom: 12px; object-fit: contain;">
                        <?php else: ?>
                            <p class="package-provider"><?php echo esc_html($provider_name); ?></p>
                        <?php endif; ?>
                        <p class="package-price">
                            <?php if ($has_promo && $promo_price && $promo_price != $regular_price): ?>
                                <span class="original-price" style="text-decoration: line-through; opacity: 0.6; color: #6b6355; font-size: 0.8em; font-weight: 400; margin-right: 8px;">R<?php echo esc_html($regular_price); ?></span>
                                <span class="package-price-value">R<?php echo esc_html($promo_price); ?></span>pm
                            <?php else: ?>
                                <span class="package-price-value">R<?php echo esc_html($display_price); ?></span>pm
                            <?php endif; ?>
                        </p>
                        <div class="package-speed">
                            <div class="package-speed-item">
                                <span class="speed-icon download">↓</span>
                                <span><?php echo esc_html($download); ?></span>
                            </div>
                            <div class="package-speed-item">
                                <span class="speed-icon upload">↑</span>
                                <span><?php echo esc_html($upload); ?></span>
                            </div>
                        </div>
                        <?php endif; endif; ?>
                    </div>
                </div>
                
                <div id="no-package-warning" class="warning-message" <?php echo !$package_id ? 'style="display: block;"' : ''; ?>>
                    <p>No package selected. Please <a href="/fibre" class="link-highlight">return to the fibre page</a> and select a package first.</p>
                </div>
                
                <form method="POST" id="signup-form" class="signup-form" enctype="multipart/form-data">
                    <input type="hidden" name="package_id" value="<?php echo $package_id; ?>" />
                    
                                            <div class="form-section">
                        <h4>Personal Information</h4>
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
                        
                        <div class="form-group">
                            <label for="contact_email">Email Address:</label>
                            <input type="email" name="contact_email" id="contact_email" required />
                        </div>
                        
                        <div class="form-row id-phone-row">
                            <div class="form-group">
                                <label for="id_number">ID Number:</label>
                                <input type="text" name="id_number" id="id_number" required pattern="[0-9]{13}" maxlength="13" placeholder="13-digit SA ID number" />
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_phone">Phone Number:</label>
                                <input type="tel" name="contact_phone" id="contact_phone" required />
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="id_document">ID Document:</label>
                            <div class="id-upload-container">
                                <input type="file" name="id_document" id="id_document" accept="image/*,.pdf" capture="environment" required />
                                <div class="upload-preview" id="upload-preview" style="display: none;">
                                    <img id="preview-image" src="" alt="ID Preview" style="max-width: 200px; max-height: 150px; margin-top: 10px;">
                                    <span id="preview-filename" style="display: block; margin-top: 5px; font-size: 0.85rem; color: #666;"></span>
                                </div>
                                <p class="upload-note">Take a photo or upload a copy of your ID (JPG, PNG, or PDF - Max 5MB)</p>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="installation_address">Installation Address:</label>
                            <input type="text" name="installation_address" id="installation_address" required placeholder="House nr, Street, Suburb, City" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95rem; background: white; color: #333 !important; display: block !important;" />
                            <small style="color: #6b6355; font-size: 0.8rem; margin-top: 4px; display: block;">Format: House number, Street name, Suburb, City</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="postal_code">Postal Code:</label>
                            <input type="text" name="postal_code" id="postal_code" required placeholder="Auto-filled from address or enter manually" pattern="[0-9]{4}" maxlength="4" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95rem; background: white; color: #333 !important; display: block !important;" />
                            <small style="color: #6b6355; font-size: 0.8rem; margin-top: 4px; display: block;">Will auto-complete from address above, or enter 4-digit postal code manually</small>
                        </div>
                    </div>
                    
                    <div class="terms-section">
                        <label class="checkbox-container">
                            <input type="checkbox" name="terms_accepted" required>
                            <span class="checkmark"></span>
                            I agree to the <a href="/terms-of-service" class="link-terms" target="_blank">Terms of Service</a> and understand that an account will be created upon approval.
                        </label>
                    </div>
                    
                    <button type="submit" name="signup_submit" id="submit-button" class="btn-submit" <?php echo !$package_id ? 'disabled' : ''; ?>>
                        SUBMIT APPLICATION
                    </button>
                    
                    <p class="terms-note">Your application will be reviewed for availability before activation. No charges will apply until your service is activated.</p>
                </form>

                <?php starcast_google_places_script('Signup', 'installation_address'); ?>
                
                <script>
                // Listen for Google Places address selection to extract postal code
                document.addEventListener('starcast_address_selected', function(event) {
                    if (event.detail && event.detail.place && event.detail.page === 'Signup') {
                        const place = event.detail.place;
                        const postalCodeField = document.getElementById('postal_code');
                        
                        if (place.address_components && postalCodeField) {
                            // Extract postal code from address components
                            for (let component of place.address_components) {
                                if (component.types.includes('postal_code')) {
                                    postalCodeField.value = component.long_name;
                                    postalCodeField.style.borderColor = '#28a745'; // Success green
                                    break;
                                }
                            }
                            
                            // If no postal code found, highlight field for manual entry
                            if (!postalCodeField.value) {
                                postalCodeField.style.borderColor = '#ffc107'; // Warning yellow
                                postalCodeField.placeholder = 'Enter 4-digit postal code manually';
                                postalCodeField.focus();
                            }
                        }
                    }
                });
                
                // Optional: Clear postal code when address field is manually edited
                document.getElementById('installation_address').addEventListener('input', function() {
                    const postalCodeField = document.getElementById('postal_code');
                    if (postalCodeField && !this.value.trim()) {
                        postalCodeField.value = '';
                        postalCodeField.style.borderColor = '';
                        postalCodeField.placeholder = 'Auto-filled from address or enter manually';
                    }
                });
                </script>
            <?php } ?>
        </div>
    </div>
</div>

<?php
/**
 * FIXED: Send ONLY confirmation emails (no account details)
 */
function send_application_confirmation_emails($order, $customer_data, $package_post, $promo_code = '', $promo_discount = 0) {
    $order_id = $order->get_id();
    $customer_name = $customer_data['first_name'];
    $customer_email = $customer_data['email'];
    $package_title = get_the_title($package_post);
    
    // Create secure download link for ID document
    $id_doc_link = '';
    if (!empty($customer_data['id_document_path']) && file_exists($customer_data['id_document_path'])) {
        $id_doc_link = add_query_arg(array(
            'download_id_doc' => '1',
            'order_id' => $order_id
        ), home_url());
    }
    
    // Admin notification email
    $admin_to = 'starcast.tech@gmail.com';
    $admin_subject = $customer_data['first_name'] . ' ' . $customer_data['last_name'] . ' - New Fibre Application #' . $order_id;
    $headers = array('Content-Type: text/html; charset=UTF-8', 'From: Starcast <info@localhost>');
    
    $admin_url = admin_url('admin.php?page=fibre-applications');
    
    // Get package pricing information from order items
    $order_items = $order->get_items();
    $package_price = 0;
    foreach ($order_items as $item) {
        $package_price = $item->get_total();
        break; // Get the first (and only) item
    }
    
    // If we have promo data, calculate original price
    if ($promo_discount > 0) {
        $original_price = $package_price + $promo_discount;
        $final_price = $package_price;
    } else {
        $original_price = $package_price;
        $final_price = $package_price;
    }
    
    $admin_message = "
    <html>
    <head>
        <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap' rel='stylesheet'>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; line-height: 1.6; color: #374151; margin: 0; padding: 0; background-color: #f8f9fa;'>
        <div style='width: 100%; background: #ffffff; overflow: hidden;'>
            
            <!-- Simple Header -->
            <div style='background: #4a90e2; padding: 20px; text-align: center;'>
                <h2 style='color: #ffffff; margin: 0; font-size: 20px; font-weight: 600;'>New Fibre Application</h2>
                <p style='color: rgba(255,255,255,0.9); margin: 5px 0 0 0; font-size: 14px;'>Order #{$order_id}</p>
            </div>
            
            <!-- Content -->
            <div style='padding: 20px;'>
                
                <!-- Customer Information -->
                <div style='margin-bottom: 24px;'>
                    <h3 style='margin: 0 0 12px 0; color: #374151; font-size: 16px; font-weight: 600; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;'>Customer Information</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 4px 0; font-weight: 500; color: #6b7280; width: 120px;'>Name:</td><td style='padding: 4px 0; color: #374151;'>{$customer_data['first_name']} {$customer_data['last_name']}</td></tr>
                        <tr><td style='padding: 4px 0; font-weight: 500; color: #6b7280;'>Email:</td><td style='padding: 4px 0; color: #374151;'>{$customer_data['email']}</td></tr>
                        <tr><td style='padding: 4px 0; font-weight: 500; color: #6b7280;'>Phone:</td><td style='padding: 4px 0; color: #374151;'>{$customer_data['phone']}</td></tr>
                        <tr><td style='padding: 4px 0; font-weight: 500; color: #6b7280;'>ID Number:</td><td style='padding: 4px 0; color: #374151;'>{$customer_data['id_number']}</td></tr>
                    </table>
                </div>
                
                <!-- Package Details -->
                <div style='margin-bottom: 24px;'>
                    <h3 style='margin: 0 0 12px 0; color: #374151; font-size: 16px; font-weight: 600; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;'>Package & Pricing</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td style='padding: 4px 0; font-weight: 500; color: #6b7280; width: 120px;'>Package:</td><td style='padding: 4px 0; color: #374151;'>{$package_title}</td></tr>";
    
    if ($promo_discount > 0) {
        $admin_message .= "<tr><td style='padding: 4px 0; font-weight: 500; color: #6b7280;'>Original Price:</td><td style='padding: 4px 0; color: #6b7280; text-decoration: line-through;'>R" . number_format($original_price, 0) . "/month</td></tr>";
        $admin_message .= "<tr><td style='padding: 4px 0; font-weight: 500; color: #6b7280;'>Promo Code:</td><td style='padding: 4px 0; color: #059669; font-weight: 600;'>{$promo_code} (-R" . number_format($promo_discount, 0) . ")</td></tr>";
        $admin_message .= "<tr><td style='padding: 4px 0; font-weight: 500; color: #6b7280;'>Monthly Price:</td><td style='padding: 4px 0; color: #059669; font-weight: 600;'>R" . number_format($final_price, 0) . "/month</td></tr>";
    } else {
        $admin_message .= "<tr><td style='padding: 4px 0; font-weight: 500; color: #6b7280;'>Monthly Price:</td><td style='padding: 4px 0; color: #374151; font-weight: 600;'>R" . number_format($final_price, 0) . "/month</td></tr>";
    }
    
    $admin_message .= "<tr><td style='padding: 4px 0; font-weight: 500; color: #6b7280;'>Setup Fee:</td><td style='padding: 4px 0; color: #374151;'>R249 (one-time)</td></tr>";
    
    $admin_message .= "</table>
                </div>
                
                <!-- Installation Address -->
                <div style='margin-bottom: 24px;'>
                    <h3 style='margin: 0 0 12px 0; color: #374151; font-size: 16px; font-weight: 600; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;'>Installation Address</h3>
                    <p style='margin: 0; color: #374151; line-height: 1.5;'>
                        {$customer_data['installation_address']}<br>
                        <strong>Postal Code:</strong> {$customer_data['postal_code']}
                    </p>
                </div>";
    
    if ($id_doc_link) {
        $admin_message .= "
                <!-- ID Document -->
                <div style='margin-bottom: 24px;'>
                    <h3 style='margin: 0 0 12px 0; color: #374151; font-size: 16px; font-weight: 600; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;'>ID Document</h3>
                    <a href='{$id_doc_link}' style='display: inline-block; background: #4a90e2; color: #ffffff; padding: 10px 16px; text-decoration: none; border-radius: 6px; font-weight: 500;'>Download ID Document</a>
                </div>";
    }
    
    $admin_message .= "
                <!-- Action Required -->
                <div style='background: #f8f9fa; padding: 16px; border-radius: 6px; border-left: 4px solid #4a90e2; margin-top: 24px;'>
                    <p style='margin: 0; color: #374151; font-weight: 500;'>Please review this application and approve or decline in the admin panel.</p>
                    <a href='{$admin_url}' style='display: inline-block; margin-top: 12px; background: #4a90e2; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: 500;'>Review Application</a>
                </div>
            </div>
            
            <!-- Footer -->
            <div style='background: #f8f9fa; padding: 16px; text-align: center; border-top: 1px solid #e5e7eb;'>
                <p style='margin: 0; font-size: 12px; color: #6b7280;'>Starcast Technologies Admin Portal</p>
            </div>
        </div>
    </body>
    </html>";
    
    wp_mail($admin_to, $admin_subject, $admin_message, $headers);
    
    // Customer confirmation email (NO ACCOUNT DETAILS) - SIMPLIFIED
    $customer_subject = 'Fibre Application Received - Reference #' . $order_id;
    
    $customer_message = "
    <html>
    <head>
        <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap' rel='stylesheet'>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='font-family: \"Inter\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; line-height: 1.6; color: #374151; margin: 0; padding: 0; background-color: #f8f9fa;'>
        <div style='width: 100%; background: #ffffff; overflow: hidden;'>
            
            <!-- Simple Header -->
            <div style='background: #4a90e2; padding: 20px; text-align: center;'>
                <h2 style='color: #ffffff; margin: 0; font-size: 20px; font-weight: 600;'>Application Received</h2>
                <p style='color: rgba(255,255,255,0.9); margin: 5px 0 0 0; font-size: 14px;'>Reference #{$order_id}</p>
            </div>
            
            <!-- Content -->
            <div style='padding: 20px;'>
                <p style='font-size: 16px; color: #374151; margin: 0 0 8px 0; font-weight: 600;'>Hi {$customer_name},</p>
                <p style='font-size: 14px; color: #6b7280; margin: 0 0 24px 0;'>Thank you for your fibre application! We've received your details and are reviewing your request.</p>
                
                <!-- Application Status -->
                <div style='background: #f0f9ff; padding: 16px; border-radius: 6px; margin: 16px 0; border-left: 4px solid #4a90e2;'>
                    <h3 style='margin: 0 0 8px 0; color: #374151; font-size: 14px; font-weight: 600;'>Application Status</h3>
                    <p style='margin: 0; font-size: 13px; color: #6b7280;'><strong>Reference:</strong> #{$order_id}</p>
                    <p style='margin: 0; font-size: 13px; color: #6b7280;'><strong>Status:</strong> Under Review</p>
                    <p style='margin: 0; font-size: 13px; color: #6b7280;'><strong>Package:</strong> {$package_title}</p>";
    
    // ADD PROMO INFO TO CUSTOMER EMAIL
    if (!empty($promo_code) && $promo_discount > 0) {
        $customer_message .= "<p style='margin: 0; font-size: 13px; color: #6b7280;'><strong>Promo Applied:</strong> {$promo_code} - Save R" . number_format($promo_discount, 0) . "</p>";
    }
    
    $customer_message .= "
                </div>
                
                <!-- Next Steps -->
                <div style='background: #f8f9fa; padding: 16px; border-radius: 6px; margin: 16px 0;'>
                    <h3 style='margin: 0 0 8px 0; color: #374151; font-size: 14px; font-weight: 600;'>What happens next:</h3>
                    <div style='color: #6b7280; font-size: 13px; line-height: 1.5;'>
                        <p style='margin: 4px 0;'>1. Our team will review your application</p>
                        <p style='margin: 4px 0;'>2. We'll check service availability in your area</p>
                        <p style='margin: 4px 0;'>3. You'll receive an email with account access once approved</p>
                        <p style='margin: 4px 0;'>4. Professional installation will be scheduled after payment setup</p>
                    </div>
                </div>
                
                <!-- Contact Info -->
                <div style='text-align: center; margin-top: 20px;'>
                    <p style='margin: 0; font-size: 13px; color: #6b7280;'>Questions? Contact us at <a href='mailto:starcast.tech@gmail.com' style='color: #4a90e2; text-decoration: none;'>starcast.tech@gmail.com</a></p>
                </div>
            </div>
            
            <!-- Footer -->
            <div style='background: #f8f9fa; padding: 16px; text-align: center; border-top: 1px solid #e5e7eb;'>
                <p style='margin: 0; font-size: 12px; color: #6b7280;'>Best regards, The Starcast Team</p>
            </div>
        </div>
    </body>
    </html>";
    
    wp_mail($customer_email, $customer_subject, $customer_message, $headers);
}
?>

<style>
:root {
    --primary-color: #d67d3e;
    --primary-color-dark: #c56d31;
    --text-dark: #2d2823;
    --text-light: #6b6355;
    --bg-light: #faf7f4;
    --border-color: #ede8e1;
    --white: #ffffff;
    --font-family-primary: 'Poppins', sans-serif;
    --font-family-secondary: 'Inter', sans-serif;
}

.signup-page {
    background: linear-gradient(135deg, #f0ebe3 0%, #e8dfd5 30%, #ede4d8 70%, #f0ebe3 100%);
    background-attachment: fixed;
    padding: 40px 20px;
    font-family: var(--font-family-secondary);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.container {
    max-width: 700px;
    margin: 0 auto;
    width: 100%;
}

.signup-card {
    background: #ffffff;
    backdrop-filter: blur(20px);
    border-radius: 16px;
    box-shadow: 
      0 8px 32px rgba(0, 0, 0, 0.3),
      inset 0 1px 0 rgba(255, 255, 255, 0.8);
    border: 1px solid #000000;
    padding: 40px;
    width: 100%;
    box-sizing: border-box;
}

.signup-title {
    font-family: var(--font-family-primary);
    font-weight: 800;
    font-size: 2.4rem;
    text-align: center;
    color: var(--text-dark);
    margin-bottom: 30px;
    line-height: 1.2;
}

.signup-title span {
    color: var(--primary-color);
}

/* Messages */
.success-message, .error-message, .warning-message {
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 30px;
    text-align: center;
}
.success-message { background-color: #e8f5e9; border: 1px solid #a5d6a7; color: #388e3c; }
.error-message { background-color: #ffebee; border: 1px solid #ef9a9a; color: #d32f2f; }
.warning-message { background-color: #fffde7; border: 1px solid #fff59d; color: #fbc02d; }
.success-message h3 { font-family: var(--font-family-primary); font-weight: 700; font-size: 1.5rem; margin-bottom: 15px; }

/* Package Summary */
.package-summary {
    background: #ffffff;
    backdrop-filter: blur(20px);
    border-radius: 16px;
    box-shadow: 
      0 8px 32px rgba(0, 0, 0, 0.3),
      inset 0 1px 0 rgba(255, 255, 255, 0.8);
    border: 1px solid #000000;
    padding: 20px;
    margin-bottom: 25px;
    text-align: center;
}
.package-provider { font-size: 1.3rem; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
.package-price { font-size: 2rem; font-weight: 800; color: #4a90e2; margin: 8px 0; }
.package-speed { 
    display: flex; 
    flex-direction: row; 
    justify-content: center; 
    gap: 20px; 
    margin: 8px 0;
}
.package-speed-item { 
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 1rem; 
    color: var(--text-dark);
    font-weight: 600;
}
.speed-icon {
    font-size: 1.1rem;
    font-weight: 700;
}
.speed-icon.download { color: #4a90e2; }
.speed-icon.upload { color: #7ed321; }

/* Form */
.signup-form { display: grid; gap: 20px; }
.form-section h4 {
    font-family: var(--font-family-primary);
    font-weight: 700;
    font-size: 1.2rem;
    color: var(--text-dark);
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--border-color);
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-weight: 600; color: var(--text-dark); font-size: 0.9rem; }
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px 16px;
    border-radius: 8px;
    border: 1px solid #000000;
    background-color: #fff;
    font-family: var(--font-family-secondary);
    font-size: 0.95rem;
    color: var(--text-dark);
    transition: border-color 0.3s, box-shadow 0.3s;
    box-sizing: border-box;
}
.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(214, 125, 62, 0.15);
}
.id-upload-container {
    border: 2px dashed var(--border-color);
    border-radius: 10px;
    padding: 25px;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.3s, background-color 0.3s;
}
.id-upload-container:hover { border-color: var(--primary-color); background-color: #fefcfb; }
.upload-note { font-size: 0.85rem; color: var(--text-light); margin-top: 12px; }
.terms-section { background-color: var(--bg-light); padding: 16px; border-radius: 8px; }
.checkbox-container {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.75rem;
    color: var(--text-light);
}
.checkbox-container .link-terms {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}
.checkbox-container .link-terms:hover {
    text-decoration: underline;
}
.checkbox-container input[type="checkbox"] { accent-color: var(--primary-color); width: 18px; height: 18px; }
.btn-submit {
    width: 100%;
    padding: 16px;
    font-size: 1rem;
    font-weight: 700;
    background: var(--primary-color);
    color: var(--white);
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: background-color 0.3s, transform 0.2s;
    box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
}
.btn-submit:hover:not(:disabled) { background-color: var(--primary-color-dark); transform: translateY(-3px); }
.btn-submit:disabled { background-color: #ccc; cursor: not-allowed; box-shadow: none; }
.terms-note {
    text-align: center;
    font-size: 0.95rem; /* Consistent font size */
    color: var(--text-light); /* Consistent color */
    margin-top: 15px;
}

/* Responsive */
@media (max-width: 768px) {
    .signup-page { padding: 20px 15px; }
    .signup-card { padding: 25px; }
    .signup-title { font-size: 2rem; }
    .package-price { font-size: 2rem; }
    .package-speed { gap: 15px; }
    .form-row { grid-template-columns: 1fr; }
    .form-group input, .form-group textarea { padding: 12px 14px; }
    .id-upload-container { padding: 16px; }
}
@media (max-width: 480px) {
    .signup-page { padding: 0; }
    .signup-card { 
        padding: 20px; 
        border-radius: 0;
        border: none;
        box-shadow: none;
        width: 100vw;
        max-width: 100vw;
        margin: 0;
    }
    .container {
        max-width: 100vw;
        padding: 0;
        margin: 0;
    }
    .signup-title { font-size: 1.6rem; margin-bottom: 20px; }
    .package-summary { padding: 16px; margin-bottom: 20px; }
    .package-price { font-size: 1.8rem; }
    .form-section h4 { font-size: 1.1rem; }
    .form-group input, .form-group textarea { padding: 12px 14px; }
    .btn-submit { padding: 14px; font-size: 0.95rem; }
    .checkbox-container { font-size: 0.7rem; gap: 8px; }
    .terms-section { padding: 14px; }
    .form-row { grid-template-columns: 1fr 1fr; gap: 15px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {

    const packageId = <?php echo $package_id ? $package_id : 'null'; ?>;
    const packageSummary = document.getElementById('package-summary');
    const noPackageWarning = document.getElementById('no-package-warning');
    const submitButton = document.getElementById('submit-button');
    
    // If package ID exists in PHP, we're good to go
    if (packageId) {
        if (packageSummary) packageSummary.style.display = 'block';
        if (noPackageWarning) noPackageWarning.style.display = 'none';
        if (submitButton) submitButton.disabled = false;
    } else {
        // No package selected
        if (packageSummary) packageSummary.style.display = 'none';
        if (noPackageWarning) noPackageWarning.style.display = 'block';
        if (submitButton) submitButton.disabled = true;
    }
    
    // Handle file upload preview
    const fileInput = document.getElementById('id_document');
    const uploadPreview = document.getElementById('upload-preview');
    const previewImage = document.getElementById('preview-image');
    const previewFilename = document.getElementById('preview-filename');
    
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                // Show filename
                previewFilename.textContent = file.name;
                uploadPreview.style.display = 'block';
                
                // Show image preview if it's an image
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        previewImage.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    // Hide image preview for PDFs
                    previewImage.style.display = 'none';
                }
            } else {
                uploadPreview.style.display = 'none';
            }
        });
    }
    
    // PROMO DISPLAY CODE - NEW ADDITION
    const urlParams = new URLSearchParams(window.location.search);
    const promoCode = urlParams.get('promo');
    
    if (promoCode && packageId) {
        // Include jQuery if not already loaded
        if (typeof jQuery === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
            script.onload = function() {
                validatePromo();
            };
            document.head.appendChild(script);
        } else {
            validatePromo();
        }
        
        function validatePromo() {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'validate_promo',
                    promo_code: promoCode,
                    package_id: packageId,
                    package_type: 'fibre'
                },
                success: function(response) {
                    if (response.success) {
                        // Update price display
                        const priceElement = document.querySelector('.package-price-value');
                        if (priceElement) {
                            const originalPrice = response.data.original_price;
                            const promoPrice = response.data.promo_price;
                            const discount = response.data.discount;
                            
                            priceElement.innerHTML = `
                                <span style="text-decoration: line-through; text-decoration-color: #9ca3af; color: #9ca3af; font-size: 0.8em; font-weight: normal;">R${originalPrice}</span>
                                <span style="color: #d67d3e; font-size: 1.2em; margin-left: 8px;">R${promoPrice}</span>
                            `;
                        }
                        
                        // Add promo badge
                        const packageSummary = document.querySelector('.package-summary');
                        if (packageSummary && !document.querySelector('.promo-badge')) {
                            const promoBadge = document.createElement('div');
                            promoBadge.className = 'promo-badge';
                            
                            // Get discount percentage for display
                            const originalPrice = response.data.original_price;
                            const promoPrice = response.data.promo_price;
                            const discount = response.data.discount;
                            const discountPercentage = Math.round((discount / originalPrice) * 100);
                            
                            promoBadge.innerHTML = `
                                <div style="background: linear-gradient(135deg, #d67d3e, #c56d31); color: white; padding: 12px 16px; border-radius: 12px; text-align: center; margin-top: 15px; box-shadow: 0 4px 12px rgba(214, 125, 62, 0.3);">
                                    <div style="font-size: 0.85em; opacity: 0.9;">SPECIAL OFFER</div>
                                    <div style="font-size: 1.2em; font-weight: 700; margin-top: 4px;">${promoCode}</div>
                                    <div style="display: flex; justify-content: center; gap: 10px; margin-top: 8px;">
                                        <div style="background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 20px; font-size: 0.9em;">Save R${discount.toFixed(2)}</div>
                                        <div style="background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 20px; font-size: 0.9em;">${discountPercentage}% OFF</div>
                                    </div>
                                </div>
                            `;
                            packageSummary.appendChild(promoBadge);
                        }
                        
                        // Add hidden promo field to form
                        const form = document.querySelector('form');
                        if (form && !document.querySelector('input[name="promo_code"]')) {
                            const promoInput = document.createElement('input');
                            promoInput.type = 'hidden';
                            promoInput.name = 'promo_code';
                            promoInput.value = promoCode;
                            form.appendChild(promoInput);
                        }
                    } else {
                        // Handle invalid promo code
                        console.error('Promo validation failed:', response.data);
                        
                        // Show error message
                        const packageSummary = document.querySelector('.package-summary');
                        if (packageSummary && !document.querySelector('.promo-error')) {
                            const promoError = document.createElement('div');
                            promoError.className = 'promo-error';
                            promoError.innerHTML = `
                                <div style="background: rgba(251, 113, 133, 0.1); backdrop-filter: blur(10px); border: 1px solid rgba(239, 68, 68, 0.2); color: #dc2626; padding: 12px 16px; border-radius: 12px; margin-top: 15px; text-align: center;">
                                    <p style="margin: 0; font-weight: 500;">Invalid promo code: ${promoCode}</p>
                                    <p style="margin: 5px 0 0 0; font-size: 0.9em;">${response.data || 'This code may be expired or not applicable to this package.'}</p>
                                </div>
                            `;
                            packageSummary.appendChild(promoError);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Promo validation error:', error);
                    
                    // Show error message
                    const packageSummary = document.querySelector('.package-summary');
                    if (packageSummary && !document.querySelector('.promo-error')) {
                        const promoError = document.createElement('div');
                        promoError.className = 'promo-error';
                        promoError.innerHTML = `
                            <div style="background: rgba(251, 113, 133, 0.1); backdrop-filter: blur(10px); border: 1px solid rgba(239, 68, 68, 0.2); color: #dc2626; padding: 12px 16px; border-radius: 12px; margin-top: 15px; text-align: center;">
                                <p style="margin: 0; font-weight: 500;">Error validating promo code</p>
                                <p style="margin: 5px 0 0 0; font-size: 0.9em;">Please try again or contact support.</p>
                            </div>
                        `;
                        packageSummary.appendChild(promoError);
                    }
                }
            });
        }
    }
    // END PROMO DISPLAY CODE
});
</script>

<?php get_footer(); ?>
