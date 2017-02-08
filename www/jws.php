<?php

require __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/Constants.php';

$logger = \Macromill\CORe\VC\LoggerFactory::create('jws');

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
    $logger->info($decoded);
} catch (\Gamegos\JWS\Exception\InvalidSignatureException $e) {
    $logger->error("InvalidSignature\n");
}
