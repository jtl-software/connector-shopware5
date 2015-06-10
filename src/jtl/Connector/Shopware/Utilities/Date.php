<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use \jtl\Connector\Core\Utilities\Date as CoreDate;

final class Date
{
    private function __construct()
    {
    }
    
    public static function check($platformValue)
    {
        return (($platformValue instanceof DateTime) || (is_object($platformValue) && isset($platformValue->date)));
    }
    
    public static function map($platformValue = null, $connectorValue = null)
    {
        if ($platformValue !== null) {
            $targetformat = 'Y-m-d H:i:s';

            $value = null;
            if ($platformValue instanceof DateTime) {
                $value = $platformValue->format($targetformat);
            } else {
                $value = $platformValue->date;
            }

            return CoreDate::map($value, $targetformat);
        }
        
        if ($connectorValue !== null) {
            return \DateTime::createFromFormat(\DateTime::ISO8601, $connectorValue);
        }

        return null;
    }
}
