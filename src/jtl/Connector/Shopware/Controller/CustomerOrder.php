<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Model\Identity;
use jtl\Connector\Result\Action;
use jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Shopware\Utilities\Payment as PaymentUtil;
use jtl\Connector\Shopware\Utilities\PaymentStatus as PaymentStatusUtil;
use jtl\Connector\Shopware\Utilities\Status as StatusUtil;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Model\Model;
use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Core\Rpc\Error;
use jtl\Connector\Core\Utilities\DataConverter;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;

/**
 * CustomerOrder Controller
 * @access public
 */
class CustomerOrder extends DataController
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

            $mapper = Mmc::getMapper('CustomerOrder');
            $productMapper = Mmc::getMapper('Product');
            $orders = $mapper->findAll($limit);

            foreach ($orders as $orderSW) {
                try {
                    // CustomerOrders
                    $order = Mmc::getModel('CustomerOrder');
                    $order->map(true, DataConverter::toObject($orderSW, true));

                    // PaymentModuleCode
                    $paymentModuleCode = PaymentUtil::map(null, $orderSW['payment']['name']);
                    if ($paymentModuleCode !== null) {
                        $order->setPaymentModuleCode($paymentModuleCode);
                    }

                    // CustomerOrderStatus
                    $customerOrderStatus = StatusUtil::map(null, $orderSW['status']);
                    if ($customerOrderStatus !== null) {
                        $order->setStatus($customerOrderStatus);
                    }

                    // PaymentStatus
                    $paymentStatus = PaymentStatusUtil::map(null, $orderSW['cleared']);
                    if ($paymentStatus !== null) {
                        $order->setPaymentStatus($paymentStatus);
                    }

                    // Locale
                    $localeSW = LocaleUtil::get((int)$orderSW['languageIso']);
                    if ($localeSW !== null) {
                        //$order->setLocaleName($localeSW->getLocale());
                        $order->setLanguageISO(LanguageUtil::map($localeSW->getLocale()));
                    }

                    if (!is_array($orderSW['details']) || count($orderSW['details']) == 0) {
                        Logger::write(sprintf('Order (%s) has no items', $orderSW['number']), Logger::WARNING, 'controller');

                        continue;
                    }

                    foreach ($orderSW['details'] as $detailSW) {
                        $orderItem = Mmc::getModel('CustomerOrderItem');
                        $orderItem->map(true, DataConverter::toObject($detailSW, true));

                        $detail = $productMapper->findDetailBy(array('number' => $detailSW['articleNumber']));
                        if ($detail !== null) {
                            //throw new \Exception(sprintf('Cannot find detail with number (%s)', $detailSW['articleNumber']));
                            $orderItem->setProductId(new Identity(sprintf('%s_%s', $detail->getId(), $detailSW['articleId'])));
                        }

                        /*
                        if ($detail->getKind() == 2) {    // is Child
                            $orderItem->setProductId(new Identity(sprintf('%s_%s', $detail->getId(), $detailSW['articleId'])));
                        }
                        */

                        $order->addItem($orderItem);
                    }

                    $this->addPos($order, 'setBillingAddress', 'CustomerOrderBillingAddress', $orderSW['billing']);
                    $this->addPos($order, 'setShippingAddress', 'CustomerOrderShippingAddress', $orderSW['shipping']);

                    // Adding shipping item
                    if ($orderSW['invoiceShippingNet'] > 0) {
                        $item = Mmc::getModel('CustomerOrderItem');
                        $item->setType(\jtl\Connector\Model\CustomerOrderItem::TYPE_SHIPPING)
                            ->setId(new Identity(sprintf('%s_ship', $orderSW['id'])))
                            ->setCustomerOrderId($order->getId())
                            ->setName('Shipping')
                            ->setPrice($orderSW['invoiceShippingNet'])
                            ->setQuantity(1)
                            ->setVat(self::calcShippingVat($order));

                        $order->addItem($item);
                    }

                    // Attributes
                    for ($i = 1; $i <= 6; $i++) {
                        if (isset($orderSW['attribute']["attribute{$i}"]) && strlen($orderSW['attribute']["attribute{$i}"]) > 0) {
                            $attributeExists = true;
                            $customerOrderAttr = Mmc::getModel('CustomerOrderAttrs');
                            $customerOrderAttr->map(true, DataConverter::toObject($orderSW['attribute']));
                            $customerOrderAttr->setKey("attribute{$i}")
                                ->setValue($orderSW['attribute']["attribute{$i}"]);

                            $order->addAttribute($customerOrderAttr);
                        }
                    }

                    // CustomerOrderItemVariations

                    // CustomerOrderPaymentInfos

                    $result[] = $order;
                } catch (\Exception $exc) {
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
            }

            $action->setResult($result);
        } catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }

    public static function calcShippingVat(\jtl\Connector\Shopware\Model\CustomerOrder &$order)
    {
        return max(array_map(function ($item) { return $item->getVat(); }, $order->getItems()));
    }
}
