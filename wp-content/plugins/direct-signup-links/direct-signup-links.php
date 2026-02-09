<?php
/**
 * Plugin Name: Direct Signup Links Generator (SAFE VERSION)
 * Description: Generate direct signup links for Facebook ads and marketing campaigns
 * Version: 1.0
 * Author: Starcast Technologies
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'dsl_add_admin_menu');
function dsl_add_admin_menu() {
    add_menu_page(
        'Direct Signup Links',
        'Signup Links',
        'manage_options',
        'direct-signup-links',
        'dsl_admin_page',
        'dashicons-admin-links',
        27
    );
}

// Simple LTE direct link handler
add_action('init', 'dsl_handle_lte_direct_links');
function dsl_handle_lte_direct_links() {
    // Check if we're on LTE signup page with package_id
    if (isset($_GET['package_id']) && !empty($_GET['package_id'])) {
        $current_url = $_SERVER['REQUEST_URI'];
        
        // Check if this looks like LTE signup page
        if (strpos($current_url, 'lte-signup') !== false || strpos($current_url, 'lte_signup') !== false) {
            
            if (!session_id()) {
                session_start();
            }
            
            $package_id = intval($_GET['package_id']);
            
            // Get package data immediately
            $package_post = get_post($package_id);
            if ($package_post && $package_post->post_type === 'lte_packages') {
                
                // Get fields using same method as Package-selection.php
                $regular_price = get_field('price', $package_id);
                if (!$regular_price) $regular_price = get_post_meta($package_id, 'price', true);
                if (!$regular_price) $regular_price = 0;
                
                // Get promotional pricing
                $effective_price = get_effective_price($package_id);
                if (!$effective_price) $effective_price = $regular_price;
                
                $speed = get_field('speed', $package_id);
                if (!$speed) $speed = get_post_meta($package_id, 'speed', true);
                if (!$speed) $speed = '';
                
                $data = get_field('data', $package_id);
                if (!$data) $data = get_post_meta($package_id, 'data', true);
                if (!$data) $data = '';
                
                // Get provider
                $provider_terms = wp_get_post_terms($package_id, 'lte_provider');
                $provider_name = !empty($provider_terms) ? $provider_terms[0]->name : 'Unknown';
                
                // Store package data in session
                $_SESSION['lte_package_data'] = array(
                    'id' => $package_id,
                    'name' => get_the_title($package_post),
                    'provider' => $provider_name,
                    'price' => intval($effective_price),
                    'speed' => $speed,
                    'data' => $data
                );
            }
        }
    }
}

// Inject package data into LTE pages
add_action('wp_footer', 'dsl_inject_package_data');
function dsl_inject_package_data() {
    // Check if we're on LTE signup page
    $current_url = $_SERVER['REQUEST_URI'];
    if (strpos($current_url, 'lte-signup') !== false || strpos($current_url, 'lte_signup') !== false) {
        
        if (!session_id()) {
            session_start();
        }
        
        if (isset($_SESSION['lte_package_data'])) {
            $package_data = $_SESSION['lte_package_data'];
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                console.log('🚀 Loading LTE package data...');
                
                const packageData = <?php echo json_encode($package_data); ?>;
                console.log('Package data:', packageData);
                
                if (packageData && packageData.id) {
                    // Store in sessionStorage
                    sessionStorage.setItem('selectedPackage', JSON.stringify(packageData));
                    
                    // Update UI elements
                    setTimeout(function() {
                        const packageSummary = document.getElementById('package-summary');
                        const noPackageWarning = document.getElementById('no-package-warning');
                        const submitButton = document.getElementById('submit-button');
                        const selectedPackageInput = document.getElementById('selected_package');
                        
                        if (packageSummary) {
                            packageSummary.style.display = 'block';
                            
                            // Update display elements
                            const providerEl = document.getElementById('display-provider');
                            const priceEl = document.getElementById('display-price');
                            const speedEl = document.getElementById('display-speed');
                            const dataEl = document.getElementById('display-data');
                            
                            if (providerEl) providerEl.textContent = packageData.provider + ' Fixed LTE ' + (packageData.speed || '');
                            if (priceEl) priceEl.textContent = 'R' + packageData.price;
                            if (speedEl) speedEl.textContent = 'Up to ' + (packageData.speed || 'N/A');
                            if (dataEl) dataEl.textContent = packageData.data || 'Uncapped';
                        }
                        
                        if (noPackageWarning) noPackageWarning.style.display = 'none';
                        if (submitButton) submitButton.disabled = false;
                        if (selectedPackageInput) selectedPackageInput.value = JSON.stringify(packageData);
                        
                        console.log('✅ Package display updated');
                    }, 500);
                }
            });
            </script>
            <?php
            
            // Clear session data after use
            unset($_SESSION['lte_package_data']);
        }
    }
}

// Main admin page
function dsl_admin_page() {
    // Get all fibre packages
    $fibre_packages = get_posts(array(
        'post_type' => 'fibre_packages',
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    // Get LTE packages
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
            $regular_price = get_field('price', $post->ID);
            if (!$regular_price) $regular_price = get_post_meta($post->ID, 'price', true);
            if (!$regular_price) $regular_price = 0;
            
            // Get promotional pricing
            $effective_price = get_effective_price($post->ID);
            if (!$effective_price) $effective_price = $regular_price;
            
            $speed = get_field('speed', $post->ID);
            if (!$speed) $speed = get_post_meta($post->ID, 'speed', true);
            if (!$speed) $speed = '';
            
            $data = get_field('data', $post->ID);
            if (!$data) $data = get_post_meta($post->ID, 'data', true);
            if (!$data) $data = '';
            
            $lte_packages[] = array(
                'id' => $post->ID,
                'title' => $provider->name . ' - ' . get_the_title($post),
                'provider' => $provider->name,
                'price' => intval($effective_price),
                'speed' => $speed,
                'data' => $data
            );
        }
    }
    ?>
    
    <div class="wrap">
        <h1>Direct Signup Links Generator</h1>
        <p>Generate direct links to signup pages for Facebook ads and marketing campaigns.</p>
        
        <div class="nav-tabs-wrapper">
            <h2 class="nav-tab-wrapper">
                <a href="#fibre" class="nav-tab nav-tab-active" onclick="switchTab('fibre')">Fibre Packages</a>
                <a href="#lte" class="nav-tab" onclick="switchTab('lte')">LTE Packages</a>
            </h2>
        </div>
        
        <!-- Fibre Tab -->
        <div id="fibre-tab" class="tab-content">
            <h3>Fibre Package Direct Links</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>Price</th>
                        <th>Speed</th>
                        <th>Direct Link</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fibre_packages as $package): 
                        $regular_price = get_field('price', $package->ID);
                        $download = get_field('download', $package->ID);
                        $upload = get_field('upload', $package->ID);
                        $provider_terms = wp_get_post_terms($package->ID, 'fibre_provider');
                        $provider_name = !empty($provider_terms) ? $provider_terms[0]->name : 'Unknown';
                        
                        // Get promotional pricing
                        $effective_price = get_effective_price($package->ID);
                        if (!$effective_price) $effective_price = $regular_price;
                        
                        $direct_link = home_url('/signup/?package_id=' . $package->ID);
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($provider_name . ' - ' . get_the_title($package)); ?></strong></td>
                        <td><strong>R<?php echo number_format($effective_price, 0); ?></strong></td>
                        <td><?php echo esc_html($download . '/' . $upload); ?></td>
                        <td>
                            <input type="text" value="<?php echo esc_attr($direct_link); ?>" 
                                   class="regular-text" readonly onclick="this.select()" />
                        </td>
                        <td>
                            <button class="button" onclick="copyToClipboard('<?php echo esc_js($direct_link); ?>')">Copy</button>
                            <a href="<?php echo esc_url($direct_link); ?>" target="_blank" class="button">Test</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- LTE Tab -->
        <div id="lte-tab" class="tab-content" style="display: none;">
            <h3>LTE Package Direct Links</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>Price</th>
                        <th>Details</th>
                        <th>Direct Link</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lte_packages as $package): 
                        $direct_link = home_url('/lte-signup/?package_id=' . $package['id']);
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($package['title']); ?></strong></td>
                        <td><strong>R<?php echo number_format($package['price'], 0); ?></strong></td>
                        <td>
                            <?php if ($package['speed']): ?>
                                Speed: <?php echo esc_html($package['speed']); ?><br>
                            <?php endif; ?>
                            <?php if ($package['data']): ?>
                                Data: <?php echo esc_html($package['data']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="text" value="<?php echo esc_attr($direct_link); ?>" 
                                   class="regular-text" readonly onclick="this.select()" />
                        </td>
                        <td>
                            <button class="button" onclick="copyToClipboard('<?php echo esc_js($direct_link); ?>')">Copy</button>
                            <a href="<?php echo esc_url($direct_link); ?>" target="_blank" class="button">Test</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    function switchTab(tab) {
        // Update tab buttons
        document.querySelectorAll('.nav-tab').forEach(btn => btn.classList.remove('nav-tab-active'));
        document.querySelector('[onclick="switchTab(\'' + tab + '\')"]').classList.add('nav-tab-active');
        
        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
        document.getElementById(tab + '-tab').style.display = 'block';
    }
    
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Link copied to clipboard!');
        });
    }
    </script>
    
    <style>
    .tab-content {
        background: white;
        padding: 20px;
        border: 1px solid #ccd0d4;
        border-top: none;
    }
    </style>
    <?php
}

// Add dashboard widget
add_action('wp_dashboard_setup', 'dsl_add_dashboard_widget');
function dsl_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'direct_signup_links_widget',
        'Direct Signup Links',
        'dsl_dashboard_widget_content'
    );
}

function dsl_dashboard_widget_content() {
    $fibre_count = count(get_posts(array('post_type' => 'fibre_packages', 'numberposts' => -1)));
    
    $lte_count = 0;
    $lte_providers = get_terms(array('taxonomy' => 'lte_provider', 'hide_empty' => false));
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
        $lte_count += count($posts);
    }
    
    ?>
    <div style="text-align: center;">
        <p><strong>Fibre Links:</strong> <?php echo $fibre_count; ?></p>
        <p><strong>LTE Links:</strong> <?php echo $lte_count; ?></p>
        <p>
            <a href="<?php echo admin_url('admin.php?page=direct-signup-links'); ?>" class="button button-primary">
                Generate Links
            </a>
        </p>
    </div>
    <?php
}
?>