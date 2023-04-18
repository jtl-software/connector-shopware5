<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use jtl\Connector\Model\ProductVariationValue as ProductVariationValueModel;

/**
 * ProductVariationValue Model
 * @access public
 */
class ProductVariationValue extends ProductVariationValueModel
{
    protected $fields = array(
        'id' => 'id',
        'productVariationId' => 'groupId',
        'extraWeight' => '',
        'sku' => '',
        'sort' => '',
        'stockLevel' => ''
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
