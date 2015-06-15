<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Core\Model\QueryFilter;
use \jtl\Connector\Core\Rpc\Error;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Result\Action;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Utilities\IdConcatenator;

class CrossSelling extends DataController
{
    /**
     * Pull
     *
     * @param \jtl\Connector\Core\Model\QueryFilter $queryFilter
     * @return \jtl\Connector\Result\Action
     */
    public function pull(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $result = array();
            $limit = $queryFilter->isLimit() ? $queryFilter->getLimit() : 100;

            $mapper = Mmc::getMapper('CrossSelling');
            $crossSellingSWs = $mapper->findAll($limit);

            if (is_array($crossSellingSWs) && count($crossSellingSWs) > 0) {
                $lastProductId = null;
                $crossSelling = null;
                foreach ($crossSellingSWs as $crossSellingSW) {
                    $productId = IdConcatenator::link(array($crossSellingSW['detailId'], $crossSellingSW['articleID']));
                    $relatedId = IdConcatenator::link(array($crossSellingSW['relatedDetailId'], $crossSellingSW['relatedarticle']));

                    if ($lastProductId !== $productId) {
                        $crossSelling = Mmc::getModel('CrossSelling');
                        $crossSelling->setProductId(new Identity($lastProductId));
                        $lastProductId = $productId;

                        if ($lastProductId !== null) {
                            $crossSelling->setProductId(new Identity($productId));
                            $result[] = $crossSelling;
                        }
                    }

                    $crossSellingItem = Mmc::getModel('CrossSellingItem');
                    $crossSellingItem->addProductId(new Identity($relatedId));

                    $crossSelling->addItem($crossSellingItem);
                }
            }

            $action->setResult($result);
        } catch (\Exception $exc) {
            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }


}