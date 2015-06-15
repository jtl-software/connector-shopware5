<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Payment as PaymentModel;

/**
 * Payment Model
 * @access public
 */
class Payment extends PaymentModel
{
    protected $fields = array(
        'customerOrderId' => 'customerOrderId',
        'id' => 'id',
        'billingInfo' => 'billingInfo',
        'creationDate' => 'creationDate',
        'paymentModuleCode' => 'paymentModuleCode',
        'totalSum' => 'totalSum',
        'transactionId' => 'transactionId'
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