<?php
namespace CMS;


/**
 * This class stores default values for the Warix installer.
 *
 * Handling of sensitive data is quite different.  Unlike Config,
 * sensitive data can be redefined and read multiple times.
 *
 * The only restriction on sensitive data is that it will not be
 * published by publishDefaultsJSON().
 *
 * @package CMS
 */
final class InstallerDefaults extends AbstractConfig {
    protected static $conf = [
        'site.db.host' => 'localhost',
        'site.db.username' => 'warix_user',
        'site.db.db_name' => 'warix_cms',
        'site.lang' => 'en-GB',
    ];
    private static $sensitive = [
        'site.db.password' => null,
    ];

    public static function publishDefaultsJSON() {
        return json_encode(self::$conf, JSON_FORCE_OBJECT, 1);
    }

    public static function defineSensitive($key, $value) {
        if ($value === null || is_scalar($value)) {
            self::$sensitive[$key] = $value;
            self::$defined[$key] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        } else {
            trigger_error("Invalid configuration value for '$key' => ".gettype($value), E_USER_WARNING);
        }
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


} 