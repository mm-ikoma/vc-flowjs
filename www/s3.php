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

$srcFile = Path::join(DST_DIR, '15GB.data');

$s3Client = new Aws\S3\S3Client([
    'profile'  => S3_PROFILE,
    'version'  => S3_VERSION,
    'region'   => S3_REGION,
]);

$uploader = new Aws\S3\MultipartUploader($s3Client, $srcFile, [
    'bucket' => S3_BUCKET,
    'key'    => 'vctest',
]);

try {
    $logger->info("upload-start:{$srcFile}");
    $result = $uploader->upload();
    $logger->info("upload-done:{$srcFile}");
} catch (Aws\Exception\MultipartUploadException $e) {
    $logger->error("upload-fail:{$e->getMessage()}");
}


// --------------------
