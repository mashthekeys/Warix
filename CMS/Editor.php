<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 15/06/2014
 * Time: 20:07
 */

namespace CMS;


use Framework\ClassRegistry;
use Framework\ClassRegistryUtils;
use Framework\NamespaceUtil;
use Framework\PersistenceDB;
use Framework\Query;
use Framework\TypeUtil;

class Editor {
    private static $debugHiddenFields = false;

    /**
     * @param string $class
     * @param object $item
     * @param string $editContext
     * @param $scriptParts
     * @return string
     */
    public static function itemEditor($class, $item, $editContext, &$scriptParts) {

        if (strlen($editContext) && (substr($editContext, -1) !== '[')) {
            $editContext .= '[';
        }

        $prefix = $editContext;
        $suffix = str_repeat(']', substr_count($editContext, '[')-substr_count($editContext, ']'));

        $editForm = '';

        $classDoc = ClassRegistry::getClassAnnotations($class);


        $info = PersistenceDB::getMemberPersistenceInfo($class);
        $persistent_fields = $info['persistent_fields'];

        $done = array();

        if (isset($classDoc['editorTemplate'])) {
            foreach ($classDoc['editorTemplate'] as $field => $unused) {
                $doc = $persistent_fields[$field];
                if (isset($doc)) {
                    $editForm .= self::fieldEditor($item->$field, "$prefix$field$suffix", $doc, $scriptParts, "$class::\$$field", $info['foreign_fields'][$field]);
                    $done[$field] = true;
                } else if (isset($done[$field])) {
                    // should warn about duplicate field
                } else {
                    // should warn about missing field
                }
            }
        }

        foreach ($persistent_fields as $field => $doc) if (!isset($done[$field])) {
            $editForm .= self::fieldEditor($item->$field, "$prefix$field$suffix", $doc, $scriptParts, "$class::\$$field", $info['foreign_fields'][$field]);
            $done[$field] = true;
        }
        return $editForm;
    }


    public static function fieldEditor($value, $key, $annotations, &$scriptParts, $context = null, $idProxyAnnotations = null) {
        $type = $annotations['var'];
        $content = $annotations['content'];
        $editor = $annotations['editor'];
        $role = $annotations['role'];

        if ($editor['hidden']) {
            if (count($role)) {
                $field = self::_hiddenField($value, $key, $annotations, $context);
            } else if (self::$debugHiddenFields) {
                $label = self::fieldLabel($context, $annotations);
                $field = "\n<div><label style='color:white;background:#707'>Ignored: <b>$label</b> $value</label></div>";
            } else {
                $field = '';
            }
            return $field;
        }

        $label = self::fieldLabel($context, $annotations);

        if ($editor['readonly']) {
            $field = self::_showField($label, $value, $key, $annotations, $context, $idProxyAnnotations, $scriptParts);

            if (count($role)) {
                $field = self::_hiddenField($value, $key, $annotations, $context)
                       . $field;
            }

        } else {
            $field = self::_editField($label, $value, $key, $annotations, $scriptParts, $context, $idProxyAnnotations);
        }
        return $field;
    }

    public static function showField($value, $key, $annotations, $context = null, $idProxyAnnotations = null, &$scriptParts) {
//        $type = $annotations['var'];
//        $content = $annotations['content'];
//        $editor = $annotations['editor'];

        $label = self::fieldLabel($context, $annotations);

        return self::_showField($label, $value, $key, $annotations, $context, $idProxyAnnotations, $scriptParts);
    }

    public static function hiddenField($value, $key, $annotations, $context = null) {
//        $type = $annotations['var'];
//        $content = $annotations['content'];
//        $editor = $annotations['editor'];

        return self::_hiddenField($value, $key, $annotations, $context);
    }

    private static function _editField($label, $value, $key, $annotations, &$scriptParts, $context, $idProxyAnnotations) {
        $type = $annotations['var'];
        $content = $annotations['content'];
//        $editor = $annotations['editor'];

        $simpleType = TypeUtil::getSimpleType($type);

        if ($idProxyAnnotations) {
            $field = self::_objectSelector($label, $value, $key, $annotations, $context, $idProxyAnnotations, $scriptParts);

        } else if ($content['date']) {
            $field = HTMLUtils::dateField($key, $value, [], $scriptParts);

        } else if ($content['html']) {

            $contentClasses = implode(' ',array_map(function($v){return "content_$v";}, array_keys($content)));

            $field = HTMLUtils::textArea($key, $value, array(
                'class' => $contentClasses,
                'rows' => 20,
                'cols' => 50,
            ), $scriptParts);

        } else if ($simpleType === 'float') {
            // should make float editor...
            $field = HTMLUtils::integerField($key, $value, [], $scriptParts);

        } else if ($simpleType === 'int') {
            $field = HTMLUtils::integerField($key, $value, [], $scriptParts);

        } else { // if ($simpleType === 'mixed' || $simpleType === 'string') {
            $field = HTMLUtils::textField($key, $value, array(
                'style' => 'width:30em;',
            ), $scriptParts);
        }

//        if ($type['null']) {
        // should surround with checkbox
//        }
//        $field .= var_export($annotations,1);

        return "\n<div><label><b>$label:</b> $field <span class='validation'></span></label></div>";
    }

