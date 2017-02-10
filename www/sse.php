<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/Constants.php';

use Webmozart\PathUtil\Path;
use Icicle\Awaitable\Promise;
use Icicle\Loop;
use Macromill\CORe\VC\SSEUtil;

$logger = \Macromill\CORe\VC\LoggerFactory::create('sse');

$request = new \Flow\Request();

header("Content-Type: text/event-stream");

// ポーリング
Loop\periodic(1, function () use($logger) {
    // ping
    SSEUtil::flush(['time' => microtime(true)], 'ping');
});

// メイン
$promise = new Promise(function (callable $resolve, callable $reject) use($request, $logger) {

    // chunkのアップロードが完了したFlow\Fileを復元
    $fileId = \Flow\Config::hashNameCallback($request);
    $logger->info("start:{$fileId}");
    $wholePath = Path::join(TMP_DIR, "{$fileId}_whole");
    if (!file_exists($wholePath)) {
        $reject(new FileOpenException("failed to open whole: {$fileId}"));
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
        $key = $request->getFileName();
        $response = $s3Client->createMultipartUpload([
            'Bucket' => S3_BUCKET,
            'Key'    => $key,
        ]);

        $parts = [];
        $logger->info("{$key} chunks={$request->getTotalChunks()}");
        for ($i = 1; $i <= $request->getTotalChunks(); $i++) {
            $chunkPath = $flowFile->getChunkPath($i);
            $chunk = fopen($chunkPath, "rb");
            if (!$chunk) {
                $reject(new FileOpenException("failed to open chunk: {$chunkPath}"));
            }
            // 3. Upload the file in parts.
            $result = $s3Client->uploadPart(array(
                'Bucket'     => S3_BUCKET,
                'Key'        => $key,
                'UploadId'   => $response['UploadId'],
                'PartNumber' => $i,
                'Body'       => fread($chunk, filesize($chunkPath)),
            ));
            $parts[] = [
                'PartNumber' => $i,
                'ETag'       => $result['ETag'],
            ];
            fclose($chunk);
        }

        $logger->info(print_r($parts, true));

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

        $resolve("{$fileId} URL:{$result['Location']}");

    } else {
        $reject(new Exception("$fileId is invalid."));
    }
});
$promise->done(
    function ($data) use($logger){
        // resolve
        $logger->info(" done:{$data}");
        SSEUtil::flush(['data' => $data], 'done');
    },
    function (\Exception $ex) use($logger){
        // reject
        $logger->error(" fail:{$ex->getMessage()}:{$ex->getTraceAsString()}");
        SSEUtil::flush(['data' => $ex->getMessage()], 'fail');
    }
);
Loop\run();
