<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ConfigItemPrice as ConfigItemPriceModel;

/**
 * ConfigItemPrice Model
 * @access public
 */
class ConfigItemPrice extends ConfigItemPriceModel
{
    protected $fields = array(
        'configItemId' => '',
        'customerGroupId' => '',
        'price' => '',
        'type' => ''
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
