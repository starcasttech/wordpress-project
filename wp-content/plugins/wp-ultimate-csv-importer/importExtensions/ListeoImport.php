<?php
/******************************************************************************************
 * Copyright (C) Smackcoders. - All Rights Reserved under Smackcoders Proprietary License
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * You can contact Smackcoders at email address info@smackcoders.com.
 *******************************************************************************************/

namespace Smackcoders\FCSV;

if (!defined('ABSPATH')) exit;

class ListeoImport {

    private static $listeo_instance = null;

    public static function getInstance() {
        if (self::$listeo_instance === null) {
            self::$listeo_instance = new ListeoImport;
        }
        return self::$listeo_instance;
    }

    /**
     * Main entry for CSV import
     *
     * @param array $header_array CSV headers
     * @param array $value_array CSV row values
     * @param array $map Mapping array for Listeo fields
     * @param int|null $user_id Optional user ID
     * @param string $type
     * @param string $hash_key
     * @param string $gmode
     * @param string $templatekey
     * @param int|null $line_number CSV line number for logging
     */
    public function set_listeo_values(
        $header_array,
        $value_array,
        $map,
        $user_id = null,
        $type = '',
        $hash_key = '',
        $gmode = '',
        $templatekey = '',
        $line_number = null
    ) {

        $helpers_instance = ImportHelpers::getInstance();

        $user_values = $helpers_instance->get_header_values($map, $header_array, $value_array);

        if (!is_array($user_values)) {
            $keys = array_values($map);
            $values = array_values($value_array);

            if (count($keys) > count($values)) {
                $values = array_pad($values, count($keys), '');
            } elseif (count($values) > count($keys)) {
                $keys = array_pad($keys, count($values), '');
            }

            $user_values = array_combine($keys, $values);
        }

        if (empty($user_id)) {
            $email = '';
            if (!empty($user_values['user_email'])) {
                $email = trim($user_values['user_email']);
            } elseif (!empty($user_values['email'])) {
                $email = trim($user_values['email']);
            }

            if ($email) {
                $user_obj = get_user_by('email', $email);
                $user_id = $user_obj ? $user_obj->ID : 0;
            }
        }

        if (!$user_id) {
            return; 
        }

        $this->listeo_import_function($user_values, $user_id, $line_number);
    }

    /**
     * Core import function
     *
     * @param array $data_array key => value for user meta
     * @param int $user_id WordPress user ID
     * @param int|null $line_number CSV line number
     * @return array Imported meta fields
     */
    private function listeo_import_function($data_array, $user_id, $line_number = null) {

        $createdFields = [];

        foreach ($data_array as $meta_key => $meta_value) {

            $createdFields[] = $meta_key;

            try {
                if ($meta_key === 'listeo_core_avatar_id' && !empty($meta_value)) {
                    $attachment_id = is_numeric($meta_value) ? $meta_value : attachment_url_to_postid($meta_value);
                    if ($attachment_id) {
                        update_user_meta($user_id, $meta_key, $attachment_id);
                    } 
                    continue;
                }

                update_user_meta($user_id, $meta_key, $meta_value);

            } catch (\Exception $e) {
            }
        }

        return $createdFields;
    }
}
