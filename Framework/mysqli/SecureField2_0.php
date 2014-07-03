<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 22/06/2014
 * Time: 02:27
 */

namespace Framework\mysqli;

/**
 * The SecureField2_0 class exists to hold data that would be disastrous to
 * security if inadvertently exposed through a print_r or var_dump call.
 *
 * For example, consider an object representing a user login.
 *
 * @package Framework\mysqli
 */
//
//

// Note to developers: the data
// Code within this class MUST NEVER create instances.
// Code within this class MUST NEVER pass $jail to any outside function.
// Code within this class MUST NEVER list the keys or values of $jail,
// except to fulfil the functions of the secure(), replaceSecure(),
// and get() methods.
final class SecureField2_0 {
    private static $jail = array();

    private function __construct() {
        throw new \ErrorException('Cannot construct instances of '.__CLASS__);
    }

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
//        echo "\n\n- -\n\n";
//        print_r(get_class_vars(__CLASS__));
        echo "\n\n===========\n\n";
        jailBreak2_0();
    }

    static function test() {
        ob_start();

        self::secure('foo');
        self::secure('bar');
        self::secure('baz');
        self::secure('SUPER_SECRET_OMG_OMG_OMG_HIDE_ME_OR_ELSE');

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
//    print_r(new SecureField2_0);
//    print_r(SecureField2_0::$jail);
}