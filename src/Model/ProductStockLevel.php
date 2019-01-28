<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductStockLevel as ProductStockLevelModel;

/**
 * ProductStockLevel Model
 * @access public
 */
class ProductStockLevel extends ProductStockLevelModel
{
    protected $fields = array(
        'stockLevel' => 'inStock',
        'productId' => 'id'
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
