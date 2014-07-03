<?php
namespace CMS;


use Framework\PersistenceDB;

// should add no-cache headers etc.
class Installer {
    public static function builtinIndex($pages) {
        $title = strip_tags(Config::get('site.title', ''));

        if (!strlen($title)) $title = 'Website content';

        $root = URLUtil::absoluteRoot();

        $content = "<h1>$title</h1><ul>";

        foreach ($pages as $page) {
            /** @var $page Page */
            $url = htmlspecialchars($root . $page->getUrl(), ENT_QUOTES);
            $title = $page->title;

            $content .= "<li><a href='$url'>$title</a></li>";
        }

        $content .= "</ul>";

        self::renderInstallPage($title, $content);
    }

    private static function renderInstallPage($title, $content) {
        $builtinPage = new Page();
        $builtinPage->template_source = file_get_contents('res/Installer_template.html');
        $builtinPage->title = $title;
        $builtinPage->content = $content;

        $builtinPage->scriptParts['WARIX_INSTALLER_DEFAULTS'] =
            'window.WARIX_INSTALLER_DEFAULTS = '.InstallerDefaults::publishDefaultsJSON().';';

        $builtinPage->scriptParts['WARIX_INSTALLER'] =
            '<script type="text/javascript" src="res/Installer_script.js"></script>';

        echo $builtinPage->render();
    }

    public static function run() {
        if ($_REQUEST['install_test']) {
            self::jsonTask_testConnection();
        }
        if ($_REQUEST['install_run']) {
            self::jsonTask_runInstaller();
        }

        if (file_exists('../CMS/__installer_config.php')) {
            include '../CMS/__installer_config.php';
        }
        if (file_exists('../CMS/__config.php')) {
            // Overwrite defaults with local values.
            // Also, on this run, errors will be logged!
            include '../CMS/__config.php';
        }

        // should rewrite installer so that sensitive details are never published
        $host = htmlspecialchars(Config::get('site.db.host',InstallerDefaults::get('site.db.host')),ENT_QUOTES);
        $username = htmlspecialchars(Config::get('site.db.username',InstallerDefaults::get('site.db.username')),ENT_QUOTES);
        $password = htmlspecialchars(InstallerDefaults::get('site.db.password'),ENT_QUOTES);
        $db = htmlspecialchars(Config::get('site.db.db_name',InstallerDefaults::get('site.db.db_name')),ENT_QUOTES);

        $content = <<<CONTENT
<h1>CMS</h1>
<h2>Ready to install</h2>
<form onsumbit='return false;'><div class='installer_form'>
    <button type='button' id='installer_go' class='cms_expander'>Go!</button>
    <div class='cms_expandable' style='display:none'>
        <h3>Database Connection</h3>
        <dl>
            <dt>Host</dt>
            <dd><input type='text' name='site.db.host' value='$host' autocomplete='off' /></dd>
            <dt>Username</dt>
            <dd><input type='text' name='site.db.username' value='$username' autocomplete='off' /></dd>
            <dt>Password</dt>
            <dd><input type='text' name='site.db.password' value='$password' autocomplete='off' /></dd>
        </dl>
        <button type='button' id='installer_connect' class='cms_expander'>Try connection</button>
        <dl class='cms_expandable' style='display:none'>
            <dt>Database</dt>
            <dd><input type='text' name='site.db.db_name' value='$db' autocomplete='off' /></dd>
            <dt>Connection</dt>
            <dd id='section_test'><span id='test_connection_status'></span>
                <button type='button' id='installer_test' disabled='disabled'>Test</button>
                <div id='test_connection_output'></div>
            </dd>
        </dl>
        <div id='section_install' style='display:none'>
            <h3>Your Admin Login</h3>
            <dl>
                <dt>Username</dt>
                <dd><input type='text' name='login.username' value='' autocomplete='off' /></dd>
                <dt>Password</dt>
                <dd><input type='text' name='login.password' value='' autocomplete='off' /></dd>
                <dd>
                    <button type='button' id='install_run' disabled='disabled'>Install</button>
                    <div id='installer_output'></div>
                </dd>
            </dl>
        </div>
    </div>
</div></form>
CONTENT;

        self::renderInstallPage("CMS – Ready to install", $content);
        exit;
    }

    public static function run_makeFirstPage() {
        $response = array();

        include 'res/Installer_FirstPage.php';

        $response['page_created'] = !!$response['page_created'];
        $response['template_created'] = !!$response['template_created'];

        return $response;
    }

    private static function jsonTask_testConnection() {
        JSONUtils::json_init();
        
        try {

            $host = strlen($_REQUEST['host']) ? $_REQUEST['host'] : 'localhost';
            $username = $_REQUEST['username'];
            $password = $_REQUEST['password'];
    
            $test = new \mysqli($host, $username, $password);
    
            if ($test->connect_error) {
                $response = array(
                    'connected' => false,
                    'connect_error' => $test->connect_error,
                );
            } else {
                // should explore SHOW GRANTS
    
                $res = $test->query('SHOW DATABASES');
    
                if ($res) {
                    $dbs = array();
                    while ($row = $res->fetch_row()) {
                        $dbs[$row[0]] = array();
                    }
                    $res->free();
    
                    foreach (array_keys($dbs) as $db) {
                        $test->select_db($db);
                        $res = $test->query('SHOW TABLES');
    
                        $dbs[$db]['access'] = !!$res;
                        if ($res) {
                            $dbs[$db]['occupied'] = ($res->num_rows > 0);
                        }
    
                        $res->free();
                    }
    
                    $response = array(
                        'connected' => true,
                        'dbs' => $dbs,
                    );
                } else {
                    $response = array(
                        'connected' => true,
                        'dbs' => array(),
                        'warning' => 'DB list not available.'
                    );
                }
            }
        } catch (\Exception $e) {
            $eClass = get_class($e);
            $eFile = $e->getFile();
            $eLine = $e->getLine();
            $eMessage = $e->getMessage();

            $response['connected'] = false;
            $response['php_error'] = "Connection test failed due to $eClass in $eFile at line $eLine";
            $response['php_error_detail'] = $eMessage;
        }
        
        echo json_encode($response, JSON_FORCE_OBJECT);
        exit;
    }

