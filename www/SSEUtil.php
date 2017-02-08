<?php

namespace Macromill\CORe\VC;

class SSEUtil {

    public static function flush(array $data, $event = ''){
        static $counter = 0;
        $event = trim($event);
        $encoded = trim(json_encode($data));
        $id = $counter++;
        // ob_start(null, 0, PHP_OUTPUT_HANDLER_FLUSHABLE);
        echo "event: $event\n";
        echo "data: $encoded\n";
        echo "id: $id\n\n";
        // error_log(var_export(ob_end_flush(), true));
        // ob_flush();
        ob_end_flush();
        flush();
    }

}
