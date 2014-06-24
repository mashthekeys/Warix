<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 27/05/2014
 * Time: 01:11
 */

namespace CMS;


class URLUtil {
    private static $siteRoot = '';

    public static function getSiteRoot() {
        return self::$siteRoot;
    }

    public static function setSiteRoot($path) {
        self::$siteRoot = $path;
    }

    /**
     * Returns the absolute root of this CMS site, as best as can be identified from the request.
     *
     * All site URLs appended to this should begin with '/'.
     *
     * @return string
     */
    public static function absoluteRoot() {
        if (strlen($_SERVER['HTTP_HOST'])) {
            $root = (($_SERVER['HTTPS'] && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST'];
        } else {
            $root = '';
        }
        return $root . self::$siteRoot;
    }

    /**
     * @param $path
     * @return array
     */
    public static function pathSplit($path) {
        if (strlen($path)==0) {
            $parts = array('','/');
        } else {
            $parts = preg_split('~(/|\.[-_0-9A-Za-z]*|)$~u', $path, -1, PREG_SPLIT_DELIM_CAPTURE);
        }
        return array($parts[0],$parts[1],'path'=>$parts[0],'ext'=>$parts[1]);
    }


    public static function redirectLocal($path) {
        $path = ltrim($path, '/');
        $root = URLUtil::absoluteRoot();
        self::redirectAbsolute("Location: $root/$path");
    }

    public static function redirectAbsolute($url) {
        header("Location: $url");
    }
}