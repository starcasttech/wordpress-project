<?php

namespace Acowebs\WCPA;

class Cron
{
    static $key = 'wcpa_daily_event';

    public function __construct()
    {
        add_action(self::$key, array($this, 'delete_temp_files'));
    }

    static function schedule_cron()
    {
        if ( ! wp_next_scheduled(self::$key)) {
            wp_schedule_event(time(), 'daily', self::$key);
            self::protect_upload_dir();
        }
    }

    static function protect_upload_dir()
    {
        $upload_dir = wp_upload_dir();

        $files = array(
            array(
                'base'    => $upload_dir['basedir'].'/'.WCPA_UPLOAD_DIR,
                'file'    => '.htaccess',
                'content' => 'Options -Indexes'."\n"
                             .'<Files *.php>'."\n"
                             .'deny from all'."\n"
                             .'</Files>'."\n"
                             .'<Files "*">'."\n"
                             .' Header set X-Robots-Tag "noindex, nofollow"'."\n"
                             .'</Files>'."\n"
            )
        ,
            array(
                'base'    => $upload_dir['basedir'].'/'.WCPA_UPLOAD_DIR,
                'file'    => 'index.php',
                'content' => '<?php '."\n"
                             .'// Silence is golden.'
            ) ,
            array(
                'base'    => $upload_dir['basedir'].'/'.WCPA_UPLOAD_DIR.'/wcpa_temp/tus',
                'file'    => 'index.php',
                'content' => '<?php '."\n"
                             .'// Silence is golden.'
            )
        );

        foreach ($files as $file) {
            if ((wp_mkdir_p($file['base'])) && ( ! file_exists(trailingslashit($file['base']).$file['file']))  // If file not exist
            ) {
                if ($file_handle = @fopen(trailingslashit($file['base']).$file['file'], 'w')) {
                    fwrite($file_handle, $file['content']);
                    fclose($file_handle);
                }
            }
        }
    }

    static function clear()
    {
        wp_clear_scheduled_hook(self::$key);
        refreshCaches();
    }


    public function delete_temp_files()
    {

        $upload    = wp_upload_dir();
        $directory = $upload['basedir'].'/'.WCPA_UPLOAD_DIR.'/wcpa_temp/';
        $this->delete_files_in_directory('/wcpa_temp/', $directory, 60 * 60 * 24 * 1);

        // Delete files That are older than a duration
        $deleteOrderFiles = apply_filters('wcpa_delete_old_order_upload_files',false);
        if($deleteOrderFiles){
            $wcpaUploads = $upload['basedir'] . '/' . WCPA_UPLOAD_DIR . '/';
            $deleteOrderFilesDuration = apply_filters('wcpa_delete_old_order_upload_files_duration',60*60*24*365);
            if($deleteOrderFilesDuration<60*60*24*30){ // protection to ensure customer add time less than one month
                $deleteOrderFilesDuration = 60*60*24*30;
            }
            $this->delete_files_in_directory('/' . WCPA_UPLOAD_DIR . '/', $wcpaUploads, $deleteOrderFilesDuration, true, 'wcpa_temp');
        }

        self::protect_upload_dir();

        $s3 = new S3();
        $s3->deleteTemp();
    }

    /**
     * Delete all files in a directory
     */
    private function delete_files_in_directory($base_dir, $directory, $duration, $dir_only = false, $exclude = '')
    {
        $now   = time();
        $files = glob($directory."*");

        if ($files) {
            foreach ($files as $file) {
                if (
                    is_file($file) &&
                    ! $dir_only &&
                    (($exclude == '') ? true : (strpos($file, $exclude) === false))
                ) {
                    if ($now - filemtime($file) >= $duration) {
                        if (strpos($file, $base_dir) !== false) {
                            wp_delete_file($file);
                        }
                    }
                } elseif (
                    is_dir($file) &&
                    (($exclude == '') ? true : (strpos($file, $exclude) === false))
                ) {
                    $files_sub = glob($file."/*");

                    foreach ($files_sub as $file_sub) {
                        if (is_file($file_sub)) {
                            if ($now - filemtime($file_sub) >= $duration) {
                                if (strpos($file_sub, $base_dir) !== false) {
                                    wp_delete_file($file_sub);
                                }
                            }
                        }
                    }
                    $files_sub = glob($file."/*");
                    if (count($files_sub) === 0) {
                        if (strpos($file, $base_dir) !== false) {
                            rmdir($file);
                        }
                    }
                }
            }
        }
    }

}