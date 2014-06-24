<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 21/06/2014
 * Time: 22:50
 */

namespace CMS;


class ActionUtils {
    public static function actionResult($actionUid, $actionCommand, $callback) {
//        try {
            $result = $callback();
//        } catch (\Exception $e) {
//            $result = // TODO
//        }

        $result['actionUid'] = $actionUid;

        return $result;
    }

    public static function success() {
        return array('ok' => true);
    }

    public static function failure($issues) {
        return array('error'=>'Action failed.','error_details'=>$issues);
    }
}