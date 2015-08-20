<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ShippingMethod as ShippingMethodModel;

/**
 * ShippingMethod Model
 * @access public
 */
class ShippingMethod extends ShippingMethodModel
{
    protected $fields = array(
        'id' => 'id',
        'name' => 'name'
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
