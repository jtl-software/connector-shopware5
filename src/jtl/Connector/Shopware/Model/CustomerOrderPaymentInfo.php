<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerOrderPaymentInfo as CustomerOrderPaymentInfoModel;

/**
 * CustomerOrderPaymentInfo Model
 * @access public
 */
class CustomerOrderPaymentInfo extends CustomerOrderPaymentInfoModel
{
    protected $fields = array(
        'id' => '',
        'customerOrderId' => '',
        'bankName' => '',
        'bankCode' => '',
        'accountHolder' => '',
        'accountNumber' => '',
        'iban' => '',
        'bic' => '',
        'creditCardNumber' => '',
        'creditCardVerificationNumber' => '',
        'creditCardExpiration' => '',
        'creditCardType' => '',
        'creditCardHolder' => ''
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
