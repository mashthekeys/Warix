<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 31/05/2014
 * Time: 23:49
 */

namespace Framework;


class CallbackUtil {
    private static $uniqueCounter = 0;

    /**
     * @param array &$callbacks
     * @param callable $newCallback
     * @param string|null $uniqueKey
     */
    public static function addCallback(&$callbacks, $newCallback, $uniqueKey = null) {
        if (is_null($uniqueKey)) {
            if (is_string($newCallback)) {
                if ($newCallback{0} === '=') {
                    // ignore the invalid function name for now
                    $uniqueKey = 'n'.(++self::$uniqueCounter);
                } else {
                    $uniqueKey = "($newCallback)";
                }
            } else if (is_array($newCallback) && is_string($newCallback[0])) {
                $uniqueKey = ":$newCallback[0]::$newCallback[1]";
            } else {
                $uniqueKey = 'n'.(++self::$uniqueCounter);
            }
        } else {
            $uniqueKey = "=$uniqueKey";
        }
        $callbacks[$uniqueKey] = $newCallback;
    }

    /**
     * @param array &$callbacks
     * @param string|callable $listener Either the callback or the unique key used when originally registered.
     * @return bool TRUE if the requested listener was removed from the list. FALSE if listener could not be found.
     */
    public static function removeCallback(&$callbacks, $listener) {
        if (is_array($listener)) {
            $callback = $listener;

            if (is_string($callback[0])) {
                $uniqueKey = ":$callback[0]::$callback[1]";

                if (array_key_exists($uniqueKey, $callbacks)) {
                    unset($callbacks[$uniqueKey]);
                    return true;
                }

            } else {
                foreach ($callbacks as $uniqueKey => $test) {
                    if ($test[1] === $callback[1] && $test[0] === $callback[0]) {
                        unset($callbacks[$uniqueKey]);
                        return true;
                    }
                }
            }
        } else if (is_string($listener)) {
            $uniqueKey = "=$listener";
            if (array_key_exists($uniqueKey, $callbacks)) {
                unset($callbacks[$uniqueKey]);
                return true;
            }
            $uniqueKey = "($listener)";
            if (array_key_exists($uniqueKey, $callbacks)) {
                unset($callbacks[$uniqueKey]);
                return true;
            }
        }
        // Should try to handle Closure objects.

        return false; // No match
    }
}