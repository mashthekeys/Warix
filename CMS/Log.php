<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 30/05/2014
 * Time: 19:34
 */

namespace CMS;

// TODO this sequential file-based approach is seriously unscalable
class Log {
    private static $IO;

    public static function recordEvent($type, $message, $data = array()) {
        Log::open();

        fprintf(self::$IO, "%s: %s\n", $type, $message);

        if (count($data)) {
            ob_start();
            var_dump($data);
            fwrite(self::$IO, ob_get_clean());
        }

        fprintf(self::$IO, "\n");
    }

    public static function stackTrace($message = '') {
        Log::open();

        fprintf(self::$IO, "Backtrace: %s\n", $message);

        ob_start();
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        fwrite(self::$IO, ob_get_clean());

        fprintf(self::$IO, "\n");
    }

    public static function open() {
        if (!self::$IO) {
            self::$IO = fopen(__DIR__.'/log/cms_log.txt','at');

            if (!self::$IO) {
                if (!is_dir(__DIR__.'/log')) {
                    mkdir(__DIR__.'/log');
                }

                self::$IO = fopen(__DIR__.'/log/cms_log.txt','at');

                if (!self::$IO) {
                    throw new \ErrorException('Cannot open CMS log file.');
                }
            }

            $date = date('r');
            $url = strtr($_SERVER['REQUEST_URI'],"\r\n\v",'   ');

            fwrite(self::$IO, "### CMS Log Entry ########################################\n### $date\n### $url\n");
        }
    }

    public static function flush() {
        if (self::$IO) fflush(self::$IO);
    }
    public static function close() {
        if (self::$IO) {
            fwrite(self::$IO, "\n\n");
            fclose(self::$IO);
            self::$IO = null;
        }
    }
}