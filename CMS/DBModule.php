<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 21/06/2014
 * Time: 22:19
 */

namespace CMS;
use Framework\ClassRegistryUtils;
use Framework\PersistenceDB;
use Framework\Query;
use Framework\StringUtil;


/**
 * DBModule implements methods for the built-in data handling module.
 *
 * @package CMS
 */
class DBModule implements CMSModule {

    public static function getModuleUrl($dbClass, $item) {
        $idField = ltrim(ClassRegistryUtils::findMemberWithRole('id', $dbClass), '$');
        $moduleUrlField = ltrim(ClassRegistryUtils::findMemberWithRole('moduleUrl', $dbClass), '$');
        $moduleUrlSuffixField = ltrim(ClassRegistryUtils::findMemberWithRole('moduleUrlSuffix', $dbClass), '$');

        if (strlen($moduleUrlField)) {
            $moduleUrl = $item->$moduleUrlField;

            if (!($moduleUrl === '' || $moduleUrl{0} === '/')) {
                // Invalid module URL - supply id-only URL
                return '/!id'.$item->$idField;
            }

            if (strlen($moduleUrlSuffixField)) {
                return $moduleUrl.$item->$moduleUrlSuffixField;
            } else {
                return $moduleUrl;
            }
        } else {
            return '/'.$item->$idField;
        }
    }

    /**
     * @param $dbClass
     * @param $moduleUrl
     * @return object|null An object of $dbClass will be returned, or null if not found.
     * @throws \ErrorException
     */
    static function loadByModuleUrl($dbClass, $moduleUrl) {
        $idField = ltrim(ClassRegistryUtils::findMemberWithRole('id', $dbClass), '$');
        $moduleUrlField = ltrim(ClassRegistryUtils::findMemberWithRole('moduleUrl', $dbClass), '$');
        $moduleUrlSuffixField = ltrim(ClassRegistryUtils::findMemberWithRole('moduleUrlSuffix', $dbClass), '$');

        if (strlen($moduleUrlField)) {
            if (strlen($moduleUrlSuffixField)) {
                list($path, $ext) = URLUtil::pathSplit($moduleUrl);

                $item = PersistenceDB::findItem($dbClass, Query::compareField($moduleUrlField, '==', $path));

                if (is_object($item) && $ext !== (string)$item->$moduleUrlSuffixField) {
                    // should return something more helpful
                    throw new \ErrorException("Item does not exist at $moduleUrl, wrong extension.");
                }
            } else {
                $item = PersistenceDB::findItem($dbClass, Query::compareField($moduleUrlField, '==', $moduleUrl));
            }

        } else if (strlen($idField)) {
            if (preg_match('~^/\S{1,200}$~u', $moduleUrl)) {
                $item = PersistenceDB::findById($dbClass, substr($moduleUrl, 1));

            } else {
                throw new \ErrorException("Module cannot handle that URL ($moduleUrl).");
            }
        } else {
            throw new \ErrorException("Class $dbClass has no id field.");
        }
        return $item;
    }

    /**
     * @param $moduleCodename
     * @param $moduleClass
     * @param $moduleUrl
     * @return array
     * @throws \ErrorException
     */
    public static function renderEditorByIdUrl($moduleCodename, $moduleClass, $moduleUrl) {
        $script = [];

        if (preg_match('~^/[0-9]+$~', $moduleUrl)) {
            $itemId = (int)substr($moduleUrl, 1);

            $item = PersistenceDB::findItem($moduleClass, Query::compareField('id','==',$itemId));

            if (!($item instanceof $moduleClass)) {
                return "<div>Item $moduleClass $itemId does not exist.</div>";
            }

            $content = self::editForm($moduleCodename, $moduleClass, null, $item, $script);
            $result = compact('content','script');
            return $result;

        } else if ($moduleUrl === '' || $moduleUrl === '/') {
            $content = self::editMenu($moduleCodename, $moduleClass, $script);
            $result = compact('content','script');

            return $result;

        } else {
            throw new \ErrorException("Wrong address, module '$moduleClass' does not handle '$moduleUrl'");
        }
    }

