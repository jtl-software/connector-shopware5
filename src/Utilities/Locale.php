<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use \jtl\Connector\Shopware\Utilities\Mmc;

final class Locale
{
    private static $_locales = array();

    /**
     * @param int $id
     * @return \Shopware\Models\Shop\Locale
     */
    public static function get($id)
    {
        if (!isset(self::$_locales[$id])) {
            $mapper = Mmc::getMapper('Locale');

            self::$_locales[$id] = $mapper->find($id);
        }

        return self::$_locales[$id];
    }

    /**
     * @param string $key
     * @return \Shopware\Models\Shop\Locale
     */
    public static function getByKey($key)
    {
        foreach (self::$_locales as $locale) {
            if ($locale->getLocale() === $key) {
                return $locale;
            }
        }

        $mapper = Mmc::getMapper('Locale');
        $locale = $mapper->findOneBy(array(
            'locale' => $key
        ));

        if ($locale) {
            self::$_locales[$locale->getId()] = $locale;
        }

        return $locale;
    }

    private function __construct()
    {
    }
    
    private function __clone()
    {
    }
}
