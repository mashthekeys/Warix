<?php
namespace CMS;

use Framework\PersistenceDB;

require_once 'constants.php';

function _db_connect() {
    if (PersistenceDB::connected()) return;

    $host = Config::get('site.db.host', 'localhost');
    $username = Config::get('site.db.username');
    $password = Config::get('site.db.password');
    $db_name = Config::get('site.db.db_name');

    if (!strlen($username) || !strlen($db_name)) {
        Installer::run();
        exit;
    }

    $connect = new \mysqli($host, $username, $password, $db_name);

    // See http://us3.php.net/manual/en/mysqli.construct.php#example-1729
    // for PHP versions < PHP 5.2.9
    if ($connect->connect_error) {
        Installer::handleConnectError($connect);
        exit;
    }

    $connect->query('SET NAMES utf8mb4');

    PersistenceDB::setDB($connect);
    PersistenceDB::addDBFilter('\CMS\DBLogger::callbackQueryFilter');
    PersistenceDB::addDBLogger('\CMS\DBLogger::callbackQueryLogger');

    PersistenceDB::createTables();
}

_db_connect();


