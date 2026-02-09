<?php if (!defined('ABSPATH')) exit;



?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/mobile-optimization.css">



<div class="lte-signup-page">
    <div class="container">
        <div class="signup-card">
            <h2 class="signup-title">Complete Your <span>LTE Application</span></h2>
            
            <?php
            $form_submitted = false;
            $submission_success = false;
            $error_message = '';
            $order_id = null;
            
            // Get package data from sessionStorage via JavaScript
            $package_data = null;
            
            // FIXED: Process LTE application without creating account
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lte_signup_submit'])) {
                // Create upload directory if it doesn't exist
                $upload_base_dir = ABSPATH . 'wp-content/uploads/ID-Documents';
                $upload_dir = $upload_base_dir;
                
                if (!file_exists($upload_dir)) {
                    wp_mkdir_p($upload_dir);
                    // Create .htaccess to protect uploads
                    $htaccess_content = "Order Allow,Deny\nDeny from all";
                    file_put_contents($upload_base_dir . '/.htaccess', $htaccess_content);
                }
                
                $selected_package = json_decode(stripslashes($_POST['selected_package']), true);
                
                // PROMO CODE VALIDATION - NEW ADDITION
                $promo_code = isset($_POST['promo_code']) ? sanitize_text_field($_POST['promo_code']) : '';
                $promo_price = null;
                $promo_discount = 0;
                
                if ($promo_code && $selected_package) {
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'starcast_promo_deals';
                    
                    // For LTE, we need to match by package details since we don't have a fixed package_id
                    $promo = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $table_name WHERE promo_code = %s AND package_type = %s AND status = 'active'",
                        $promo_code,
                        'lte'
                    ));
                    
                    if ($promo) {
                        $now = current_time('mysql');
                        if ($now >= $promo->start_date && $now <= $promo->end_date) {
                            if ($promo->usage_limit == 0 || $promo->times_used < $promo->usage_limit) {
                                // Calculate promo price based on selected package
                                $original_price = floatval($selected_package['price']);
                                if ($promo->discount_type === 'percentage') {
                                    $promo_price = $original_price * (1 - $promo->discount_value / 100);
                                } else {
                                    $promo_price = $original_price - $promo->discount_value;
                                }
                                $promo_discount = $original_price - $promo_price;
                                
                                if (!session_id()) {
                                    session_start();
                                }
                                $_SESSION['active_promo'] = $promo;
                            }
                        }
                    }
                }
                // END PROMO CODE VALIDATION
                
                $contact_name = sanitize_text_field($_POST['contact_name']);
                $contact_surname = sanitize_text_field($_POST['contact_surname']);
                $contact_email = sanitize_email($_POST['contact_email']);
                $contact_phone = sanitize_text_field($_POST['contact_phone']);
                $id_number = sanitize_text_field($_POST['id_number']);
                $delivery_address = sanitize_text_field($_POST['delivery_address']);
                $postal_code = sanitize_text_field($_POST['postal_code']);
                
                $form_submitted = true;
                
                // Validate package data
                if (!$selected_package || !isset($selected_package['id']) || !isset($selected_package['price'])) {
                    $error_message = 'Invalid package selected. Please go back and select a package.';
                } else {
                    // Check if email already has a TRULY PENDING application (not approved/declined)
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
                                'value' => 'lte_subscription'
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
                        'status' => 'pending'
                    ));

                    if (!empty($existing_orders)) {
                        $existing_order = $existing_orders[0];
                        $error_message = 'You already have a pending LTE application (Order #' . $existing_order->get_id() . '). Please wait for it to be processed or contact support if you need assistance.';
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
                            else {
                                // Generate filename: name-surname-idnr.ext
                                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                                $safe_first_name = preg_replace('/[^a-zA-Z]/', '', $contact_name);
                                $safe_last_name = preg_replace('/[^a-zA-Z]/', '', $contact_surname);
                                $safe_id_number = preg_replace('/[^0-9]/', '', $id_number);
                                $unique_filename = $safe_first_name . '-' . $safe_last_name . '-' . $safe_id_number . '.' . $file_extension;
                                $target_path = $upload_dir . '/' . $unique_filename;
                                
                                // Handle file processing with auto-compression
                                $processed_file = process_uploaded_id_document($file, $target_path);
                                
                                if ($processed_file['success']) {
                                    $id_document_path = $processed_file['path'];
                                    $id_document_url = content_url('uploads/ID-Documents/' . $unique_filename);
                                    
                                    // Add compression note if file was compressed
                                    if ($processed_file['compressed']) {
                                        error_log('ID document compressed: Original size ' . formatBytes($file['size']) . ', Final size ' . formatBytes(filesize($id_document_path)));
                                    }
                                } else {
                                    $error_message = $processed_file['error'];
                                }
                            }
                        } else {
                            $error_message = 'ID document is required. Please upload a copy of your ID.';
                        }
                        
                        if (empty($error_message)) {
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
                                    'delivery_address' => $delivery_address,
                                    'postal_code' => $postal_code
                                );
                                
                                // Format package name: "MTN Fixed LTE 125Mbps"
                                $package_name = $selected_package['provider'] . ' Fixed LTE ' . 
                                              str_replace(['mbps', 'Mbps', 'MBPS'], 'Mbps', $selected_package['speed']);
                                
                                // Add the LTE package as a line item (manual)
                                $item = new WC_Order_Item_Product();
                                $item->set_name($package_name);
                                $item->set_quantity(1);
                                
                                // PROMO PRICING - MODIFIED
                                if ($promo_price !== null) {
                                    $item->set_subtotal($promo_price);
                                    $item->set_total($promo_price);
                                } else {
                                    $item->set_subtotal($selected_package['price']);
                                    $item->set_total($selected_package['price']);
                                }
                                // END PROMO PRICING
                                
                                $order->add_item($item);
                                
                                // Set order status to pending for manual review
                                $order->set_status('pending');
                                
                                // Store all data as order meta (NO ACCOUNT YET)
                                $order->update_meta_data('_customer_application_data', $customer_data);
                                $order->update_meta_data('_lte_package_data', $selected_package);
                                $order->update_meta_data('_lte_provider', $selected_package['provider']);
                                $order->update_meta_data('_delivery_address', $delivery_address);
                                $order->update_meta_data('_requires_manual_approval', 'yes');
                                $order->update_meta_data('_application_type', 'lte_subscription');
                                $order->update_meta_data('_customer_account_created', 'no'); // Track account status
                                
                                // ADD PROMO META
                                if ($promo_code && $promo_price !== null) {
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
                                $order->add_order_note('LTE application submitted. Awaiting admin approval before account creation.');
                                
                                // Get router data from sessionStorage if available
                                $router_data = null;
                                if (isset($_POST['selected_router']) && !empty($_POST['selected_router'])) {
                                    $router_data = json_decode(stripslashes($_POST['selected_router']), true);
                                }
                                
                                // Send ONLY confirmation emails (no account details)
                                send_lte_application_confirmation_emails($order, $customer_data, $selected_package, $router_data, $promo_code, $promo_discount);
                                
                                $submission_success = true;
                                
                            } catch (Exception $e) {
                                $error_message = 'There was an error processing your application: ' . $e->getMessage();
                                error_log('LTE signup error: ' . $e->getMessage());
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
                    <p>Thank you for choosing our LTE service! Your application has been received and assigned reference number <strong>#<?php echo $order_id; ?></strong>.</p>
                    
                    <div class="next-steps">
                        <h4>What happens next?</h4>
                        <ol>
                            <li>Our team will review your application within 24 hours</li>
                            <li>We'll check coverage availability in your area</li>
                            <li>You'll receive an email with account access once approved</li>
                            <li>Device delivery typically completed within 5-7 days</li>
                        </ol>
                    </div>
                    
                    <div class="contact-info">
                        <p>Questions? Contact us at <a href="mailto:starcast.tech@gmail.com">starcast.tech@gmail.com</a></p>
                    </div>
                </div>
                
                <div class="return-actions">
                    <a href="<?php echo home_url(); ?>" class="btn-secondary">Return to Home</a>
                    <a href="<?php echo home_url('/lte-5g'); ?>" class="btn-primary">Browse More Packages</a>
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
                <div id="package-summary" class="package-summary" style="display: none;">
                    <div id="promo-badge-container" class="promo-badge-container" style="display: none;"></div>
                    <div id="package-details" class="package-details">
                        <p class="package-provider" id="display-provider">Provider</p>
                        <div class="package-price-container">
                            <div id="original-price" class="original-price" style="display: none;">
                                <span id="display-original-price">R0</span>pm
                            </div>
                            <p class="package-price">
                                <span class="package-price-value" id="display-price">R0</span>pm
                            </p>
                            <div id="promo-savings" class="promo-savings" style="display: none;">
                                <span id="display-savings">Save R0</span>
                            </div>
                        </div>
                        <div id="promo-description" class="promo-description" style="display: none;">
                            <span id="display-promo-text"></span>
                        </div>
                        <div class="package-speed">
                            <div class="package-speed-item">
                                <span class="speed-icon upload">↑</span>
                                <span id="display-speed">Speed</span>
                            </div>
                            <div class="package-speed-item">
                                <span class="speed-icon download">📊</span>
                                <span id="display-data">Data</span>
                            </div>
                        </div>
                        <div id="router-summary" class="router-summary" style="display: none;">
                            <div class="router-divider"></div>
                            <h5>Router Selection</h5>
                            <div class="router-details">
                                <div class="router-option" id="display-router-option">Router Option</div>
                                <div class="router-price" id="display-router-price">R0</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="no-package-warning" class="warning-message" style="display: block;">
                    <p>No package selected. Please <a href="/lte-5g" class="link-highlight">return to the LTE page</a> and select a package first.</p>
                </div>
                
                <form method="POST" id="lte-signup-form" class="signup-form" enctype="multipart/form-data">
                    <input type="hidden" name="selected_package" id="selected_package" value="" />
                    <input type="hidden" name="selected_router" id="selected_router" value="" />
                    
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
                        
                        <div class="form-group delivery-group">
                            <label for="delivery_address">Delivery Address:</label>
                            <input type="text" name="delivery_address" id="delivery_address" required placeholder="House nr, Street, Suburb, City" />
                            <small style="color: #6b6355; font-size: 0.8rem; margin-top: 4px; display: block;">Format: House number, Street name, Suburb, City</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="postal_code">Postal Code:</label>
                            <input type="text" name="postal_code" id="postal_code" required placeholder="Auto-filled from address or enter manually" pattern="[0-9]{4}" maxlength="4" />
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
                    
                    <button type="submit" name="lte_signup_submit" id="submit-button" class="btn-submit" disabled>
                        SUBMIT APPLICATION
                    </button>
                    
                    <p class="terms-note">Your application will be reviewed for availability before activation. No charges will apply until your service is activated.</p>
                </form>

                <?php starcast_google_places_script('LTE', 'delivery_address'); ?>
            <?php } ?>
        </div>
    </div>
