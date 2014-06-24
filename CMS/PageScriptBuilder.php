<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 21/06/2014
 * Time: 14:53
 */

namespace CMS;


class PageScriptBuilder {
    /**
     * @param string|null $id
     * @param string $javascript
     * @param array|null $atts
     * @return string
     */
    public static function __scriptTag($id, $javascript, $atts = null) {
        if (strlen($id)) {
            $atts['id'] = $id;
        }
        $atts['type'] = 'text/javascript';
        $atts = HTMLUtils::implodeAtts($atts);
        $OPEN = '![CDATA[';
        $CLOSE = ']]';
        return "<script $atts>//<$OPEN\n$javascript\n//$CLOSE></script>";
    }

    /**
     * @param string|null $id
     * @param string $javascript
     * @param array|null $atts
     * @return string
     */
    public static function __jQueryScriptTag($id, $javascript, $atts = null) {
        $javascript = 'jQuery(function($){'
            . $javascript
            . '});';

        return self::__scriptTag($id, $javascript, $atts);
    }

    public static function jquery_document_ready($statements) {
        if (empty($statements)) {
            return '';
        }

        // any jQuery-wrapped function is equivalent to adding a $(document).ready handler
        return self::__jQueryScriptTag('jquery_document_ready', implode("\n", $statements));
    }

    public static function jquery_window_load($statements) {
        if (empty($statements)) {
            return '';
        }

        $javascript = '$(window).load(function(){' . implode("\n", $statements) . '});';

        return self::__jQueryScriptTag('jquery_window_load', $javascript);
    }

}