<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 01/06/2014
 * Time: 17:33
 */

namespace CMS;


/**
 * Class Template
 * @package CMS
 * @Framework
 * @persist
 */
class Template extends \Framework\TimeStampedItem {
    /**
     * @var string
     * @persist length=191
     * @role name
     */
    public $template_name;

    /**
     * @var string
     * @persist length=65535
     * @content tags,html,htmlDocument
     */
    public $template_source;
}