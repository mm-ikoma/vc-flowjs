<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/Constants.php';

$headers = getallheaders();
if (isset($headers['Content-Type']) && strpos($headers['Content-Type'], 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data) {
        $logger = \Macromill\CORe\VC\LoggerFactory::create('common');
        $logger->addRecord($data['level'], "{$data['name']}:".json_encode($data['payload']));
        http_response_code(204);
    } else {
        http_response_code(400);
    }
} else {
    http_response_code(400);
}
