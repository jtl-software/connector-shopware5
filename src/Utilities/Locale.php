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
     * @return \Shopware\Models\Shop\Locale[]
     */
    public static function getByKey($key)
    {
        foreach (self::$_locales as $locale) {
            if ($locale->getLocale() === $key) {
                return $locale;
            }
        }

        /** @var \jtl\Connector\Shopware\Mapper\Locale $mapper */
        $mapper = Mmc::getMapper('Locale');
        $locales = $mapper->findByLocale($key);

        foreach ($locales as $locale) {
            self::$_locales[$locale->getId()] = $locale;
        }

        return $locales;
    }

    /**
     * @param string $locale
     * @return string
     * @throws \Exception
     */
    public static function extractLanguageIsoFromLocale(string $locale): string
    {
        list($languageIsoCode, $countryCode) = explode('_', $locale);

        if (empty($languageIsoCode)) {
            throw new \Exception(sprintf("Invalid locale '%s'. Cannot extract language code.", $locale));
        }

        return strtolower($languageIsoCode);
    }

    private function __construct()
    {
    }
    
    private function __clone()
    {
    }
}
