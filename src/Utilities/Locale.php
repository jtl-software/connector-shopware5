<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use jtl\Connector\Shopware\Utilities\Mmc;

final class Locale
{
    private static $locales = array();

    /**
     * @param int $id
     * @return \Shopware\Models\Shop\Locale
     */
    public static function get($id)
    {
        if (!isset(self::$locales[$id])) {
            $mapper = Mmc::getMapper('Locale');

            self::$locales[$id] = $mapper->find($id);
        }

        return self::$locales[$id];
    }

    /**
     * @param string $key
     * @return \Shopware\Models\Shop\Locale[]
     */
    public static function getByKey($key)
    {
        foreach (self::$locales as $locale) {
            if ($locale->getLocale() === $key) {
                return $locale;
            }
        }

        /** @var \jtl\Connector\Shopware\Mapper\Locale $mapper */
        $mapper  = Mmc::getMapper('Locale');
        $locales = $mapper->findByLocale($key);

        foreach ($locales as $locale) {
            self::$locales[$locale->getId()] = $locale;
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
        @list($languageIsoCode, $countryCode) = \explode('_', $locale);

        if (empty($languageIsoCode)) {
            throw new \Exception(\sprintf("Invalid locale '%s'. Cannot extract language code.", $locale));
        }

        return \strtolower($languageIsoCode);
    }

    private function __construct()
    {
    }

    private function __clone()
    {
    }
}
