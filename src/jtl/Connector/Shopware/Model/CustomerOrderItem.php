<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerOrderItem as CustomerOrderItemModel;
use \jtl\Connector\Core\Utilities\Money;

/**
 * CustomerOrderItem Model
 * @access public
 */
class CustomerOrderItem extends CustomerOrderItemModel
{
    protected $type = CustomerOrderItemModel::TYPE_PRODUCT;

    protected $fields = array(
        'id' => 'id',
        'productId' => 'articleId',
        'shippingClassId' => '',
        'customerOrderId' => 'orderId',
        'name' => 'articleName',
        'sku' => 'articleNumber',
        'price' => 'price',
        'vat' => 'taxRate',
        'quantity' => 'quantity',
        'type' => '',
        'unique' => '',
        'configItemId' => ''
    );
    
    /**
     * (non-PHPdoc)
     * @see \jtl\Connector\Shopware\Model\DataModel::map()
     */
    public function map($toWawi = false, \stdClass $obj = null)
    {
        $obj->price = Money::AsNet($obj->price, $obj->taxRate);

        return DataModel::map($toWawi, $obj, $this);
    }
}
