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
use jtl\Connector\Result\Action;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Model\ConnectorIdentification;
use jtl\Connector\Shopware\Utilities\Shop;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use jtl\Connector\Shopware\Connector as SwConnector;
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
     * @return Action
     */
    public function identify()
    {
        $action = new Action();
        $action->setHandled(true);

        $pluginController = new \Shopware_Plugins_Frontend_Jtlconnector_Bootstrap('JTL Shopware Connector');

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

        $identification = new ConnectorIdentification();
        $identification->setEndpointVersion($pluginController->getVersion())
            ->setPlatformName('Shopware')
            ->setPlatformVersion(ShopUtil::version())
            ->setProtocolVersion(Application()->getProtocolVersion())
            ->setServerInfo($serverInfo);

        $action->setResult($identification);

        return $action;
    }

    /**
     * Finish
     *
     * @return Action
     */
    public function finish()
    {
        $action = new Action();
        
        $action->setHandled(true);
        $action->setResult(true);

        $cacheManager = Shop::cacheManager();
        $clearCacheTags = $_SESSION[SwConnector::SESSION_CLEAR_CACHE] ?? [];
        foreach($clearCacheTags as $clearCacheTag => $clearIt) {
            if($clearIt === true) {
                $cacheManager->clearByTag($clearCacheTag);
            }
        }
        
        return $action;
    }

    /**
     * Statistic
     *
     * @param QueryFilter $queryFilter
     * @return Action
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
