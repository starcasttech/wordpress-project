<?php

namespace Acowebs\WCPA;


use WP_Query;

/**
 * Handling Options related functions
 *
 */
class Options
{

    static $CPT = "wcpa_pt_options";
    static $META_KEY_1 = "_wcpa_options_data";
    static $META_KEY_2 = "_wcpa_options_unique_key";
    /**
     * @var ML
     */
    private $ml;

    /**
     * Class Constructor
     *
     */

    public function __construct()
    {
        $this->ml = new ML();
        $this->register_cpt();
    }

    /**
     * Register Custom Post Type
     *
     */
    public function register_cpt()
    {
        $labels = array(
            'name' => _x('Options Lists', 'Option Custom Post Type Name', "woo-custom-product-addons-pro"),
            'singular_name' => _x('Options List', 'Option Custom Post Type Name', "woo-custom-product-addons-pro"),
            'name_admin_bar' => _x('Options Lists', 'Option Custom Post Type Name', "woo-custom-product-addons-pro"),
            'add_new' => __('Add New Options List', 'woo-custom-product-addons-pro'),
            'add_new_item' => __('Add New Options List', "woo-custom-product-addons-pro"),
            'edit_item' => __('Edit Options List', "woo-custom-product-addons-pro"),
            'new_item' => __('New Options List', "woo-custom-product-addons-pro"),
            'all_items' => __('Options Lists', "woo-custom-product-addons-pro"),
            'view_item' => __('View Options List', "woo-custom-product-addons-pro"),
            'search_items' => __('Search Options List', "woo-custom-product-addons-pro"),
            'not_found' => __('No Options List Found', "woo-custom-product-addons-pro"),
            'not_found_in_trash' => __('No Options List Found In Trash', "woo-custom-product-addons-pro"),
            'parent_item_colon' => __('Parent Options List', "woo-custom-product-addons-pro"),
            'menu_name' => 'Options Lists'
        );

        $args = array(
            'labels' => apply_filters(self::$CPT . '_labels', $labels),
            'description' => '',
            'public' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_nav_menus' => false,
            'query_var' => false,
            'can_export' => true,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'rest_base' => self::$CPT,
            'hierarchical' => false,
            'show_in_rest' => false,
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'supports' => array('title'),
            'menu_position' => 5,
            'menu_icon' => 'dashicons-admin-post',
        );

        register_post_type(self::$CPT, apply_filters(self::$CPT . '_register_args', $args, self::$CPT));
    }

    public function init()
    {
        $this->register_cpt();
    }

    /**
     *  To ensure the post_type in QP_Query has not modified.
     * Some customers writing custom codes to filter out 'posts' from front end search by setting post type 'product'
     * This can cause issue it rest api requests for forms, options fetching
     * @param $query
     * @return mixed
     */
    public function suppress_filters($query)
    {
        $query->set('post_type', array(self::$CPT));
        return $query;
    }

    /**
     * Get the Field Options
     */
    public function get_options_lists($tab, $page = 1, $per_page = 20, $search = '')
    {
        //TODO wpml compatibility
        add_filter('wpml_should_use_display_as_translated_snippet', '__return_false');
        $args = [
            'post_type' => self::$CPT,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => array('publish', 'draft'),
            's' => $search,

            //    'lang'=>'en',
            'suppress_filters' => false // set false avoid listing all translation for wpml
        ];
        if ($this->ml->is_active()) {
            $args = $this->ml->listArgs($args);
        }
        if ($tab == 'trash') {
            $args['post_status'] = 'trash';
        }
        add_filter('pre_get_posts', array($this, 'suppress_filters'), 999, 1);

        $posts = new WP_Query($args);
        remove_filter('pre_get_posts', array($this, 'suppress_filters'), 999);
        $options = [];
        if ($posts->have_posts()): while ($posts->have_posts()) {
            $posts->the_post();
            $p = [
                'id' => get_the_ID(),
                'title' => html_entity_decode(get_the_title()),
                'active' => get_post_status() === 'publish',
                'post_parent' => wp_get_post_parent_id(get_the_ID()),
                'uniqueId' => get_post_meta(get_the_ID(), self::$META_KEY_2, true)
            ];
            if ($this->ml->is_active()) {
                $p['translations'] = $this->ml->get_post_translations_links(get_the_ID());
                $p['lang'] = $this->ml->get_post_language(get_the_ID());
            }
            $options[] = $p;
        } endif;
        wp_reset_postdata();

        return ['options' => $options, 'totalOptions' => $posts->found_posts, 'totalPages' => $posts->max_num_pages];
    }

