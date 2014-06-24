<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 27/05/2014
 * Time: 20:21
 */

namespace CMS;


class VarTag implements ITag {
    private $name;
    private $atts;
    private $content;
    private $vars;

    function __construct($name, $atts, $content, $vars) {
        $this->name = $name;
        $this->atts = $atts;
        $this->content = $content;
        $this->vars = $vars;
    }

    public function render() {

        $varName = substr($this->name,5);

        $varValue = $this->vars[$varName];

        $encode = $this->atts['encode'];

        if (strlen($encode)) {
            foreach (explode(',', $encode) as $e) switch ($e) {
                case 'html':
                    $varValue = Tag::encode((string)$varValue);
                    break;
                case 'js':
                case 'json':
                    $varValue = json_encode($varValue, JSON_HEX_TAG);
                    $varValue = Tag::encode($varValue, false);
                    break;
                case 'url':
                    $varValue = rawurlencode((string)$varValue);
                    $varValue = Tag::encode($varValue, false);
                    break;
            }
        }
        return (string)$varValue;
    }
} 