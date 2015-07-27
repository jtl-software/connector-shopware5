<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use \jtl\Connector\Payment\PaymentTypes;

final class Payment
{
    private static $_mappings = array(
        PaymentTypes::TYPE_DIRECT_DEBIT => 'debit',
        PaymentTypes::TYPE_CASH_ON_DELIVERY => 'cash',
        PaymentTypes::TYPE_INVOICE => 'invoice',
        PaymentTypes::TYPE_PREPAYMENT => 'prepayment',
        PaymentTypes::TYPE_PAYPAL_EXPRESS => 'paypal'
    );

    public static function map($paymentModuleCode = null, $swCode = null)
    {
        if ($paymentModuleCode !== null && isset(self::$_mappings[$paymentModuleCode])) {
            return self::$_mappings[$paymentModuleCode];
        } elseif ($swCode !== null) {
            if (($connectorType = array_search($swCode, self::$_mappings)) !== false) {
                return $connectorType;
            }
        }

        return null;
    }
}
