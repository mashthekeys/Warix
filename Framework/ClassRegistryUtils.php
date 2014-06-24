<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 14/06/2014
 * Time: 21:37
 */

namespace Framework;


class ClassRegistryUtils {
    public static function findMemberWithRole($roleName, $class) {
//        $members = ClassRegistry::listAnnotatedMembers('role', $class);
//
//        foreach ($members as $fqMember) {
//            list($matchClass, $member) = explode('::', $fqMember);
//
//            if ($matchClass === $class) {
//                $doc = ClassRegistry::getMemberAnnotations($class, $member);
//
//                if ($doc['role'][$roleName]) {
//                    return $member;
//                }
//            }
//        }
//
//        return null;
        $lookup = self::makeRoleLookup($class);
        return $lookup[$roleName];
    }

    private static $roleLookup = array();

    public static function makeRoleLookup($class) {
        $lookup = self::$roleLookup[$class];

        if (!isset($lookup)) {
            $members = ClassRegistry::listAnnotatedMembers('role', $class);
            $lookup = array();

            foreach ($members as $fqMember) {
                list($matchClass, $member) = explode('::', $fqMember, 2);

                if ($matchClass === $class) {
                    $doc = ClassRegistry::getMemberAnnotations($class, $member);

                    foreach ($doc['role'] as $roleName => $params) {
                        $lookup[$roleName] = $member;
                    }
                }
            }

            self::$roleLookup[$class] = $lookup;
        }

        return $lookup;
    }
}