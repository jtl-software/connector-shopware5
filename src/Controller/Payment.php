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
use Shopware\Models\Order\Order;

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

            $customerOrderMapper = Mmc::getMapper('CustomerOrder');

            foreach ($payments as $paymentSW) {
                $paymentModuleCode = PaymentUtil::map(null, $paymentSW['paymentModuleCode']);
                $paymentModuleCode = ($paymentModuleCode !== null) ? $paymentModuleCode : $paymentSW['paymentModuleCode'];

                if (PaymentUtil::isPayPalUnifiedType($paymentModuleCode)) {
                    $orderSW = $customerOrderMapper->find($paymentSW['customerOrderId']);
                    if (!is_null($orderSW)) {
                        $orderAttributes = $orderSW->getAttribute();
                        $payPalUnifiedPaymentType = $orderAttributes->getSwagPaypalUnifiedPaymentType();

                        if (!is_null($payPalUnifiedPaymentType)) {
                            $paymentModuleCode = PaymentUtil::mapPayPalUnified($payPalUnifiedPaymentType);
                        }
                    }
                }

                $payment = Mmc::getModel('Payment');
                $payment->map(true, DataConverter::toObject($paymentSW, true));
                $payment->setPaymentModuleCode($paymentModuleCode);

                $result[] = $payment;
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
}