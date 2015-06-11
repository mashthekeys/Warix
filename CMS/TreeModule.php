<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 14/06/2014
 * Time: 18:30
 */

namespace CMS;


use Framework\ClassRegistry;
use Framework\ClassRegistryUtils;
use Framework\PersistenceDB;
use Framework\Query;

class TreeModule implements CMSModule
{
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
     * @throws \Exception Any exception thrown by the module will be handled by the CMS exception handler.
     */
    public function renderModuleUrl($moduleUrl) {
        $moduleContent = '';
        $scriptParts = array();

        foreach (CMS::$modules as $modulePath => $moduleClass) {
            if ($moduleClass === __CLASS__) continue;

            $moduleLabel = strtr($moduleClass, array('\\' => NBSP));

            $moduleContent .= "<li><a href='!$modulePath' target='_top'>$moduleLabel</a>";

            $tree = true;

            if (in_array('CMS\CMSModule', class_implements($moduleClass))) {
                /** @var CMSModule $module */
                $module = new $moduleClass();

                $tree = $module->getModuleTree();
            }

            if ($tree === false) {
                // no tree
            } else if ($tree === true) {
                if (PersistenceDB::getClassPersistenceInfo($moduleClass)) {
                    $moduleContent .= self::listAll($modulePath, $moduleClass, $scriptParts, '_top');
                } else {
                    $moduleContent .= "<ul><li class='warning empty_list_placeholder'>Invalid module tree</li></ul>";
                }
            } else if (is_array($tree)) {
                $moduleContent .= '<ul>';
                foreach ($tree as $treeItem) {
                    // TODO something sensible!
                    $moduleContent .= '<li>'.Tag::encode(var_export($treeItem, true)).'</li>';
                }
                $moduleContent .= '</ul>';
            } else {
                $moduleContent .= "<ul><li class='warning empty_list_placeholder'>Invalid module tree</li></ul>";
            }

            $moduleContent .= "</li>";
        }

        $nav = "<ul role='tree'>$moduleContent</ul>";

        return array(
            'content' => $nav,
            'script' => $scriptParts,
            'fullPage' => true,
        );
    }

    /**
     * The Tree module has no entries in the module tree.
     * @return array
     */
    public function getModuleTree() {
        return array();
    }


    /**
     * @param $moduleCodename
     * @param $moduleClass
     * @param &array $scriptParts
     * @param string|null $target Link attribute 'target'
     * @return string
     */
    public static function listAll($moduleCodename, $moduleClass, &$scriptParts, $target = null) {
        $items = PersistenceDB::findItems($moduleClass, Query::matchAll());

        if (!count($items)) {
            return "<ul><li class='empty_list_placeholder'>No $moduleClass items.</li></ul>";
        }
        $output = "<ul>";

        $editUrl = "!$moduleCodename";

        if ($target !== null) $target = " target='" . Tag::encode($target) . "'";

        foreach ($items as $item) {
//            $idField = 'id';
            $idField = ltrim(ClassRegistryUtils::findMemberWithRole($moduleClass, 'id'), '$');
//            $id = $item->$idField;

            $nameField = ltrim(ClassRegistryUtils::findMemberWithRole($moduleClass, 'name'), '$');
            if (strlen($nameField)) {
                $name = $item->$nameField;
            } else {
                $name = $item->$idField;
            }

            $urlField = ltrim(ClassRegistryUtils::findMemberWithRole($moduleClass, 'moduleUrl'), '$');
            if (strlen($urlField)) {
                $url = $item->$urlField;

                $urlSuffixField = ltrim(ClassRegistryUtils::findMemberWithRole($moduleClass, 'moduleUrlSuffix'), '$');
                if (strlen($urlSuffixField)) {
                    $url .= $item->$urlSuffixField;
                }
            } else {
                $url = '/' . $item->$idField;
            }


            $output .= "<li><a href='$editUrl$url'$target>$name</a></li>\n";
        }

        $output .= "</ul>";

        return $output;
    }

    /**
     * Implements the interface between this module and the backend.
     *
     * This is most commonly used when handling a JSON request from an
     * actionCommand button, but it can also be used directly.
     *
     * @param string $actionUid Client-submitted action UID.
     * @param string $actionUrl
     * @param string $actionCommand
     * @param array $data Parameters for the action command.
     * @return array
     */
    public function actionCommand($actionUid, $actionUrl, $actionCommand, $data) {
        return null;
    }


}