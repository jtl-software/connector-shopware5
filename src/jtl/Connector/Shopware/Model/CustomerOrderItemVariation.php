<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerOrderItemVariation as CustomerOrderItemVariationModel;

/**
 * CustomerOrderItemVariation Model
 * @access public
 */
class CustomerOrderItemVariation extends CustomerOrderItemVariationModel
{
    protected $fields = array(
        'id' => '',
        'customerOrderItemId' => '',
        'productVariationId' => '',
        'productVariationValueId' => '',
        'productVariationName' => '',
        'productVariationValueName' => '',
        'freeField' => '',
        'surcharge' => ''
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
