<?php
namespace CMS;
use Framework\PersistenceDB;
use Framework\Query;
use Framework\TimeStampedItem;

/**
 * @Framework
 * @persist
 * @editorTemplate path,ext,lang,title,content,template,stamp_created,stamp_modified
 * @js export
 * @php export
 */
class Page extends TimeStampedItem {

    /**
     * @label Page Path
     * @var string
     * @persist length=191, unique
     * @role moduleUrl
     */
    public $path;

    /**
     * @label Extension
     * @var string
     * @persist length=10
     * @editor readonly
     * @role moduleUrlSuffix
     */
    public $ext = '/';

    /**
     * @label Title
     * @var string
     * @persist length=191
     * @role name
     */
    public $title;

    /**
     * @label Content
     * @var string
     * @content html,tags
     * @persist length=65536
     */
    public $content;

    /**
     * @label Language
     * @var string
     * @role lang
     * @persist length=20
     * @editor readonly
     */
    public $lang;

    /** @var int */
    public $__template_id;

    /**
     * @label Template
     * @var null|\CMS\Template
     * @persist
     */
    public $template = null;

    /**
     * Overrides the $template field.  This cannot be stored in the database;
     * it is only used to apply page templates at runtime.
     *
     * @var string|null
     */
    public $template_source = null;

    /**
     * Builds JavaScript content to be placed in the header.
     *
     * See buildScript() for implementation.
     *
     * @var array[]
     */
    public $scriptParts = array();

    function __construct() {
        $this->lang = Config::get('site.lang','unk');
    }

    public static function getByPath($path) {
        $page = PersistenceDB::findItem('CMS\Page', Query::compareField('path', '==', $path));

        return $page;
    }

    public function getChildrenByPath($depth = 0) {
        $path = strtr($this->path,array('_'=>'\_','%'=>'\%'));
        $matchChildren = "$path/_%";
        $matchGrandchildren = "$path/_%/%";

        /** @var Page[] $descendants */
        $descendants = array();

        /** @var Page[] $children */
        $children = PersistenceDB::findItems('CMS\Page',
            new Query(
                array('cmp', 'AND',
                    array('cmp', 'LIKE',
                        array('field', null, 'path'),
                        array('value', $matchChildren),
                    ),
                    array('not',
                        array('cmp', 'LIKE',
                            array('field', null, 'path'),
                            array('value', $matchGrandchildren),
                        )
                    )
                )
            )
        );

        foreach ($children as $child) {
            $descendants[$child->path] = $child;
        }

        if ($depth > 0) {
            --$depth;
            foreach ($children as $child) {
                foreach ($child->getChildrenByPath($depth) as $descendant) {
                    $descendants[$child->path] = $descendant;
                }
            }
        }

        return $children;
    }


    public function render() {
        $content = Tag::interpret($this->content, $this);

        $template_source = $this->buildTemplateSource();

        $html = Tag::interpret($template_source, array(
            $this,
            array(
                'content' => $content,
                'script' => $this->buildScript(),
            ),
        ));

        return $html;
    }

    /**
     * @return null|string
     */
    public function buildTemplateSource() {
        if ($this->template_source !== null) {
            $source = $this->template_source;
        } else {
            if ($this->__persistenceDB_incomplete) {

                if ($this->template === null && $this->__template_id > 0) {
                    /** @var Template $template */
                    $this->template = PersistenceDB::findItem('CMS\Template', Query::compareField('id', '==', $this->__template_id));
                }

                unset($this->__persistenceDB_incomplete);
            }


            if ($this->template instanceof Template) {
                $source = $this->template->template_source;
            } else {
                $source = null;
            }
        }

        return strlen($source)
            ? $source
            : BLANK_PAGE_TEMPLATE;
    }

    public function getUrl() {
        return $this->path.$this->ext;
    }

    public function buildScript() {
        $script = '';
        if (is_array($this->scriptParts)) foreach ($this->scriptParts as $part => $statements) {
            if (substr($part,0,2) === '__') continue;

            if (is_string($statements) && preg_match('~^<script\s~iu', $statements)) {
                // <script> tags go out as-is.
                // should check for </script> tag!
                $script .= $statements;
            } else {
                // Wrap statements in <script> tag

                $PSB = '\CMS\PageScriptBuilder';
                if (method_exists($PSB,$part)) {
                    $script .= call_user_func(array($PSB,$part),$statements);
                } else {
                    $code = is_array($statements) ? implode("\n//\n", $statements) : $statements;
                    $script .= PageScriptBuilder::__scriptTag(array('id'=>$part), $code);
                }
            }

        }
        return $script;
    }

    /**
     * should probably move this to a general CMS / Module class.
     * @param $path string
     * @return bool
     */
    public static function isValidPath($path) {
        return $path === '' || $path === '/' ||
        ($path{0} === '/'
            && strlen($local = substr($path,1))
            && strlen($local) == strcspn($local, "?&!<> \t\r\n\v") // no ? & ! < > or spaces anywhere
            && (strpos($local,'//') === false) // no empty path segments
            && ($parts = explode('/', $local))
            && !in_array('.', $parts, true) // no . path segments
            && !in_array('..', $parts, true) // no .. path segments
        );
    }
}