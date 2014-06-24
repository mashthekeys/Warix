<?php
namespace CMS;

class HTMLUtils {
    public static function implodeAtts($atts) {
        $A = '';
        if (count($atts)) {
            foreach ($atts as $k => $v) {
                $A .= ' ' . Tag::encode($k) . '="' . Tag::encode($v) . '"';
            }
            $A = substr($A, 1);
        }
        return $A;
    }

//    const FORMKEY = 'values';

//    public static function buildForm($formHandler, $options, $content) {
//        if (is_array($options)) {
//            $method = $options['method'];
//        } else {
//            $method = $options;
//            $options = array();
//        }
//
//        if (empty($method)) $method = 'POST';
//
//        $url = SiteManager::formSubmitUrl($formHandler);
//
//        $atts = array();
//
//        if (strlen($options['validationFn'])) {
//            $atts['onsubmit'] = 'return JSForm.submitForm(this,'.$options['validationFn'].');';
//        }
//
//        $atts = HTMLUtils::implodeAtts($atts);
//
//        return "<form action='$url' method='$method' $atts>\n$content\n</form>'";
//    }

    public static function labelledField($label, $field) {
        return "\n<label><b>$label</b> $field</label>";
    }

    public static function textField($name, $value='', $options=array(), &$scriptParts) {
        $atts = HTMLUtils::composeTagAtts($name, $value, $options, $scriptParts);

        return "<input type='text' $atts>";
    }
    
    public static function textArea($name, $value='', $options=array(), &$scriptParts) {
        $atts = array();

        if ($options['rows']) {
            $atts['rows'] = $options['rows'];
        }
        if ($options['cols']) {
            $atts['cols'] = $options['cols'];
        }

        $atts = HTMLUtils::composeTagAtts($name, null, $options, $scriptParts, $atts);

        $text = Tag::encode($value);

        return "<textarea $atts>$text</textarea>";
    }
    public static function integerField($name, $value='', $options=array(), &$scriptParts) {
        // TODO add javascript validation
        return self::textField($name, $value, $options, $scriptParts);
    }
    public static function dateField($name, $value='', $options=array(), &$scriptParts) {
        // TODO add jquery ui datepicker
        return self::textField($name, date('c', $value), $options, $scriptParts);
    }

    public static function checkBox($name, $value = 1, $options = array(), &$scriptParts) {
        return self::_____Box('check', $name, $value, $options, $scriptParts);
    }
    public static function radioBox($name, $value = 1, $options = array(), &$scriptParts) {
        return self::_____Box('radio', $name, $value, $options, $scriptParts);
    }
    private static function _____Box($type, $name, $value, $options, &$scriptParts) {
        $atts = compact('type');

        $atts = HTMLUtils::composeTagAtts($name, $value, $options, $scriptParts, $atts);

        return "<input $atts>";
    }

    public static function submitButton($label, $options = array(), &$scriptParts) {
        return self::_button('submit', $label, $options);
    }
    public static function clickButton($label, $options = array(), &$scriptParts) {
        return self::_button('button', $label, $options, $scriptParts);
    }
    private static function _button($type, $label, $options = array(), &$scriptParts) {
        $atts = self::composeTagAtts($options['name'], $options['value'], $options, $scriptParts);

        return "<button type='$type' $atts>$label</button>";
    }

    public static function hiddenField($name, $options = array()) {
        $atts = array();
        if ($name !== null) $atts['name'] = $name;

        if ($options['id']) $atts['id'] = $options['id'];
        if ($options['value']) $atts['value'] = $options['value'];
        if ($options['disabled']) $atts['disabled'] = 'disabled';

        $atts = HTMLUtils::implodeAtts($atts);

        return "<input type='hidden' $atts />";
    }

    public static function pre_export($var,$return = true) {
        $var = Tag::encode(var_export($var, true));
        $var = "\n<pre class='php_export'>$var</pre>\n";

        if ($return) {
            return $var;
        } else {
            echo $var;
            return null;
        }
    }

    private static $nextId = array();

    public static function nextId($prefix = '_id') {
        return ++self::$nextId[$prefix];
    }

