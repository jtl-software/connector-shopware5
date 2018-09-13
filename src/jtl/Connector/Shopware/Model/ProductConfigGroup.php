<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductConfigGroup as ProductConfigGroupModel;

/**
 * ProductConfigGroup Model
 * @access public
 */
class ProductConfigGroup extends ProductConfigGroupModel
{
    protected $fields = array(
        'id' => '',
        'configGroupId' => '',
        'productId' => '',
        'sort' => ''
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
