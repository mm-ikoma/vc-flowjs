<?php

require __DIR__.'/../vendor/autoload.php';

use Webmozart\PathUtil\Path;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

define('LOG_DIR', Path::join(__DIR__, 'logs'));

$logger = new Logger('async');
$logger->pushHandler(new StreamHandler(Path::join(LOG_DIR, 'jws.log'), Logger::DEBUG));

$headers = [
    'alg' => 'HS256', //alg is required. see *Algorithms* section for supported algorithms
    'typ' => 'JWT'
];

// anything that json serializable
$payload = [
    'mid' => '900000',
    'cid' => '1'
];

$key = 'some-secret-for-hmac';

$jws = new \Gamegos\JWS\JWS();

$jwsString = $jws->encode($headers, $payload, $key);
// $jwsString = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtaWQiOiI5MDAwMDEiLCJjaWQiOiIxIn0.ppqbrrx_I0pdXXiScpSv2U6Y73XPjsZxK-uzQor5DmA";

try {
    $decoded = $jws->verify($jwsString, $key);
    print_r($decoded);
} catch (\Gamegos\JWS\Exception\InvalidSignatureException $e) {
    echo "InvalidSignature\n";
}