    public function save_options_list($post_id, $post_data)
    {
        $this->init();
        $response = ['status' => true, 'id' => $post_id, 'redirect' => false];
        $allowedHtml = array(
            'a' => array(// on allow a tags
                'href' => true, // and those anchors can only have href attribute
                'target' => true,
                'class' => true,// and those anchors can only have href attribute
                'style' => true
            ),
            'b' => array('style' => true, 'class' => true),
            'strong' => array('style' => true, 'class' => true),
            'i' => array('style' => true, 'class' => true),
            'img' => array('style' => true, 'class' => true, 'src' => true),
            'span' => array('style' => true, 'class' => true),
            'p' => array('style' => true, 'class' => true)
        );


        $settings = $post_data['settings'];
        $post = $post_data['post'];
        $title = $post['title'];
        $uniqueId = $post_data['id'];

        $settings_json = wp_slash(json_encode($settings));

        $lang = false;
        if ($this->ml->is_active()) {
            $lang = $post['lang'];
        }

        if ($post_id === 0) {
            $new_post_id = $this->insert($title, $uniqueId, $settings_json, $lang);
            $response['id'] = $new_post_id;
            $post_id = $new_post_id;
        } else {
            $this->update($post_id, $title, $settings_json, $lang);
        }


        if ($this->ml->is_active()) {
            // TODO 
            $this->ml->sync_data($post_id);
        }

        refreshCaches($post_id);

        Cron::schedule_cron();

        return $response;
    }

    public function insert($title, $uniqueId, $settings_json, $lang = false, $base_lang_id = false)
    {
        $my_post = array(
            'post_title' => $title,
            'post_type' => self::$CPT,
            'post_status' => 'publish',
        );

        $post_id = wp_insert_post($my_post);

        if ($lang) {
            $this->ml->set_post_lang($post_id, $lang, $base_lang_id, self::$CPT);
        }

        update_post_meta($post_id, self::$META_KEY_1, $settings_json);
        update_post_meta($post_id, self::$META_KEY_2, $uniqueId);

        return $post_id;
    }

