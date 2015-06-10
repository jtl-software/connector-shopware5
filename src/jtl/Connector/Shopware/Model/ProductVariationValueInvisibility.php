<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductVariationValueInvisibility as ProductVariationValueInvisibilityModel;

/**
 * ProductVariationValueInvisibility Model
 * @access public
 */
class ProductVariationValueInvisibility extends ProductVariationValueInvisibilityModel
{
    protected $fields = array(
        'customerGroupId' => '',
        'productVariationValueId' => ''
    );
    
    /**
     * (non-PHPdoc)
     * @see \jtl\Connector\Shopware\Model\DataModel::map()
     */
    public function map($toWawi = false, \stdClass $obj = null)
    {
        return DataModel::map($toWawi, $obj, $this);
    }
}
