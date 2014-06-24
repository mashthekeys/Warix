<?php

namespace CMS;

use Framework\PersistenceDB;
use Framework\Query;

//header('Content-type: text/plain; charset="UTF-8"');
header('Content-type: text/html; charset="UTF-8"');

ini_set('display_errors','1');
error_reporting(E_ALL & ~(E_NOTICE | E_STRICT | E_DEPRECATED));

require_once '../Framework/__autoload.php';
require_once '../CMS/__autoload.php';
require_once '../Shop/__autoload.php';
@include_once '../CMS/__config.php';
require_once '../CMS/__init.php';

ErrorLogger::register();

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