    public function update($post_id, $title, $settings_json, $lang = false)
    {
        update_post_meta($post_id, self::$META_KEY_1, $settings_json);
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $title,
            'post_status' => 'publish',
        ));

        if ($lang) {
            $this->ml->set_post_lang($post_id, $lang);
        }
    }

    public function get_options($options_id)
    {
        $response = ['status' => false];

        $post = get_post($options_id);
        if ($post) {
            $response['post'] = array(
                'title' => $post->post_title,
                'id' => $post->ID
            );
            if ($this->ml->is_active()) {
                $postLang = $this->ml->get_post_language($post->ID);
                $response['post']['lang'] = $postLang == false ? $this->ml->default_language() : $postLang;
                $response['post']['translations'] = $this->ml->get_post_translations_links($post->ID);
            }
        }

        $settings = get_post_meta($options_id, self::$META_KEY_1, true);
        $uniqueId = get_post_meta($options_id, self::$META_KEY_2, true);


        $response['settings'] = json_decode($settings);
        $response['id'] = $uniqueId;
        $response['status'] = true;

        return $response;
    }

    public function delete_options_list($posts)
    {
        $response = array();
        if (is_array($posts)) {
            foreach ($posts as $post_id) {
                $status = wp_delete_post($post_id);
                if ($status) {
                    $response[$post_id] = ['status' => true];
                } else {
                    $response[$post_id] = ['status' => false];
                }
            }
        }

        return $response;
    }

    public function trash_options_list($posts)
    {
        $response = array();
        if (is_array($posts)) {
            foreach ($posts as $post_id) {
                $status = wp_trash_post($post_id);
                if ($status) {
                    $response[$post_id] = ['status' => true];
                } else {
                    $response[$post_id] = ['status' => false];
                }
            }
        }

        return $response;
    }


    public function restore_options_list($posts)
    {
        $response = array();
        if (is_array($posts) && !empty($posts)) {
            foreach ($posts as $post_id) {
                $status = wp_untrash_post($post_id);
                wp_publish_post($post_id);
                if ($status) {
                    $response[$post_id] = ['status' => true];
                } else {
                    $response[$post_id] = ['status' => false];
                }
            }
        }

        return $response;
    }

    public function duplicate_options_list($options_list_id)
    {
        $response = array();
        if ($options_list_id) {
            global $wpdb;

            $_duplicate = get_post($options_list_id);

            if (!isset($_duplicate->post_type) || $_duplicate->post_type !== self::$CPT) {
                return ['status' => false];
            }


            $title = $_duplicate->post_title . ' ' . __('Copy', 'woo-custom-product-addons-pro');

            $settings_json = get_post_meta($options_list_id, self::$META_KEY_1, true);
            $settings_json = wp_slash($settings_json);
            $uniqueId = "wcpa-options-list-" . time();

            $lang = false;
            if ($this->ml->is_active()) {
                $lang = $this->ml->get_post_language($options_list_id);
            }

            $new_post_id = $this->insert($title, $uniqueId, $settings_json, $lang);

            $item = [
                'id' => $new_post_id,
                'title' => get_the_title($new_post_id),
                'active' => get_post_status($new_post_id) === 'publish' ? true : false,
                'post_parent' => wp_get_post_parent_id($new_post_id)
            ];
            if ($this->ml->is_active()) {
                $item['translations'] = $this->ml->get_post_translations_links($new_post_id);
                $item['lang'] = $this->ml->get_post_language($new_post_id);
            }
            $response = ['status' => true, 'item' => $item];
        }

        return $response;
    }

    /**
     * Export only options to a CSV file
     *
     * @param int $post_id
     *
     * @return array $result
     */
    public function export_options($post_id)
    {
        $response = ['status' => true, 'data' => []];
        $settings_json = get_post_meta($post_id, self::$META_KEY_1, true);
        $settings = json_decode($settings_json);

        // $this->download_send_headers("options_export_" . date("Y-m-d") . "_" . get_the_title($post_id) . ".csv");

        // ob_start();
        // $df = fopen("php://output", 'w');

        $headings = ['group'];
        if (isset($settings->fields) && !empty($settings->fields)) {
            foreach ($settings->fields as $field) {
                if ($field == 'image') {
                    $headings[] = 'imageurl';
                    $headings[] = 'imageid';
                } else {
                    $headings[] = $field;
                }
            }
        }

        $result[] = $headings;
        // fputcsv($df, $headings);

        if (isset($settings->groups) && !empty($settings->groups)) {
            foreach ($settings->groups as $group) {
                $group_title = $group->label;
                if (isset($group->options) && !empty($group->options)) {
                    foreach ($group->options as $option) {
                        $data = [$group_title != false ? $group_title : ''];
                        $group_title = false;
                        foreach ($option as $field => $value) {
                            if ($field == 'image') {
                                $index = array_search('imageurl', $headings);
                                if ($index != false) {
                                    $data[$index] = isset($value->url) ? $value->url : '';
                                    $data[$index + 1] = isset($value->id) ? $value->id : '';
                                }
                            } elseif ($field == 'id') {
                                continue;
                            } else {
                                $index = array_search($field, $headings);
                                if ($index !== false) {
                                    if ($field == 'selected') {
                                        $data[$index] = $value ? 1 : 0;
                                    } else {
                                        $data[$index] = $value;
                                    }
                                }
                            }
                        }
                        // fputcsv($df, $data);
                        $result[] = $data;
                    }
                }
            }
        }

        // fclose($df);
        // ob_flush();
        // exit;
        $response['data'] = $result;
        return $response;
    }

    private function download_send_headers($filename)
    {
        // disable caching
        $now = gmdate("D, d M Y H:i:s");
        header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
        header("Last-Modified: {$now} GMT");

        // force download
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");

        // disposition / encoding on response body
        header("Content-Disposition: attachment;filename={$filename}");
        header("Content-Transfer-Encoding: binary");
    }

    /**
     * Retrive options items for front end
     *
     * @param $key
     */
    public function get_options_by_key($key)
    {
        $args = array(
            'post_type' => self::$CPT,
            'meta_key' => self::$META_KEY_2,
            'meta_value' => $key
        );
        if ($this->ml->is_active()) {
            $args['lang'] = '';
        }
        add_filter('pre_get_posts', array($this, 'suppress_filters'), 999, 1);
        $query = new WP_Query($args);
        remove_filter('pre_get_posts', array($this, 'suppress_filters'), 999);
        $post_id = wp_list_pluck($query->posts, 'ID');
        if (!$post_id || !isset($post_id[0])) {
            return [];
        }
        if ($this->ml->is_active()) {
            $post_id = $this->ml->lang_object_ids($post_id, 'post');
        }
        $json_string = get_post_meta($post_id[0], self::$META_KEY_1, true);
        $json_decode = json_decode($json_string);
        if (isset($json_decode->groups)) {
            return $json_decode->groups;
        }

        return [];
    }

    /**
     * Import via csv file
     *
     * @param int $post_id
     *
     * @return array $result
     */
    public function import_options($post_id, $post_data)
    {
        $response = ['status' => true, 'id' => $post_id, 'settings' => []];
        $post = $post_data['post'];

        $data = [];
        $headings = [];
        $removeExisting = (isset($post_data['removeExisting']) && ($post_data['removeExisting'] == 'true'))
            ? true : false;
        $uploadImages = (isset($post_data['uploadImages']) && ($post_data['uploadImages'] == 'true'))
            ? true : false;

        if (isset($_FILES['file']) && !empty($_FILES['file'])) {
            $csv = $_FILES['file'];
            if (isset($csv['type']) && $csv['type'] == 'text/csv') {
                if (is_uploaded_file($csv["tmp_name"])) {
                    $csvFile = fopen($_FILES['file']['tmp_name'], 'r');

                    $headings = fgetcsv($csvFile);
                    $group = '';
                    while (($line = fgetcsv($csvFile)) !== false) {
                        if ($line[0] != '') {
                            $group = $line[0];
                        }

                        $row = array();
                        if (!empty($headings)) {
                            $imageField = [];
                            foreach ($headings as $key => $h) {
                                if ($h == 'group') {
                                    $row[$h] = $group;
                                } elseif ($h == 'selected') {
                                    $row[$h] = $line[$key] == '1' ? true : false;
                                } elseif ($h == 'imageurl') {
                                    $imageField['url'] = $line[$key];
                                } elseif ($h == 'imageid') {
                                    $imageField['id'] = (!empty($line[$key])) ? (int)$line[$key] : '';
                                } else {
                                    $row[$h] = trim($line[$key]);
                                }
                            }
                            if (!empty($imageField)) {
                                $row['image'] = (object)$this->may_be_add_media_to_library($imageField, $uploadImages);
                            }
                        }

                        // Remove old repeating value option
                        if (!empty($data)) {
                            $newdata = [];
                            foreach ($data as $key => $r) {
                                if ($r['value'] != $row['value']) {
                                    $newdata[] = $r;
                                }
                            }
                            $data = $newdata;
                        }

                        $data[] = $row;
                    }
                }
            }
        }


        $settings_json = (object)[];
        $group_data = [];
        $groups = [];
        $fields = [];
        $groupId = 1;
        $optionId = 1;
        $uniqueId = "wcpa-options-list-" . time();
        // to keep value unique
        $values = [];

        if ($post_id != 0) {
            $settings = get_post_meta($post_id, self::$META_KEY_1, true);
            $uniqueId = get_post_meta($post_id, self::$META_KEY_2, true);
            $settings = json_decode($settings);

            $fields = $settings->fields;

            if (!empty($settings->groups)) {
                foreach ($settings->groups as $g) {
                    $group_data[$g->id] = clone $g;
                    $groups[$g->id] = $g->label;
                    if ((int)$g->id > (int)$groupId) {
                        $groupId = (int)$g->id;
                    }

                    $group_data[$g->id]->options = [];
                    if (!empty($g->options)) {
                        foreach ($g->options as $index => $opt) {
                            if (!in_array($opt->value, $values)) {
                                $group_data[$g->id]->options[] = clone $opt;
                                $values[$opt->value] = [
                                    'group' => $g->id,
                                    'optionIndex' => $index
                                ];

                                if ((int)$opt->id > (int)$optionId) {
                                    $optionId = (int)$opt->id;
                                }
                            }
                        }
                    }
                }
            }

            $groupId++;
            $optionId++;
        } else {
            if (!empty($headings)) {
                foreach ($headings as $head) {
                    if ($head != 'group') {
                        if (in_array($head, array('imageurl', 'imageid'))) {
                            if (!in_array('image', $fields)) {
                                $fields[] = 'image';
                            }
                        } else {
                            $fields[] = $head;
                        }
                    }
                }
            }
        }

        if (!empty($data)) {
            foreach ($data as $row) {
                if (!empty($row) && !isset($values[$row['value']])) {
                    if (!in_array($row['group'], $groups)) {
                        $groups[$groupId] = $row['group'];
                        $groupId++;
                    }

                    $currGroupIndex = array_search($row['group'], $groups);

                    if (!(isset($group_data[$currGroupIndex]) && !empty($group_data[$currGroupIndex]))) {
                        $group_data[$currGroupIndex] = (object)[
                            'id' => $currGroupIndex,
                            'label' => $row['group'],
                            'options' => []
                        ];
                    }

                    $options = $group_data[$currGroupIndex]->options;

                    $option = [];
                    $option['id'] = $optionId;
                    $optionId++;

                    if (!empty($fields)) {
                        foreach ($fields as $fd) {
                            $impVal = '';
                            if (isset($row[$fd])) {
                                $impVal = $row[$fd];
                            }
                            $option[$fd] = $impVal;
                        }
                    }

                    $options[] = (object)$option;
                    $group_data[$currGroupIndex]->options = $options;
                } else {
                    $option = $group_data[$values[$row['value']]['group']]->options[$values[$row['value']]['optionIndex']];
                    if (!empty($fields)) {
                        foreach ($fields as $fd) {
                            $option->{$fd} = $row[$fd];
                        }
                    }
                    $group_data[$values[$row['value']]['group']]->options[$values[$row['value']]['optionIndex']] = $option;
                }
            }
        }

        $settings = (object)[
            'fields' => $fields,
            'groups' => array_values($group_data)
        ];

        $settings_json = wp_slash(json_encode($settings));

        $lang = false;
        if ($this->ml->is_active()) {
            $lang = $post->lang;
        }

        $response['settings'] = $settings;

        if ($post_id === 0) {
            $new_post_id = $this->insert('', $uniqueId, $settings_json, $lang);
            $response['id'] = $new_post_id;
        } else {
            $this->update($post_id, get_the_title($post_id), $settings_json, $lang);
        }

        return $response;
    }

    /**
     * Add media to library
     * if not exist in the current site.
     */
    private function may_be_add_media_to_library($media_data, $mustUpload = false)
    {
        if (!(isset($media_data['url']) && !empty($media_data['url']))) {
            return $media_data;
        }

        // if file not found
        $headers = @get_headers($media_data['url']);
        if (strpos($headers[0], '404') !== false) {
            return ['url' => '', 'id' => ''];
        }

        if ($mustUpload) {
            $attachment_id = $this->uploadImportFileByUrl($media_data['url']);

            return $attachment_id !== false
                ? ['url' => wp_get_attachment_url($attachment_id), 'id' => $attachment_id]
                : ['url' => '', 'id' => ''];
        } else {
            $site_url = get_site_url();

            //If domain is same
            if (strpos($media_data['url'], $site_url) !== false) {
                $imageId = attachment_url_to_postid($media_data['url']);
                if ($imageId !== 0) {
                    if ($imageId == $media_data['id']) {
                        return $media_data;
                    } else {
                        return ['url' => $media_data['url'], 'id' => $imageId];
                    }
                }
            }

            $attachment_id = $this->uploadImportFileByUrl($media_data['url']);

            return $attachment_id !== false
                ? ['url' => wp_get_attachment_url($attachment_id), 'id' => $attachment_id]
                : ['url' => '', 'id' => ''];
        }
    }

    /**
     * Upload file to media by url
     *
     */
    private function uploadImportFileByUrl($url)
    {
        require_once(ABSPATH . "/wp-load.php");
        require_once(ABSPATH . "/wp-admin/includes/image.php");
        require_once(ABSPATH . "/wp-admin/includes/file.php");
        require_once(ABSPATH . "/wp-admin/includes/media.php");

        // Download url to a temp file
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return false;
        }

        // Get the filename and extension ("photo.png" => "photo", "png")
        $filename = pathinfo($url, PATHINFO_FILENAME);
        $extension = pathinfo($url, PATHINFO_EXTENSION);

        // Upload by "sideloading": "the same way as an uploaded file is handled by media_handle_upload"
        $args = array(
            'name' => "$filename.$extension",
            'tmp_name' => $tmp,
        );

        // Do the upload
        $attachment_id = media_handle_sideload($args);

        // Cleanup temp file
        @unlink($tmp);

        // Error uploading
        if (is_wp_error($attachment_id)) {
            return false;
        }

        // Success, return attachment ID (int)
        return (int)$attachment_id;
    }

    /**
     * Export Options Lists to json
     *
     * @param array $options
     *
     * @return array $result
     */
    function export_options_lists($posts = 'all')
    {
        $response = ['status' => true, 'data' => []];
        $args = array(
            'post_type' => self::$CPT,
            'posts_per_page' => -1
        );

        if (!($posts == 'all')) {
            if (is_array($posts) && !empty($posts)) {
                $args['post__in'] = $posts;
            } else {
                $args['post__in'] = [];
            }
        }
        add_filter('pre_get_posts', array($this, 'suppress_filters'), 999, 1);
        $opt_query = new WP_Query($args);
        remove_filter('pre_get_posts', array($this, 'suppress_filters'), 999);
        $result = [];
        if ($opt_query->have_posts()) {
            while ($opt_query->have_posts()) {
                $opt_query->the_post();
                $element = [];
                $element['title'] = get_the_title();
                $element['settings'] = json_decode(get_post_meta(get_the_ID(), self::$META_KEY_1, true));

                $result[] = $element;
            }
        }
        wp_reset_postdata();

        // if ($posts == 'all') {
        //     $this->download_send_headers("wcpa-options-lists-export-" . date('d-m-Y') . ".json");
        //     $df = fopen("php://output", 'w');
        //     fwrite($df, json_encode($result));
        //     fclose($df);
        //     exit;
        // }

        $response['data'] = $result;

        return $response;
    }

    /**
     * Import Options Lists from json
     * @return array $result
     */
    public function import_bulk_options_lists($post_data)
    {
        $response = ['status' => true];

        $data = [];
        $headings = [];

        $uploadImages = (isset($post_data['uploadImages']) && ($post_data['uploadImages'] == 'true'))
            ? true : false;
        if (isset($_FILES['file']) && !empty($_FILES['file'])) {
            $json_file = $_FILES['file'];
            if (isset($json_file['type']) && $json_file['type'] == 'application/json') {
                if (is_uploaded_file($json_file["tmp_name"])) {
                    $jsonData = file_get_contents($json_file["tmp_name"]);
                    $posts = json_decode($jsonData);
                    if (!empty($posts)) {
                        foreach ($posts as $p) {
                            $uniqueId = "wcpa-options-list-" . uniqid(rand(0, 10), false);

                            $settings = $p->settings;
                            if (in_array('image', $settings->fields)) {
                                if (isset($settings->groups) && !empty($settings->groups)) {
                                    foreach ($settings->groups as $index => $group) {
                                        if (isset($group->options) && !empty($group->options)) {
                                            foreach ($group->options as $key => $option) {
                                                $newOptions = $option;
                                                $imageField = isset($option->image) ? (array)$option->image : [
                                                    'id' => '', 'url'
                                                ];
                                                $newOptions->image = (object)$this->may_be_add_media_to_library($imageField,
                                                    $uploadImages);
                                                $group->options[$key] = $newOptions;
                                            }
                                        }
                                    }
                                }
                            }

                            $settings_json = wp_slash(json_encode($settings));

                            $lang = false;
                            if ($this->ml->is_active()) {
                                $lang = $this->ml->default_language();
                            }
                            $this->insert($p->title, $uniqueId, $settings_json, $lang);
                        }
                    }
                }
            }
        }


        return $response;
    }

    public function translate_options($post_id, $newLang)
    {
        $this->init();
        // check if post has already translation in the same lang
        $langList = $this->ml->get_post_translations_links($post_id);

        $base_form_id = $this->ml->base_form($post_id);

        //check $newLang if in $langList object array
        foreach ($langList as $l) {
            if ($l['code'] == $newLang) {
                // a translation already exists in this lang, so redirect to that form
                return ['status' => true, 'new_post_id' => $l['post_id']];
            }
        }
        // creating a new form with details from base form;
        $originalPost = get_post($base_form_id);
        $title = $originalPost->post_title . ' - ' . $newLang;
        /**  get_the_title(  ) converts special characters */
        $optionsData = get_post_meta($base_form_id, self::$META_KEY_1, true);
        // $uniqKey = get_post_meta($base_form_id, self::$META_KEY_2, true); //Commented to translated optionlist global connection
        $uniqueId = get_post_meta($base_form_id, self::$META_KEY_2, true);

        // $uniqueId = uniqSectionId();
        $new_post_id = $this->insert($title, $uniqueId, $optionsData, $newLang, $base_form_id);
        if ($new_post_id) {
            return [
                'status' => true,
                'new_post_id' => $new_post_id,
                // 'redirect' => get_edit_post_link($new_post_id, 'link')
                'redirect' => admin_url('admin.php?page=wcpa-admin-ui#/option/' . $new_post_id)

            ];
        }

        return ['status' => false];
    }

