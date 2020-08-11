<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Utilities;

use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Shopware\Service\Translation;
use Shopware\Components\Thumbnail\Manager;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use Shopware\Models\Shop\Shop as SwShop;

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
     * @param string $languageIso6392Code
     * @param string $localeCode
     * @return bool
     * @throws \jtl\Connector\Core\Exception\LanguageException
     */
    public static function areSameLanguages(string $languageIso6392Code, string $localeCode): bool
    {
        return
            Locale::extractLanguageFromLocale(LanguageUtil::map(null, null, $languageIso6392Code)) ===
            Locale::extractLanguageFromLocale($localeCode);
    }

    /**
     * @param string $languageIso6392Code
     * @return bool
     * @throws \jtl\Connector\Core\Exception\LanguageException
     */
    public static function isShopwareDefaultLanguage(string $languageIso6392Code): bool
    {
        return self::areSameLanguages($languageIso6392Code, Shopware()->Shop()->getLocale()->getLocale());
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
     * @return Translation
     */
    public static function translationService()
    {
        return static::get()->Container()->get('translation');
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public static function connection()
    {
        return static::entityManager()->getConnection();
    }

    /**
     * @return \Shopware\Components\Model\ModelManager
     */
    public static function entityManager()
    {
        return static::get()->Models();
    }

    /**
     * @return string
     */
    public static function getUrl()
    {
        $shop = ShopUtil::entityManager()->getRepository(SwShop::class)->findOneBy(['default' => 1, 'active' => 1]);

        $url = "";

        if (!is_null($shop)) {
            $proto = $shop->getSecure() ? 'https' : 'http';
            $url = sprintf('%s://%s%s/', $proto, $shop->getHost(), $shop->getBasePath());
        }

        return $url;
    }

}
