<?php
namespace CMS;


class Config {
    private static $defined;
    private static $conf;

    public static function define($key, $value) {
        if ($value === null || is_scalar($value)) {
            self::$conf[$key] = $value;
            self::$defined[$key] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        } else {
            trigger_error("Invalid configuration value for '$key' => ".gettype($value), E_USER_WARNING);
        }
    }

    public static function get($key, $ifEmpty = null) {
        $v = self::$conf[$key];
        return $v ? $v : $ifEmpty;
    }

    public static function about($key, &$isDefined = null, &$definedBy = null) {
        $isDefined = array_key_exists($key, self::$conf);

        if ($isDefined) $definedBy = self::$defined[$key];

        return self::$conf[$key];
    }
}