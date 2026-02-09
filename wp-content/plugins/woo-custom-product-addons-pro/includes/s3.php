<?php


namespace Acowebs\WCPA;


/*
 *  Tus php wrapper
 */


use Aws\Exception\InvalidRegionException;
use Aws\S3\Exception;
use Aws\S3\S3Client;

class S3
{
    protected $uploadDir;
    protected $active;
    protected $config;

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
            $this->config = Config::get_config('cloud');
            if (isset($this->config['key']) && !empty($this->config['key'])) {
                $this->active = true;
            }

        }

    }

    public function isActive()
    {
        return $this->active;
    }

    public function verify($creds)
    {


        try {
            // Create the S3 client.
            $s3 = new S3Client([
                'version' => 'latest',
                'region' => $creds['region'],
                'use_accelerate_endpoint' => false,
                'use_aws_shared_config_files' => false,
                'credentials' => [
                    'key' => $creds['key'],
                    'secret' => $creds['secret'],
                ],
            ]);
        } catch (InvalidRegionException $e) {
            return $e->getMessage();
        }
        try {

            $bucket = $creds['bucket'];

            $result = $s3->listObjects([
                'Bucket' => $bucket
            ]);
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function serve($file)
    {

        if (!$this->active) {
            return false;
        }

        //  $this->init();
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: GET");


        $awsEndpoint = '';
        $awsRegion = '';
        if ($this->config['service'] == 's3') {
            $awsRegion = $this->config['region'];
        } else if ($this->config['service'] == 'space') {
            $awsEndpoint = $this->config['region'];
        }
        $bucket = $this->config['bucket'];
        $directory = $this->config['directory'];

        $customer = new Customer();
        $file_directory = $customer->upload_directory_base(true);
        if($directory!==''){
            $directory = $directory.'/';
        }
        $directory = $directory .$file_directory;


        // Create the S3 client.
        $s3 = $this->getClient();

        $filename = $file['filename'];
        $contentType = $file['type'];
        $fileInfo = pathinfo($filename);
        $filename = $fileInfo['filename'] . '-' . uniqid() . '.' . $fileInfo['extension'];
        // Prepare a PutObject command.
        $command = $s3->getCommand('putObject', [
            'Bucket' => $bucket,
            'Key' => "{$directory}/{$filename}",
            'ContentType' => $contentType,
            'Body' => '',
        ]);

        $request = $s3->createPresignedRequest($command, '+5 minutes');

        header('content-type: application/json');
        echo json_encode([
            'method' => $request->getMethod(),
            'url' => (string)$request->getUri(),
            'fields' => [],
            // Also set the content-type header on the request, to make sure that it is the same as the one we used to generate the signature.
            // Else, the browser picks a content-type as it sees fit.
            'headers' => [
                'content-type' => $contentType,
            ],
        ]);

    }

    public function getClient()
    {

        $args = [
            'version' => 'latest',

            'use_accelerate_endpoint' => false,
            'use_aws_shared_config_files' => false,
            'credentials' => [
                'key' => $this->config['key'],
                'secret' => $this->config['secret'],
            ],
        ];
        $awsRegion = '';
        if ($this->config['service'] == 's3') {
            $args['region'] = $this->config['region'];
        }else{
            $args['region'] = 'auto';

            $args['endpoint'] = $this->config['region'];
        }

        // Create the S3 client.
        return new S3Client($args);

    }

    public function moveFile($file)
    {
        $s3 = $this->getClient();
        $customer = new Customer();
        $file_directory = $customer->upload_directory_base();
//        $s3->getCommand('CopyObject', [
//            'Bucket' => $this->config['bucket'],
//            'Key' => $file_directory . '/' . basename($file),
//            'CopySource' => $file,
//        ]);
        try {
            $directory = $this->config['directory'];
            if($directory!==''){
                $directory = $directory.'/';
            }
            $path = $directory. $file_directory . '/' . basename($file);
            $s3->copyObject([
                'Bucket' => $this->config['bucket'],
                'CopySource' => $file,
                'Key' => $path
            ]);

            //delete temp file after moving file
            $s3->deleteObject([
                'Bucket' => $this->config['bucket'],
                'Key' => $file
            ]);
            return $s3->getObjectUrl($this->config['bucket'], $path);
        } catch (Exception $e) {
            echo 'Error copying file: ' . $e->getMessage();
        }

    }
//    public function getFile($key)
//    {
//        $server = new Server();
//        $cache = $server->getCache();
//        if (!$fileMeta = $cache->get($key)) {
//            return false;
//        }
//        return $fileMeta;
//    }
//
//    public function deleteFile($key)
//    {
//        $server = new Server();
//
//        $cache = $server->getCache();
//        $cache->delete($key);
//    }
    public function deleteTemp()
    {
        if ($this->active) {
            $s3 = $this->getClient();
            $bucket = $this->config['bucket'];
            $directory = $this->config['directory'];

            if($directory!==''){
                $directory = $directory.'/';
            }
            $directory = $directory . WCPA_UPLOAD_DIR . '/wcpa_temp/';


            try {
                $result = $s3->listObjects([
                    'Bucket' => $bucket,
                    'Prefix' => $directory,
                ]);

                // Current timestamp
                $currentTimestamp = time();
                foreach ($result['Contents'] as $object) {
                    // Get the timestamp of the object
                    $objectTimestamp = strtotime($object['LastModified']);

                    // Calculate the time difference in seconds
                    $timeDifference = $currentTimestamp - $objectTimestamp;

                    // Check if the object is older than 24 hours (86400 seconds)
                    if ($timeDifference > 86400) {
                        // Delete the object
                        try {
                            $s3->deleteObject([
                                'Bucket' => $bucket,
                                'Key' => $object['Key'],
                            ]);
                            // echo "Deleted object: {$object['Key']}\n";
                        } catch (AwsException $e) {
                            // echo "Error deleting object: {$object['Key']}: {$e->getMessage()}\n";
                        }
                    }
                }
            } catch (AwsException $e) {
                //  echo "Error listing objects: {$e->getMessage()}\n";
            }
        }

    }
}