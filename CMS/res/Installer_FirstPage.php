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


$template2 = new Template();

$template2->template_name = 'Fancy Template';
$template2->template_source = <<<FANCY_TEMPLATE
<!DOCTYPE html>
<html lang="[[this:lang /]]">
<head>
    <meta charset="utf-8">
    <title>[[this:title /]]</title>
    <!-- This template is much fancier than the first one created when CMS was installed. -->
    [[this:script /]]
</head>
<body>
    <div style='font-size:100px;position:fixed;top:0;left:0;width=1em;height=100%;overflow:hidden'>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑</div>
    <div style='font-size:100px;position:fixed;top:0;right:0;width=1em;height=100%;overflow:hidden'>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑<br>👑</div>
    <div style='margin: 0 100px 0 100px; background-color:#90C;'>
        <div style='font-size:20px; width:100%;'>
          <div style='padding: 100px 10px 130px 10px;'>
              <header><nav>[[cms:menu /]]</nav></header>
              <h1>[[this:title /]]</h1>
              <div id="content">[[this:content /]]</div>
          </div>
        </div>
    </div>
</body>
</html>
FANCY_TEMPLATE;

$response['template2_created'] = PersistenceDB::storeItem($template2);

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



