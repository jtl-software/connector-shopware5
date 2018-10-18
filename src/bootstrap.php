<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 */


use jtl\Connector\Application\Application;
use jtl\Connector\Shopware\Connector;

// Connector instance
/* @var $connector jtl\Connector\Shopware\Connector */
/* @var $application jtl\Connector\Application\Application */

$connector = Connector::getInstance();
$application = Application::getInstance();
$application->register($connector);
$application->run();