    /**
     * @param $moduleCodename
     * @param $moduleClass
     * @param $moduleUrl
     * @return array
     * @throws \ErrorException
     */
    public static function renderEditorByModuleUrl($moduleCodename, $moduleClass, $moduleUrl) {
        $urlField = ltrim(ClassRegistryUtils::findMemberWithRole('moduleUrl', $moduleClass), '$');
        $urlSuffixField = ltrim(ClassRegistryUtils::findMemberWithRole('moduleUrlSuffix', $moduleClass), '$');
        $script = [];

        list($path,$ext) = URLUtil::pathSplit($moduleUrl);

        $isValid = Page::isValidPath($path);

        if (!$isValid) {
            // should show redirect to nearest valid path
            throw new \ErrorException("Wrong address, module '$moduleClass' cannot handle requests at '$moduleUrl'");
        }

        $item = PersistenceDB::findItem($moduleClass, Query::compareField($urlField,'==',$path));

        if ($item !== null) {
            $currentExt = strlen($urlSuffixField) ? (string)($item->$urlSuffixField) : '';

            if ($ext !== $currentExt) {
                URLUtil::redirectLocal('!' . $moduleCodename . $item->$urlField . $currentExt);
                exit;
            }
        } else {
            // offer to create
            $item = new $moduleClass();

            $defaultExt = strlen($urlSuffixField) ? (string)($item->$urlSuffixField) : '';

            $item->$urlField = $path;

            $nameField = ltrim(ClassRegistryUtils::findMemberWithRole('name', $moduleClass), '$');
            if (strlen($nameField)) {
                $item->$nameField = 'New ' . strtr($moduleClass, '\\', ' ');
            }

            $langField = ltrim(ClassRegistryUtils::findMemberWithRole('lang', $moduleClass), '$');
            if (strlen($langField)) {
                $item->$langField = Config::get('site.lang');
            }

            if (strlen($urlSuffixField) && $ext !== $defaultExt) {
                // redirect to default extension for this object type
                URLUtil::redirectLocal('!' . $moduleCodename . $item->$urlField . $defaultExt);
                exit;
            }
        }

//        $script['jquery_document_ready'][] = 'alert("document ready");';
//        $script['jquery_window_load'][] = 'alert("window loaded");';

        $content = self::editForm($moduleCodename, $moduleClass, null, $item, $script);

        return compact('content','script');
    }

    public static function editMenu($moduleCodename, $moduleClass, &$scriptParts, $target = null) {
        return TreeModule::listAll($moduleCodename, $moduleClass, $scriptParts, $target);
    }

    /**
     * @param $moduleCodename
     * @param $moduleClass
     * @param null|string $actionUrl  Use null for the default form URL, or supply a custom URL here.
     * @param $item
     * @param $scriptParts
     * @return string
     */
    public static function editForm($moduleCodename, $moduleClass, $actionUrl, $item, &$scriptParts) {
        $editForm = Editor::itemEditor($moduleClass, $item, 'editor', $scriptParts);

        $toolbar = CMSBackendUtils::standardToolbar();

        if ($actionUrl === null) {
            $moduleUrl = DBModule::getModuleUrl($moduleClass, $item);

            $actionUrl = "!$moduleCodename$moduleUrl";
        }

        $actionUrlEnc = Tag::encode($actionUrl);

        return "<form method='POST' action='$actionUrlEnc'>"
//        ."<a href='$actionUrlEnc'>$actionUrlEnc</a>"
        ."$editForm$toolbar</form>";
    }


    /**
     * @var string  The class which this module will manage.
     */
    private $dbClass;

    /**
     * DBModule breaks the general contact of CMSModule constructors,
     * which should have no parameters in general.
     *
     * @param $dbClass
     */
    function __construct($dbClass) {
        $this->dbClass = $dbClass;
    }



    /**
     * Renders or returns module content.
     *
     * The result array has the following keys:
     *
     * If the module renders directly to the browser, use:
     *   * 'directOutput' - set to true to prevent any further output from the CMS.
     *
     * If the module returns content for the page template, use:
     *   * 'content' - content to be placed in the CMS template.
     *   * 'title' - page title.
     *   * 'fullPage' - content will be placed directly on the page, rather than in the CMS template.
     *
     * @param string $moduleUrl
     * @return array
     */
    public function renderModuleUrl($moduleUrl) {
        $idField = ltrim(ClassRegistryUtils::findMemberWithRole('id',$this->dbClass), '$');
        $urlField = ltrim(ClassRegistryUtils::findMemberWithRole('moduleUrl', $this->dbClass), '$');

        $moduleCodename = array_search($this->dbClass, CMS::$modules);

        if (strlen($urlField)) {
            $result = self::renderEditorByModuleUrl($moduleCodename, $this->dbClass, $moduleUrl);
        } else if (strlen($idField)) {
            $result = self::renderEditorByIdUrl($moduleCodename, $this->dbClass, $moduleUrl);
        } else {
            throw new \RuntimeException("Cannot edit class without an id field");
        }
        return $result;
    }

