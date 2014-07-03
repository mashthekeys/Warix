<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 15/06/2014
 * Time: 02:24
 */

namespace CMS;

use Framework\PersistenceDB;

class DevModule implements CMSModule {

    /**
     * Describes the entries presented for this module in the module tree.
     *
     * @return array
     */
    public function getModuleTree() {
        return array();
    }


    /**
     * A dumping ground for testing code.
     *
     * The easiest way to use this module is to place code in the $code array.
     *
     * It will be executed and the results displayed using var_export.
     *
     * @param string $moduleUrl
     * @return array
     * @throws \Exception Any exception thrown by the module will be handled by the CMS exception handler.
     */
    public function renderModuleUrl($moduleUrl) {
        $test1 = '1234567890qwertyuiopasdfghjklzxcvbnm';
        $test2 = '1234567890abcdefghijklmnopqrstuvwxyz';
        $test3 = '12345#####cvbnm';
        $test4 = '1234567890#####qrstuvwxyz';
        $test5 = '1234567890qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM';
        $test6 = '1234567890abcdefghijklmnopqrstuvwxyzQWERTYUIOPASDFGHJKLZXCVBNM';

        $code = array(
            'time()',
            '\Framework\mysqli\SecureField2_0::test()',

            "strlen('$test1')",
            "\\Framework\\StringUtil::prefixLength('$test1','$test1')",
            "\\Framework\\StringUtil::prefixLength('$test1','$test2')",
            "\\Framework\\StringUtil::prefixLength('$test1','$test3')",
            "\\Framework\\StringUtil::prefixLength('$test1','$test4')",
            "\\Framework\\StringUtil::prefixLength('$test1','$test5')",
            "\\Framework\\StringUtil::prefixLength('$test1','$test6')",
            "\\Framework\\StringUtil::suffixLength('$test1','$test1')",
            "\\Framework\\StringUtil::suffixLength('$test1','$test2')",
            "\\Framework\\StringUtil::suffixLength('$test1','$test3')",
            "\\Framework\\StringUtil::suffixLength('$test1','$test4')",
            "\\Framework\\StringUtil::suffixLength('$test1','$test5')",
            "\\Framework\\StringUtil::suffixLength('$test5','$test6')",
//            'gmmktime()',
//            'date("c")',
//            'gmdate("c")',
//            'new DateTime("@".time())',
//            '($d=new DateTime("@".time())) ? $d->format("c") : false',
//            '($d=new DateTime("@".time())) && $d->setTimezone(new DateTimeZone("Europe/London")) ? $d->format("c") : false',
//            '($d=new DateTime("@1410000000")) && $d->setTimezone(new DateTimeZone("Europe/London")) ? $d->format("c") : false',
//            'gmdate("c",time())',
//            'class_exists(\'MessageFormatter\')',
//            '\Framework\ClassRegistryUtils::makeRoleLookup("CMS\Page")',
//            '\Framework\ClassRegistryUtils::findMemberWithRole("id","CMS\Page")',
//            '\Framework\ClassRegistryUtils::findMemberWithRole("name","CMS\Page")',
//            '\Framework\ClassRegistryUtils::findMemberWithRole("id","CMS\Template")',
        );

        $values = array_map('\CMS\DevModule::eval_export', array_combine($code,$code));
        // Append extra values now...
//        $values['Hello'] = "World!";
//
//        $res = PersistenceDB::query("SELECT * FROM CMS\$Page WHERE id=1");

        $secureField = \Framework\mysqli\SecureField2_0::secure(mt_rand());

        $values['secureFieldValue'] = var_export($secureField,true);
        $values['secureFieldStr'] = var_export("$secureField",true);

//        ob_start();
//        var_dump($secureField);
//        $secureFieldDump = ob_get_clean();
//
//        $values['secureFieldDump'] = $secureFieldDump;
//        $values['secureFieldPrint'] = print_r($secureField,true);
//
//        for ($n=0; $n < 10; ++$n) {
//            $fn = "\0lambda_$n";
//            if (function_exists($fn)) {
//                $values[$fn] = $fn;
//
//                try {
//                    $values[$fn] = $fn();
//                } catch (\Exception $e) {
//                    $values[$fn] = get_class($e);
//                }
//            }
//        }

        // Stop! Hammertime.
        return array('content' => "<dl>".self::dlImplode($values)."</dl>");
    }

    public static function eval_export($code){
//        $result = call_user_func($callback);
        return var_export(eval("return $code;"), true);
    }
    public static function dlImplode($values) {
        $list = '';
        foreach ($values as $code => $result) {
            $code = Tag::encode($code);
            $result = Tag::encode($result);
            $list .= "<dt><code>$code</code></dt><dd><pre>= $result</pre></dd>\n";
        }
        return $list;
    }

    /**
     * Implements the interface between this module and the backend.
     *
     * This is most commonly used when handling a JSON request from an
     * actionCommand button, but it can also be used directly.
     *
     * @param string $actionUid Client-submitted action UID.
     * @param string $actionUrl
     * @param string $actionCommand
     * @param array $data Parameters for the action command.
     * @return array|null
     */
    public function actionCommand($actionUid, $actionUrl, $actionCommand, $data) {
        return null;
    }


}