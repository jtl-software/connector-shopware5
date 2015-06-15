<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Utilities;

final class Shop
{
    public static function getProtocol()
    {
        return ((bool) Shopware()->Shop()->getAlwaysSecure()) ? 'https' : 'http';
    }
}