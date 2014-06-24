<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 14/06/2014
 * Time: 18:13
 */

namespace CMS;


interface CMSModule {
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
    public function renderModuleUrl($moduleUrl);

    /**
     * Implements the interface between this module and the backend.
     *
     * This is most commonly used when handling a JSON request from an
     * actionCommand button, but it can also be used directly.
     *
     * @param string $actionUid  Client-submitted action UID.
     * @param string $actionUrl
     * @param string $actionCommand
     * @param array $data Parameters for the action command.
     * @return array
     */
    public function actionCommand($actionUid, $actionUrl, $actionCommand, $data);

    /**
     * Describes the entries presented for this module in the module tree.
     *
     * @return array
     */
    public function getModuleTree();
}