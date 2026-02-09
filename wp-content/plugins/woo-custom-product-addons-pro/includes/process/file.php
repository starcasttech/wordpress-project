<?php


namespace Acowebs\WCPA;
/**
 * Handle File Uploads
 * Class File
 * @package Acowebs\WCPA
 */
class File
{

    public function __construct()
    {

    }

    public function move_file($field, $file)
    {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $filesTemp = array();
        if(WC()->session){
            $filesTemp =   WC()->session->get('wcpa_upload_file_temp');
            if(!is_array($filesTemp)){
                $filesTemp = array();
            }
        }


        if (activeUploadMethod() === 'tus' && isset($file->file)) {
            $key = basename(parse_url($file->file, PHP_URL_PATH));
            $tus = new Tus();
            if (!$fileMeta = $tus->getFile($key)) {
                $hasFile = false;
                if(is_array($filesTemp)){
                    foreach($filesTemp as $k=>$fileTemp){
                        if($fileTemp['tusKey'] == $key){
                            $fileMeta = ['file_path'=>$fileTemp['tusFilePath'],'name'=>$fileTemp['filename']];
                            $hasFile = true;
                        }
                    }
                }
                if(!$hasFile){
                    return false;
                }
                // return false;
            }
            $uploadedFile = $fileMeta['file_path'];
            $uploadedFileName = $fileMeta['name'];

        }else   if (activeUploadMethod() === 'cloud' && isset($file->file)) {
            $s3 = new S3();
            $s3File = $s3->moveFile($file->file);

            return array(
                'file' => $s3File,
                'cloud' => 's3',
                'url' => $s3File,
                'type' => $file->type,
                'file_name' => $file->file_name
            );

        } else if (isset($file->file)) {
            $_file = $file->file;
            $uploadedFileName = $file->file_name;
        } else {
            if(isset($file['url'])){
                // in case cart rebuild klavio
               return array(
                    'file' => wp_normalize_path($file['url']),
                    'url' => $file['url'],
                    'type' => $file['type'],
                    'file_name' => $file['file_name']
                );
            }
            return false;
        }

        $customer = new Customer();
        $file_directory = $customer->upload_directory_base();
        if (WCPA_UPLOAD_CUSTOM_BASE_DIR == false) {
            $upload = wp_upload_dir();
        } else {
            $upload = WCPA_UPLOAD_CUSTOM_BASE_DIR;
            $upload['path'] = ABSPATH . '/' . WCPA_UPLOAD_CUSTOM_BASE_DIR;
            $upload['url'] = get_option('siteurl') . '/' . WCPA_UPLOAD_CUSTOM_BASE_DIR;
        }

        $uploadedFile = isset($_file) ? $upload['basedir'] . '/' . WCPA_UPLOAD_DIR . '/' . $_file : $uploadedFile;

        $upload['subdir'] = '/' . $file_directory;
        $upload['path'] = $upload['basedir'] . '/' . $file_directory;
        $upload['url'] = $upload['baseurl'] . '/' . $file_directory;

        $wp_filetype = wp_check_filetype_and_ext($uploadedFile, $uploadedFileName);

        $ext = empty($wp_filetype['ext']) ? '' : $wp_filetype['ext'];
        $type = empty($wp_filetype['type']) ? '' : $wp_filetype['type'];
        $proper_filename = empty($wp_filetype['proper_filename']) ? '' : $wp_filetype['proper_filename'];
        // Check to see if wp_check_filetype_and_ext() determined the filename was incorrect
        if ($proper_filename) {
            $uploadedFileName = $proper_filename;
        }

        if ((!$type || !$ext)) {
            //   $this->add_cart_error(sprintf(__('File %s could not be uploaded.', 'woo-custom-product-addons-pro'), $field->label));

            return false;
        }
        $originalFilePath = $upload['path'] . "/".$uploadedFileName;// before formatting with unique
        $filename = wp_unique_filename($upload['path'], $uploadedFileName);
        $new_file = $upload['path'] . "/$filename";
        if (!is_dir($upload['path'])) {
            wp_mkdir_p($upload['path']);
        }

        $move_new_file = @copy($uploadedFile, $new_file);
        // using a temp session storage to use if the files is missing in temp foloder, it can cause due to some plugins polling cart data before adding to cart

        if (false === $move_new_file) {
            // $this->add_cart_error(sprintf(__('File %s could not be uploaded.', 'woo-custom-product-addons-pro'), $field->label));
            //check if $uploadedFile is in $filesTemp
            if(is_array($filesTemp)){
                foreach($filesTemp as $key=>$fileTemp){
                    if($fileTemp['originalFilePath'] == $originalFilePath){
                        $new_file = $fileTemp['new_file'];
                        $filename = $fileTemp['filename'];
                        $move_new_file = true;
                    }
                }
            }
            if(!$move_new_file){
                return false;
            }
          
        }else if ( WC()->session ) {
            $filesTemp[] = ['originalFilePath'=>$originalFilePath,
                'new_file'=>$new_file,
                'filename'=>$filename,
                'tusKey'=>isset($key)?$key:false,
                'tusFilePath'=>isset($uploadedFile)?$uploadedFile:false,
            ];
            WC()->session->set('wcpa_upload_file_temp', $filesTemp);
        }

        if (Config::get_config('upload_method') === 'tus' && isset($tus)) {
            $tus->deleteFile($key);
        }
        @unlink($uploadedFile);

        $stat = stat(dirname($new_file));
        $perms = $stat['mode'] & 0000666;
        @chmod($new_file, $perms);
        // Compute the URL.
        $url = $upload['url'] . "/$filename";

        return array(
            'file' => wp_normalize_path($new_file),
            'url' => $url,
            'type' => $type,
            'file_name' => $uploadedFileName
        );
    }