</div>

<?php
/**
 * Process uploaded ID document with auto-compression for large images
 */
function process_uploaded_id_document($file, $target_path) {
    $max_file_size = 5242880; // 5MB
    $max_image_size = 10485760; // 10MB - we'll compress images up to 10MB
    
    // For PDF files, just check size and move
    if ($file['type'] === 'application/pdf') {
        if ($file['size'] > $max_file_size) {
            return array(
                'success' => false,
                'error' => 'PDF file too large. Maximum size is 5MB. Please compress the PDF or scan at lower quality.'
            );
        }
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            return array(
                'success' => true,
                'path' => $target_path,
                'compressed' => false
            );
        } else {
            return array(
                'success' => false,
                'error' => 'Failed to upload PDF document. Please try again.'
            );
        }
    }
    
    // For images, handle compression
    if (in_array($file['type'], ['image/jpeg', 'image/jpg', 'image/png'])) {
        // If image is extremely large (over 10MB), reject it
        if ($file['size'] > $max_image_size) {
            return array(
                'success' => false,
                'error' => 'Image file too large. Maximum size is 10MB. Please resize the image first.'
            );
        }
        
        // If file is already under 5MB, just move it
        if ($file['size'] <= $max_file_size) {
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                return array(
                    'success' => true,
                    'path' => $target_path,
                    'compressed' => false
                );
            } else {
                return array(
                    'success' => false,
                    'error' => 'Failed to upload image. Please try again.'
                );
            }
        }
        
        // File is over 5MB but under 10MB - compress it
        $compressed_result = compress_image($file['tmp_name'], $target_path, $file['type'], $max_file_size);
        
        if ($compressed_result['success']) {
            return array(
                'success' => true,
                'path' => $target_path,
                'compressed' => true,
                'original_size' => $file['size'],
                'compressed_size' => filesize($target_path)
            );
        } else {
            return array(
                'success' => false,
                'error' => $compressed_result['error']
            );
        }
    }
    
    return array(
        'success' => false,
        'error' => 'Unsupported file type.'
    );
}

