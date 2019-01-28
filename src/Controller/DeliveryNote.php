<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Core\Model\QueryFilter;
use \jtl\Connector\Result\Action;
use \jtl\Connector\Core\Rpc\Error;
use \jtl\Connector\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\IdConcatenator;

/**
 * DeliveryNote Controller
 * @access public
 */
class DeliveryNote extends DataController
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

            $productMapper = Mmc::getMapper('Product');
            $mapper = Mmc::getMapper('DeliveryNote');
            $deliveryNotes = $mapper->findAll($limit);

            foreach ($deliveryNotes as $deliveryNoteSW) {
                $orderMapper = Mmc::getMapper('CustomerOrder');
                $orderSW = $orderMapper->find($deliveryNoteSW['orderId']);

                $deliveryNote = Mmc::getModel('DeliveryNote');
                $deliveryNote->map(true, DataConverter::toObject($deliveryNoteSW, true));

                if ($orderSW !== null) {
                    foreach ($orderSW->getDetails() as $orderDetail) {
                        $deliveryNoteItem = Mmc::getModel('DeliveryNoteItem');
                        $deliveryNoteItem->setId(new Identity(IdConcatenator::link(array($orderDetail->getId(), $deliveryNoteSW['id']))))
                            ->setDeliveryNoteId(new Identity($deliveryNoteSW['id']))
                            ->setCustomerOrderItemId(new Identity($orderDetail->getId()))
                            ->setQuantity($orderDetail->getQuantity());

                        $detail = $productMapper->findDetailBy(array('number' => $orderDetail->getArticleNumber()));
                        if ($detail !== null) {
                            $deliveryNoteItem->setProductId(new Identity(IdConcatenator::link([$detail->getId(), $detail->getArticleId()])));
                        }

                        $deliveryNote->addItem($deliveryNoteItem);
                    }

                    // TrackingList
                    if ($orderSW->getDispatch() !== null && strlen($orderSW->getTrackingCode()) > 0) {
                        $trackingList = Mmc::getModel('DeliveryNoteTrackingList');
                        $trackingList->setName($orderSW->getDispatch()->getName())
                            ->addCode($orderSW->getTrackingCode());

                        $deliveryNote->addTrackingList($trackingList);
                    }
                }

                $result[] = $deliveryNote;
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
