<?php
namespace CMS;


final class Config extends AbstractConfig {
    private static $sensitive = array();
    private static $sensitiveRead = array();

    public static function defineSensitive($key, $value) {
        if ($firstRead = self::$sensitiveRead[$key]) {
            throw new \ErrorException('Cannot redefine sensitive data: '.$key, 0, 1,
                $firstRead['file'], $firstRead['line']);

        } else if ($value !== null && !is_scalar($value)) {
            trigger_error("Invalid configuration value for '$key' => ".gettype($value), E_USER_WARNING);
            $value = null;
        }
        self::$conf[$key] = $value;
        self::$defined[$key] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    }

    public static function define($key, $value) {
        if (array_key_exists($key, self::$sensitive)) {
            $overwritten = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
            throw new \ErrorException('Cannot use define on sensitive data: '.$key, 0, 1,
                $overwritten['file'], $overwritten['line']);
        }
        parent::define($key, $value);
    }

    public static function get($key, $ifEmpty = null) {
        if (array_key_exists($key, self::$sensitive)) {
            return self::getSensitive($key);
        }
        return parent::get($key, $ifEmpty);
    }

    /**
     * Fetches the key value, and information on where it was defined.
     *
     * Note that about() will supply information about sensitive keys,
     * but always returns NULL for their value. Sensitive data can only
     * be read once, and the get() method must be used.
     *
     * @param $key string         The key to look up.
     * @param &$isDefined bool    Returns whether or not this key has been defined.
     * @param &$definedBy array   Returns the location where this key was defined.
     *
     * @return mixed  The value defined for a regular key, or NULL for a sensitive key.
     */
    public static function about($key, &$isDefined = null, &$definedBy = null) {
        return parent::about($key, $isDefined, $definedBy);
    }

    /**
     * @param $key
     * @return mixed
     * @throws \ErrorException
     */
    private static function getSensitive($key) {
        if (array_key_exists($key, self::$sensitiveRead)) {
            $firstRead = self::$sensitiveRead[$key];
            $secondRead = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);

            $firstException = new \ErrorException('Sensitive data requested twice. First time at:',
                0, 1, $firstRead['file'], $firstRead['line']);

            $secondException = new \ErrorException('Sensitive data requested twice. Second time at: ',
                0, 1, $secondRead['file'], $secondRead['line'], $firstException);

            throw $secondException;
        } else {
            // Read value
            $value = self::$sensitive[$key];

            // Clear record of value
            self::$sensitive[$key] = null;

            // Store where the var was accessed
            self::$sensitiveRead[$key] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);

            return $value;
        }
    }
}