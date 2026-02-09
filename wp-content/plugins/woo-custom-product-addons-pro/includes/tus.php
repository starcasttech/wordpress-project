<?php


namespace Acowebs\WCPA;


/*
 *  Tus php wrapper
 */

class Tus
{
    protected $uploadDir;
    protected $active;

    public function __construct()
    {

        $addons = addonsList();
        $this->active = false;
        if (isset($addons['wcpa_upload'])) {
            $addonPath = $addons['wcpa_upload'];
            include $addonPath . '/vendor/autoload.php';
            $upload = wp_upload_dir();
            $this->uploadDir = $upload['basedir'] . '/' . WCPA_UPLOAD_DIR;
            putenv('TUS_CACHE_HOME=' . $this->uploadDir);
            $this->active = true;
        }

    }


    public function isActive()
    {
        return $this->active;
    }

    public function serve()
    {
        $server = new \TusPhp\Tus\Server();
        $server->setUploadDir($this->uploadDir . '/wcpa_temp/tus');
        $server->setApiPath(site_url('/wp-json/wcpa/front/tus_upload'));
        $response = $server->serve();
        $response->send();
        exit(0);

    }

    public function getFile($key)
    {
        $server = new \TusPhp\Tus\Server();
        $cache = $server->getCache();
        if (!$fileMeta = $cache->get($key)) {
            return false;
        }
        return $fileMeta;
    }

    public function deleteFile($key)
    {
        $server = new \TusPhp\Tus\Server();

        $cache = $server->getCache();
        $cache->delete($key);
    }
}