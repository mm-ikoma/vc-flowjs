<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/Constants.php';

use Webmozart\PathUtil\Path;

header('Access-Control-Allow-Origin: *');

// --------------------
// Logs
// --------------------
$logger = \Macromill\CORe\VC\LoggerFactory::create('vc-flowjs');

$config = new \Flow\Config([
    'tempDir' => TMP_DIR,
]);
$request = new \Flow\Request();
$flowFile = new \Flow\File($config, $request);
$file = $request->getFile();

$destination = Path::join(DST_DIR, $request->getFileName());
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contents = [
        'id' => $flowFile->getIdentifier(),
        'tmp_name' => $file['tmp_name'],
        'size' => $file['size'],
        'error' => $file['error'],
        'currentChunkSize' => $request->getCurrentChunkSize()
    ];
    $logger->debug(json_encode($contents));
}

$save = function($destination, \Flow\ConfigInterface $config, \Flow\RequestInterface $request = null) use($logger)
{
    $flowFile = new \Flow\File($config, $request);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($flowFile->checkChunk()) {
            header("HTTP/1.1 200 Ok");
        } else {
            // The 204 response MUST NOT include a message-body, and thus is always terminated by the first empty line after the header fields.
            header("HTTP/1.1 204 No Content");
            return false;
        }
    } else {

        $badRequest = function($message) use($logger){
            // error, invalid chunk upload request, retry
            $logger->error($message);
            header("HTTP/1.1 400 Bad Request");
            return false;
        };

        $chunkId = "{$request->getIdentifier()}-{$request->getCurrentChunkNumber()}";

        if (!$flowFile->validateChunk()) {
            $file = $request->getFile();
            return $badRequest("Invalid chunk. id={$chunkId} tmp_name={$file['tmp_name']} size={$file['size']} error={$file['error']}");
        }

        $chunkPath = $flowFile->getChunkPath($request->getCurrentChunkNumber());
        if (!move_uploaded_file($request->getFile()['tmp_name'], $chunkPath)) {
            return $badRequest("move_uploaded_file() failed. id={$chunkId}");
        }

        $paramFingerPrint = $request->getParam('flowChunkFingerPrint');
        if (!$paramFingerPrint) {
            return $badRequest("No finger print. id={$chunkId}");
        }

        // Validate finger print.
        $chunkHandle = fopen($chunkPath, "rb");
        $realChunkSize = filesize($chunkPath);
        fseek($chunkHandle, $realChunkSize - 32);
        $chunkContents = fread($chunkHandle, 32);
        fclose($chunkHandle);
        $fileFingerPrint = bin2hex($chunkContents);
        if ($paramFingerPrint != $fileFingerPrint) {
            return $badRequest("Unmatch finger print. id={$chunkId}, param={$paramFingerPrint} file={$fileFingerPrint}");
        }

        if ($request->getCurrentChunkNumber() == 1) {
            $logger->info("Start uploading. {$flowFile->getIdentifier()}");
        }

    }

    if ($flowFile->validateFile()) {
        $serialized = serialize($flowFile);
        $wholePath = Path::join(TMP_DIR, "{$flowFile->getIdentifier()}_whole");
        if(file_put_contents($wholePath, $serialized) === false){
            return $badRequest("Whole file write error. {$flowFile->getIdentifier()}");
        }
        return true;
    }

    return false;
};

if ($save($destination, $config, $request)) {
    $logger->info("File was saved in {$destination}");
}
