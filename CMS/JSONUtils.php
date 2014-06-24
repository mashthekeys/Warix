<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 21/06/2014
 * Time: 19:57
 */

namespace CMS;


class JSONUtils {
    public static function json_init() {
        header('Status: 200 OK');
        header("Content-type: application/json");
        ini_set('display_errors', '0');

        error_reporting(E_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
        set_error_handler('\CMS\JSONUtils::json_errorHandler', E_USER_ERROR);

        // Some fatal errors bypass the error handler.  The shutdown handler detects and reports these.
        register_shutdown_function('\CMS\JSONUtils::json_shutdownHandler');
    }

    public static function json_shutdownHandler() {
        $error = error_get_last();

        if (is_array($error)) {
            self::json_errorHandler($error["type"], $error["message"], $error["file"], $error["line"]);
        }
    }

    /**
     * In the case of a fatal error, displays a JSON-formatted error
     * message, then exits.
     *
     * Warnings and notices are silently ignored, but are passed on to
     * ErrorLogger to be logged.
     *
     * @param int $errorNum
     * @param string $eMessage
     * @param string $eFile
     * @param string $eLine
     */
    public static function json_errorHandler($errorNum, $eMessage, $eFile, $eLine) {
        if ($errorNum == E_ERROR || $errorNum == E_USER_ERROR || $errorNum == E_RECOVERABLE_ERROR) {
            self::json_displayError($eMessage, $eFile, $eLine);
            ErrorLogger::handleError($errorNum, $eMessage, $eFile, $eLine);
            exit;
        } else {
            ErrorLogger::handleError($errorNum, $eMessage, $eFile, $eLine);
        }
    }

    /**
     * Displays a JSON-formatted error message, then exits.
     *
     * This integrates with ErrorLogger::handleException to write details
     * to the log.
     *
     * @param \Exception $e
     */
    public static function json_exceptionHandler($e) {
        if ($e instanceof \Exception) {
            self::json_displayError($e->getMessage(), $e->getFile(), $e->getLine());
            ErrorLogger::handleException($e);
        } else {
            self::json_errorHandler(E_USER_ERROR, 'No exception passed to handler.', __FILE__, __LINE__);
        }
        exit;
    }

    /**
     * @param $eMessage
     * @param $eFile
     * @param $eLine
     */
    private static function json_displayError($eMessage, $eFile, $eLine) {
        $response = array(
            'error' => 'Task failed (PHP error)',
            'php_error' => "Task failed due to fatal error in $eFile at line $eLine. \n($eMessage)",
            'responseOrigin' => 'json_errorHandler'
        );

        if (!headers_sent()) {
            header('HTTP/1.0 500 Internal Server Error');
            header('Status: 500 Internal Server Error');
            header("Content-type: application/json");
        }

        echo json_encode($response, JSON_FORCE_OBJECT);
    }
} 