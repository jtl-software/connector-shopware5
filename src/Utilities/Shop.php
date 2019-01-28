<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Utilities;

use Shopware\Components\Thumbnail\Manager;

final class Shop
{
    /**
     * @return \Shopware
     */
    public static function get()
    {
        return Shopware();
    }

    /**
     * @return \Shopware\Models\Shop\Locale
     */
    public static function locale()
    {
        return static::get()->Shop()->getLocale();
    }

    /**
     * @return string
     */
    public static function version()
    {
        if(static::get()->Container()->has('shopware.release')) {
            /** @var ShopwareReleaseStruct $shopwareRelease */
            $shopwareRelease = static::get()->Container()->get('shopware.release');
            return $shopwareRelease->getVersion();
        } elseif (defined('Shopware::VERSION') && \Shopware::VERSION !== '___VERSION___') {
            return \Shopware::VERSION;
        } else {
            throw new \RuntimeException('Shopware version could not get found!');
        }
    }

    /**
     * @return \Shopware\Bundle\MediaBundle\MediaService
     */
    public static function mediaService()
    {
        return static::get()->Container()->get('shopware_media.media_service');
    }

    /**
     * @return Manager
     */
    public static function thumbnailManager()
    {
        return static::get()->Container()->get('thumbnail_manager');
    }

    /**
     * @return \Shopware\Components\Model\ModelManager
     */
    public static function entityManager()
    {
        return static::get()->Models();
    }

}