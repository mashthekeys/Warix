<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 01/06/2014
 * Time: 23:42
 */

namespace CMS\tags;


use CMS\ITag;
use CMS\Page;
use CMS\Tag;
use CMS\URLUtil;

class TagBase implements ITag {
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

    function render() {
        $absRoot = Tag::encode(URLUtil::absoluteRoot());

        return "<base href='$absRoot/'>";
    }
} 