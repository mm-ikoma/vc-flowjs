<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/Constants.php';

use Webmozart\PathUtil\Path;
use Monolog\Logger;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;

// Logs
// --------------------
$logger = new Logger('common');
$logger->pushHandler(new StreamHandler(Path::join(LOG_DIR, 'common.log'), Logger::DEBUG));
ErrorHandler::register($logger);

$headers = getallheaders();
if (isset($headers['Content-Type']) && strpos($headers['Content-Type'], 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
    $logger->addRecord($data['level'], "{$data['name']}:".json_encode($data['payload']));
    http_response_code(204);
} else {
    http_response_code(400);
}
