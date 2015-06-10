<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerOrderBasket as CustomerOrderBasketModel;

/**
 * CustomerOrderBasket Model
 * @access public
 */
class CustomerOrderBasket extends CustomerOrderBasketModel
{
    protected $fields = array(
        'id' => '',
        'customerId' => '',
        'shippingAddressId' => '',
        'customerOrderPaymentInfoId' => ''
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
