<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerOrder as CustomerOrderModel;

/**
 * CustomerOrder Model
 * @access public
 */
class CustomerOrder extends CustomerOrderModel
{
    protected $fields = array(
        'customerId' => array('customer', 'id'),
        'id' => 'id',
        'carrierName' => '',
        'creationDate' => 'orderTime',
        'currencyIso' => 'currency',
        'estimatedDeliveryDate' => '',
        'languageISO' => '',
        'note' => 'customerComment',
        'orderNumber' => 'number',
        'paymentDate' => '',
        'paymentInfo' => '',
        'paymentModuleCode' => '',
        'paymentStatus' => '',
        'shippingDate' => '',
        'shippingInfo' => '',
        'shippingMethodName' => array('dispatch', 'name'),
        'status' => '',
        'totalSum' => 'invoiceAmountNet'
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
