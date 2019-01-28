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
use jtl\Connector\Shopware\Utilities\CrossSellingGroup as CrossSellingGroupUtil;

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
                $lastArticleId = null;
                $lastProductId = null;
                $crossSelling = null;
                foreach ($crossSellingSWs as $crossSellingSW) {
                    $productId = IdConcatenator::link(array($crossSellingSW['detailId'], $crossSellingSW['articleID']));
                    $relatedId = IdConcatenator::link(array($crossSellingSW['relatedDetailId'], $crossSellingSW['relatedarticle']));

                    if ($lastProductId !== $productId) {
                        $crossSelling = Mmc::getModel('CrossSelling');
                        $crossSelling->setId(new Identity($lastArticleId))
                            ->setProductId(new Identity($lastProductId));

                        $lastProductId = $productId;
                        $lastArticleId = $crossSellingSW['articleID'];

                        if ($lastProductId !== null) {
                            $crossSelling->setId(new Identity($crossSellingSW['articleID']))
                                ->setProductId(new Identity($productId));

                            $result[] = $crossSelling;
                        }
                    }

                    $crossSellingItem = Mmc::getModel('CrossSellingItem');
                    $crossSellingItem->addProductId(new Identity($relatedId));

                    $crossSellingGroup = CrossSellingGroupUtil::get($crossSellingSW['group_id']);
                    if ($crossSellingGroup !== null) {
                        $crossSellingItem->setCrossSellingGroupId($crossSellingGroup->getId());
                    }

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