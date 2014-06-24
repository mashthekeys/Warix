<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 14/06/2014
 * Time: 22:55
 */

namespace Framework;


class NamespaceUtil {
    /**
     * Split a fully-qualified class name into namespace and class.
     * Unqualified class names will return null for namespace.
     * Classes in the global namespace return the empty string as namespace.
     *
     * @param string $fqClass Fully-qualified class name.
     * @return array        0 => namespace, 1 => class
     */
    public static function splitClass($fqClass){
        $fqClass = (string)$fqClass;

        $index = strrpos($fqClass, '\\');

        if ($index !== false) {
            return array(
                substr($fqClass, 0, $index),
                substr($fqClass, $index + 1)
            );
        } else {
            return array(
                null,
                $fqClass
            );
        }
    }

    /**
     * Split a fully-qualified class member name into namespace and class.
     * Unqualified class names will return null for namespace.
     * Classes in the global namespace return the empty string as namespace.
     *
     * @param string $fqMember Fully-qualified class member name.
     * @return array        0 => namespace, 1 => class, 2 => member
     */
    public static function splitMember($fqMember){
        $parts = self::splitClass($fqMember);

        $subparts = explode('::', $parts[1], 2);

        $parts[1] = $subparts[0];
        $parts[2] = $subparts[1];

        return $parts;
    }

    /**
     * Returns the first name component of a fully-qualified class name
     * (the part before the first '\\' ).
     *
     * @param string $fqClass
     * @return string First name component of $fqClass
     */
    public static function firstName($fqClass) {
        $fqClass = (string)$fqClass;

        $len = strcspn($fqClass, '\\');
        return substr($fqClass, 0, $len);
    }
}