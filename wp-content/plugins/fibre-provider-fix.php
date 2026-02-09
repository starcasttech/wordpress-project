<?php
/**
 * Plugin Name: Fibre Provider Fix & Import
 * Description: Creates fibre_provider taxonomy and imports packages from CSV
 * Version: 1.0
 * Author: Starcast
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ================================
// REGISTER FIBRE PROVIDER TAXONOMY
// ================================

add_action('init', 'register_fibre_provider_taxonomy', 5);
function register_fibre_provider_taxonomy() {
    
    $labels = array(
        'name'              => 'Fibre Providers',
        'singular_name'     => 'Fibre Provider',
        'search_items'      => 'Search Fibre Providers',
        'all_items'         => 'All Fibre Providers',
        'edit_item'         => 'Edit Fibre Provider',
        'add_new_item'      => 'Add New Fibre Provider',
        'menu_name'         => 'Fibre Providers',
    );

    $args = array(
        'labels'            => $labels,
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'show_in_rest'      => true,
        'rewrite'           => array('slug' => 'fibre-provider'),
    );

    register_taxonomy('fibre_provider', array('fibre_packages'), $args);
}

// ================================
// UPDATE LTE PROVIDER TAXONOMY
// ================================

add_action('init', 'update_lte_provider_taxonomy', 4);
function update_lte_provider_taxonomy() {
    // First unregister the old taxonomy if it exists
    if (taxonomy_exists('provider')) {
        // Note: This only works if the taxonomy was registered without the 'lte_packages' post type
        unregister_taxonomy_for_object_type('provider', 'lte_packages');
    }
    
    // Register the new lte_provider taxonomy
    $labels = array(
        'name'              => 'LTE Providers',
        'singular_name'     => 'LTE Provider',
        'search_items'      => 'Search LTE Providers',
        'all_items'         => 'All LTE Providers',
        'edit_item'         => 'Edit LTE Provider',
        'add_new_item'      => 'Add New LTE Provider',
        'menu_name'         => 'LTE Providers',
    );

    $args = array(
        'labels'            => $labels,
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'show_in_rest'      => true,
        'rewrite'           => array('slug' => 'lte-provider'),
    );

    register_taxonomy('lte_provider', array('lte_packages'), $args);
}

// ================================
// ADMIN MENU
// ================================

add_action('admin_menu', 'fibre_fixer_add_menu');
function fibre_fixer_add_menu() {
    add_submenu_page(
        'tools.php',
        'Fix Fibre Packages', 
        'Fix Fibre Packages', 
        'manage_options', 
        'fibre-package-fixer', 
        'fibre_fixer_admin_page'
    );
}

// ================================
// ADMIN PAGE
// ================================

function fibre_fixer_admin_page() {
    ?>
    <div class="wrap">
        <h1>Fix Fibre Package Providers & Import</h1>
        
        <?php
        // Handle the import
        if (isset($_POST['fix_packages']) && check_admin_referer('fibre_fixer_nonce')) {
            $result = fibre_fixer_import_packages();
            echo '<div class="notice notice-success"><p>' . esc_html($result) . '</p></div>';
        }
        
        // Handle taxonomy migration
        if (isset($_POST['migrate_taxonomies']) && check_admin_referer('fibre_fixer_nonce')) {
            $result = migrate_provider_taxonomies();
            echo '<div class="notice notice-success"><p>' . esc_html($result) . '</p></div>';
        }
        ?>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Current Status</h2>
            <?php
            $fibre_packages = wp_count_posts('fibre_packages')->publish;
            $lte_packages = wp_count_posts('lte_packages')->publish;
            
            echo '<p>Fibre packages in database: <strong>' . $fibre_packages . '</strong></p>';
            echo '<p>LTE packages in database: <strong>' . $lte_packages . '</strong></p>';
            
            // Check for old provider taxonomy
            $old_providers = get_terms(array('taxonomy' => 'provider', 'hide_empty' => false));
            if (!is_wp_error($old_providers) && count($old_providers) > 0) {
                echo '<p style="color: #d63638;">⚠️ Old "provider" taxonomy still has ' . count($old_providers) . ' terms</p>';
            }
            ?>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Step 1: Migrate Taxonomies</h2>
            <p>This will migrate existing provider terms to the new taxonomy structure.</p>
            <form method="post">
                <?php wp_nonce_field('fibre_fixer_nonce'); ?>
                <p class="submit">
                    <input type="submit" name="migrate_taxonomies" class="button button-secondary" value="Migrate Provider Taxonomies">
                </p>
            </form>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Step 2: Import/Update Fibre Packages</h2>
            <p>This will import fibre packages with correct provider assignments.</p>
            <form method="post">
                <?php wp_nonce_field('fibre_fixer_nonce'); ?>
                <p class="submit">
                    <input type="submit" name="fix_packages" class="button button-primary" value="Import Fibre Packages" onclick="return confirm('This will update all fibre packages. Continue?');">
                </p>
            </form>
        </div>
    </div>
    <?php
}

// ================================
// MIGRATE TAXONOMIES
// ================================

function migrate_provider_taxonomies() {
    $migrated = 0;
    
    // Get all fibre packages
    $fibre_packages = get_posts(array(
        'post_type' => 'fibre_packages',
        'numberposts' => -1,
        'post_status' => 'any'
    ));
    
    foreach ($fibre_packages as $package) {
        // Get current provider terms
        $old_terms = wp_get_object_terms($package->ID, 'provider');
        if (!is_wp_error($old_terms) && !empty($old_terms)) {
            foreach ($old_terms as $term) {
                // Add to new fibre_provider taxonomy
                wp_set_object_terms($package->ID, $term->name, 'fibre_provider', true);
                // Remove from old taxonomy
                wp_remove_object_terms($package->ID, $term->term_id, 'provider');
                $migrated++;
            }
        }
    }
    
    // Do the same for LTE packages
    $lte_packages = get_posts(array(
        'post_type' => 'lte_packages',
        'numberposts' => -1,
        'post_status' => 'any'
    ));
    
    foreach ($lte_packages as $package) {
        $old_terms = wp_get_object_terms($package->ID, 'provider');
        if (!is_wp_error($old_terms) && !empty($old_terms)) {
            foreach ($old_terms as $term) {
                wp_set_object_terms($package->ID, $term->name, 'lte_provider', true);
                wp_remove_object_terms($package->ID, $term->term_id, 'provider');
                $migrated++;
            }
        }
    }
    
    return "Migration complete! Migrated $migrated provider assignments.";
}

// ================================
// IMPORT FUNCTION
// ================================

function fibre_fixer_import_packages() {
    $imported = 0;
    $updated = 0;
    
    // Ensure providers exist in fibre_provider taxonomy
    $providers = ['openserve', 'octotel', 'frogfoot', 'vumatel', 'metrofibre'];
    foreach ($providers as $provider) {
        if (!term_exists($provider, 'fibre_provider')) {
            wp_insert_term(ucfirst($provider), 'fibre_provider', ['slug' => $provider]);
        }
    }
    
    // Sample fibre packages based on your CSV structure
    // In production, you would read from your CSV file
    $packages = [
        // OPENSERVE
        ['provider' => 'openserve', 'name' => 'Openserve 25/25Mbps', 'download' => '25Mbps', 'upload' => '25Mbps', 'price' => 765],
        ['provider' => 'openserve', 'name' => 'Openserve 50/25Mbps', 'download' => '50Mbps', 'upload' => '25Mbps', 'price' => 885],
        ['provider' => 'openserve', 'name' => 'Openserve 50/50Mbps', 'download' => '50Mbps', 'upload' => '50Mbps', 'price' => 985],
        ['provider' => 'openserve', 'name' => 'Openserve 100/50Mbps', 'download' => '100Mbps', 'upload' => '50Mbps', 'price' => 1085],
        ['provider' => 'openserve', 'name' => 'Openserve 100/100Mbps', 'download' => '100Mbps', 'upload' => '100Mbps', 'price' => 1225],
        ['provider' => 'openserve', 'name' => 'Openserve 200/100Mbps', 'download' => '200Mbps', 'upload' => '100Mbps', 'price' => 1225],
        ['provider' => 'openserve', 'name' => 'Openserve 200/200Mbps', 'download' => '200Mbps', 'upload' => '200Mbps', 'price' => 1285],
        ['provider' => 'openserve', 'name' => 'Openserve 300/150Mbps', 'download' => '300Mbps', 'upload' => '150Mbps', 'price' => 1455],
        ['provider' => 'openserve', 'name' => 'Openserve 500/250Mbps', 'download' => '500Mbps', 'upload' => '250Mbps', 'price' => 1655],
        ['provider' => 'openserve', 'name' => 'Openserve 1000/500Mbps', 'download' => '1000Mbps', 'upload' => '500Mbps', 'price' => 2455],
        
        // OCTOTEL
        ['provider' => 'octotel', 'name' => 'Octotel 25/25Mbps', 'download' => '25Mbps', 'upload' => '25Mbps', 'price' => 765],
        ['provider' => 'octotel', 'name' => 'Octotel 50/50Mbps', 'download' => '50Mbps', 'upload' => '50Mbps', 'price' => 985],
        ['provider' => 'octotel', 'name' => 'Octotel 100/100Mbps', 'download' => '100Mbps', 'upload' => '100Mbps', 'price' => 1225],
        ['provider' => 'octotel', 'name' => 'Octotel 200/200Mbps', 'download' => '200Mbps', 'upload' => '200Mbps', 'price' => 1455],
        ['provider' => 'octotel', 'name' => 'Octotel 500/500Mbps', 'download' => '500Mbps', 'upload' => '500Mbps', 'price' => 1655],
        ['provider' => 'octotel', 'name' => 'Octotel 1000/1000Mbps', 'download' => '1000Mbps', 'upload' => '1000Mbps', 'price' => 2455],
        
        // Add more providers as needed...
    ];
    
    // Process each package
    foreach ($packages as $index => $pkg) {
        // Check if exists by title
        $existing = get_posts([
            'post_type' => 'fibre_packages',
            'title' => $pkg['name'],
            'post_status' => 'any',
            'numberposts' => 1
        ]);
        
        if (!empty($existing)) {
            $post_id = $existing[0]->ID;
            $action = 'updated';
            $updated++;
        } else {
            $post_id = wp_insert_post([
                'post_title' => $pkg['name'],
                'post_type' => 'fibre_packages',
                'post_status' => 'publish'
            ]);
            $action = 'imported';
            $imported++;
        }
        
        if ($post_id && !is_wp_error($post_id)) {
            // Set provider in fibre_provider taxonomy
            wp_set_object_terms($post_id, $pkg['provider'], 'fibre_provider', false);
            
            // Set meta fields
            update_post_meta($post_id, 'price', $pkg['price']);
            update_post_meta($post_id, 'download', $pkg['download']);
            update_post_meta($post_id, 'upload', $pkg['upload']);
            
            // Also update ACF fields if they exist
            if (function_exists('update_field')) {
                update_field('price', $pkg['price'], $post_id);
                update_field('download', $pkg['download'], $post_id);
                update_field('upload', $pkg['upload'], $post_id);
            }
            
            // Set display order
            update_post_meta($post_id, 'display_order', ($index + 1) * 10);
        }
    }
    
    return "Import complete! Imported: $imported new packages, Updated: $updated packages.";
}

// ================================
// UPDATE TEMPLATE FILES TO USE NEW TAXONOMIES
// ================================

add_action('admin_notices', 'fibre_taxonomy_admin_notice');
function fibre_taxonomy_admin_notice() {
    if (get_current_screen()->base !== 'tools_page_fibre-package-fixer') {
        return;
    }
    ?>
    <div class="notice notice-warning">
        <p><strong>Important:</strong> After running this fix, update your template files:</p>
        <ul>
            <li>In <code>page-fibre.php</code>: Change <code>'taxonomy' => 'provider'</code> to <code>'taxonomy' => 'fibre_provider'</code></li>
            <li>In <code>page-lte.php</code>: Change <code>'taxonomy' => 'provider'</code> to <code>'taxonomy' => 'lte_provider'</code></li>
            <li>Update any other files that reference the provider taxonomy</li>
        </ul>
    </div>
    <?php
}

// ================================
// CSV IMPORT FUNCTION (OPTIONAL)
// ================================

function fibre_import_from_csv($csv_file_path) {
    if (!file_exists($csv_file_path)) {
        return "CSV file not found.";
    }
    
    $imported = 0;
    $updated = 0;
    
    if (($handle = fopen($csv_file_path, "r")) !== FALSE) {
        // Skip header row
        $header = fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $provider = strtolower(trim($data[0]));
            $name = trim($data[1]);
            $download = trim($data[2]);
            $upload = trim($data[3]);
            $price = floatval($data[4]);
            
            // Ensure provider exists
            if (!term_exists($provider, 'fibre_provider')) {
                wp_insert_term(ucfirst($provider), 'fibre_provider', ['slug' => $provider]);
            }
            
            // Check if package exists
            $existing = get_posts([
                'post_type' => 'fibre_packages',
                'title' => $name,
                'post_status' => 'any',
                'numberposts' => 1
            ]);
            
            if (!empty($existing)) {
                $post_id = $existing[0]->ID;
                $updated++;
            } else {
                $post_id = wp_insert_post([
                    'post_title' => $name,
                    'post_type' => 'fibre_packages',
                    'post_status' => 'publish'
                ]);
                $imported++;
            }
            
            if ($post_id && !is_wp_error($post_id)) {
                // Set provider
                wp_set_object_terms($post_id, $provider, 'fibre_provider', false);
                
                // Set meta fields
                update_post_meta($post_id, 'price', $price);
                update_post_meta($post_id, 'download', $download);
                update_post_meta($post_id, 'upload', $upload);
                
                // Update ACF fields if available
                if (function_exists('update_field')) {
                    update_field('price', $price, $post_id);
                    update_field('download', $download, $post_id);
                    update_field('upload', $upload, $post_id);
                }
            }
        }
        fclose($handle);
    }
    
    return "CSV Import complete! Imported: $imported new packages, Updated: $updated packages.";
}
?>