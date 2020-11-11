<?php

namespace jtl\Connector\Shopware\Utilities;

/**
 * Class Plugin
 * @package jtl\Connector\Shopware\Utilities
 */
class Plugin
{
    /**
     *
     */
    public const
        SWAG_CUSTOM_PRODUCTS = 'SwagCustomProducts';

    /**
     * @param string $pluginName
     * @return bool
     */
    public static function isActive(string $pluginName): bool
    {
        $pluginId = Shopware()->Db()->fetchOne('SELECT id FROM s_core_plugins WHERE active = 1 AND name = ?', [$pluginName]);
        return !is_null($pluginId) && $pluginId !== false;
    }

    /**
     * @return bool
     */
    public static function isCustomProductsActive(): bool
    {
        return self::isActive(self::SWAG_CUSTOM_PRODUCTS);
    }
}