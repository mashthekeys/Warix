<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 02/06/2014
 * Time: 20:38
 */

namespace CMS;


class CMS {
    static $modules = array(
        '' => 'CMS\Page',
        'template' => 'CMS\Template',
        'user' => 'CMS\User',
        'tree' => 'CMS\TreeModule',
//        'cms' => 'CMS\ConfigModule',
        'dev' => 'CMS\DevModule',
    );

    static $cmsHomeUrl = '!user';
//    static $cmsHomeUrl = '!page';

    public static function authenticate() {
        // TODO
    }

    public static function adminUrl($adminPath) {
        header("Cache-Control: no-store, no-cache, must-revalidate");

        list($module, $moduleUrl) = self::parseAdminUrl($adminPath);

        if (isset(self::$modules[$module])) {
//            if ($moduleUrl === '{}') {
            if ($moduleUrl === '%7B%7D') {
                // JSON parameters should be sent in POST data
                CMSBackendUtils::renderModuleJSON();
//                CMSBackendUtils::renderModuleJSON(self::$modules[$module]);
            } else {
                CMSBackendUtils::renderModule($module, self::$modules[$module], $moduleUrl);
            }
        } else {
            // should handle exception
            throw new \ErrorException("Wrong address, no such module '$module'");
        }

        exit;
    }

    public static function parseAdminUrl($adminPath, &$valid = null) {
        if (!is_scalar($adminPath) || !strlen($adminPath)) {
            $valid = is_string($adminPath);
            $adminPath = self::$cmsHomeUrl;
        } else {
            $valid = $adminPath{0} === '!';

            if (!$valid) {
                return array('', '');
            }
        }

        $moduleLength = strspn($adminPath, '-1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz', 1);

        $module = substr($adminPath, 1, $moduleLength);

        $moduleUrl = substr($adminPath, 1 + $moduleLength);

        if ($module === false) $module = '';
        if ($moduleUrl === false) $moduleUrl = '';

        return array($module, $moduleUrl);
    }
}