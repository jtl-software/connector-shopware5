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
            $limit  = $queryFilter->isLimit() ? $queryFilter->getLimit() : 100;

            /** @var \jtl\Connector\Shopware\Mapper\Payment $mapper */
            $mapper     = Mmc::getMapper('Payment');
            $swPayments = $mapper->findAllNative($limit);

            /** @var \jtl\Connector\Shopware\Mapper\CustomerOrder $customerOrderMapper */
            $customerOrderMapper = Mmc::getMapper('CustomerOrder');

            foreach ($swPayments as $swPayment) {
                $paymentModuleCode = PaymentUtil::map(null, $swPayment['name'], $swPayment['description']);

                if (PaymentUtil::isPayPalUnifiedType($swPayment['name'])) {
                    $swOrder = $customerOrderMapper->find($swPayment['customerOrderId']);
                    if (!\is_null($swOrder)) {
                        $orderAttributes = $swOrder->getAttribute();
                        if (\method_exists($orderAttributes, 'getSwagPaypalUnifiedPaymentType') === true) {
                            $paymentModuleCode = PaymentUtil::mapPayPalUnified(
                                $orderAttributes->getSwagPaypalUnifiedPaymentType()
                            );
                        }
                    }
                }

                $jtlPayment = Mmc::getModel('Payment');
                $jtlPayment->map(true, DataConverter::toObject($swPayment, true));
                $jtlPayment->setPaymentModuleCode($paymentModuleCode);

                $result[] = $jtlPayment;
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
