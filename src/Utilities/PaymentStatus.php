<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use jtl\Connector\Model\CustomerOrder;

final class PaymentStatus
{
    private static $mappings = array(
        CustomerOrder::PAYMENT_STATUS_COMPLETED => 12,
        CustomerOrder::PAYMENT_STATUS_PARTIALLY => 11,
        CustomerOrder::PAYMENT_STATUS_UNPAID => 17
    );

    public static function map($paymentStatus = null, $swStatus = null)
    {
        if ($paymentStatus != null && isset(self::$mappings[$paymentStatus])) {
            return self::$mappings[$paymentStatus];
        } elseif ($swStatus !== null) {
            if (($connectorStatus = \array_search($swStatus, self::$mappings)) !== false) {
                return $connectorStatus;
            }
        }

        return null;
    }
}
