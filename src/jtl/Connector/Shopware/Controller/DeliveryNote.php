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

            $mapper = Mmc::getMapper('DeliveryNote');
            $deliveryNotes = $mapper->findAll($limit);

            foreach ($deliveryNotes as $deliveryNoteSW) {
                $orderMapper = Mmc::getMapper('CustomerOrder');
                $orderSW = $orderMapper->find($deliveryNoteSW['orderId']);

                $deliveryNote = Mmc::getModel('DeliveryNote');
                $deliveryNote->map(true, DataConverter::toObject($deliveryNoteSW, true));

                if ($orderSW !== null) {
                    foreach ($orderSW->getDetails() as $detail) {
                        $deliveryNoteItem = Mmc::getModel('DeliveryNoteItem');
                        $deliveryNoteItem->setId(new Identity(IdConcatenator::link(array($detail->getId(), $deliveryNoteSW['id']))))
                            ->setDeliveryNoteId(new Identity($deliveryNoteSW['id']))
                            ->setCustomerOrderItemId(new Identity($detail->getId()))
                            ->setQuantity($detail->getQuantity());

                        $deliveryNote->addItem($deliveryNoteItem);
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