    /**
     * Implements the interface between this module and the backend.
     *
     * This is most commonly used when handling a JSON request from an
     * actionCommand button, but it can also be used directly.
     *
     *
     * If the command is executed successfully, $response['ok'] must be true.
     *
     * In case of error, $response['error'] should be set to a description.
     *
     * @param string $actionUid Client-submitted action UID.
     * @param string $moduleUrl
     * @param string $actionCommand
     * @param array $data Parameters for the action command.
     * @return array Results of executing the command.
     */
    public function actionCommand($actionUid, $moduleUrl, $actionCommand, $data) {
        if ($actionCommand === 'save') {
            $response = ActionUtils::actionResult($actionUid, $actionCommand, function()use($actionUid, $moduleUrl, $actionCommand, $data){

                $info = PersistenceDB::getMemberPersistenceInfo($this->dbClass);

                if (!$info) {
                    throw new \ErrorException("Not a persistent class: '$this->dbClass'");
                }

                $persistent_fields = $info['persistent_fields'];
                $foreign_fields = $info['foreign_fields'];


                $validationProblems = array();

                $item = DBModule::loadByModuleUrl($this->dbClass, $moduleUrl);

                if ($item === null) {
                    $moduleAllowsCreation = ($this->dbClass === 'CMS\Page');
                    $moduleAllowsPath = Page::isValidPath($moduleUrl);

                    if (!$moduleAllowsCreation) {
                        throw new \ErrorException('Module does not allow item creation.');
                    } else if (!$moduleAllowsPath) {
                        throw new \ErrorException('Module does not allow item creation at that path.'.$moduleUrl);
                    } else {
                        $dbClass = $this->dbClass;
                        $item = new $dbClass();

                        $moduleUrlField = ltrim(ClassRegistryUtils::findMemberWithRole('moduleUrl', $dbClass));
                        $moduleUrlSuffixField = ltrim(ClassRegistryUtils::findMemberWithRole('moduleUrlSuffix', $dbClass));

                        if (strlen($moduleUrlSuffixField)) {
                            list($path, $ext) = URLUtil::pathSplit($moduleUrl);

                            $item->$moduleUrlField = $path;
                            $item->$moduleUrlSuffixField = $ext;
                        } else {
                            $item->$moduleUrlField = $moduleUrl;
                        }
                    }
                }
                if ($item === null) {
                    throw new \ErrorException("Item 'does not exist.");
                }

//                $debug_written = array();

                foreach ($persistent_fields as $field => $doc) {
                    if ($doc['editor']['hidden'] || $doc['editor']['readonly']) {
                        // ignore
                    } else {
                        if ($foreign_fields[$field]) {
                            list($value, $validation) = Editor::parseUserInput($data, $field, $doc, $foreign_fields[$field]);
                            $write = "__{$field}_id";
                        } else {
                            list($value, $validation) = Editor::parseUserInput($data, $field, $doc);
                            $write = $field;
                        }

                        if ($field === 'template' && !$value) {
                            $validationProblems[$field] = "$field / $write = '$value' ".gettype($value);
                        }

                        if ($validation === null) {
                            $item->$write = $value;
//                            $debug_written["$field / $write"] = $value;
                        } else {
                            $validationProblems[$field] = $validation;
                        }
                    }
                }


                if (count($validationProblems)) {
                    return ActionUtils::failure(var_export($validationProblems, 1));
//                    return ActionUtils::failure(compact('validationProblems'));
                } else if (PersistenceDB::storeItem($item)) {
//                    return ActionUtils::failure('GOT '.var_export($debug_written,1));
                    return ActionUtils::success();
                } else {
                    return ActionUtils::failure('PersistenceDB error.');
                }

            });

        } else if ($actionCommand === 'delete') {
            $response = ActionUtils::actionResult($actionUid, $actionCommand, function()use($actionUid, $moduleUrl, $actionCommand, $data){

//                $info = PersistenceDB::getClassPersistenceInfo($this->dbClass);

                $item = DBModule::loadByModuleUrl($this->dbClass, $moduleUrl);

                if ($item === null) {
                    throw new \ErrorException("Item does not exist.");
                }

                if (PersistenceDB::deleteItem($item)) {
                    return ActionUtils::success();
                } else {
                    return ActionUtils::failure(['reason'=>'PersistenceDB error.']);
                }

            });

        } else {
            $response = array(
                'error' => 'This module does not handle this URL.',
                'responseOrigin' => 'DBModule::actionCommand',
            );
        }
        return $response;
    }

    /**
     * Describes the entries presented for this module in the module tree.
     *
     * @return array
     */
    public function getModuleTree() {
        // should fill this method with content from CMSBackendUtils or TreeModule
    }

}