<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductVariationInvisibility as ProductVariationInvisibilityModel;

/**
 * ProductVariationInvisibility Model
 * @access public
 */
class ProductVariationInvisibility extends ProductVariationInvisibilityModel
{
    protected $fields = array(
        'customerGroupId' => '',
        'productVariationId' => ''
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
