<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @copyright 2010-2013 JTL-Software GmbH
 */

require_once(__DIR__ . '/../vendor/autoload.php');

use \jtl\Connector\Application\Application;
use \jtl\Connector\Shopware\Connector;

// Connector instance
$connector = Connector::getInstance();
$application = Application::getInstance();
$application->register($connector);
$application->run();
