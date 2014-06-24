<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 30/05/2014
 * Time: 22:32
 */

namespace CMS;


class DBLogger {
    public static $verbose = false;

    private static $SHOW_permissions = array(
        # All permitted SHOW commands must be delared here
        'CHARACTER' => array('SET'=>true),
        'COLLATION' => true,
        'COLUMNS' => true,
        'CREATE' => array('DATABASE'=>true,'FUNCTION'=>true,'PROCEDURE'=>true,'TABLE'=>true),
        'DATABASES' => true,
        'ENGINE' => true,
        'ENGINES' => true,
        'FULL' => array(
            'COLUMNS'=>true,
            'TABLES'=>true,
            'PROCESSLIST'=>false // Not permitted: SHOW FULL PROCESSLIST
        ),
        'GLOBAL' => array('STATUS'=>true,'VARIABLES'=>true),
        'GRANTS' => true,
        'INDEX' => true,
        'INNODB' => array('STATUS'=>true),
        'LOGS' => true,
        'MASTER' => array('LOGS'=>true,'STATUS'=>true),
        'MUTEX' => array('STATUS'=>true),
        'OPEN' => array('TABLES'=>true),
        'PRIVILEGES' => true,
        'SESSION' => array('STATUS'=>true,'VARIABLES'=>true),
        'STATUS' => true,
        'STORAGE' => array('ENGINES'=>true),
        'TABLE' => array('STATUS'=>true),
        'TABLES' => true,
        'VARIABLES' => true,

        # No need to explicitly declare non-permitted commands.
        # Other valid SHOW commands include:
//        'BDB' => array('LOGS'=>false),
//        'BINARY' => array('LOGS'=>false),
//        'BINLOG' => array('EVENTS'=>false),
//        'ERRORS' => false,
//        'FUNCTION' => array('CODE'=>false,'STATUS'=>false),
//        'PROCEDURE' => array('CODE'=>false,'STATUS'=>false),
//        'PROCESSLIST' => false,
//        'PROFILE' => false,
//        'PROFILES' => false,
//        'SLAVE' => array('HOSTS'=>false,'STATUS'=>false),
//        'TRIGGERS' => false,
//        'WARNINGS' => false,

    );

    public static function analyzeQuery($sqlQuery, &$command, &$permitted, &$mustLog) {
        $sqlQuery = ltrim($sqlQuery);

        preg_match('/^(\S*)/u', $sqlQuery, $match);

        $command = strtoupper($match[1]);

//        if (strlen($sqlQuery) && !strlen($command)) {
//            die("Matched nothing in $sqlQuery");
//        }

        $mustLog = false;

        switch ($command) {
            ////////////////////////////////////////////
            // Data definition (permitted, logged)
            case 'ALTER':
            case 'CREATE':
            case 'DROP':
            case 'RENAME':
            case 'TRUNCATE':
                $permitted = true;
                $mustLog = true;
                break;

            ////////////////////////////////////////////
            // Data manipulation (permitted, logged)
            case 'DELETE':
            case 'INSERT':
            case 'REPLACE':
            case 'UPDATE':
                $permitted = true;
                $mustLog = true;
                break;

            // Data manipulation (permitted)
            case 'SELECT':
                $permitted = true;
                break;
            // Data manipulation (not permitted)
            case 'CALL':
            case 'DO':
            case 'HANDLER':
            case 'LOAD_DATA_INFILE':
                $permitted = false;
                break;

            ////////////////////////////////////////////
            // Transactional and locking (not permitted)
            case 'START': //START TRANSACTION|SLAVE
            case 'COMMIT':
            case 'ROLLBACK':
            case 'SAVEPOINT':
            case 'RELEASE': //RELEASE SAVEPOINT
            case 'LOCK':
            case 'UNLOCK':

            // Replication (not permitted)
            case 'PURGE': //PURGE BINARY LOGS
                // Also RESET MASTER|SLAVE, below
            case 'CHANGE': //CHANGE MASTER TO
            case 'LOAD': // LOAD TABLE|DATA FROM MASTER, LOAD INDEX INTO CACHE
                // Also START TRANSACTION|SLAVE, above
            case 'STOP': //STOP SLAVE

            // Prepared statements (not permitted)
            case 'PREPARE':
            case 'EXECUTE':
            case 'DEALLOCATE':

            // Compound statements (not permitted)
            case 'DECLARE':
            case 'BEGIN':
                $permitted = false;
                break;

            ////////////////////////////////////////////
            // Administrative (permitted, logged)
            case 'SHOW':
                $tokens = preg_split('/\s+/u', $sqlQuery, 4);
                $tokens = array_map('strtoupper', $tokens);

                $allow = self::$SHOW_permissions[$tokens[1]];

                if (is_array($allow)) {
                    $permitted = !!$allow[$tokens[2]];
                } else {
                    $permitted = !!$allow;
                }

                if ($permitted) $mustLog = false;
            break;

            // Administrative (not permitted)
            case 'SET': // SET, SET TRANSACTION, many other uses
                // should allow some variants of SET, but for now forbid all uses
                $permitted = false;
                break;

            // Administrative - User
            case 'GRANT':
            case 'REVOKE':

                // Administrative - Table
            case 'ANALYZE':
            case 'BACKUP':
            case 'CHECK':
            case 'CHECKSUM':
            case 'OPTIMIZE':
            case 'REPAIR':
            case 'RESTORE':

                // Administrative - Other
            case 'CACHE':
            case 'FLUSH':
            case 'KILL':
                // Also LOAD INDEX INTO CACHE, above
            case 'RESET': // RESET, also RESET MASTER|SLAVE
                $permitted = false;
                break;

            ////////////////////////////////////////////
            // Utility (permitted, logged)
            case 'USE':
                $permitted = true;
                $mustLog = true;
                break;

            // Utility (permitted)
            case 'DESCRIBE':
            case 'EXPLAIN':
            case 'HELP':
                $permitted = true;
                break;

            default: // Unknown
                $command = strlen($sqlQuery) ? "UNKNOWN SQL COMMAND'$sqlQuery'" : 'EMPTY SQL QUERY';
                $permitted = false;
        }
    }

    public static function callbackQueryFilter($params) {
        $query = ltrim($params['query']);

        self::analyzeQuery($query, $command, $permitted, $mustLog);

        if (!$permitted) {
            \CMS\Log::recordEvent('DB','Blocked statement',array('query'=>$query,'blocked'=>$command));
            \CMS\Log::stackTrace();
        }
        return $permitted;
    }

    public static function callbackQueryLogger($params) {
        $query = ltrim($params['query']);
        $result = $params['result'];

        self::analyzeQuery($query, $command, $permitted, $mustLog);

        if ($result === false) {
            \CMS\Log::recordEvent('DB','Failed statement',array('query'=>$query,'error'=>$params['error']));
        } else if ($mustLog || self::$verbose) {

            if ($result instanceof \mysqli_result) {
                $data['num_rows'] = $result->num_rows;
            } else {
                $data['success'] = $result;
            }

            \CMS\Log::recordEvent('DB','Executed statement',array('query'=>$query,'result'=>$result));
        }
    }

} 