<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 */

$loader = require_once(CONNECTOR_DIR . '/vendor/autoload.php');

$loader->add('', CONNECTOR_DIR . '/plugins');

use jtl\Connector\Application\Application;
use jtl\Connector\Shopware\Connector;

// Connector instance
$connector = Connector::getInstance();
$application = Application::getInstance();
$application->register($connector);
$application->run();
