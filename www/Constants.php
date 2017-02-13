<?php

use Webmozart\PathUtil\Path;

define('TMP_DIR', Path::join(__DIR__, 'temp'));
define('DST_DIR', Path::join(__DIR__, 'dest'));
define('LOG_DIR', Path::join(__DIR__, 'logs'));

define('S3_PROFILE', 'default');
define('S3_VERSION', 'latest');
define('S3_REGION', 'ap-northeast-1');
define('S3_BUCKET', 'test-custom');