    private static function _showField($label, $value, $key, $annotations, $context,
                                       $idProxyAnnotations = null, &$scriptParts) {
        $type = $annotations['var'];
        $content = $annotations['content'];
//        $editor = $annotations['editor'];

        $displayTag = null;

        if ($idProxyAnnotations) {
            $display = self::_objectShow($label, $value, $key, $annotations, $context, $idProxyAnnotations, $scriptParts);

        } else if ($content['date']) {
            if (!($value > 0)) {
                $display = '---';
            } else {
                $display = null;

                $rfcDate = date('c', $value);
                $htmlDate = strtr(Lang::date('H:i(:s) ~ d M Y', $value),array('('=>'<sub>',')'=>'</sub>','~'=>NBSP));
                $displayTag = "<time class='value-display value-date' datetime='$rfcDate'>$htmlDate</time>";
            }

        } else if ($content['html']) {
            $display = Tag::encode($value);

//        } else if ($simpleType === 'float') {
//        } else if ($simpleType === 'int') {
        } else { // if ($simpleType === 'mixed' || $simpleType === 'string') {
            $display = Tag::encode((string)$value);
        }

//        $display .= var_export($annotations,1);

        if ($displayTag === null) {
            $displayTag = "<span class='value-display'>$display</span>";
        }
        return "\n<div><label><b>$label:</b> $displayTag</label></div>";
    }

    private static function _hiddenField($value, $key, $annotations, $context) {
        $type = $annotations['var'];
        $content = $annotations['content'];

        $simpleType = TypeUtil::getSimpleType($type);

//        if ($type['null'] && $value === null) {
//            // if field has null value, only provide null-value field
//            $field = HTMLUtils::hiddenField(makeKey($key, '~null'), array('value'=>1));
//
//        } else
        if ($content['date']) {
            // should take account of date out/input parsing
            $field = HTMLUtils::hiddenField($key, compact('value'));

        } else if ($content['html']) {
            $field = HTMLUtils::hiddenField($key, compact('value'));

        } else if ($simpleType === 'float') {
            // should make float editor...
            $field = HTMLUtils::hiddenField($key, compact('value'));

        } else if ($simpleType === 'int') {
            $field = HTMLUtils::hiddenField($key, compact('value'));

        } else { // if ($simpleType === 'mixed' || $simpleType === 'string') {
            $field = HTMLUtils::hiddenField($key, compact('value'));
        }

        if (self::$debugHiddenFields) {
            $label = self::fieldLabel($context, $annotations);
            $field .= "\n<div><label style='color:white;background:black'>Hidden: <b>$label</b> $value</label></div>";
        }

        return $field;
    }

    /**
     * @param string|null $context
     * @param array $annotations
     * @return mixed
     */
    public static function fieldLabel($context, $annotations) {
        if (is_array($annotations['label'])) {
            reset($annotations['label']);
            $label = key($annotations['label']);
        } else if ($context !== null) {
            if (strpos($context,'::') !== false) {
                list($ns, $class, $member) = NamespaceUtil::splitMember($context);
                $label = trim($member, '$()');
            } else {
                $label = $context;
            }
        } else {
            $label = 'Unlabelled';
        }
        return $label;
    }

    public static function parseUserInput($data, $field, $doc, $idProxyDoc = null) {
        // TODO!!!!!!!

        $input = $data['editor'][$field];

        $value = null;

        if (!isset($input)) {
            $validation = 'Value missing from input';
        } else if (!is_scalar($input)) {
            $validation = 'Value supplied as ' . gettype($input);
        } else {
            $value = $input;
            $validation = null;
        }

        return array($value, $validation);
    }

    private static function _objectShow($label, $value, $key, $annotations, $context, $idProxyAnnotations, &$scriptParts) {
        $classes = array_keys($annotations['var']['object']);
        $class = reset($classes);

        if (empty($class)) {
            throw new \InvalidArgumentException("Object selector cannot display non-existent class '$class'");
        } else if (!PersistenceDB::getClassPersistenceInfo($class)) {
            throw new \InvalidArgumentException("Object selector cannot display non-persistent class '$class'");
        }

        $currentId = self::objectLabel($value, $class);

        // should explicitly ensure that _objectShow and _showField can never go into a loop
        return self::_showField($label, $currentId, $key, $idProxyAnnotations, $context, null, $scriptParts);
    }
    private static function _objectSelector($label, $value, $key, $annotations, $context, $idProxyAnnotations, &$scriptParts) {
        $classes = array_keys($annotations['var']['object']);
        $class = reset($classes);

        if (empty($class)) {
            throw new \InvalidArgumentException("Object selector cannot display non-existent class '$class'");
        } else if (!PersistenceDB::getClassPersistenceInfo($class)) {
            throw new \InvalidArgumentException("Object selector cannot display non-persistent class '$class'");
        }

        $idField = ltrim(ClassRegistryUtils::findMemberWithRole($class, 'id'), '$');

        return HTMLUtils::selectField($key, $value->$idField, array(
            'values' => self::makeSelectLabels($class),
            'style' => 'width:30em;',
        ), $scriptParts);
    }

    private static function makeSelectLabels($class) {
        $labels = array();
        $idField = ltrim(ClassRegistryUtils::findMemberWithRole($class, 'id'), '$');
        $nameField = ltrim(ClassRegistryUtils::findMemberWithRole($class, 'name'), '$');

        foreach (PersistenceDB::findItems($class, Query::matchAll()) as $id => $item) {
            if (strlen($nameField)) {
                $label = $item->$nameField;
            } else {
                $label = $class . NBSP . ($item->$idField);
            }

            $labels[$id] = $label;
        }

        return $labels;
    }

    /**
     * @param object|null $value
     * @param string|null $class
     * @return string
     */
    public static function objectLabel($value, $class = null) {
        if ($class !== null && is_object($value)) $class = get_class($value);

        $idField = ltrim(ClassRegistryUtils::findMemberWithRole($class, 'id'), '$');
        $nameField = ltrim(ClassRegistryUtils::findMemberWithRole($class, 'name'), '$');

        if ($value instanceof $class) {
            if (strlen($nameField)) {
                return $value->$nameField;
            } else {
                return $class . NBSP . ($value->$idField);
            }
        } else {
            return 'null';
        }
    }

}