/**
 * Compress image to target file size
 */
function compress_image($source_path, $target_path, $mime_type, $max_size) {
    // Create image resource based on type
    switch ($mime_type) {
        case 'image/jpeg':
        case 'image/jpg':
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $source_image = imagecreatefrompng($source_path);
            break;
        default:
            return array('success' => false, 'error' => 'Unsupported image type for compression.');
    }
    
    if (!$source_image) {
        return array('success' => false, 'error' => 'Failed to process image. File may be corrupted.');
    }
    
    // Get original dimensions
    $original_width = imagesx($source_image);
    $original_height = imagesy($source_image);
    
    // Start with high quality and gradually reduce
    $quality = 85;
    $resize_factor = 1.0;
    $attempts = 0;
    $max_attempts = 10;
    
    do {
        // Calculate new dimensions if we need to resize
        $new_width = (int)($original_width * $resize_factor);
        $new_height = (int)($original_height * $resize_factor);
        
        // Create new image with calculated dimensions
        $compressed_image = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG
        if ($mime_type === 'image/png') {
            imagealphablending($compressed_image, false);
            imagesavealpha($compressed_image, true);
            $transparent = imagecolorallocatealpha($compressed_image, 255, 255, 255, 127);
            imagefill($compressed_image, 0, 0, $transparent);
        }
        
        // Resize image
        imagecopyresampled($compressed_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
        
        // Save compressed image to temporary location
        $temp_path = $target_path . '.tmp';
        
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                imagejpeg($compressed_image, $temp_path, $quality);
                break;
            case 'image/png':
                // PNG compression level (0-9, 9 is highest compression)
                $png_quality = (int)(9 - ($quality / 100) * 9);
                imagepng($compressed_image, $temp_path, $png_quality);
                break;
        }
        
        imagedestroy($compressed_image);
        
        // Check file size
        $compressed_size = filesize($temp_path);
        
        if ($compressed_size <= $max_size || $attempts >= $max_attempts) {
            // Success or max attempts reached
            rename($temp_path, $target_path);
            imagedestroy($source_image);
            
            if ($compressed_size <= $max_size) {
                return array(
                    'success' => true,
                    'compressed_size' => $compressed_size,
                    'quality' => $quality,
                    'resize_factor' => $resize_factor
                );
            } else {
                return array('success' => false, 'error' => 'Unable to compress image to required size. Please try a smaller image.');
            }
        }
        
        // Remove temp file and adjust compression
        unlink($temp_path);
        
        // Reduce quality more aggressively as attempts increase
        if ($attempts < 3) {
            $quality -= 10; // First few attempts: reduce quality
        } elseif ($attempts < 6) {
            $quality -= 15; // Middle attempts: reduce quality more
            $resize_factor *= 0.9; // Start resizing
        } else {
            $quality = max(30, $quality - 20); // Final attempts: aggressive compression
            $resize_factor *= 0.85; // More aggressive resizing
        }
        
        $attempts++;
        
    } while ($attempts < $max_attempts);
    
    imagedestroy($source_image);
    return array('success' => false, 'error' => 'Unable to compress image to required size after multiple attempts.');
}

