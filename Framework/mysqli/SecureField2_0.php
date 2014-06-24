<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 22/06/2014
 * Time: 02:27
 */

namespace Framework\mysqli;


class SecureField2_0 {
    private static $jail = array();

    static function secure($v) {
        do {
            $k = mt_rand();
        } while (array_key_exists($k,self::$jail));

        self::$jail[$k] = $v;

        return $k;
    }

    static function replace($k, $v) {
        self::$jail[$k] = $v;
    }

    static function get($k) {
        return self::$jail[$k];
    }

    static function jailBreak() {
//        var_dump(get_class_vars(__CLASS__));
//        print_r(get_class_vars(__CLASS__));
        echo "\n\n===========\n\n";
        jailBreak2_0();
    }

    static function test() {
        ob_start();

        self::put('foo','bar');
        self::put('baz','SUPER_SECRET_OMG_OMG_OMG_HIDE_ME_OR_ELSE');

        echo "\n\n===========\n\n";
        self::jailBreak();
        echo "\n\n-----------\n\n";
//        echo self::get('baz');

        return ob_get_clean();
    }
}

function jailBreak2_0() {
    var_dump(get_class_vars('SecureField2_0'));
    echo "\n\n- -\n\n";
    print_r(get_class_vars('SecureField2_0'));
//    print_r(SecureField2_0::$jail);
}