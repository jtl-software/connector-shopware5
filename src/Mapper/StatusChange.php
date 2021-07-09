<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use Doctrine\ORM\EntityNotFoundException;
use jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Shopware\Utilities\Shop;
use \jtl\Connector\Model\StatusChange as StatusChangeModel;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Utilities\Status as StatusUtil;
use \jtl\Connector\Shopware\Utilities\PaymentStatus as PaymentStatusUtil;
use \jtl\Connector\Core\Exception\DatabaseException;

class StatusChange extends DataMapper
{
    public function save(StatusChangeModel $status)
    {
        try {
            $customerOrderId = (int)$status->getCustomerOrderId()->getEndpoint();
            if ($customerOrderId > 0) {
                /** @var CustomerOrder $mapper */
                $mapper = Mmc::getMapper('CustomerOrder');
                $customerOrder = $mapper->find($customerOrderId);
                if (!is_null($customerOrder)) {

                    // Payment Status
                    if ($status->getPaymentStatus() !== null && strlen($status->getPaymentStatus()) > 0) {
                        $statusId = PaymentStatusUtil::map($status->getPaymentStatus());
                        if (!is_null($statusId)) {
                            $customerOrderStatusSW = $mapper->findStatus($statusId);
                            if ($customerOrderStatusSW !== null) {
                                $customerOrder->setPaymentStatus($customerOrderStatusSW);

                                if ($status->getPaymentStatus() === \jtl\Connector\Model\CustomerOrder::PAYMENT_STATUS_COMPLETED) {
                                    $customerOrder->setClearedDate(new \DateTime());
                                }

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
            }
        } catch (EntityNotFoundException $ex) {
            if (Shop::isCustomerNotFoundException($ex->getMessage())) {
                Logger::write($ex->getMessage(), Logger::ERROR, Logger::CHANNEL_DATABASE);
            } else {
                throw $ex;
            }
        }

        return $status;
    }
}
