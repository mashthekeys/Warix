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

class TagMenu extends TagBase implements ITag {
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
        if (strlen($this->atts['depth'])) {
            $depth = (int)$this->atts['depth'];
        } else {
            $depth = 1;
        }

        if (strlen($this->atts['paths'])) {
            $paths = preg_split('/\s+/',$this->atts['paths']);
        } else if (strlen($this->atts['path'])) {
            $paths = preg_split('/\s+/',$this->atts['path']);
        } else {
            $paths = array('/');
        }

        $output = '<ul>';
        $absRoot = URLUtil::absoluteRoot();

        foreach ($paths as $fullPath) {
            list($path, $ext) = URLUtil::pathSplit($fullPath);

            $root = Page::getByPath($path);

            if ($root instanceof Page) {
                $url = Tag::encode($absRoot.$root->getUrl());

                $output .= "<li><a href='$url'>$root->title</a>";

                if ($depth) {
                    $output .= "<ul>";
                    $descendants = $root->getChildrenByPath();
                    foreach ($descendants as $descendant) {
                        // TODO should create hierarchical not flat list
                        $url = Tag::encode($absRoot.$descendant->getUrl());
                        $output .= "<li><a href='$url'>$descendant->title</a></li>";
                    }
                    $output .= "</ul>";
                }

                $output .= "</li>";
            } else {
                $path = Tag::encode($path);
                $output .= "<!-- Missing page: $path -->";
            }
        }

        $output .= "</ul>\n";

        return $output;
    }
} 