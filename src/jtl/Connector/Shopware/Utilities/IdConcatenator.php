<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

final class IdConcatenator
{
    const SEPARATOR = '_';

    private function __construct() { }    
    private function __clone() { }

    public static function link(array $endpointIds)
    {
        return implode(self::SEPARATOR, $endpointIds);
    }

    public static function unlink($endpointId)
    {
        return explode(self::SEPARATOR, $endpointId);
    }

    public static function isProductId($endpointId)
    {
        return (bool) preg_match('/\d{1,}' . self::SEPARATOR . '\d{1,}/', $endpointId);
    }

    public static function isImageId($endpointId)
    {
        return (bool) preg_match('/[a-z]{1}' . self::SEPARATOR . '\d{1,}' . self::SEPARATOR . '\d{1,}/', $endpointId);
    }
}
