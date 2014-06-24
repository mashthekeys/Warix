<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 21/05/2014
 * Time: 00:59
 */

namespace CMS;

final class Tag {

    const START_MARKER = '[[';
    const END_MARKER = ']]';
    const TERMINATOR_MARKER = '/';

    const REGEX = '/\[\[([-_:0-9A-Za-z]+)(|\s[^\[\]\/]*)(?:\/\]\]|\]\](.*)\[\[\/\g1\s*\]\])/us';
    //               [ [      $1=tag       $2 = attributes  / ] ]OR] ] $3  [ [ / $1    ] ]

    /**
     * IMPORTANT
     *
     * Where possible, Tag::encode should replace htmlspecialchars, to ensure that Tag content is not parsed.
     *     e.g. $input = '<input value="' . Tag::encode($value) . '>';
     *
     * If HTML entity encoding is not desired, set $html to false.
     *
     * @param string $str
     * @param bool $html
     * @return mixed
     */
    public static function encode($str, $html = true) {

        if (!is_scalar($str)) {
            if (is_object($str)) {
                if (method_exists($str,'__toString')) {
                    $str = (string)$str;
                } else {
                    $type = gettype($str);
                    trigger_error("Warning: Tag::encode() expects parameter 1 to be string, $type given", E_USER_WARNING);
                    debug_print_backtrace();
                    $str = $type;
                }
            }
        }

        if ($html) $str = htmlspecialchars($str, ENT_QUOTES);

        return str_replace(']','&#93;',str_replace('[','&#91;',$str));
    }

    /**
     * @param $content string
     * @param $context array|object
     * @return string
     */
    public static function interpret($content, $context = array()) {
        if (strpos($content, '[[') === false) return $content;

        $vars = self::mergeContext($context);

        $recurseLimit = 100;

        do {
            $content = preg_replace_callback(self::REGEX, function ($match) use ($vars,$recurseLimit) {
                $name = $match[1];
                $atts = $match[2];
                $selfClose = count($match) < 4;
                $content = $match[3];

                if (!$recurseLimit) {
                    $replaceContent = self::markFailedInterpretation($name, $atts, $selfClose, $content, null, 'Tag recursion loop');
                } else {
                    $tag = self::createTag($name, $atts, $content, $vars);

                    if (is_object($tag)) {
                        try {
                            $replaceContent = $tag->render();
                        } catch (\Exception $error) {
                            $replaceContent = self::markFailedInterpretation($name, $atts, $selfClose, $content, $tag, $error);
                        }
                    } else {
                        $replaceContent = self::markFailedInterpretation($name, $atts, $selfClose, $content, null);
                    }
                }
                return $replaceContent;
            }, $content, -1, $count);
        } while ($count && $recurseLimit--);

        return $content;
    }

    /**
     * @param array|object $context
     * @return array
     */
    public static function mergeContext($context) {
        if (!is_array($context)) {
            $context = array($context);
        }

        $vars = array();
        foreach ($context as $item) {
            if (is_array($item)) {
                $vars = $vars + $item;
            } else if (is_object($item)) {
                $vars = $vars + get_object_vars($item);
            } else {
                trigger_error('Tag::mergeContext: invalid context item '.substr($item,0,30), E_USER_WARNING);
            }
        }

        return $vars;
    }

    private static function markFailedInterpretation($name, $atts, $selfClose, $content, $tag, $error = null) {
        if (strlen($atts)) $atts = ' '.ltrim($atts);

        if (is_object($error)) {
            $reason = "Tag interpretation failed: ".get_class($error);
        } else if (strlen($error)) {
            $reason = "Tag interpretation failed: ".get_class($error);
        } else if ($tag) {
            $reason = "Tag interpretation failed.";
        } else {
            $reason = "Unknown tag.";
        }

        if ($selfClose) {
            return "<!--\n(($name$atts /))\n$reason\n-->";
        } else {
            return "<!--\n(($name$atts))\n$reason\n-->"
                 . "\n$content"
                 . "<!--\n((/$name))\n$reason\n-->";
        }
    }

    /**
     * @param string $name
     * @param string|array $atts
     * @param string|null $content
     * @param array $vars
     * @return object|null
     */
    public static function createTag($name, $atts, $content = null, $vars = array()) {

        if (!is_array($atts)) {
//            $attSrc = $atts;

            $atts = self::parseAttributes($atts);

//            echo "\$name = $name\n";
//            echo "\$attSrc = $attSrc\n";
//            echo "\$atts = ";
//            var_export($atts);
//            echo "\n\n\n";
        }

        if (substr($name,0,5) === 'this:') {
            return new VarTag($name, $atts, $content, $vars);
        } else {
            // TODO load tags dynamically or through autoload

            if ($name === 'cms:menu') {
                include_once __DIR__ . '/tags/TagMenu.php';

                return new \CMS\tags\TagMenu($name, $atts, $content, $vars);

            } else if ($name === 'cms:base') {
                include_once __DIR__ . '/tags/TagBase.php';

                return new \CMS\tags\TagBase($name, $atts, $content, $vars);
            }
        }

        return null;
    }

    /** Parses HTML tag attributes.  Parsing is forgiving, and allows unquoted and missing values.
     *
     * In the case of missing values, the empty string is substituted – for example, writing <tt>checked</tt> is
     * equivalent to writing <tt>checked=""</tt>.  This differs from the HTML spec behaviour
     * where, for example, writing <tt>checked</tt> is equivalent to writing <tt>checked="checked"</tt>
     *
     * Allowed formats:<ul>
     *      <li>attName
     *      <li>attName = attValue
     *      <li>attName = 'att value'
     *      <li>attName = "att value"
     * </ul>
     *
     * @param string $atts
     * @return array
     */
    public static function parseAttributes($atts) {
        $parsed = array();

        $pos = 0;
        $END = strlen($atts);

        $pos += strspn($atts, " \t\r\n", $pos); // whitespace

        while ($pos < $END) {
            $nameLen = strcspn($atts, "= \t\r\n", $pos + 1) + 1;

            $name = substr($atts, $pos, $nameLen);

            $pos += $nameLen;

            $pos += strspn($atts, " \t\r\n", $pos); // whitespace

            if ($atts{$pos} !== '=') {
                $value = '';
            } else {
                ++$pos; // =

                $pos += strspn($atts, " \t\r\n", $pos); // whitespace

                $q = $atts{$pos};
                if ($q === '"' || $q === "'") {
                    ++$pos;

                    $valueLen = strcspn($atts, $q, $pos);

                    $value = substr($atts, $pos, $valueLen);

                    $pos += $valueLen + 1;

                    // discard non-whitespace after quotes
                    $pos += strcspn($atts, " \t\r\n", $pos);
                } else {
                    $valueLen = strcspn($atts, " \t\r\n", $pos);

                    $value = substr($atts, $pos, $valueLen);

                    $pos += $valueLen;
                }
            }

            $pos += strspn($atts, " \t\r\n", $pos); // whitespace

            $parsed[$name] = $value;
        }

        return $parsed;
    }
}

/*
echo htmlentities("'",ENT_QUOTES);
 Tag::interpret('
Hello world <br />
[[Testing now="with" some="attributes&amp;and&amp;entities&amp;and&amp;t&#039;ing" /]] <br/>
Goodbye world

Oh wait I\'m [[Test1 this-time-atts="with
line
breaks!"]] with some content!!!
blah blah blah
blah blah blah
blah blah blah
blah blah blah
[[/Test1]]

[[Test2 bare-atts unquoted=atts and-unquoted=entities&ellip;blah&ellip;blah&ellip;blah&ellip; /]]
');
*/

