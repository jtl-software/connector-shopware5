<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

class MediaService
{
    public static function get()
    {
        $sw = Shopware();
        if (version_compare($sw::VERSION, '5.1', '>=')) {
             return Shopware()->Container()->get('shopware_media.media_service');
        }

        return null;
    }
}