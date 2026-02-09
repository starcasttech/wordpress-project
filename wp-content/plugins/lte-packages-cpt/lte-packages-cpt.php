<?php
/**
 * Plugin Name: LTE Packages CPT
 * Description: Creates custom post type for LTE/5G packages
 * Version: 1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register Custom Post Type
add_action('init', 'register_lte_packages_post_type');
function register_lte_packages_post_type() {
    
    $labels = array(
        'name'               => 'LTE Packages',
        'singular_name'      => 'LTE Package',
        'menu_name'          => 'LTE Packages',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New LTE Package',
        'edit_item'          => 'Edit LTE Package',
        'view_item'          => 'View LTE Package',
        'all_items'          => 'All LTE Packages',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_position'      => 25,
        'menu_icon'          => 'dashicons-smartphone',
        'supports'           => array('title'),
        'show_in_rest'       => true,
    );

    register_post_type('lte_packages', $args);
}

// Register Provider Taxonomy
add_action('init', 'register_lte_provider_taxonomy');
function register_lte_provider_taxonomy() {
    
    $labels = array(
        'name'              => 'Providers',
        'singular_name'     => 'Provider',
        'search_items'      => 'Search Providers',
        'all_items'         => 'All Providers',
        'edit_item'         => 'Edit Provider',
        'add_new_item'      => 'Add New Provider',
        'menu_name'         => 'Providers',
    );

    $args = array(
        'labels'            => $labels,
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'show_in_rest'      => true,
    );

    register_taxonomy('lte_provider', array('lte_packages'), $args);
}

// Flush rewrite rules on activation
register_activation_hook(__FILE__, 'lte_packages_flush_rewrites');
function lte_packages_flush_rewrites() {
    register_lte_packages_post_type();
    register_lte_provider_taxonomy();
    flush_rewrite_rules();
}

// Add columns to admin list
add_filter('manage_lte_packages_posts_columns', 'add_lte_packages_columns');
function add_lte_packages_columns($columns) {
    $new_columns = array();
    
    foreach ($columns as $key => $value) {
        if ($key == 'title') {
            $new_columns[$key] = $value;
            $new_columns['price'] = 'Price';
            $new_columns['speed'] = 'Speed';
            $new_columns['data'] = 'Data';
        } else {
            $new_columns[$key] = $value;
        }
    }
    
    return $new_columns;
}

// Populate custom columns
add_action('manage_lte_packages_posts_custom_column', 'populate_lte_packages_columns', 10, 2);
function populate_lte_packages_columns($column, $post_id) {
    switch ($column) {
        case 'price':
            $price = get_field('price', $post_id) ?: get_post_meta($post_id, 'price', true);
            echo 'R' . esc_html($price);
            break;
        case 'speed':
            $speed = get_field('speed', $post_id) ?: get_post_meta($post_id, 'speed', true);
            echo $speed ? esc_html($speed) . ' Mbps' : '-';
            break;
        case 'data':
            $data = get_field('data', $post_id) ?: get_post_meta($post_id, 'data', true);
            echo esc_html($data);
            break;
    }
}

// Make columns sortable
add_filter('manage_edit-lte_packages_sortable_columns', 'make_lte_packages_sortable');
function make_lte_packages_sortable($columns) {
    $columns['price'] = 'price';
    return $columns;
}

// Handle column sorting
add_action('pre_get_posts', 'lte_packages_orderby');
function lte_packages_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    if ($query->get('post_type') !== 'lte_packages') {
        return;
    }
    
    if ($query->get('orderby') == 'price') {
        $query->set('meta_key', 'price');
        $query->set('orderby', 'meta_value_num');
    }
}

?>