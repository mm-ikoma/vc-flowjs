<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/Constants.php';

use Macromill\CORe\VC\SSEUtil;

$logger = \Macromill\CORe\VC\LoggerFactory::create('sse');

header('Content-Type: text/event-stream');

// イベントループ
$loop = React\EventLoop\Factory::create();

$request = new \Flow\Request();
$fileId = \Flow\Config::hashNameCallback($request);
$fileName = $request->getFileName();
$totalChunks = $request->getTotalChunks();

// 子プロセス
$childProc = new React\ChildProcess\Process("php sse-child.php -i {$fileId} -n {$fileName} -c {$totalChunks}", getcwd(), $_SERVER);
$childProc->on('exit', function ($exitCode, $termSignal) {
    if ($exitCode === 0) {
        SSEUtil::flush(['code' => $exitCode], 'done');
    } else {
        SSEUtil::flush(['code' => $exitCode], 'fail');
    }
});

// 子プロセスの起動
$loop->addTimer(0.1, function ($timer) use ($childProc) {
    $childProc->start($timer->getLoop());
    $childProc->stdout->on('data', function ($output) {
        SSEUtil::flush(['result' => $output], 'stdout');
    });
});

// ループの繰り返しタスク
$loop->addPeriodicTimer(5, function ($timer) {
    SSEUtil::flush(['time' => microtime(true)], 'ping');
});

$loop->run();
