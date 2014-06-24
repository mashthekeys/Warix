<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 15/06/2014
 * Time: 00:32
 */

namespace CMS;


use Framework\StringUtil;

// Note to self: try throwing runtimeExceptions in the ClassRegistry to ensure ErrorLogger is robust
class ErrorLogger {
    // Set this to true before exiting to ensure fatal errors are not reported twice
    private static $noShutdownError = false;

    // Utility: lookup error names
    private static $errorLookup = [
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        E_ALL => 'E_ALL',
    ];

    private static function errorType($errorNum) {
        $type = self::$errorLookup[$errorNum];
        return isset($type) ? $type : 'ERROR_0x' . dechex($errorNum);
    }

    // Registering and unregistering
    public static function register() {
//        self::$overrideDisplayErrors = ini_get('display_errors');
//        ini_set('display_errors', '0');

        set_error_handler('\CMS\ErrorLogger::handleError');
        register_shutdown_function('\CMS\ErrorLogger::handleShutdown');

    }

    /**
     * Handles errors, warnings and notices. Exits in the case of a fatal error.
     * @param int $errorNum
     * @param string $eMessage
     * @param string $eFile
     * @param string $eLine
     */
    public static function handleError($errorNum, $eMessage, $eFile, $eLine) {
//        $report = error_reporting() & $errorNum;

        if (self::isFatal($errorNum)) {
            // Fatal errors are always logged
            self::handleEverything('Error', self::errorType($errorNum), $errorNum, $eMessage, $eFile, $eLine);
            self::$noShutdownError = true;
            exit;

        } else if ($errorNum == E_WARNING || $errorNum == E_USER_WARNING || $errorNum == E_COMPILE_WARNING) {
            // Warnings are always logged
            self::handleEverything('Warning', self::errorType($errorNum), $errorNum, $eMessage, $eFile, $eLine);

        } else if (error_reporting() & $errorNum) {
            // Any other errors are announced if allowed by error_reporting()
            self::handleEverything('Notice', self::errorType($errorNum), $errorNum, $eMessage, $eFile, $eLine);
        }
    }

    /**
     * Displays and logs the exception, then exits.
     *
     * Note that this does not integrate with any other
     * error catching or handling methods that may be active,
     * such as the JSONUtil methods.
     *
     * @param \Exception $e
     */
    public static function handleException($e) {
        if ($e instanceof \Exception) {
            self::handleEverything('Exception', get_class($e), $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        } else {
            self::handleEverything('Error', self::$errorLookup[E_USER_ERROR], E_USER_ERROR, 'No exception passed to handler.', __FILE__, __LINE__);
        }
        self::$noShutdownError = true;
        exit;
    }

    public static function handleShutdown() {
        if (self::$noShutdownError) return;

        $error = error_get_last();

        if (is_array($error)) {
            $errorNum = $error["type"];
            if (self::isFatal($errorNum)) {
                self::handleEverything('Crash', self::errorType($errorNum), $errorNum, $error["message"], $error["file"], $error["line"]);
            }
        }

    }

    // Private functions
    private static function handleEverything($type, $subtype, $errorNum, $eMessage, $eFile, $eLine) {
        self::logError($type, $subtype, $errorNum, $eMessage, $eFile, $eLine);

        $report = error_reporting() & $errorNum;

        if ($report && ini_get('display_errors')) {
            self::displayError($type, $subtype, $errorNum, $eMessage, $eFile, $eLine);
        }
    }

    private static function logError($type, $subtype, $errorNum, $message, $file, $line) {
        Log::recordEvent("$type $subtype",$message,compact('errorNum','message','file','line'));
        Log::stackTrace();
    }

    private static function displayError($type, $subtype, $errorNum, $eMessage, $eFile, $eLine) {
        $skip = StringUtil::prefixLength($eFile, dirname($_SERVER['SCRIPT_NAME']));

        $eFile = substr($eFile, $skip);

        echo <<<ERROR_TEMPLATE
<dl class='php_error'>
    <dt>$type</dt><dd>$subtype</dd>
    <dt>Message</dt><dd>$eMessage</dd>
    <dt>Location</dt><dd>$eFile $eLine</dd>
</dl>
ERROR_TEMPLATE;
    }

    /**
     * @param $errorNum
     * @return bool
     */
    private static function isFatal($errorNum) {
        return ($errorNum == E_ERROR || $errorNum == E_USER_ERROR || $errorNum == E_RECOVERABLE_ERROR || $errorNum == E_COMPILE_ERROR);
    }
}