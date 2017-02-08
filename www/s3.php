<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/Constants.php';

use Webmozart\PathUtil\Path;

// --------------------
// Logs
// --------------------
$logger = \Macromill\CORe\VC\LoggerFactory::create('s3');

// --------------------
// S3
// --------------------
use Aws\S3\MultipartUploader;
use Aws\Exception\MultipartUploadException;

$s3Client = new Aws\S3\S3Client([
    'profile'  => 'default',
    'version'  => 'latest',
    'region'   => 'ap-northeast-1',
]);

$bucket = 'test-custom';
$key = 'vctest';
$srcFile = Path::join(DST_DIR, '15GB.data');

$logger->info("put-start:{$srcFile}");

$uploader = new MultipartUploader($s3Client, $srcFile, [
    'bucket' => $bucket,
    'key'    => $key,
]);

try {
    $result = $uploader->upload();
    $logger->info("put-done:{$srcFile}");
} catch (MultipartUploadException $e) {
    $logger->error("put-fail:{$e->getMessage()}");
}


// --------------------
