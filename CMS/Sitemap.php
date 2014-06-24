<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 01/06/2014
 * Time: 20:34
 */

namespace CMS;


use Framework\PersistenceDB;
use Framework\Query;

class Sitemap {

    public static function output() {
        header("Content-type: application/xml; charset=UTF-8");
        header('Status: 200 OK');
        ini_set('display_errors', '0');

//        register_shutdown_function('\CMS\XMLUtils::xml_shutdownHandler');

        echo self::render();
    }

    public static function render() {
$content = '';

$root = URLUtil::absoluteRoot();

/** @var $pages Page[] */
$pages = PersistenceDB::findItems('Page',Query::matchAll());

foreach ($pages as $page) {
    $url = $page->getUrl();
    $modTime = date('c', $page->stamp_modified);

    $content .= "<url><loc>$root$url</loc><lastmod>$modTime</lastmod></url>\n";
}


return <<<SITEMAP_XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
$content
</urlset>
SITEMAP_XML;
    }
}