/**
 * Format bytes to human readable format
 */
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * FIXED: Send ONLY confirmation emails (no account details) - SIMPLIFIED VERSION
 */
function send_lte_application_confirmation_emails($order, $customer_data, $package_data, $router_data = null, $promo_code = '', $promo_discount = 0) {
    $order_id = $order->get_id();
    $customer_name = $customer_data['first_name'];
    $customer_email = $customer_data['email'];
    
    // Format package name properly
    $package_title = $package_data['provider'] . ' Fixed LTE ' . 
                    str_replace(['mbps', 'Mbps', 'MBPS'], 'Mbps', $package_data['speed']);
    
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
    $admin_subject = $customer_data['first_name'] . ' ' . $customer_data['last_name'] . ' - New LTE Application #' . $order_id;
    $headers = array('Content-Type: text/html; charset=UTF-8', 'From: Starcast <info@localhost>');
    
    $admin_url = admin_url('admin.php?page=fibre-applications');
    
    // Calculate pricing details
    $original_price = floatval($package_data['price']);
    $final_price = $promo_discount > 0 ? ($original_price - $promo_discount) : $original_price;
    $router_price = 0;
    $router_description = 'Not selected';
    
    if ($router_data && isset($router_data['price'])) {
        $router_price = floatval($router_data['price']);
        $router_description = $router_data['description'] ?? $router_data['option'] ?? 'Router selected';
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
                <h2 style='color: #ffffff; margin: 0; font-size: 20px; font-weight: 600;'>New LTE Application</h2>
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
    
    $admin_message .= "<tr><td style='padding: 4px 0; font-weight: 500; color: #6b7280;'>Router:</td><td style='padding: 4px 0; color: #374151;'>{$router_description}";
    if ($router_price > 0) {
        $admin_message .= " (R" . number_format($router_price, 0) . " setup fee)";
    }
    $admin_message .= "</td></tr>";
    
    if ($router_price > 0) {
        $admin_message .= "<tr><td style='padding: 4px 0; font-weight: 500; color: #6b7280;'>Setup Cost:</td><td style='padding: 4px 0; color: #374151; font-weight: 600;'>R" . number_format($router_price, 0) . " (one-time)</td></tr>";
    }
    
    $admin_message .= "</table>
                </div>
                
                <!-- Delivery Address -->
                <div style='margin-bottom: 24px;'>
                    <h3 style='margin: 0 0 12px 0; color: #374151; font-size: 16px; font-weight: 600; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;'>Delivery Address</h3>
                    <p style='margin: 0; color: #374151; line-height: 1.5;'>
                        {$customer_data['delivery_address']}<br>
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
    $customer_subject = 'LTE Application Received - Reference #' . $order_id;
    
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
                <p style='font-size: 14px; color: #6b7280; margin: 0 0 24px 0;'>Thank you for your LTE application! We've received your details and are reviewing your request.</p>
                
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
                        <p style='margin: 4px 0;'>4. Device delivery will be arranged after payment setup</p>
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

.lte-signup-page {
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

.router-summary {
    margin-top: 16px;
    padding-top: 16px;
}

.router-divider {
    width: 100%;
    height: 1px;
    background: linear-gradient(to right, transparent, #e8e3db 20%, #e8e3db 80%, transparent);
    margin-bottom: 12px;
}

.router-summary h5 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2d2823;
    margin-bottom: 8px;
}

.router-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.router-option {
    font-size: 1rem;
    font-weight: 500;
    color: #6b6355;
}

.router-price {
    font-size: 1.2rem;
    font-weight: 700;
    color: #d67d3e;
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
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
.form-group { display: flex; flex-direction: column; gap: 10px; }
.form-group label { font-weight: 600; color: var(--text-dark); font-size: 0.95rem; }
.form-group input, .form-group textarea {
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
.form-group input:focus, .form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(214, 125, 62, 0.15);
}
.id-upload-container {
    border: 2px dashed var(--border-color);
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.3s, background-color 0.3s;
}
.id-upload-container:hover { border-color: var(--primary-color); background-color: #fefcfb; }
.upload-note { font-size: 0.9rem; color: var(--text-light); margin-top: 15px; }
.terms-section { background-color: var(--bg-light); padding: 16px; border-radius: 8px; }
.checkbox-container { display: flex; align-items: center; gap: 10px; font-size: 0.75rem; color: var(--text-light); }
.checkbox-container input[type="checkbox"] { accent-color: var(--primary-color); width: 20px; height: 20px; }
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
.terms-note { text-align: center; font-size: 0.9rem; color: var(--text-light); margin-top: 20px; }

/* Responsive */
@media (max-width: 768px) {
    .lte-signup-page { padding: 20px 15px; }
    .signup-card { padding: 25px; }
    .signup-title { font-size: 2rem; }
    .package-price { font-size: 2rem; }
    .package-speed { gap: 15px; }
    .form-row { grid-template-columns: 1fr; }
    .form-group input, .form-group textarea { padding: 12px 14px; }
    .id-upload-container { padding: 16px; }
}
@media (max-width: 480px) {
    .lte-signup-page { padding: 0; }
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
    const packageSummary = document.getElementById('package-summary');
    const noPackageWarning = document.getElementById('no-package-warning');
    const submitButton = document.getElementById('submit-button');
    const selectedPackageInput = document.getElementById('selected_package');
    
    // Try to get package data from sessionStorage
    let packageData = null;
    let routerData = null;
    
    try {
        const storedPackage = sessionStorage.getItem('selectedPackage');
        if (storedPackage) {
            packageData = JSON.parse(storedPackage);
            console.log('Package loaded from sessionStorage:', packageData);
        }
        
        const storedRouter = sessionStorage.getItem('selectedRouter');
        if (storedRouter) {
            routerData = JSON.parse(storedRouter);
            console.log('Router loaded from sessionStorage:', routerData);
        }
    } catch (error) {
        console.error('Error reading package/router from sessionStorage:', error);
    }
    
    // Helper function to format speed with Mbps
    function formatSpeed(speed) {
        if (!speed) return '';
        
        // Extract number from speed (e.g., "20" from "20Mbps" or "20")
        const speedNumber = speed.toString().replace(/[^0-9]/g, '');
        
        return speedNumber ? speedNumber + 'Mbps' : speed;
    }
    
    // Helper function to format data allowance with dynamic FUP
    function formatDataAllowance(data, aup) {
        if (!data) return '';
        
        // Convert common terms
        const dataLower = data.toString().toLowerCase();
        
        if (dataLower.includes('unlimited') || dataLower.includes('uncapped')) {
            // Use dynamic AUP if available, otherwise show as uncapped
            if (aup && aup !== '' && aup !== '0') {
                const aupValue = aup.toString();
                const aupNumber = aupValue.replace(/[^0-9]/g, '');
                return aupNumber ? aupNumber + 'GB FUP' : 'Uncapped';
            } else {
                return 'Uncapped';
            }
        }
        
        // If it already contains GB or has a number, return as is
        if (dataLower.includes('gb') || dataLower.includes('fup')) {
            return data;
        }
        
        // For other cases, assume it's GB
        const dataNumber = data.toString().replace(/[^0-9]/g, '');
        return dataNumber ? dataNumber + 'GB' : data;
    }
    
    // Function to update promo display
    function updatePromoDisplay(packageData, promoData = null) {
        const promoBadgeContainer = document.getElementById('promo-badge-container');
        const originalPriceEl = document.getElementById('original-price');
        const displayOriginalPrice = document.getElementById('display-original-price');
        const displayPrice = document.getElementById('display-price');
        const promoSavings = document.getElementById('promo-savings');
        const displaySavings = document.getElementById('display-savings');
        const promoDescription = document.getElementById('promo-description');
        const displayPromoText = document.getElementById('display-promo-text');
        
        if (promoData && promoData.promo_price && promoData.promo_price !== packageData.price) {
            // Show promo badge
            if (packageData.promo_badge_html) {
                promoBadgeContainer.innerHTML = packageData.promo_badge_html;
                promoBadgeContainer.style.display = 'block';
            }
            
            // Show original price crossed out
            displayOriginalPrice.textContent = 'R' + packageData.price;
            originalPriceEl.style.display = 'block';
            
            // Show promo price
            displayPrice.textContent = 'R' + promoData.promo_price;
            displayPrice.parentElement.classList.add('promo-active');
            
            // Show savings
            const savings = packageData.price - promoData.promo_price;
            displaySavings.textContent = 'Save R' + savings;
            promoSavings.style.display = 'block';
            
            // Show promo description
            if (packageData.promo_display_text || promoData.promo_text) {
                displayPromoText.textContent = packageData.promo_display_text || promoData.promo_text;
                promoDescription.style.display = 'block';
            }
        } else {
            // Hide promo elements
            promoBadgeContainer.style.display = 'none';
            originalPriceEl.style.display = 'none';
            displayPrice.textContent = 'R' + packageData.price;
            displayPrice.parentElement.classList.remove('promo-active');
            promoSavings.style.display = 'none';
            promoDescription.style.display = 'none';
        }
    }

    // Update UI based on package data
    if (packageData && packageData.id) {
        // Format package display properly
        const provider = packageData.provider || 'Unknown';
        const speed = formatSpeed(packageData.speed);
        const data = formatDataAllowance(packageData.data, packageData.aup);
        const price = packageData.price || '0';
        
        // Format the package title as required: "MTN Fixed LTE 125Mbps"
        const packageTitle = provider + ' Fixed LTE ' + speed;
        
        // Update display elements
        document.getElementById('display-provider').textContent = packageTitle;
        document.getElementById('display-speed').textContent = 'Up to ' + speed;
        document.getElementById('display-data').textContent = data;
        
        // Check for promo data and update display accordingly
        if (packageData.has_promo) {
            updatePromoDisplay(packageData, {
                promo_price: packageData.promo_price,
                promo_text: packageData.promo_display_text
            });
        } else {
            updatePromoDisplay(packageData);
        }
        
        // Store package data in hidden form field
        selectedPackageInput.value = JSON.stringify(packageData);
        
        // Store router data in hidden form field if available
        if (routerData) {
            const selectedRouterInput = document.getElementById('selected_router');
            if (selectedRouterInput) {
                selectedRouterInput.value = JSON.stringify(routerData);
            }
        }
        
        // Display router information if available
        if (routerData) {
            const routerSummary = document.getElementById('router-summary');
            const displayRouterOption = document.getElementById('display-router-option');
            const displayRouterPrice = document.getElementById('display-router-price');
            
            if (routerSummary && displayRouterOption && displayRouterPrice) {
                displayRouterOption.textContent = routerData.description || routerData.option;
                displayRouterPrice.textContent = routerData.price > 0 ? 'R' + routerData.price : 'Free';
                routerSummary.style.display = 'block';
            }
        }
        
        // Show/hide elements
        packageSummary.style.display = 'block';
        noPackageWarning.style.display = 'none';
        submitButton.disabled = false;
    } else {
        // No package selected
        packageSummary.style.display = 'none';
        noPackageWarning.style.display = 'block';
        submitButton.disabled = true;
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
    
    if (promoCode) {
        // For LTE, we validate promo without specific package
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
            // Show promo pending message immediately
            const packageSummary = document.querySelector('.package-summary');
            if (packageSummary && !document.querySelector('.promo-pending')) {
                const promoPending = document.createElement('div');
                promoPending.className = 'promo-pending';
                promoPending.innerHTML = `
                    <div style="background: #fef3c7; border: 1px solid #fbbf24; padding: 15px; border-radius: 8px; margin-top: 15px; text-align: center;">
                        <p style="margin: 0; color: #92400e; font-weight: 600;">
                            🎫 Promo Code: <span style="color: #f59e0b;">${promoCode}</span>
                        </p>
                        <p style="margin: 5px 0 0 0; color: #78716c; font-size: 0.9em;">
                            Discount will be applied after package selection
                        </p>
                    </div>
                `;
                packageSummary.appendChild(promoPending);
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
            
            // If package is selected, validate the promo
            if (packageData && packageData.price) {
                jQuery.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'validate_promo',
                        promo_code: promoCode,
                        package_id: packageData.id || 0,
                        package_type: 'lte'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update price display
                            const priceElement = document.getElementById('display-price');
                            if (priceElement) {
                                const originalPrice = packageData.price;
                                const discount = response.data.discount_type === 'percentage' 
                                    ? originalPrice * (response.data.discount_value / 100)
                                    : response.data.discount_value;
                                const promoPrice = originalPrice - discount;
                                
                                priceElement.innerHTML = `
                                    <span style="text-decoration: line-through; color: #6b7280; font-size: 0.9em;">R${originalPrice}</span>
                                    <span style="color: #10b981; font-size: 1.2em; margin-left: 8px;">R${promoPrice.toFixed(0)}</span>
                                `;
                            }
                            
                            // Replace pending message with success badge
                            const promoPending = document.querySelector('.promo-pending');
                            if (promoPending) {
                                promoPending.innerHTML = `
                                    <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 8px 16px; border-radius: 8px; text-align: center; margin-top: 10px;">
                                        <div style="font-size: 0.85em; opacity: 0.9;">PROMO APPLIED</div>
                                        <div style="font-size: 1.1em; font-weight: 600;">${promoCode}</div>
                                        <div style="font-size: 0.9em; margin-top: 4px;">Discount will be applied</div>
                                    </div>
                                `;
                            }
                        }
                    }
                });
            }
        }
    }
    // END PROMO DISPLAY CODE
});
</script>

<?php get_footer(); ?>