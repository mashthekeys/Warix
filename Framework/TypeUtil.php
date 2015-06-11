<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 08/06/2014
 * Time: 16:14
 */

namespace Framework;


class TypeUtil {
    /**
     * @param string|array $type
     * @return string
     */
    public static function isScalar($type) {
        if (count($type) == 0 || $type === '') return true;

        if (is_array($type)) {
            $possibleTypes = $type;
        } else {
//            throw new \ErrorException('Not a type spec: '.gettype($type));
            $possibleTypes = self::parseVarAnnotation($type);
        }

        if ($possibleTypes['array']) return false;
        if ($possibleTypes['object']) return false;

        // mixed is assumed to represent a scalar type, unless object or array was specifically listed.
//        if ($possibleTypes['mixed']) return 'mixed';
//        if ($possibleTypes['string']) return 'string';
//        if ($possibleTypes['float']) return 'float';
//        if ($possibleTypes['int']) return 'int';
//        if ($possibleTypes['bool']) return 'bool';
//        if ($possibleTypes['null']) return 'null';

        // Finally, if no type descriptor at all was given, return mixed.
        return true;
    }

    /**
     * @param string|array $type
     * @return string
     */
    public static function getSimpleType($type) {
        if (count($type) == 0 || $type === '') return 'mixed';

        if (is_array($type)) {
            $possibleTypes = $type;
        } else {
//            throw new \ErrorException('Not a type spec: '.gettype($type));
            $possibleTypes = self::parseVarAnnotation($type);
        }

//        $isObject = false;
//        $isArray = false;
//        $isMixed = false;
//        $isString = false;
//        $isFloat = false;
//        $isInt = false;
//        $isBool = false;
//        $isNull = false;
//
//        foreach ($possibleTypes as $possibleType => $typeParams) {
//            if ($possibleType === 'object') {
//                $isObject = true;
//            } else if ($possibleType === 'array') {
//                $isArray = true;
//            } else if ($possibleType === 'mixed') {
//                $isMixed = true;
//            } else if ($possibleType === 'string') {
//                $isString = true;
//            } else if ($possibleType === 'float' || $possibleType === 'double') {
//                $isFloat = true;
//            } else if ($possibleType === 'int' || $possibleType === 'integer') {
//                $isInt = true;
//            } else if ($possibleType === 'bool' || $possibleType === 'boolean') {
//                $isBool = true;
//            } else if ($possibleType === 'null') {
//                $isNull = true;
//            } else if (substr($possibleType, -2) === '[]') {
//                $isArray = true;
//            } else {
//                $isObject = true;
//            }
//        }

        // In case of object|array type, return array.
        if ($possibleTypes['array']) return 'array';
        if ($possibleTypes['object']) return 'object';

        // mixed is assumed to represent a scalar type, unless object or array was specifically listed.
        if ($possibleTypes['mixed']) return 'mixed';
        if ($possibleTypes['string']) return 'string';
        if ($possibleTypes['float']) return 'float';
        if ($possibleTypes['int']) return 'int';
        if ($possibleTypes['bool']) return 'bool';
        if ($possibleTypes['null']) return 'null';

        // Finally, if no type descriptor at all was given, return mixed.
        return 'mixed';
    }

    public static function parseVarAnnotation($argString) {
        $possibleTypes = preg_split('/\s*\|\s*/', trim($argString), -1, PREG_SPLIT_NO_EMPTY);

        $parsed = array();

        if (count($possibleTypes) == 0) {
//            throw new \ErrorException("TEMP Should not be empty: ".var_export($argString,1));

            // assume a default type of 'mixed|null'
            $parsed['mixed'] = 'assumed';
            $parsed['null'] = 'assumed';

        } else foreach ($possibleTypes as $possibleType) {
            if ($possibleType === 'object') {
                $parsed['object']['*'] = true;
            } else if ($possibleType === 'array') {
                $parsed['array']['*'] = true;
            } else if ($possibleType === 'mixed') {
                $parsed['mixed'] = true;
            } else if ($possibleType === 'string') {
                $parsed['string'] = true;
            } else if ($possibleType === 'float' || $possibleType === 'double') {
                $parsed['float'] = true;
            } else if ($possibleType === 'int' || $possibleType === 'integer') {
                $parsed['int'] = true;
            } else if ($possibleType === 'bool' || $possibleType === 'boolean') {
                $parsed['bool'] = true;
            } else if ($possibleType === 'null') {
                $parsed['null'] = true;
            } else if ($possibleType === '[]' || $possibleType === '0[]') {
                // handle malformed array specifications
                $parsed['array']['*'] = true;
            } else if (substr($possibleType, -2) === '[]') {
                // should parse recursively
                $itemType = substr($possibleType, 0, -2);

                $parsed['array'][$itemType] = true;
            } else {
                if (0 < substr_count($possibleType, '\\', 1)) {
                    $possibleType = ltrim($possibleType, '\\');
                } else if ($possibleType{0} !== '\\') {
                    throw new \RuntimeException("DEV NOTE: full resolution of class name here will need namespace context");
                }
                $parsed['object'][$possibleType] = true;
            }
        }

        return $parsed;
    }
}