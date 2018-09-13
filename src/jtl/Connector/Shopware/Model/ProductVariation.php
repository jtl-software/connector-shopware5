<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductVariation as ProductVariationModel;

/**
 * ProductVariation Model
 * @access public
 */
class ProductVariation extends ProductVariationModel
{
    protected $fields = array(
        'id' => 'id',
        'productId' => 'articleId',
        'type' => '',
        'sort' => 'position'
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
