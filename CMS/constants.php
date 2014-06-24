<?php

define('NBSP',"\xC2\xA0");

define('BLANK_PAGE_TEMPLATE',<<<BLANK_PAGE_TEMPLATE
<!DOCTYPE html>
<html lang="[[this:lang /]]">
<head>
    <meta charset="utf-8">
    <title>[[this:title /]]</title>
    <!-- This is a blank template. It is used when a page template is blank or missing. -->
    [[this:script /]]
</head>
<body>
    <h1>[[this:title /]]</h1>
    <div id="content">[[this:content /]]</div>
</body>
</html>
BLANK_PAGE_TEMPLATE
);
