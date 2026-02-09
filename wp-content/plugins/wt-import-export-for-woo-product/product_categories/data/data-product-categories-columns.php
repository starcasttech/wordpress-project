<?php

if (!defined('WPINC')) {
    exit;
}
$post_columns =  array(
    'term_id' => 'term_id',
    'name' => 'name',
    'slug' => 'slug',
    'description' => 'description',
    'display_type' => 'display_type',
    'parent' => 'parent',
    'thumbnail' => 'thumbnail',
);

if(class_exists('WPSEO_Options')){
    /* Yoast is active */

    $post_columns['meta:_yoast_data'] = 'meta:_yoast_data';
    
}
return apply_filters('taxonomies_csv_product_post_columns',$post_columns);
