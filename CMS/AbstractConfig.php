<?php
namespace CMS;


abstract class AbstractConfig {
    protected static $defined;
    protected static $conf;

    public static function define($key, $value) {
        if ($value !== null && !is_scalar($value)) {
            $value = null;
            trigger_error("Invalid configuration value for '$key' => ".gettype($value), E_USER_WARNING);
        }
        self::$conf[$key] = $value;
        self::$defined[$key] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    }

    public static function get($key, $ifEmpty = null) {
        $v = self::$conf[$key];
        return $v ? $v : $ifEmpty;
    }

    /**
     * Fetches the key value, and information on where it was defined.
     *
     * @param $key
     * @param &$isDefined bool    Returns whether or not this key has been defined.
     * @param &$definedBy array   Returns the location where this key was defined.
     * @return mixed              The value defined for the key.
     */
    public static function about($key, &$isDefined = null, &$definedBy = null) {
        $isDefined = array_key_exists($key, self::$defined);

        if ($isDefined) $definedBy = self::$defined[$key];

        return self::$conf[$key];
    }
}