<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use jtl\Connector\Model\ProductVariation;

final class VariationType
{
    private static $mappings = array(
        ProductVariation::TYPE_RADIO => 0,
        ProductVariation::TYPE_SELECT => 1,
        ProductVariation::TYPE_TEXTBOX => 0,
        ProductVariation::TYPE_FREE_TEXT => 0,
        ProductVariation::TYPE_FREE_TEXT_OBLIGATORY => 0,
        ProductVariation::TYPE_IMAGE_SWATCHES => 2
    );

    public static function map($type = null, $swType = null)
    {
        if ($type != null && isset(self::$mappings[$type])) {
            return self::$mappings[$type];
        } elseif ($swType !== null) {
            if (($connectorType = array_search($swType, self::$mappings)) !== false) {
                return $connectorType;
            }
        }

        return null;
    }
}