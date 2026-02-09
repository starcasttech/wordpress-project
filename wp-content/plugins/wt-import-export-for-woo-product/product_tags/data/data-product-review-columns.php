<?php

if (!defined('WPINC')) {
    exit;
}

$post_columns = array(
    'term_id' => 'term_id',
    'name' => 'name',
    'slug' => 'slug',
    'description' => 'description',
        );

if(class_exists('WPSEO_Options')){
/* Yoast is active */
$post_columns['meta:_yoast_data'] = 'meta:_yoast_data';
}

return apply_filters('wt_tags_csv_product_post_columns',$post_columns);