    public function upload_dir_temp($upload)
    {
        $customer = new Customer();
        $file_directory = $customer->upload_directory_base(true);
        $upload['subdir'] = '/' . $file_directory;
        $upload['path'] = $upload['basedir'] . '/' . $file_directory;
        $upload['url'] = $upload['baseurl'] . '/' . $file_directory;

        return $upload;
    }

    public function upload_dir($upload)
    {

        $customer = new Customer();
        $file_directory = $customer->upload_directory_base();

        $upload['subdir'] = '/' . $file_directory;
        $upload['path'] = $upload['basedir'] . '/' . $file_directory;
        $upload['url'] = $upload['baseurl'] . '/' . $file_directory;

        return $upload;
    }

    public function handle_upload_ajax($field, $file)
    {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }


        $upload_overrides = array('test_form' => false);
        add_filter('upload_dir', array($this, 'upload_dir_temp'));

        $moveFile = wp_handle_upload($file, $upload_overrides);

        remove_filter('upload_dir', array($this, 'upload_dir_temp'));
        if ($moveFile && !isset($movefile['error'])) {
            $file_name = explode('/' . WCPA_UPLOAD_DIR . '/', $moveFile['file']);
            $moveFile['file'] = $file_name[1];

            return ['status' => true, 'file' => array_merge($moveFile, array('file_name' => $file["name"]))];
        } else {
            /**
             * Error generated by _wp_handle_upload()
             * @see _wp_handle_upload() in wp-admin/includes/file.php
             */
            return ['status' => false, 'error' => $moveFile['error']];

            //echo $movefile['error'];
        }
    }

    public function handle_upload($field, $file)
    {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $uploadedFileName = $file["name"];

        $upload_overrides = array('test_form' => false);
        add_filter('upload_dir', array($this, 'upload_dir'));
        if (!is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        $moveFile = wp_handle_upload($file, $upload_overrides);

        remove_filter('upload_dir', array($this, 'upload_dir'));

        if ($moveFile && !isset($moveFile['error'])) {
            return array_merge($moveFile, array('file_name' => $uploadedFileName));
        } else {
            /**
             * Error generated by _wp_handle_upload()
             * @see _wp_handle_upload() in wp-admin/includes/file.php
             */
            //   $this->add_cart_error($moveFile['error']);

            return false;
            //echo $movefile['error'];
        }
    }
}

