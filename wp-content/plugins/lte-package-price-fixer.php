<?php
/**
 * Plugin Name: LTE Package Price Fixer
 * Description: One-time import to fix LTE package prices from PDF data
 * Version: 1.0
 * Author: Starcast
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'lte_fixer_add_menu');
function lte_fixer_add_menu() {
    add_submenu_page(
        'tools.php',
        'Fix LTE Packages', 
        'Fix LTE Packages', 
        'manage_options', 
        'lte-package-fixer', 
        'lte_fixer_admin_page'
    );
}

// Admin page
function lte_fixer_admin_page() {
    ?>
    <div class="wrap">
        <h1>Fix LTE Package Prices</h1>
        
        <?php
        // Handle the import
        if (isset($_POST['fix_packages']) && check_admin_referer('lte_fixer_nonce')) {
            $result = lte_fixer_import_packages();
            echo '<div class="notice notice-success"><p>' . esc_html($result) . '</p></div>';
        }
        ?>
        
        <div class="card">
            <h2>Package Import Status</h2>
            <?php
            $total_packages = wp_count_posts('lte_packages')->publish;
            echo '<p>Current packages in database: <strong>' . $total_packages . '</strong></p>';
            echo '<p>Expected packages from PDF: <strong>71</strong></p>';
            
            if ($total_packages < 71) {
                echo '<p style="color: #d63638;">⚠️ Missing ' . (71 - $total_packages) . ' packages</p>';
            }
            ?>
        </div>
        
        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('lte_fixer_nonce'); ?>
            <p>This will update all existing packages with correct prices and add any missing packages.</p>
            <p class="submit">
                <input type="submit" name="fix_packages" class="button button-primary" value="Fix Package Prices" onclick="return confirm('This will update all package prices. Continue?');">
            </p>
        </form>
    </div>
    <?php
}

// The import function
function lte_fixer_import_packages() {
    $imported = 0;
    $updated = 0;
    $deleted = 0;
    
    // STEP 1: Clean up existing duplicates first
    $all_existing = get_posts([
        'post_type' => 'lte_packages',
        'numberposts' => -1,
        'post_status' => 'any'
    ]);
    
    // Group by provider and identify duplicates
    $packages_by_provider = [];
    foreach ($all_existing as $post) {
        $providers = wp_get_post_terms($post->ID, 'lte_provider');
        if (!empty($providers)) {
            $provider_slug = $providers[0]->slug;
            $title = $post->post_title;
            $price = get_post_meta($post->ID, 'price', true);
            
            if (!isset($packages_by_provider[$provider_slug])) {
                $packages_by_provider[$provider_slug] = [];
            }
            
            $packages_by_provider[$provider_slug][] = [
                'id' => $post->ID,
                'title' => $title,
                'price' => $price,
                'post' => $post
            ];
        }
    }
    
    // Delete duplicates - keep only packages with provider prefix
    foreach ($packages_by_provider as $provider_slug => $packages) {
        $provider_term = get_term_by('slug', $provider_slug, 'lte_provider');
        $provider_name = $provider_term ? $provider_term->name : ucfirst($provider_slug);
        
        foreach ($packages as $package) {
            $title_lower = strtolower($package['title']);
            $provider_lower = strtolower($provider_name);
            
            // If package title doesn't start with provider name, check for duplicates
            if (strpos($title_lower, $provider_lower) !== 0) {
                // Look for a package with provider prefix
                $expected_full_title = $provider_name . ' ' . $package['title'];
                
                foreach ($packages as $other_package) {
                    if ($other_package['id'] !== $package['id'] && 
                        strtolower($other_package['title']) === strtolower($expected_full_title)) {
                        // Found duplicate - delete the one without provider prefix
                        wp_delete_post($package['id'], true);
                        error_log("DELETED DUPLICATE: '{$package['title']}' (kept '{$other_package['title']}')");
                        $deleted++;
                        break;
                    }
                }
            }
        }
    }
    
    // Ensure providers exist
    $providers = ['vodacom', 'mtn', 'telkom'];
    foreach ($providers as $provider) {
        if (!term_exists($provider, 'lte_provider')) {
            wp_insert_term(ucfirst($provider), 'lte_provider', ['slug' => $provider]);
        }
    }
    
    // All packages data with full provider names (original format)
    $packages = [
        // VODACOM FIXED LTE - CAPPED
        ['title' => 'Vodacom Fixed LTE 25GB', 'provider' => 'vodacom', 'price' => 169, 'data' => '25GB', 'type' => 'capped'],
        ['title' => 'Vodacom Fixed LTE 50GB', 'provider' => 'vodacom', 'price' => 269, 'data' => '50GB', 'type' => 'capped'],
        ['title' => 'Vodacom Fixed LTE 100GB', 'provider' => 'vodacom', 'price' => 369, 'data' => '100GB', 'type' => 'capped'],
        ['title' => 'Vodacom Fixed LTE 200GB', 'provider' => 'vodacom', 'price' => 469, 'data' => '200GB', 'type' => 'capped'],
        
        // VODACOM FIXED LTE - UNCAPPED
        ['title' => 'Vodacom Uncapped 20Mbps', 'provider' => 'vodacom', 'price' => 269, 'speed' => '20', 'data' => 'Uncapped', 'aup' => '50', 'throttle' => '2Mbps'],
        ['title' => 'Vodacom Uncapped 30Mbps', 'provider' => 'vodacom', 'price' => 369, 'speed' => '30', 'data' => 'Uncapped', 'aup' => '150', 'throttle' => '2Mbps'],
        ['title' => 'Vodacom Uncapped 50Mbps', 'provider' => 'vodacom', 'price' => 469, 'speed' => '50', 'data' => 'Uncapped', 'aup' => '300', 'throttle' => '2Mbps'],
        ['title' => 'Vodacom Uncapped LTE PRO', 'provider' => 'vodacom', 'price' => 669, 'data' => 'Uncapped', 'aup' => '600', 'throttle' => '1Mbps'],
        
        // VODACOM 5G
        ['title' => 'Vodacom 5G Standard', 'provider' => 'vodacom', 'price' => 445, 'speed' => '500', 'data' => 'Uncapped', 'aup' => '250', 'type' => '5g'],
        ['title' => 'Vodacom 5G Advanced', 'provider' => 'vodacom', 'price' => 645, 'speed' => '500', 'data' => 'Uncapped', 'aup' => '350', 'type' => '5g'],
        ['title' => 'Vodacom 5G Pro', 'provider' => 'vodacom', 'price' => 845, 'speed' => '500', 'data' => 'Uncapped', 'aup' => '550', 'type' => '5g'],
        ['title' => 'Vodacom 5G Pro+', 'provider' => 'vodacom', 'price' => 945, 'speed' => '500', 'data' => 'Uncapped', 'aup' => '750', 'type' => '5g'],
        
        // MTN FIXED LTE - UNCAPPED
        ['title' => 'MTN Fixed LTE 30Mbps', 'provider' => 'mtn', 'price' => 339, 'speed' => '30', 'data' => 'Uncapped', 'aup' => '50', 'throttle' => '2Mbps'],
        ['title' => 'MTN Fixed LTE 75Mbps', 'provider' => 'mtn', 'price' => 379, 'speed' => '75', 'data' => 'Uncapped', 'aup' => '150', 'throttle' => '2Mbps'],
        ['title' => 'MTN Fixed LTE 125Mbps', 'provider' => 'mtn', 'price' => 469, 'speed' => '125', 'data' => 'Uncapped', 'aup' => '300', 'throttle' => '2Mbps'],
        ['title' => 'MTN Fixed LTE 150Mbps', 'provider' => 'mtn', 'price' => 569, 'speed' => '150', 'data' => 'Uncapped', 'aup' => '500', 'throttle' => '2Mbps'],
        ['title' => 'MTN Uncapped LTE Pro', 'provider' => 'mtn', 'price' => 799, 'data' => 'Uncapped', 'aup' => '1000', 'throttle' => '1Mbps'],
        
        // MTN CAPPED
        ['title' => 'MTN 60GB + 60GB Night', 'provider' => 'mtn', 'price' => 236, 'data' => '60GB + 60GB Night', 'type' => 'capped'],
        ['title' => 'MTN 120GB + 120GB Night', 'provider' => 'mtn', 'price' => 379, 'data' => '120GB + 120GB Night', 'type' => 'capped'],
        ['title' => 'MTN 250GB Day + Uncapped Night', 'provider' => 'mtn', 'price' => 569, 'data' => '250GB Day + Uncapped Night', 'type' => 'day-night'],
        ['title' => 'MTN 500GB Day + Uncapped Night', 'provider' => 'mtn', 'price' => 759, 'data' => '500GB Day + Uncapped Night', 'type' => 'day-night'],
        ['title' => 'MTN Uncapped On-Demand', 'provider' => 'mtn', 'price' => 89, 'data' => '5GB + Uncapped on demand', 'type' => 'on-demand'],
        
        // MTN 5G
        ['title' => 'MTN 5G Standard', 'provider' => 'mtn', 'price' => 399, 'speed' => '500', 'data' => 'Uncapped', 'aup' => '300', 'type' => '5g'],
        ['title' => 'MTN 5G Advanced', 'provider' => 'mtn', 'price' => 549, 'speed' => '500', 'data' => 'Uncapped', 'aup' => '450', 'type' => '5g'],
        ['title' => 'MTN 5G Pro', 'provider' => 'mtn', 'price' => 649, 'speed' => '500', 'data' => 'Uncapped', 'aup' => '600', 'type' => '5g'],
        ['title' => 'MTN 5G Pro+', 'provider' => 'mtn', 'price' => 849, 'speed' => '500', 'data' => 'Uncapped', 'aup' => '1000', 'type' => '5g'],
        
        // MTN MOBILE
        ['title' => 'MTN Mobile 2.5GB', 'provider' => 'mtn', 'price' => 64, 'data' => '2.5GB', 'type' => 'mobile'],
        ['title' => 'MTN Mobile 5.5GB', 'provider' => 'mtn', 'price' => 96, 'data' => '5.5GB', 'type' => 'mobile'],
        ['title' => 'MTN Mobile 7.5GB', 'provider' => 'mtn', 'price' => 144, 'data' => '7.5GB', 'type' => 'mobile'],
        ['title' => 'MTN Mobile 15GB', 'provider' => 'mtn', 'price' => 244, 'data' => '15GB', 'type' => 'mobile'],
        ['title' => 'MTN Mobile 25GB', 'provider' => 'mtn', 'price' => 289, 'data' => '25GB', 'type' => 'mobile'],
        ['title' => 'MTN Mobile 50GB', 'provider' => 'mtn', 'price' => 489, 'data' => '50GB', 'type' => 'mobile'],
        
        // TELKOM CAPPED
        ['title' => 'Telkom 22.5GB + 22.5GB Night', 'provider' => 'telkom', 'price' => 179, 'data' => '22.5GB + 22.5GB Night', 'type' => 'capped'],
        ['title' => 'Telkom 40GB + 40GB Night', 'provider' => 'telkom', 'price' => 225, 'data' => '40GB + 40GB Night', 'type' => 'capped'],
        ['title' => 'Telkom 80GB + 80GB Night', 'provider' => 'telkom', 'price' => 255, 'data' => '80GB + 80GB Night', 'type' => 'capped'],
        ['title' => 'Telkom 120GB + 120GB Night', 'provider' => 'telkom', 'price' => 310, 'data' => '120GB + 120GB Night', 'type' => 'capped'],
        ['title' => 'Telkom 180GB + 180GB Night', 'provider' => 'telkom', 'price' => 410, 'data' => '180GB + 180GB Night', 'type' => 'capped'],
        ['title' => 'Telkom 2TB', 'provider' => 'telkom', 'price' => 775, 'data' => '2TB', 'type' => 'capped'],
        
        // TELKOM UNCAPPED
        ['title' => 'Telkom Uncapped 10Mbps', 'provider' => 'telkom', 'price' => 299, 'speed' => '10', 'data' => 'Uncapped', 'aup' => '500', 'type' => 'uncapped'],
        ['title' => 'Telkom Uncapped 20Mbps', 'provider' => 'telkom', 'price' => 679, 'speed' => '20', 'data' => 'Uncapped', 'aup' => '600', 'type' => 'uncapped'],
    ];
    
    // Process each package
    foreach ($packages as $index => $pkg) {
        // Check if package exists by title
        $existing = get_posts([
            'post_type' => 'lte_packages',
            'title' => $pkg['title'],
            'post_status' => 'any',
            'numberposts' => 1
        ]);
        
        if (!empty($existing)) {
            $post_id = $existing[0]->ID;
            $action = 'updated';
            $updated++;
        } else {
            $post_id = wp_insert_post([
                'post_title' => $pkg['title'],
                'post_type' => 'lte_packages',
                'post_status' => 'publish'
            ]);
            $action = 'imported';
            $imported++;
        }
        
        if ($post_id && !is_wp_error($post_id)) {
            // Set provider
            wp_set_object_terms($post_id, $pkg['provider'], 'lte_provider', false);
            
            // Set all meta fields
            update_post_meta($post_id, 'price', $pkg['price']);
            update_field('price', $pkg['price'], $post_id);
            
            if (!empty($pkg['data'])) {
                update_post_meta($post_id, 'data', $pkg['data']);
                update_field('data', $pkg['data'], $post_id);
            }
            
            if (!empty($pkg['speed'])) {
                update_post_meta($post_id, 'speed', $pkg['speed']);
                update_field('speed', $pkg['speed'], $post_id);
            }
            
            if (!empty($pkg['aup'])) {
                update_post_meta($post_id, 'aup', $pkg['aup']);
                update_field('aup', $pkg['aup'], $post_id);
            }
            
            if (!empty($pkg['throttle'])) {
                update_post_meta($post_id, 'throttle', $pkg['throttle']);
                update_field('throttle', $pkg['throttle'], $post_id);
            }
            
            if (!empty($pkg['type'])) {
                update_post_meta($post_id, 'package_type', $pkg['type']);
            }
            
            // Set display order
            update_post_meta($post_id, 'display_order', ($index + 1) * 10);
        }
    }
    
    return "Import complete! Deleted: $deleted duplicate packages, Imported: $imported new packages, Updated: $updated packages.";
}
?>