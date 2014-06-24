<?php

namespace CMS;
Config::define('site.db.host','localhost');
Config::define('site.db.username','cms_user');
Config::define('site.db.password','abGJDK394BaMNXce');
Config::define('site.db.db_name','framework_cms');



Config::define('site.lang', 'en-GB');

ini_set('display_errors','1');
error_reporting(E_ALL & ~(E_NOTICE | E_STRICT | E_DEPRECATED));
