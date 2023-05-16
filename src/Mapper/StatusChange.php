<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use Doctrine\ORM\EntityNotFoundException;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Linker\IdentityLinker;
use jtl\Connector\Shopware\Utilities\Shop;
use jtl\Connector\Model\StatusChange as StatusChangeModel;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Shopware\Utilities\Status as StatusUtil;
use jtl\Connector\Shopware\Utilities\PaymentStatus as PaymentStatusUtil;
use jtl\Connector\Shopware\Model\CustomerOrder as CustomerOrderModel;
use sOrder;

class StatusChange extends DataMapper
{
    /**
     * @param \jtl\Connector\Model\StatusChange $status
     *
     * @return \jtl\Connector\Model\StatusChange
     * @throws \Doctrine\ORM\EntityNotFoundException
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     * @noinspection PhpClassConstantAccessedViaChildClassInspection
     */
    public function save(StatusChangeModel $status): StatusChangeModel
    {
        try {
            $coId = $status->getCustomerOrderId();
            if ($coId === null) {
                throw new \RuntimeException('CustomerOrderId is null');
            }
            $customerOrderId = (int)$coId->getEndpoint();
            if ($customerOrderId > 0) {
                /** @var CustomerOrder $mapper */
                $mapper        = Mmc::getMapper('CustomerOrder');
                $customerOrder = $mapper->find($customerOrderId);
                if (!\is_null($customerOrder)) {
                    // Payment Status
                    if ($status->getPaymentStatus() !== null && $status->getPaymentStatus() !== '') {
                        $statusId = PaymentStatusUtil::map($status->getPaymentStatus());
                        if (!\is_null($statusId)) {
                            $customerOrderStatusSW = $mapper->findStatus($statusId);
                            if ($customerOrderStatusSW !== null) {
                                if ($status->getPaymentStatus() === CustomerOrderModel::PAYMENT_STATUS_COMPLETED) {
                                    $this->createMappingIfNotLinked($status);
                                }

                                $customerOrder->setPaymentStatus($customerOrderStatusSW);

                                if ($status->getPaymentStatus() === CustomerOrderModel::PAYMENT_STATUS_COMPLETED) {
                                    $customerOrder->setClearedDate(new \DateTime());
                                }

                                $this->Manager()->persist($customerOrder);
                                $this->Manager()->flush();
                            }
                        }
                    }

                    // Order Status
                    if ($status->getOrderStatus() !== null && $status->getOrderStatus() !== '') {
                        $statusId = StatusUtil::map($status->getOrderStatus());

                        if ($statusId !== null) {
                            $customerOrderStatusSW = $mapper->findStatus($statusId);
                            if ($customerOrderStatusSW !== null) {
                                $order = \Shopware()->Modules()->Order();
                                if ($order instanceof sOrder) {
                                    $order->setOrderStatus(
                                        $customerOrder->getId(),
                                        $customerOrderStatusSW->getId(),
                                        true
                                    );
                                }
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

    /**
     * @param StatusChangeModel $statusChange
     * @throws \Exception
     */
    protected function createMappingIfNotLinked(StatusChangeModel $statusChange)
    {
        $primaryKeyMapper = new PrimaryKeyMapper();
        $endpointId       = $statusChange->getCustomerOrderId()->getEndpoint();

        $paymentLink = $primaryKeyMapper->getHostId($endpointId, IdentityLinker::TYPE_PAYMENT);
        if (\is_null($paymentLink)) {
            $primaryKeyMapper->save($endpointId, 0, IdentityLinker::TYPE_PAYMENT);
        }
    }
}
