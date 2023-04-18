<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use jtl\Connector\Shopware\Utilities\Mmc;

final class CustomerGroup
{
    private static $customerGroups;

    /**
     * @param int $id
     * @param bool $useCache
     * @return \Shopware\Models\Customer\Group
     */
    public static function get($id, $useCache = true)
    {
        if (\is_null(self::$customerGroups)) {
            self::$customerGroups = [];
        }

        if (!$useCache || !isset(self::$customerGroups[$id])) {
            $mapper = Mmc::getMapper('CustomerGroup');

            self::$customerGroups[$id] = $mapper->find($id);
        }

        return self::$customerGroups[$id];
    }

    /**
     * @param string $key
     * @param bool $useCache
     * @return \Shopware\Models\Customer\Group
     */
    public static function getByKey($key, $useCache = true)
    {
        if (\is_null(self::$customerGroups)) {
            self::$customerGroups = [];
        }

        if ($useCache) {
            foreach (self::$customerGroups as $customerGroup) {
                if ($customerGroup->getKey() === $key) {
                    return $customerGroup;
                }
            }
        }

        $mapper        = Mmc::getMapper('CustomerGroup');
        $customerGroup = $mapper->findOneBy(array(
            'key' => $key
        ));

        if ($customerGroup) {
            self::$customerGroups[$customerGroup->getId()] = $customerGroup;
        }

        return $customerGroup;
    }

    private function __construct()
    {
    }

    private function __clone()
    {
    }
}
