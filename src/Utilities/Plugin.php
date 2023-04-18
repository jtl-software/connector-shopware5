<?php

namespace jtl\Connector\Shopware\Utilities;

use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use Shopware\Models\Plugin\Plugin as SwPlugin;

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
        $plugin = ShopUtil::entityManager()->getRepository(SwPlugin::class)
            ->findOneBy(['name' => $pluginName, 'active' => 1]);
        return !\is_null($plugin);
    }

    /**
     * @return bool
     */
    public static function isCustomProductsActive(): bool
    {
        return self::isActive(self::SWAG_CUSTOM_PRODUCTS);
    }
}
