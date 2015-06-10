<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Model\StatusChange as StatusChangeModel;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Utilities\Status as StatusUtil;
use \jtl\Connector\Shopware\Utilities\PaymentStatus as PaymentStatusUtil;
use \jtl\Connector\Core\Exception\DatabaseException;

class StatusChange extends DataMapper
{
    public function save(StatusChangeModel $status)
    {
        $customerOrderId = (int) $status->getCustomerOrderId()->getEndpoint();
        if ($customerOrderId > 0) {
            $mapper = Mmc::getMapper('CustomerOrder');
            $customerOrder = $mapper->find($customerOrderId);
            if ($customerOrder !== null) {

                // Payment Status
                if ($status->getPaymentStatus() !== null && strlen($status->getPaymentStatus()) > 0) {
                    $statusId = PaymentStatusUtil::map($status->getPaymentStatus());
                    if ($statusId !== null) {
                        $customerOrderStatusSW = $mapper->findStatus($statusId);
                        if ($customerOrderStatusSW !== null) {
                            $customerOrder->setPaymentStatus($customerOrderStatusSW);
                            $this->Manager()->persist($customerOrder);
                            $this->Manager()->flush();
                        }
                    }
                }

                // Order Status
                if ($status->getOrderStatus() !== null && strlen($status->getOrderStatus()) > 0) {
                    $statusId = StatusUtil::map($status->getOrderStatus());
                    if ($statusId !== null) {
                        $customerOrderStatusSW = $mapper->findStatus($statusId);
                        if ($customerOrderStatusSW !== null) {
                            $customerOrder->setOrderStatus($customerOrderStatusSW);
                            $this->Manager()->persist($customerOrder);
                            $this->Manager()->flush();
                        }
                    }
                }

                return $status;
            }

            throw new DatabaseException(sprintf('Customer Order with Endpoint Id (%s) cannot be found', $customerOrderId));
        }

        throw new DatabaseException('Customer Order Endpoint Id cannot be empty');
    }
}