/**
     * merging optionlist with different languages
     *
     * @param $base_id optionlist base language id,
     * @param $tran_id
     *
     * @return array|string
     */
    public function merge_meta($base_id, $tran_id)
    {
        $original = $this->get_optionlist_meta_data($base_id);

        $trans = $this->get_optionlist_meta_data($tran_id);

        if ($original && $trans) {
            foreach ($original->groups as $key => $data) {
                foreach ($trans->groups as $g => $tg) {
                    if ($tg->id == $data->id) {
                        $original->groups[$key] = $this->merge_data($data, $tg);
                    }

                }
                foreach ($data->options as $i => $col) {
                    $flag = false;
                    foreach ($trans->groups[0]->options as $j => $_col) {
                        if ($_col->id == $col->id) {
                            $original->groups[0]->options[$i] = $this->merge_data($col, $_col);

                            $flag = true;
                            break;
                        }
                        if ($flag) {
                            break;
                        }
                    }
                }
            }
}

        $settings_json = wp_slash(json_encode($original));
        update_post_meta($tran_id, self::$META_KEY_1, $settings_json);
    }

    /**
     *  Merge each fields data with translated version, here it limits to certain fields only, not syncing all fields,
     * only fields which are translatable are synced
     *
     * @param $base_data
     * @param $trans_data
     *
     * @return mixed
     */
    public function merge_data($base_data, $trans_data)
    {
        $keys = array(
            'label',
            'tooltip',
            'description'
        );

        foreach ($keys as $key => $val) {
            if (isset($trans_data->{$val}) && !isEmpty($trans_data->{$val})) {
                $base_data->{$val} = $trans_data->{$val};
            }
        }

        return $base_data;
    }

    public function get_optionlist_meta_data($form_id)
    {
        $json_string = get_post_meta($form_id, self::$META_KEY_1, true);

        $json_decode = json_decode($json_string);

        return $json_decode;
    }
}