    public static function selectField($name, $value, $options, &$scriptParts) {
        $options['name'] = $name; // TODO should not need to do this!

        $output = self::_options($value, $options);

        $atts = self::composeTagAtts($name, $value, $options, $scriptParts);

        return "<select $atts>\n$output\n</select>";
    }

    /**
     * @param $name
     * @param $value
     * @param $options
     * @param $scriptParts
     * @param array $atts
     * @return array
     */
    private static function composeTagAtts($name, $value, $options, &$scriptParts, $atts = array()) {
        $jsId = '""';

        $id = $options['id'];
        $needsId = $options['onclick'] || $options['onclickFn']
                || $options['onselect'] || $options['onselectFn'];
        if ($needsId && !strlen($id)) {
            $id = HTMLUtils::nextId('btn');
        }


        if (strlen($id)) {
            $jsId = json_encode('#' . $id);
            $atts['id'] = $id;
        }

        if (strlen($name)) $atts['name'] = $name;

        if ($value !== null) $atts['value'] = $value;

        if ($options['readonly']) $atts['readonly'] = 'readonly';

        if ($options['disabled']) $atts['disabled'] = 'disabled';

        if (strlen($options['class'])) $atts['class'] = $options['class'];

        if (strlen($options['style'])) $atts['style'] = $options['style'];

        if ($options['onclickFn']) {
            $scriptParts['jquery_document_ready'][] = "$($jsId).click($options[onclickFn]);";
        } else if ($options['onclick']) {
//            $atts['onclick'] = $options['onclick'];
            $scriptParts['jquery_document_ready'][] = "$($jsId).click(function(){{$options['onclick']}});";
        }

        if ($options['onchangeFn']) {
            $scriptParts['jquery_document_ready'][] = "$($jsId).change($options[onchangeFn]);";
        } else if ($options['onchange']) {
            $scriptParts['jquery_document_ready'][] = "$($jsId).change(function(){{$options['onchange']}});";
        }

        $atts = HTMLUtils::implodeAtts($atts);
        return $atts;
    }

    /**
     * @param $value
     * @param $options
     * @return string
     */
    public static function _options($value, $options) {
        $values = $options['values'];
        if (!is_array($values)) $values = array();

        $valueStr = (string)$value;

        $groupValues = $options['groupValues'];
        if (!is_array($groupValues)) $groupValues = null;

        $groupLabels = $options['groupLabels'];
        if (!is_array($groupLabels)) $groupLabels = null;

        $plain = array();
        $groups = array();

        foreach ($values as $optValue => $optLabel) {
            $optValue = (string)$optValue;
            $optValueEnc = Tag::encode($optValue);
            $optLabelEnc = Tag::encode($optLabel);

            $selected = $valueStr === $optValue;
            $prop = $selected ? ' selected' : '';

            $optTag = "<option value='$optValueEnc'$prop>$optLabelEnc</option>";

            $optGroup = null;
            if ($groupValues !== null) {
                $optGroup = $groupValues[$optValue];
            }

            if ($optGroup === null) {
                $plain[] = $optTag;
            } else {
                $groups[$optGroup][] = $optTag;
            }
        }

        if (!array_key_exists($valueStr, $values)) {
            if (strlen($valueStr)) {
                $optLabelEnc = "Current value: $valueStr";
            } else {
                $optLabelEnc = "Blank";
            }
            $prop = ' selected';
            $optTag = "<option value='$valueStr'$prop>$optLabelEnc</option>";
            $optGroup = '__invalid';
            $groups[$optGroup][] = $optTag;
        }

        $output = implode('', $plain);

        foreach ($groups as $groupId => $tags) {
            $groupIdEnc = Tag::encode($groupId);

            if ($groupLabels !== null) {
                $optLabel = $groupLabels[$groupId];

                if ($optLabel !== null) {
                    $optLabelEnc = Tag::encode($optLabel);
                } else {
                    $optLabelEnc = $groupId;
                }
            } else {
                $optLabelEnc = $groupId;
            }

            $output .= "\n<optgroup label='$optLabelEnc' class='$groupIdEnc'>";
            $output .= implode('', $tags);
            $output .= "</optgroup>";
        }
        return $output;
    }

}