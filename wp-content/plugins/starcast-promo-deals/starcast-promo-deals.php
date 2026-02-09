<?php
/**
 * Plugin Name: Starcast Promo Deals Manager
 * Description: Create and manage promotional deals for fibre and LTE packages with shareable links
 * Version: 1.0
 * Author: Starcast Technologies
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Create database table on plugin activation
register_activation_hook(__FILE__, 'spdm_create_promo_table');
function spdm_create_promo_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'starcast_promo_deals';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        promo_code varchar(50) NOT NULL,
        promo_name varchar(255) NOT NULL,
        package_type varchar(20) NOT NULL,
        package_id mediumint(9) NOT NULL,
        original_price decimal(10,2) NOT NULL,
        promo_price decimal(10,2) NOT NULL,
        discount_type varchar(20) NOT NULL,
        discount_value decimal(10,2) NOT NULL,
        start_date datetime DEFAULT '0000-00-00 00:00:00',
        end_date datetime DEFAULT '0000-00-00 00:00:00',
        usage_limit int DEFAULT 0,
        times_used int DEFAULT 0,
        status varchar(20) DEFAULT 'active',
        created_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY promo_code (promo_code)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Add admin menu
add_action('admin_menu', 'spdm_add_admin_menu');
function spdm_add_admin_menu() {
    add_menu_page(
        'Promo Deals',
        'Promo Deals',
        'manage_options',
        'starcast-promo-deals',
        'spdm_admin_page',
        'dashicons-megaphone',
        25
    );
    
    add_submenu_page(
        'starcast-promo-deals',
        'All Promos',
        'All Promos',
        'manage_options',
        'starcast-promo-deals',
        'spdm_admin_page'
    );
    
    add_submenu_page(
        'starcast-promo-deals',
        'Create New Promo',
        'Create New Promo',
        'manage_options',
        'starcast-create-promo',
        'spdm_create_promo_page'
    );
}

// Main admin page - list all promos
function spdm_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'starcast_promo_deals';
    
    // Handle actions
    if (isset($_GET['action']) && isset($_GET['promo_id'])) {
        $promo_id = intval($_GET['promo_id']);
        
        if ($_GET['action'] === 'delete' && wp_verify_nonce($_GET['_wpnonce'], 'delete_promo')) {
            $wpdb->delete($table_name, array('id' => $promo_id));
            echo '<div class="notice notice-success"><p>Promo deleted successfully!</p></div>';
        }
        
        if ($_GET['action'] === 'deactivate' && wp_verify_nonce($_GET['_wpnonce'], 'deactivate_promo')) {
            $wpdb->update($table_name, array('status' => 'inactive'), array('id' => $promo_id));
            echo '<div class="notice notice-success"><p>Promo deactivated!</p></div>';
        }
        
        if ($_GET['action'] === 'activate' && wp_verify_nonce($_GET['_wpnonce'], 'activate_promo')) {
            $wpdb->update($table_name, array('status' => 'active'), array('id' => $promo_id));
            echo '<div class="notice notice-success"><p>Promo activated!</p></div>';
        }
    }
    
    // Get all promos
    $promos = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_date DESC");
    ?>
    
    <div class="wrap">
        <h1 class="wp-heading-inline">Promo Deals Manager</h1>
        <a href="<?php echo admin_url('admin.php?page=starcast-create-promo'); ?>" class="page-title-action">Create New Promo</a>
        
        <div style="margin: 20px 0;">
            <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin-top: 0;">Quick Stats</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 20px; background: #f0f9ff; border-radius: 8px;">
                        <div style="font-size: 2em; font-weight: bold; color: #3b82f6;"><?php echo count($promos); ?></div>
                        <div style="color: #64748b;">Total Promos</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #f0fdf4; border-radius: 8px;">
                        <div style="font-size: 2em; font-weight: bold; color: #10b981;">
                            <?php echo count(array_filter($promos, function($p) { return $p->status === 'active'; })); ?>
                        </div>
                        <div style="color: #64748b;">Active Promos</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #fef3c7; border-radius: 8px;">
                        <div style="font-size: 2em; font-weight: bold; color: #f59e0b;">
                            <?php echo array_sum(array_column($promos, 'times_used')); ?>
                        </div>
                        <div style="color: #64748b;">Total Uses</div>
                    </div>
                </div>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Promo Code</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Package</th>
                    <th>Discount</th>
                    <th>Valid Period</th>
                    <th>Usage</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($promos)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px;">
                            No promos created yet. <a href="<?php echo admin_url('admin.php?page=starcast-create-promo'); ?>">Create your first promo</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($promos as $promo): 
                        // Get package details
                        $package_title = 'N/A';
                        if ($promo->package_type === 'fibre') {
                            $package = get_post($promo->package_id);
                            if ($package) {
                                $package_title = get_the_title($package);
                            }
                        } else {
                            // For LTE, we'll need to match from the stored package data
                            $package_title = 'LTE Package #' . $promo->package_id;
                        }
                        
                        // Calculate discount display
                        $discount_display = $promo->discount_type === 'percentage' 
                            ? $promo->discount_value . '%' 
                            : 'R' . number_format($promo->discount_value, 2);
                            
                        // Format dates
                        $start_date = date('M j, Y', strtotime($promo->start_date));
                        $end_date = date('M j, Y', strtotime($promo->end_date));
                        $is_expired = strtotime($promo->end_date) < time();
                        
                        // Generate promo link
                        $promo_link = $promo->package_type === 'fibre' 
                            ? home_url('/signup/?package_id=' . $promo->package_id . '&promo=' . $promo->promo_code)
                            : home_url('/lte-signup/?promo=' . $promo->promo_code);
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($promo->promo_code); ?></strong>
                            <div class="row-actions">
                                <span class="copy">
                                    <a href="#" onclick="copyPromoLink('<?php echo esc_js($promo_link); ?>'); return false;">Copy Link</a>
                                </span>
                            </div>
                        </td>
                        <td><?php echo esc_html($promo->promo_name); ?></td>
                        <td>
                            <span class="badge" style="background: <?php echo $promo->package_type === 'fibre' ? '#3b82f6' : '#f59e0b'; ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.85em;">
                                <?php echo ucfirst($promo->package_type); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($package_title); ?></td>
                        <td>
                            <strong><?php echo $discount_display; ?></strong><br>
                            <small>R<?php echo number_format($promo->original_price, 2); ?> → R<?php echo number_format($promo->promo_price, 2); ?></small>
                        </td>
                        <td>
                            <?php echo $start_date; ?><br>to <?php echo $end_date; ?>
                            <?php if ($is_expired): ?>
                                <span style="color: #dc2626; font-size: 0.85em;">(Expired)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $promo->times_used; ?>
                            <?php if ($promo->usage_limit > 0): ?>
                                / <?php echo $promo->usage_limit; ?>
                            <?php else: ?>
                                <small>(Unlimited)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($promo->status === 'active' && !$is_expired): ?>
                                <span style="color: #10b981;">● Active</span>
                            <?php else: ?>
                                <span style="color: #dc2626;">● Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <?php if ($promo->status === 'active'): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=starcast-promo-deals&action=deactivate&promo_id=' . $promo->id), 'deactivate_promo'); ?>" 
                                       class="button button-small">Deactivate</a>
                                <?php else: ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=starcast-promo-deals&action=activate&promo_id=' . $promo->id), 'activate_promo'); ?>" 
                                       class="button button-small">Activate</a>
                                <?php endif; ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=starcast-promo-deals&action=delete&promo_id=' . $promo->id), 'delete_promo'); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('Are you sure you want to delete this promo?')">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    function copyPromoLink(link) {
        navigator.clipboard.writeText(link).then(function() {
            alert('Promo link copied to clipboard!');
        }, function(err) {
            prompt('Copy this link:', link);
        });
    }
    </script>
    
    <style>
    .badge {
        display: inline-block;
        font-weight: 500;
    }
    </style>
    <?php
}

// Create new promo page
function spdm_create_promo_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'starcast_promo_deals';
    
    // Handle form submission
    if (isset($_POST['create_promo']) && wp_verify_nonce($_POST['promo_nonce'], 'create_promo')) {
        $promo_code = strtoupper(sanitize_text_field($_POST['promo_code']));
        $promo_name = sanitize_text_field($_POST['promo_name']);
        $package_type = sanitize_text_field($_POST['package_type']);
        $package_id = intval($_POST['package_id']);
        $discount_type = sanitize_text_field($_POST['discount_type']);
        $discount_value = floatval($_POST['discount_value']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $usage_limit = intval($_POST['usage_limit']);
        
        // Get original price
        $original_price = 0;
        if ($package_type === 'fibre') {
            $original_price = floatval(get_field('price', $package_id));
        } else {
            // For LTE packages, we'll need to get from the package data
            $original_price = floatval($_POST['original_price']);
        }
        
        // Calculate promo price
        $promo_price = $original_price;
        if ($discount_type === 'percentage') {
            $promo_price = $original_price * (1 - $discount_value / 100);
        } else {
            $promo_price = $original_price - $discount_value;
        }
        
        // Insert into database
        $result = $wpdb->insert(
            $table_name,
            array(
                'promo_code' => $promo_code,
                'promo_name' => $promo_name,
                'package_type' => $package_type,
                'package_id' => $package_id,
                'original_price' => $original_price,
                'promo_price' => $promo_price,
                'discount_type' => $discount_type,
                'discount_value' => $discount_value,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'usage_limit' => $usage_limit,
                'status' => 'active'
            )
        );
        
        if ($result) {
            echo '<div class="notice notice-success"><p>Promo created successfully!</p></div>';
            
            // Generate promo link
            $promo_link = $package_type === 'fibre' 
                ? home_url('/signup/?package_id=' . $package_id . '&promo=' . $promo_code)
                : home_url('/lte-signup/?promo=' . $promo_code);
            
            echo '<div class="notice notice-info"><p><strong>Promo Link:</strong> <code>' . $promo_link . '</code> 
                  <button onclick="navigator.clipboard.writeText(\'' . esc_js($promo_link) . '\')">Copy</button></p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error creating promo. The promo code might already exist.</p></div>';
        }
    }
    
    // Get all fibre packages
    $fibre_packages = get_posts(array(
        'post_type' => 'fibre_packages',
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    // Get all LTE packages
    $lte_packages = array();
    $lte_providers = get_terms(array(
        'taxonomy' => 'lte_provider',
        'hide_empty' => false
    ));
    
    foreach ($lte_providers as $provider) {
        $posts = get_posts(array(
            'post_type' => 'lte_packages',
            'numberposts' => -1,
            'tax_query' => array(array(
                'taxonomy' => 'lte_provider',
                'field' => 'slug',
                'terms' => $provider->slug
            ))
        ));
        
        foreach ($posts as $post) {
            $price = get_field('price', $post->ID);
            if (!$price) $price = get_post_meta($post->ID, 'price', true);
            
            $lte_packages[] = array(
                'id' => $post->ID,
                'title' => $provider->name . ' - ' . get_the_title($post),
                'price' => $price
            );
        }
    }
    ?>
    
    <div class="wrap">
        <h1>Create New Promo</h1>
        <p>Create special promotional deals with discounted prices. Share the generated link via WhatsApp, email, or social media.</p>
        
        <form method="post" action="" style="max-width: 800px;">
            <?php wp_nonce_field('create_promo', 'promo_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="promo_code">Promo Code</label></th>
                    <td>
                        <input type="text" name="promo_code" id="promo_code" class="regular-text" required 
                               pattern="[A-Za-z0-9\-_]+" title="Letters, numbers, hyphens, and underscores only"
                               placeholder="SUMMER2024" />
                        <p class="description">Unique code for this promotion (letters, numbers, hyphens, underscores)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="promo_name">Promo Name</label></th>
                    <td>
                        <input type="text" name="promo_name" id="promo_name" class="regular-text" required 
                               placeholder="Summer Special - 50% Off" />
                        <p class="description">Internal name for this promotion</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="package_type">Package Type</label></th>
                    <td>
                        <select name="package_type" id="package_type" required onchange="updatePackageList()">
                            <option value="">Select Type</option>
                            <option value="fibre">Fibre</option>
                            <option value="lte">LTE/5G</option>
                        </select>
                    </td>
                </tr>
                
                <tr id="package_row" style="display: none;">
                    <th scope="row"><label for="package_id">Select Package</label></th>
                    <td>
                        <select name="package_id" id="package_id" required style="width: 100%; max-width: 400px;">
                            <option value="">Select a package</option>
                        </select>
                        <p class="description">Choose the package this promo applies to</p>
                        <input type="hidden" name="original_price" id="original_price" value="0" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="discount_type">Discount Type</label></th>
                    <td>
                        <select name="discount_type" id="discount_type" required onchange="updateDiscountLabel()">
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed Amount</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="discount_value">Discount Value</label></th>
                    <td>
                        <input type="number" name="discount_value" id="discount_value" class="small-text" 
                               min="0" step="0.01" required />
                        <span id="discount_suffix">%</span>
                        <p class="description">Amount to discount from the original price</p>
                        <div id="price_preview" style="margin-top: 10px; padding: 10px; background: #f0f9ff; border-radius: 4px; display: none;">
                            Original: R<span id="preview_original">0</span> → 
                            Promo: R<span id="preview_promo">0</span>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="start_date">Start Date</label></th>
                    <td>
                        <input type="datetime-local" name="start_date" id="start_date" required />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="end_date">End Date</label></th>
                    <td>
                        <input type="datetime-local" name="end_date" id="end_date" required />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="usage_limit">Usage Limit</label></th>
                    <td>
                        <input type="number" name="usage_limit" id="usage_limit" class="small-text" 
                               min="0" value="0" />
                        <p class="description">Maximum number of times this promo can be used (0 = unlimited)</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="create_promo" class="button button-primary" value="Create Promo" />
                <a href="<?php echo admin_url('admin.php?page=starcast-promo-deals'); ?>" class="button">Cancel</a>
            </p>
        </form>
    </div>
    
    <script>
    const fibrePackages = <?php echo json_encode($fibre_packages); ?>;
    const ltePackages = <?php echo json_encode($lte_packages); ?>;
    
    function updatePackageList() {
        const type = document.getElementById('package_type').value;
        const packageSelect = document.getElementById('package_id');
        const packageRow = document.getElementById('package_row');
        
        packageSelect.innerHTML = '<option value="">Select a package</option>';
        
        if (type === 'fibre') {
            packageRow.style.display = 'table-row';
            fibrePackages.forEach(pkg => {
                const price = pkg.custom_fields?.price || '0';
                const option = new Option(pkg.post_title + ' - R' + price + '/pm', pkg.ID);
                option.dataset.price = price;
                packageSelect.add(option);
            });
        } else if (type === 'lte') {
            packageRow.style.display = 'table-row';
            ltePackages.forEach(pkg => {
                const option = new Option(pkg.title + ' - R' + pkg.price + '/pm', pkg.id);
                option.dataset.price = pkg.price;
                packageSelect.add(option);
            });
        } else {
            packageRow.style.display = 'none';
        }
        
        updatePricePreview();
    }
    
    function updateDiscountLabel() {
        const type = document.getElementById('discount_type').value;
        document.getElementById('discount_suffix').textContent = type === 'percentage' ? '%' : ' Rand';
        updatePricePreview();
    }
    
    function updatePricePreview() {
        const packageSelect = document.getElementById('package_id');
        const selectedOption = packageSelect.options[packageSelect.selectedIndex];
        const originalPrice = parseFloat(selectedOption?.dataset?.price || 0);
        const discountType = document.getElementById('discount_type').value;
        const discountValue = parseFloat(document.getElementById('discount_value').value || 0);
        
        document.getElementById('original_price').value = originalPrice;
        
        let promoPrice = originalPrice;
        if (discountType === 'percentage') {
            promoPrice = originalPrice * (1 - discountValue / 100);
        } else {
            promoPrice = originalPrice - discountValue;
        }
        
        if (originalPrice > 0) {
            document.getElementById('preview_original').textContent = originalPrice.toFixed(2);
            document.getElementById('preview_promo').textContent = Math.max(0, promoPrice).toFixed(2);
            document.getElementById('price_preview').style.display = 'block';
        } else {
            document.getElementById('price_preview').style.display = 'none';
        }
    }
    
    document.getElementById('package_id').addEventListener('change', updatePricePreview);
    document.getElementById('discount_value').addEventListener('input', updatePricePreview);
    
    // Set default dates
    document.getElementById('start_date').value = new Date().toISOString().slice(0, 16);
    const endDate = new Date();
    endDate.setMonth(endDate.getMonth() + 1);
    document.getElementById('end_date').value = endDate.toISOString().slice(0, 16);
    </script>
    <?php
}

// Handle promo code on signup pages
add_action('init', 'spdm_handle_promo_codes');
function spdm_handle_promo_codes() {
    if (isset($_GET['promo'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'starcast_promo_deals';
        $promo_code = sanitize_text_field($_GET['promo']);
        
        $promo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE promo_code = %s AND status = 'active'",
            $promo_code
        ));
        
        if ($promo) {
            // Check if promo is valid
            $now = current_time('mysql');
            if ($now >= $promo->start_date && $now <= $promo->end_date) {
                // Check usage limit
                if ($promo->usage_limit == 0 || $promo->times_used < $promo->usage_limit) {
                    // Store promo in session
                    if (!session_id()) {
                        session_start();
                    }
                    $_SESSION['active_promo'] = $promo;
                }
            }
        }
    }
}

// AJAX endpoint to validate promo
add_action('wp_ajax_validate_promo', 'spdm_validate_promo');
add_action('wp_ajax_nopriv_validate_promo', 'spdm_validate_promo');
function spdm_validate_promo() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'starcast_promo_deals';
    
    $promo_code = sanitize_text_field($_POST['promo_code']);
    $package_id = intval($_POST['package_id']);
    $package_type = sanitize_text_field($_POST['package_type']);
    
    $promo = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE promo_code = %s AND package_id = %d AND package_type = %s AND status = 'active'",
        $promo_code,
        $package_id,
        $package_type
    ));
    
    if ($promo) {
        $now = current_time('mysql');
        if ($now >= $promo->start_date && $now <= $promo->end_date) {
            if ($promo->usage_limit == 0 || $promo->times_used < $promo->usage_limit) {
                wp_send_json_success(array(
                    'promo_price' => $promo->promo_price,
                    'original_price' => $promo->original_price,
                    'discount' => $promo->original_price - $promo->promo_price
                ));
            } else {
                wp_send_json_error('Promo code usage limit reached');
            }
        } else {
            wp_send_json_error('Promo code expired');
        }
    } else {
        wp_send_json_error('Invalid promo code');
    }
}

// Track promo usage when order is created
add_action('woocommerce_new_order', 'spdm_track_promo_usage', 10, 2);
function spdm_track_promo_usage($order_id, $order) {
    if (!session_id()) {
        session_start();
    }
    
    if (isset($_SESSION['active_promo'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'starcast_promo_deals';
        $promo = $_SESSION['active_promo'];
        
        // Increment usage count
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET times_used = times_used + 1 WHERE id = %d",
            $promo->id
        ));
        
        // Store promo details in order meta
        $order->update_meta_data('_promo_code', $promo->promo_code);
        $order->update_meta_data('_promo_discount', $promo->original_price - $promo->promo_price);
        $order->save();
        
        // Clear promo from session
        unset($_SESSION['active_promo']);
    }
}

// Add promo field to signup pages
add_action('wp_footer', 'spdm_add_promo_scripts');
function spdm_add_promo_scripts() {
    if (is_page_template('page-signup.php') || is_page_template('lte_signup_page.php')) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we have a promo in the URL
            const urlParams = new URLSearchParams(window.location.search);
            const promoCode = urlParams.get('promo');
            
            if (promoCode) {
                // Add promo display to the page
                const packageSummary = document.querySelector('.package-summary');
                if (packageSummary) {
                    const promoDisplay = document.createElement('div');
                    promoDisplay.className = 'promo-applied';
                    promoDisplay.innerHTML = `
                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: 8px; margin-top: 15px;">
                            <p style="margin: 0; color: #065f46; font-weight: 600;">
                                🎉 Promo Code Applied: <span style="color: #10b981;">${promoCode}</span>
                            </p>
                            <p style="margin: 5px 0 0 0; color: #047857; font-size: 0.9em;">
                                Special pricing will be applied when you submit your application.
                            </p>
                        </div>
                    `;
                    packageSummary.appendChild(promoDisplay);
                }
                
                // Add hidden field to form
                const form = document.querySelector('form');
                if (form) {
                    const promoInput = document.createElement('input');
                    promoInput.type = 'hidden';
                    promoInput.name = 'promo_code';
                    promoInput.value = promoCode;
                    form.appendChild(promoInput);
                }
            }
        });
        </script>
        <?php
    }
}

// Modify order processing to apply promo pricing
add_filter('woocommerce_order_item_subtotal', 'spdm_apply_promo_pricing', 10, 3);
function spdm_apply_promo_pricing($subtotal, $item, $order) {
    if (!session_id()) {
        session_start();
    }
    
    if (isset($_SESSION['active_promo'])) {
        $promo = $_SESSION['active_promo'];
        return wc_price($promo->promo_price);
    }
    
    return $subtotal;
}

// Add sharing buttons to promo list
add_action('admin_footer', 'spdm_add_sharing_scripts');
function spdm_add_sharing_scripts() {
    if (isset($_GET['page']) && $_GET['page'] === 'starcast-promo-deals') {
        ?>
        <script>
        function shareOnWhatsApp(promoCode, packageName, discount, link) {
            const message = `🎉 Special Offer! Get ${discount} off on ${packageName}!\n\nUse promo code: ${promoCode}\n\nSign up here: ${link}`;
            const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, '_blank');
        }
        
        function shareOnFacebook(link) {
            const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(link)}`;
            window.open(facebookUrl, '_blank', 'width=600,height=400');
        }
        
        function generateQRCode(link) {
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(link)}`;
            window.open(qrUrl, '_blank');
        }
        </script>
        <?php
    }
}

// Add cron job to auto-deactivate expired promos
add_action('spdm_check_expired_promos', 'spdm_deactivate_expired_promos');
function spdm_deactivate_expired_promos() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'starcast_promo_deals';
    
    $wpdb->query($wpdb->prepare(
        "UPDATE $table_name SET status = 'inactive' WHERE end_date < %s AND status = 'active'",
        current_time('mysql')
    ));
}

// Schedule the cron job
register_activation_hook(__FILE__, 'spdm_schedule_cron');
function spdm_schedule_cron() {
    if (!wp_next_scheduled('spdm_check_expired_promos')) {
        wp_schedule_event(time(), 'daily', 'spdm_check_expired_promos');
    }
}

// Clear cron on deactivation
register_deactivation_hook(__FILE__, 'spdm_clear_cron');
function spdm_clear_cron() {
    wp_clear_scheduled_hook('spdm_check_expired_promos');
}

// Add custom column to orders list showing if promo was used
add_filter('manage_edit-shop_order_columns', 'spdm_add_promo_column');
function spdm_add_promo_column($columns) {
    $columns['promo_used'] = 'Promo';
    return $columns;
}

add_action('manage_shop_order_posts_custom_column', 'spdm_show_promo_column');
function spdm_show_promo_column($column) {
    global $post;
    
    if ($column === 'promo_used') {
        $order = wc_get_order($post->ID);
        $promo_code = $order->get_meta('_promo_code');
        
        if ($promo_code) {
            echo '<span style="background: #10b981; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.85em;">' . esc_html($promo_code) . '</span>';
        }
    }
}

// Add REST API endpoint for external integrations
add_action('rest_api_init', function() {
    register_rest_route('starcast/v1', '/validate-promo', array(
        'methods' => 'POST',
        'callback' => 'spdm_rest_validate_promo',
        'permission_callback' => '__return_true'
    ));
});

function spdm_rest_validate_promo($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'starcast_promo_deals';
    
    $promo_code = $request->get_param('promo_code');
    $package_id = $request->get_param('package_id');
    
    if (!$promo_code) {
        return new WP_Error('missing_code', 'Promo code is required', array('status' => 400));
    }
    
    $promo = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE promo_code = %s AND status = 'active'",
        $promo_code
    ));
    
    if (!$promo) {
        return new WP_Error('invalid_code', 'Invalid promo code', array('status' => 404));
    }
    
    // Validate package if provided
    if ($package_id && $promo->package_id != $package_id) {
        return new WP_Error('wrong_package', 'Promo code not valid for this package', array('status' => 400));
    }
    
    // Check dates
    $now = current_time('mysql');
    if ($now < $promo->start_date || $now > $promo->end_date) {
        return new WP_Error('expired', 'Promo code has expired', array('status' => 400));
    }
    
    // Check usage
    if ($promo->usage_limit > 0 && $promo->times_used >= $promo->usage_limit) {
        return new WP_Error('limit_reached', 'Promo code usage limit reached', array('status' => 400));
    }
    
    return array(
        'valid' => true,
        'promo_code' => $promo->promo_code,
        'discount_type' => $promo->discount_type,
        'discount_value' => $promo->discount_value,
        'promo_price' => $promo->promo_price,
        'original_price' => $promo->original_price,
        'savings' => $promo->original_price - $promo->promo_price
    );
}

// Add dashboard widget
add_action('wp_dashboard_setup', 'spdm_add_dashboard_widget');
function spdm_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'starcast_promo_stats',
        'Promo Deals Overview',
        'spdm_dashboard_widget_content'
    );
}

function spdm_dashboard_widget_content() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'starcast_promo_deals';
    
    $active_promos = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'active'");
    $total_uses = $wpdb->get_var("SELECT SUM(times_used) FROM $table_name");
    $total_savings = $wpdb->get_var("SELECT SUM((original_price - promo_price) * times_used) FROM $table_name");
    
    ?>
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; text-align: center;">
        <div>
            <div style="font-size: 2em; font-weight: bold; color: #10b981;"><?php echo $active_promos ?: 0; ?></div>
            <div style="color: #6b7280; font-size: 0.9em;">Active Promos</div>
        </div>
        <div>
            <div style="font-size: 2em; font-weight: bold; color: #3b82f6;"><?php echo $total_uses ?: 0; ?></div>
            <div style="color: #6b7280; font-size: 0.9em;">Total Uses</div>
        </div>
        <div>
            <div style="font-size: 2em; font-weight: bold; color: #f59e0b;">R<?php echo number_format($total_savings ?: 0, 0); ?></div>
            <div style="color: #6b7280; font-size: 0.9em;">Customer Savings</div>
        </div>
    </div>
    <p style="text-align: center; margin-top: 15px;">
        <a href="<?php echo admin_url('admin.php?page=starcast-promo-deals'); ?>" class="button">Manage Promos</a>
        <a href="<?php echo admin_url('admin.php?page=starcast-create-promo'); ?>" class="button button-primary">Create New</a>
    </p>
    <?php
}

// Export functionality
add_action('admin_init', 'spdm_handle_export');
function spdm_handle_export() {
    if (isset($_GET['action']) && $_GET['action'] === 'export_promos' && current_user_can('manage_options')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'starcast_promo_deals';
        
        $promos = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="promo_deals_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, array(
            'Promo Code',
            'Name',
            'Type',
            'Package ID',
            'Original Price',
            'Promo Price',
            'Discount Type',
            'Discount Value',
            'Start Date',
            'End Date',
            'Usage Limit',
            'Times Used',
            'Status'
        ));
        
        // Data
        foreach ($promos as $promo) {
            fputcsv($output, array(
                $promo['promo_code'],
                $promo['promo_name'],
                $promo['package_type'],
                $promo['package_id'],
                $promo['original_price'],
                $promo['promo_price'],
                $promo['discount_type'],
                $promo['discount_value'],
                $promo['start_date'],
                $promo['end_date'],
                $promo['usage_limit'],
                $promo['times_used'],
                $promo['status']
            ));
        }
        
        fclose($output);
        exit;
    }
}

// Add export button to admin page
add_filter('admin_footer', 'spdm_add_export_button');
function spdm_add_export_button() {
    if (isset($_GET['page']) && $_GET['page'] === 'starcast-promo-deals') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.wp-heading-inline').after('<a href="<?php echo admin_url('admin.php?action=export_promos'); ?>" class="page-title-action">Export CSV</a>');
        });
        </script>
        <?php
    }
}
?>