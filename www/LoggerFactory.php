<?php

namespace Macromill\CORe\VC;

require_once __DIR__.'/Constants.php';

use Webmozart\PathUtil\Path;
use Monolog\Logger;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class LoggerFactory{

    public static function create($name){

        static $utc = null;
        if ($utc === null) {
            $utc = new \DateTimeZone('UTC');
        }

        $logger = new Logger($name);
        $formatter = new LineFormatter(null, null, true, true);
        $handler = new StreamHandler(Path::join(LOG_DIR, "{$name}.log"), Logger::INFO);
        $handler->setFormatter($formatter);
        $logger->pushHandler($handler);
        $logger->pushProcessor(function ($record) use($utc){
            $record['datetime']->setTimezone($utc);
            return $record;
        });
        ErrorHandler::register($logger);

        return $logger;

    }

}
