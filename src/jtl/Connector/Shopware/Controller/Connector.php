<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Result\Action;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Core\Model\QueryFilter;
use \jtl\Connector\Model\ConnectorIdentification;

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

        $identification = new ConnectorIdentification;
        $identification->setEndpointVersion($plugin_controller->getVersion())
            ->setPlatformName('Shopware')
            ->setPlatformVersion($sw::VERSION);

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

        $results = array();

        $mainControllers = array(
            'Category',
            'Customer',
            'CustomerOrder',
            'DeliveryNote',
            'Image',
            'Product',
            'Manufacturer',
            'Payment'
        );

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
