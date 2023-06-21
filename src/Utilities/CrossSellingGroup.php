<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use jtl\Connector\Shopware\Utilities\Mmc;

final class CrossSellingGroup
{
    private static $groups;
    /**
     * @param int $id
     * @param bool $useCache
     * @return \jtl\Connector\Shopware\Model\CrossSellingGroup
     */
    public static function get($id, $useCache = true)
    {
        if (self::$groups === null) {
            self::$groups = [];
        }

        if (!$useCache || !isset(self::$groups[$id])) {
            $mapper            = Mmc::getMapper('CrossSellingGroup');
            self::$groups[$id] = $mapper->find($id, true);
        }

        return self::$groups[$id];
    }
}
