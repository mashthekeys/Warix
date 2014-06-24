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
     * @param string $string
     * @param string $prefix
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
}