    public static function jsonTask_runInstaller() {
        JSONUtils::json_init();

        //////////////////////////////////////////////
        $TASK = 'connect to server';
        try {
            $host = strlen($_REQUEST['host']) ? $_REQUEST['host'] : 'localhost';
            $username = $_REQUEST['username'];
            $password = $_REQUEST['password'];
            $db = $_REQUEST['db_name'];

            $server = new \mysqli($host, $username, $password);

            if ($server->connect_error) {
                // If the installer cannot connect to MySQL, there is no written log message.
                // The installer will only begin to log errors to disk once a successful MySQL
                // connection is made, to avoid junk log entries.
                $response = array(
                    'connected' => false,
                    'connect_error' => $server->connect_error,
                );
                $ok = false;
            } else {

                $server->query('SET NAMES utf8mb4');

                PersistenceDB::setDB($server); // send all queries via PersistenceDB::query to be logged
                PersistenceDB::addDBLogger('\CMS\DBLogger::callbackQueryLogger');

                DBLogger::$verbose = true;

//                $dbEscaped = strtr($server->real_escape_string($db), array('%' => '\%', '_' => '\_'));
//                $res = PersistenceDB::query("SHOW DATABASES LIKE '$dbEscaped'");
//                $selectOk = ($res && $res->num_rows > 0 && $server->select_db($db));

                $selectOk = $server->select_db($db);

                if (!$selectOk) {
                    $TASK = 'create requested database';

                    Log::recordEvent('Install','Creating the requested database',compact('host','db'));

                    $createQuery = "CREATE DATABASE `$db` "
                        . "CHARACTER SET utf8mb4 DEFAULT CHARACTER SET utf8mb4 "
                        . "COLLATE utf8mb4_bin DEFAULT COLLATE utf8mb4_bin";

                    $createOk = PersistenceDB::query($createQuery);
                    if (!$createOk) {
                        $server_error = $server->error;
                        Log::recordEvent('Install','Could not create requested database',compact('host','db','createQuery','server_error'));
                    } else {
                        $TASK = 'select requested database';
                    }
                    $selectOk = $createOk && $server->select_db($db);

                    if (!$selectOk) {
                        if ($createOk) {
                            $server_error = $server->error;
                            Log::recordEvent('Install','Could not select requested database',compact('host','db','server_error'));
                        }
                    }
                }

                $ok = $selectOk;
                $response = array(
                    'connected' => true,
                    'db_exists' => $selectOk,
                );

                if ($selectOk) {
                    Log::recordEvent('Install','Successfully connected',compact('host','db'));
                } else {
                    $response['server_error'] = $server->error;
                }
            }

            if ($ok) {
                //////////////////////////////////////////////
                $TASK = 'create tables';

                $ok = PersistenceDB::createTables();

                $response['tables_created'] = $ok;

                if (!$ok) {
                    $server_error = $server->error;
                    Log::recordEvent('Install', 'Could not create needed tables', compact('db', 'server_error'));
                }
            }

            if ($ok) {
                //////////////////////////////////////////////
                $TASK = 'write config file';

                $ok = ConfigEditor::editConfigFile(__DIR__ . '/__config.php', array(
                    'site.db.host' => $host,
                    'site.db.username' => $username,
                    'site.db.password' => $password,
                    'site.db.db_name' => $db,
                ));

                $response['config_stored'] = $ok;

                if (!$ok) {
                    Log::recordEvent('Install', 'Could not write config file', array());
                }
            }

            if ($ok) {
                //////////////////////////////////////////////
                $TASK = 'create first page';

                include 'res/Installer_FirstPage.php';

                $response['page_created'] = !!$response['page_created'];
                $response['template_created'] = !!$response['template_created'];

                $ok = $response['page_created'] && $response['template_created'];

                if (!$ok) {
                    $server_error = $server->error;
                    Log::recordEvent('Install', "Could not $TASK", compact('server_error'));
                    $response['server_error'] = $server_error;
                }
            }

            if ($ok) {
                $TASK = 'install complete';
                Log::recordEvent('Install', "Install complete.");
                $response['install_complete'] = true;
            }

        } catch (\Exception $e) {
            $eClass = get_class($e);
            $eFile = $e->getFile();
            $eLine = $e->getLine();
            $eMessage = $e->getMessage();

            $response['install_complete'] = false;
            $response['php_error'] = "Installer task '$TASK' failed due to $eClass in $eFile at line $eLine";
            $response['php_error_detail'] = $eMessage;
        }

        // Report back
        echo json_encode($response, JSON_FORCE_OBJECT);
        exit;
    }
}