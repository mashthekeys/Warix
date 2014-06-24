<?php
namespace CMS;

use Framework\PersistenceDB;

$template = new Template();

$template->template_name = 'Basic Template';
$template->template_source = <<<BASIC_TEMPLATE
<!DOCTYPE html>
<html lang="[[this:lang /]]">
<head>
    <meta charset="utf-8">
    <title>[[this:title /]]</title>
    <!-- This is the basic template created when CMS was installed. -->
    [[this:script /]]
</head>
<body>
    <header><nav>[[cms:menu /]]</nav></header>
    <h1>[[this:title /]]</h1>
    <div id="content">[[this:content /]]</div>
</body>
</html>
BASIC_TEMPLATE;

$response['template_created'] = PersistenceDB::storeItem($template);

//if (!$template->id) {
//    throw new \ErrorException("Database did not give an ID for the first template.");
//}

$page = new Page();

$page->path = '';
$page->ext = '/';
$page->title = 'New Website';
$page->content = '<p>CMS is installed and ready to go.  Sign in to the <a href="!/">Control Panel</a> to edit it!</p>';
$page->lang = Config::get('site.lang','en-GB');
$page->__template_id = $template->id;
$page->template = $template;

$response['page_created'] = PersistenceDB::storeItem($page);



