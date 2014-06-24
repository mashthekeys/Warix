<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 19/06/2014
 * Time: 00:41
 */

namespace CMS;


/*
 * Need to write actual localisation code here!
 *
 * Since set_locale cannot be used for a multilingual site on all setups,
 * conversion to the appropriate language will have to happen in this class.
 */
class Lang {
    private static $datePreConvert = array();
    private static $datePostConvert = array();
    private static $fPreConvert = array();
    private static $fPostConvert = array();


    static function date($format, $time = null) {
        $format = strtr($format, self::$datePreConvert);

        if (func_num_args() == 1) {
            $value = date($format);
        } else {
            $value = date($format, $time);
        }

        return strtr($value, self::$datePostConvert);
    }
    static function gmdate($format, $time = null) {
        $format = strtr($format, self::$datePreConvert);

        if (func_num_args() == 1) {
            $value = gmdate($format);
        } else {
            $value = gmdate($format, $time);
        }

        return strtr($value, self::$datePostConvert);
    }
    static function strftime($format, $time = null) {
        $format = strtr($format, self::$fPreConvert);

        if (func_num_args() == 1) {
            $value = strftime($format);
        } else {
            $value = strftime($format, $time);
        }

        return strtr($value, self::$fPostConvert);
    }
    static function gmstrftime($format, $time = null) {
        $format = strtr($format, self::$fPreConvert);

        if (func_num_args() == 1) {
            $value = gmstrftime($format);
        } else {
            $value = gmstrftime($format, $time);
        }

        return strtr($value, self::$fPostConvert);
    }
}