<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 03/06/2014
 * Time: 01:04
 */

namespace CMS;


use Framework\ClassRegistry;
use Framework\ClassRegistryUtils;
use Framework\PersistenceDB;
use Framework\Query;

class CMSBackendUtils {
    public static function renderModule($moduleCodename, $moduleClass, $moduleUrl) {
        // should use try and error catch, unless CMS class did that
        /** @var CMSModule $module */
        if (in_array('CMS\CMSModule',class_implements($moduleClass))) {
            $module = new $moduleClass();
            $result = $module->renderModuleUrl($moduleUrl);

        } else {
            $module = new DBModule($moduleClass);
            $result = $module->renderModuleUrl($moduleUrl);
        }

        if (!$result['directOutput']) {
            $title = isset($result['title']) ? $result['title'] : "$moduleClass $moduleUrl";

            if ($result['fullPage']) {
                $content = $result['content'];
            } else {
                $footnote = '';
                $tree = self::moduleTree();
                $content = "<nav class='cms_module_tree'>$tree</nav><main class='cms_module_content' role='content'>$result[content]<footer>$footnote</footer></main>";
            }

            self::renderModulePage($title,$content,$result['script']);
        }
    }

    private static function renderModulePage($title, $content, $script) {
        $modulePage = new Page();
        $modulePage->template_source = file_get_contents('res/CMS_template.html');
        $modulePage->title = $title;
        $modulePage->content = $content;
        $modulePage->scriptParts = $script;
        echo $modulePage->render();
    }

    private static function toolbar($buttons) {
        $toolbar = '';

        foreach ($buttons as $button) {
            if (is_array($button)) {
                $value = Tag::encode($button['command']);
                $label = $button['label'];
                $toolbar .= "<button type='submit' name='actionCommand' value='$value'>$label</button>";
            } else {
                $toolbar .= (string)$button;
            }
        }

        return "<div class='cms_toolbar'>$toolbar</div>";
    }

    private static function moduleTree() {
        $treeUrl = Tag::encode(URLUtil::absoluteRoot().'/!tree');

        return "<iframe seamless class='cms_module_tree' src='$treeUrl'><a href='$treeUrl'>CMS Navigation</a></iframe>";
    }

    /**
     * @return string
     */
    public static function standardToolbar() {
        $toolbar = self::toolbar(array(
            array('command' => 'save', 'label' => 'Save'),
            array('command' => 'delete', 'label' => 'Delete'),
        ));
        return $toolbar;
    }

    public static function renderModuleJSON() {
        $response = array('error'=>'Unknown processing error','responseOrigin'=>'renderModuleJSON');

        JSONUtils::json_init();

        try {
            $jsonData = $_POST;

            $actionUrl = $jsonData['actionUrl'];
            $actionCommand = $jsonData['actionCommand'];
            $actionUid = $jsonData['actionUid'];

            list($moduleCodename, $moduleUrl) = CMS::parseAdminUrl($actionUrl);
            $moduleClass = CMS::$modules[$moduleCodename];
            /** @var CMSModule $module */

            if (!isset($moduleClass)) {
                $response['error'] = 'No such module.';

            } else if (!class_exists($moduleClass)) {
                $response['error'] = 'Module class '.$moduleClass.' not found.';

            } else if (in_array('CMS\CMSModule', class_implements($moduleClass))) {
                $module = new $moduleClass;

                $response = $module->actionCommand($actionUid, $moduleUrl, $actionCommand, $jsonData);

            } else if (PersistenceDB::getClassPersistenceInfo($moduleClass)) {
                $module = new DBModule($moduleClass);

                $response = $module->actionCommand($actionUid, $moduleUrl, $actionCommand, $jsonData);

            } else {
                $response['error'] = 'Module is not a data module.';
            }

//            if ($actionCommand === 'save') {
//                $response = array('ok' => true, 'actionUid' => $actionUid);
//
//            } else {
//                $choice = mt_rand(1,3);
//
//                switch ((int)$choice) {
//                    case 1:
//                        new CAUSE_A_PHP_CRASH();
//                        break;
//                    case 2:
//                        throw new \RuntimeException("EXCEPTION SENT TO HANDLER");
//                        break;
//                    case 3:
//                    default:
//                        trigger_error('TRIGGER ERROR SENT', E_USER_ERROR);
//                        break;
//                }
//            }
        } catch (\Exception $e) {
            JSONUtils::json_exceptionHandler($e);
            exit;
        }

        echo json_encode($response, JSON_FORCE_OBJECT);
        exit;
    }


}