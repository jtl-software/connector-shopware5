<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

final class Salutation
{
    private function __construct()
    {
    }

    public static function toConnector($salutation)
    {
        switch ($salutation) {
            case 'mr':
                return 'm';
            case 'ms':
                return 'w';
            default:
                return $salutation;
        }
    }

    public static function toEndpoint($salutation)
    {
        switch ($salutation) {
            case 'm':
                return 'mr';
            case 'w':
                return 'ms';
            default:
                return $salutation;
        }
    }
}
