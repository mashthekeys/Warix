<?php
namespace CMS;

use Framework\PersistenceDB;

function _db_connect() {
    if (PersistenceDB::connected()) return;

    $host = Config::get('site.db.host', 'localhost');
    $username = Config::get('site.db.username');
    $db_name = Config::get('site.db.db_name');

    // 'installer.run' can be true, false, or null.
    //    * TRUE causes table integrity checks.
    //    * FALSE prevents the installer being run on any occasions.
    //    * NULL allows the default behaviour of running the installer
    //      if there are problems during bootstrap.
    $installer_run = Config::get('installer.run', null);
    if ($installer_run !== null) $installer_run = (bool)$installer_run;

    if (!strlen($username) || !strlen($db_name)) {
        if ($installer_run !== false) {
            Installer::run();
        } else {
            throw new \ErrorException('CMS: Missing database parameters.');
        }
        exit;
    }

    $connect = new \mysqli(
        $host,
        $username,
        Config::get('site.db.password'), // <--- this is the one and only time site.db.password is read
        $db_name
    );

    // See http://us3.php.net/manual/en/mysqli.construct.php#example-1729
    // for PHP versions < PHP 5.2.9
    if ($connect->connect_error) {
        if ($installer_run !== false) {
            Installer::handleConnectError($connect);
            exit;
        } else {
            throw new \ErrorException('CMS: Error connecting to the database.');
        }
    }

    $connect->query('SET NAMES utf8mb4');

    PersistenceDB::setDB($connect);
    PersistenceDB::addDBFilter('\CMS\DBLogger::callbackQueryFilter');
    PersistenceDB::addDBLogger('\CMS\DBLogger::callbackQueryLogger');

    if ($installer_run === true) {
        PersistenceDB::createTables(true);
    }
}

// Start logging errors
ErrorLogger::register();

// Core constants
require_once 'constants.php';

// Connect to DB
_db_connect();

// Load addon modules
require_once '../Shop/__autoload.php';



