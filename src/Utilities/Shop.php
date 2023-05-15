<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Utilities;

use jtl\Connector\Core\Exception\LanguageException;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Shopware\Service\Translation;
use Shopware\Bundle\MediaBundle\MediaServiceInterface;
use Shopware\Components\CacheManager;
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
     * @param string $languageIsoCode2B
     * @param string $locale
     * @return bool
     * @throws LanguageException
     * @throws \Exception
     */
    public static function areSameLanguages(string $languageIsoCode2B, string $locale): bool
    {
        $convertedLocale = LanguageUtil::map(null, null, $languageIsoCode2B);
        return $convertedLocale !== null
            && Locale::extractLanguageIsoFromLocale($convertedLocale) === Locale::extractLanguageIsoFromLocale($locale);
    }

    /**
     * @param string $languageIsoCode2B
     * @return bool
     * @throws LanguageException
     */
    public static function isShopwareDefaultLanguage(string $languageIsoCode2B): bool
    {
        return self::areSameLanguages($languageIsoCode2B, Shopware()->Shop()->getLocale()->getLocale());
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
     * @return CacheManager
     */
    public static function cacheManager(): CacheManager
    {
        return static::get()->Container()->get('shopware.cache_manager');
    }

    /**
     * @return MediaServiceInterface
     */
    public static function mediaService(): MediaServiceInterface
    {
        return static::get()->Container()->get('shopware_media.media_service');
    }

    /**
     * @return Manager
     */
    public static function thumbnailManager(): Manager
    {
        return static::get()->Container()->get('thumbnail_manager');
    }

    /**
     * @return Translation
     */
    public static function translationService(): Translation
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

    /**
     * @param $message
     * @return bool
     */
    public static function isCustomerNotFoundException($message): bool
    {
        $pattern = "/Entity of type 'Shopware\\\Models\\\Customer\\\Customer' for IDs id\([0-9]+\) was not found/";
        return (int)preg_match($pattern, $message) > 0;
    }

}
