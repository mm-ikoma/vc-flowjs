<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/Constants.php';

use Webmozart\PathUtil\Path;
use Macromill\CORe\VC\SSEUtil;

$logger = \Macromill\CORe\VC\LoggerFactory::create('sse-child');
$handleError = function($message) use($logger){
    $logger->error($message);
    die(1);
};

$opts = filter_var_array(getopt('i:n:c:'), [
    'i' => FILTER_DEFAULT,
    'n' => FILTER_DEFAULT,
    'c' => FILTER_VALIDATE_INT,
]);

if ($opts === false) {
    $handleError('Illegal arguments.'.print_r($opts, true));
}

$logger->info(print_r($opts, true));

// 全chunkのアップロードが完了したFlow\Fileを復元
$fileId = $opts['i'];
$logger->info("start:{$fileId}");
$wholePath = Path::join(TMP_DIR, "{$fileId}_whole");
if (!file_exists($wholePath)) {
    $handleError("failed to open whole: {$fileId}");
}
$serialized = file_get_contents($wholePath);
$flowFile = unserialize($serialized);

if ($flowFile->validateFile()) {

    // 1. Instantiate the client.
    $s3Client = new Aws\S3\S3Client([
        // 'profile'  => S3_PROFILE,
        // 'credentials' => Aws\Credentials\CredentialProvider::ini('default', '/home/ec2-user/.aws/credentials'),
        'credentials' => Aws\Credentials\CredentialProvider::env(),
        'version'  => S3_VERSION,
        'region'   => S3_REGION,
    ]);

    // 2. Create a new multipart upload and get the upload ID.
    $key = $opts['n'];
    $response = $s3Client->createMultipartUpload([
        'Bucket' => S3_BUCKET,
        'Key'    => $key,
    ]);

    $parts = [];
    $logger->info("{$fileId} chunks={$opts['c']}");
    for ($i = 1; $i <= $opts['c']; $i++) {
        $chunkPath = $flowFile->getChunkPath($i);
        $chunk = fopen($chunkPath, "rb");
        if (!$chunk) {
            $handleError("failed to open chunk: {$chunkPath}");
        }
        // 3. Upload the file in parts.
        $result = $s3Client->uploadPart([
            'Bucket'     => S3_BUCKET,
            'Key'        => $key,
            'UploadId'   => $response['UploadId'],
            'PartNumber' => $i,
            'Body'       => $chunk,
        ]);
        $parts[] = [
            'PartNumber' => $i,
            'ETag'       => $result['ETag'],
        ];
        fclose($chunk);
    }

    // 4. Complete multipart upload.
    $result = $s3Client->completeMultipartUpload([
        'Bucket'   => S3_BUCKET,
        'Key'      => $key,
        'UploadId' => $response['UploadId'],
        'MultipartUpload' => [
            'Parts' => $parts
        ],
    ]);

    $flowFileArray = (array)$flowFile;
    while($e = array_shift($flowFileArray)){
        if ($e instanceof Flow\Config) {
            if ($e->getDeleteChunksOnSave()) {
                $flowFile->deleteChunks();
            }
        }
    }

    if(unlink($wholePath)){
        $logger->warn("Cannot unlink. Please delete {$wholePath}");
    }

    $logger->info("end:{$fileId} URL:{$result['Location']}");

} else {
    $handleError("$fileId is invalid.");
}
