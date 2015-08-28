<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use \jtl\Connector\Model\CustomerOrder;

final class Status
{
    private static $_mappings = array(
        CustomerOrder::STATUS_NEW => 0,
        //CustomerOrder::STATUS_PROCESSING => 1,
        //CustomerOrder::STATUS_COMPLETED => 2,
        CustomerOrder::STATUS_PARTIALLY_SHIPPED => 6,
        CustomerOrder::STATUS_SHIPPED => 7,
        CustomerOrder::STATUS_CANCELLED => 4
    );

    public static function map($orderStatus = null, $swStatus = null)
    {
        if ($orderStatus !== null && isset(self::$_mappings[$orderStatus])) {
            return self::$_mappings[$orderStatus];
        } elseif ($swStatus !== null) {
            if (($connectorStatus = array_search($swStatus, self::$_mappings)) !== false) {
                return $connectorStatus;
            }
        }

        return null;
    }
}
