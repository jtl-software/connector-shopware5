<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductSpecific as ProductSpecificModel;

/**
 * ProductSpecific Model
 * @access public
 */
class ProductSpecific extends ProductSpecificModel
{
    protected $fields = array(
        'id' => '',
        'specificValueId' => 'specificValueId',
        'productId' => 'productId'
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
