<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductInvisibility as ProductInvisibilityModel;

/**
 * ProductInvisibility Model
 * @access public
 */
class ProductInvisibility extends ProductInvisibilityModel
{
    protected $fields = array(
        'customerGroupId' => 'id',
        'productId' => 'articleId'
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
