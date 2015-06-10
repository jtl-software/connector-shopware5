<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\WarehouseI18n as WarehouseI18nModel;

/**
 * WarehouseI18n Model
 * @access public
 */
class WarehouseI18n extends WarehouseI18nModel
{
    protected $fields = array(
        'warehouseId' => '',
        'name' => ''
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
