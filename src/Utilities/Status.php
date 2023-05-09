<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use jtl\Connector\Model\CustomerOrder;

final class Status
{
    private static $wawiStatusMappings = array(
        CustomerOrder::STATUS_NEW => 1,
        //CustomerOrder::STATUS_PROCESSING => 1,
        //CustomerOrder::STATUS_COMPLETED => 2,
        CustomerOrder::STATUS_PARTIALLY_SHIPPED => 6,
        CustomerOrder::STATUS_SHIPPED => 7,
        CustomerOrder::STATUS_CANCELLED => 4
    );

    protected static $shopwareStatusMappings = [
        0 => CustomerOrder::STATUS_NEW,
        1 => CustomerOrder::STATUS_NEW,
        2 => CustomerOrder::STATUS_SHIPPED,
        4 => CustomerOrder::STATUS_CANCELLED,
        6 => CustomerOrder::STATUS_PARTIALLY_SHIPPED,
        7 => CustomerOrder::STATUS_SHIPPED,
    ];

    /**
     * @param string $wawiStatus
     * @param integer $shopwareStatus
     * @return string|integer|null
     */
    public static function map($wawiStatus = null, $shopwareStatus = null)
    {
        if (isset(self::$wawiStatusMappings[$wawiStatus])) {
            return self::$wawiStatusMappings[$wawiStatus];
        } elseif (isset(self::$shopwareStatusMappings[$shopwareStatus])) {
            return self::$shopwareStatusMappings[$shopwareStatus];
        }

        return null;
    }
}
