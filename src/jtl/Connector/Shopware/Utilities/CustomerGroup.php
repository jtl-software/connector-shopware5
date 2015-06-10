<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use \jtl\Connector\Shopware\Utilities\Mmc;

final class CustomerGroup
{
    private static $_customerGroups;

    /**
     * @param int $id
     * @param bool $useCache
     * @return \Shopware\Models\Customer\Group
     */
    public static function get($id, $useCache = true)
    {
        if (self::$_customerGroups === null) {
            self::$_customerGroups = array();
        }

        if (!$useCache || !isset(self::$_customerGroups[$id])) {
            $mapper = Mmc::getMapper('CustomerGroup');

            self::$_customerGroups[$id] = $mapper->find($id);
        }

        return self::$_customerGroups[$id];
    }

    /**
     * @param string $key
     * @param bool $useCache
     * @return \Shopware\Models\Customer\Group
     */
    public static function getByKey($key, $useCache = true)
    {
        if (self::$_customerGroups === null) {
            self::$_customerGroups = array();
        }

        if ($useCache) {
            foreach (self::$_customerGroups as $customerGroup) {
                if ($customerGroup->getKey() === $key) {
                    return $customerGroup;
                }
            }
        }

        $mapper = Mmc::getMapper('CustomerGroup');
        $customerGroup = $mapper->findOneBy(array(
            'key' => $key
        ));

        if ($customerGroup) {
            self::$_customerGroups[$customerGroup->getId()] = $customerGroup;
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
