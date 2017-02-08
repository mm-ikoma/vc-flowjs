<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/Constants.php';

use Webmozart\PathUtil\Path;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

header('Access-Control-Allow-Origin: *');

// Logger
$logPath = Path::join(LOG_DIR, 'vc-flowjs.log');
$logger = new Logger('vc-flowjs');
$logger->pushHandler(new StreamHandler($logPath, Logger::DEBUG));
\Monolog\ErrorHandler::register($logger);

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
            $logger->debug($message);
            header("HTTP/1.1 400 Bad Request");
            return false;
        };

        $chunkId = "{$request->getIdentifier()}-{$request->getCurrentChunkNumber()}";

        if (!$flowFile->validateChunk()) {
            return $badRequest("Invalid chunk. id={$chunkId}");
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

    }

    if ($flowFile->validateFile()) {
        $serialized = serialize($flowFile);
        $wholePath = Path::join(TMP_DIR, "{$flowFile->getIdentifier()}_whole");
        if(file_put_contents($wholePath, $serialized) === false){
            return $badRequest("Whole file write error.");
        }
        return true;
    }

    return false;
};

if ($save($destination, $config, $request)) {
    $logger->debug("File was saved in {$destination}");
}
