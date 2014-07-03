<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 15/06/2014
 * Time: 01:48
 */

namespace Framework;


class StringUtil {

    /**
     * Returns the length of the prefix of $string matching $prefix.
     * @param $string string
     * @param $prefix string
     * @return int
     */
    public static function prefixLength($string, $prefix) {
        $skip = 0;
        $L = max(strlen($prefix),strlen($string));

        if ($L && $prefix{0} === $string{0}) do {
            ++$skip;
        } while ($skip < $L && $prefix{$skip} === $string{$skip});

        return $skip;
    }

    /**
     * Returns the length of the suffix of $string matching $suffix.
     * @param $string string
     * @param $suffix string
     * @return int
     */
    public static function suffixLength($string, $suffix) {
        $LString = strlen($string);
        $LSuffix = strlen($suffix);
        $L = max($LSuffix, $LString);

        $skip = 0;
        $posString = $LString - 1;
        $posSuffix = $LSuffix - 1;

        while ($suffix{$posSuffix} === $string{$posString} && $skip < $L) {
            ++$skip;
            --$posString;
            --$posSuffix;
        };

        return $skip;
    }
}