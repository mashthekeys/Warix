<?php

namespace CMS;

use Framework\PersistenceDB;
use Framework\Query;

/**************************************************************************
 * BEGIN Warix Bootstrap
 **************************************************************************/
// Set development flags
define('CMS_DEV_MODE','1');

// Set default content header
header('Content-type: text/html; charset="UTF-8"');

// Set initial error reporting
ini_set('display_errors',@CMS_DEV_MODE ? 1 : 0);
error_reporting(E_ALL & ~(E_NOTICE | E_STRICT | E_DEPRECATED));

// Load core modules
require_once '../Framework/__autoload.php';
require_once '../CMS/__autoload.php';

// Load __config.php, if it exists.
// Note that errors in __config.php are suppressed, and will only be
// logged if the Installer is called in to repair the CMS installation.
@include_once '../CMS/__config.php';

// __init.php handles the rest of the bootstrap procedure.
// * The ErrorLogger is started.
// * The installer will be run if site.db config is missing.
// * The installer will be run if the database connection fails.
// * Additional modules are loaded.
require_once '../CMS/__init.php';

$fullPath = (string)$_SERVER['REQUEST_URI'];

$script = $_SERVER['SCRIPT_NAME'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);

if (substr($fullPath,0,strlen($scriptDir)) === $scriptDir) {
    if (substr($fullPath, 0, strlen($script)) === $script) {
        $fullPath = substr($fullPath, strlen($script));
        URLUtil::setSiteRoot($script);
    } else {
        $fullPath = substr($fullPath, strlen($scriptDir));
        URLUtil::setSiteRoot($scriptDir);
    }

    if ($fullPath{0} !== '/') {
        $fullPath = "/$fullPath";
    }
}

if (substr($fullPath,0,2) === '/!') {
    CMS::authenticate();
    CMS::adminUrl(substr($fullPath,1));
    exit;

} else if (substr($fullPath,0,5) === '/cms/') {
    CMS::authenticate();
    CMS::adminUrl(substr($fullPath,5));
    exit;

} else if ($fullPath === '/cms') {
    URLUtil::redirectLocal('/cms/');
    exit;

} else if ($fullPath === '/info.php') {
    CMS::authenticate();
    phpinfo();
    exit;

} else if ($fullPath === '/sitemap.xml') {
    Sitemap::output();
    exit;
}

list($path, $ext) = URLUtil::pathSplit($fullPath);

$page = Page::getByPath($path);

if ($page instanceof Page) {
    if ($ext !== '/') {
        URLUtil::redirectLocal("{$page->path}/");
        exit;
    }
//    if ($ext !== $page->ext) {
//        URLUtil::redirectLocal("{$page->path}{$page->ext}");
//        exit;
//    }

    echo $page->render();

} else if ($path === '' && $ext === '/') {
    $pages = PersistenceDB::findItems('CMS\Page',Query::matchAll());

//    if (empty($pages)) throw new \ErrorException("DEBUG: Loaded 0 pages :-(");

    if (!count($pages)) {
        Installer::run_makeFirstPage();

        $pages = PersistenceDB::findItems('CMS\Page',Query::matchAll());
    }

    Installer::builtinIndex($pages);
} else {
    $errorPage = new Page();

    $errorPage->title = "404 Not Found";

    header("Status: 404 Not Found");

    $fullPath = htmlentities($fullPath);

    $errorPage->content = "<p>There's nothing found at the address you requested.</p><pre>$fullPath</pre>";

    echo $errorPage->render();
}


