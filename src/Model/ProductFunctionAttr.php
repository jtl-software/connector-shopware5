<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductFunctionAttr as ProductFunctionAttrModel;

/**
 * ProductFunctionAttr Model
 * @access public
 */
class ProductFunctionAttr extends ProductFunctionAttrModel
{
    protected $fields = array(
        'id' => '',
        'productId' => '',
        'key' => '',
        'value' => ''
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
