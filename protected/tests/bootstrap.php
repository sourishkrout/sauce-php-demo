<?php

// change the following paths if necessary
$yiit=dirname(__FILE__).'/../../framework/yiit.php';
$config=dirname(__FILE__).'/../config/test.php';

require_once($yiit);
require_once(dirname(__FILE__).'/../../vendor/autoload.php');
require_once(dirname(__FILE__).'/Selenium2BrowserSuite.php');
require_once(dirname(__FILE__).'/WebDriverTestCase.php');
require_once(dirname(__FILE__).'/CWebDriverTestCase.php');

Yii::createWebApplication($config);
