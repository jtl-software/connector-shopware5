<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Core\Rpc\Error;
use jtl\Connector\Core\Utilities\DataConverter;
use jtl\Connector\Result\Action;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Shopware\Utilities\Payment as PaymentUtil;

/**
 * Payment Controller
 * @access public
 */
class Payment extends DataController
{
    /**
     * @param QueryFilter $queryFilter
     * @return Action
     */
    public function pull(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $result = array();
            $limit = $queryFilter->isLimit() ? $queryFilter->getLimit() : 100;

            $mapper = Mmc::getMapper('Payment');
            $payments = $mapper->findAllNative($limit);

            foreach ($payments as $paymentSW) {
                $payment = Mmc::getModel('Payment');
                $payment->map(true, DataConverter::toObject($paymentSW, true));

                $result[] = $payment;
            }

            /*
            $payments = $mapper->findAll($limit);

            foreach ($payments as $paymentSW) {
                $payment = Mmc::getModel('Payment');
                $payment->map(true, DataConverter::toObject($paymentSW, true));

                $orderMapper = Mmc::getMapper('CustomerOrder');
                $orderSW = $orderMapper->find($paymentSW['customerOrderId']);

                if ($orderSW !== null) {
                    if ($orderSW->getPayment() !== null) {
                        $paymentModuleCode = PaymentUtil::map(null, strtolower($orderSW->getPayment()->getName()));
                        $paymentModuleCode = ($paymentModuleCode !== null) ? $paymentModuleCode : strtolower($orderSW->getPayment()->getName());
                        $payment->setPaymentModuleCode($paymentModuleCode);
                    }

                    $result[] = $payment;
                }
            }
            */

            $action->setResult($result);
        } catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }
}