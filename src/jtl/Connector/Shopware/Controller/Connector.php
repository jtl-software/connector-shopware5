<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use jtl\Connector\Application\Application;
use jtl\Connector\Event\EventHandler;
use jtl\Connector\Model\BoolResult;
use jtl\Connector\Model\ConnectorServerInfo;
use \jtl\Connector\Result\Action;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Core\Model\QueryFilter;
use \jtl\Connector\Model\ConnectorIdentification;
use Shopware\Components\ShopwareReleaseStruct;

/**
 * Connector Controller
 * @access public
 */
class Connector extends DataController
{
    /**
     * Identify
     *
     * @return \jtl\Connector\Result\Action
     */
    public function identify()
    {
        $action = new Action();
        $action->setHandled(true);

        $plugin_controller = new \Shopware_Plugins_Frontend_Jtlconnector_Bootstrap('JTL Shopware Connector');

        $sw = Shopware();

        $returnMegaBytes = function($value) {
            $value = trim($value);
            $unit = strtolower($value[strlen($value) - 1]);
            $value = (int) str_replace($unit, '', strtolower($value));
            switch ($unit) {
                case 'g':
                    $value *= 1024;
                //case 'm':
                //    $value *= 1024;
                //case 'k':
                //    $value *= 1024;
            }

            return (int) $value;
        };

        $serverInfo = new ConnectorServerInfo();
        $serverInfo->setMemoryLimit($returnMegaBytes(ini_get('memory_limit')))
            ->setExecutionTime((int) ini_get('max_execution_time'))
            ->setPostMaxSize($returnMegaBytes(ini_get('post_max_size')))
            ->setUploadMaxFilesize($returnMegaBytes(ini_get('upload_max_filesize')));

        if($sw->Container()->has('shopware.release')) {
            /** @var ShopwareReleaseStruct $shopwareRelease */
            $shopwareRelease = $sw->Container()->get('shopware.release');
            $version = $shopwareRelease->getVersion();
        } elseif (defined('Shopware::VERSION')) {
            $version = \Shopware::VERSION;
        } else {
            throw new \RuntimeException('Shopware version could not get found!');
        }

        $identification = new ConnectorIdentification();
        $identification->setEndpointVersion($plugin_controller->getVersion())
            ->setPlatformName('Shopware')
            ->setPlatformVersion($version)
            ->setProtocolVersion(Application()->getProtocolVersion())
            ->setServerInfo($serverInfo);

        $action->setResult($identification);

        return $action;
    }

    /**
     * Finish
     *
     * @return \jtl\Connector\Result\Action
     */
    public function finish()
    {
        $action = new Action();
        
        $action->setHandled(true);
        $action->setResult(true);
        
        return $action;
    }

    /**
     * Statistic
     *
     * @param \jtl\Connector\Core\Model\QueryFilter $queryFilter
     * @return \jtl\Connector\Result\Action
     */
    public function statistic(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);

        $results = [];

        $mainControllers = [
            'Category',
            'Customer',
            'CustomerOrder',
            'CrossSelling',
            'DeliveryNote',
            'Image',
            'Product',
            'Manufacturer',
            'Payment'
        ];

        foreach ($mainControllers as $mainController) {
            try {
                $controller = Mmc::getController($mainController);
                $result = $controller->statistic($queryFilter);
                if ($result !== null && $result->isHandled() && !$result->isError()) {
                    $results[] = $result->getResult();
                }
            } catch (\Exception $exc) {
                Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
            }
        }
        
        $action->setResult($results);

        return $action;
    }
}
