<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerOrderBillingAddress as CustomerOrderBillingAddressModel;
use \jtl\Connector\Shopware\Utilities\Salutation;

/**
 * CustomerOrderBillingAddress Model
 * @access public
 */
class CustomerOrderBillingAddress extends CustomerOrderBillingAddressModel
{
    protected $fields = array(
        'id' => 'id',
        'customerId' => 'customerId',
        'salutation' => 'salutation',
        'firstName' => 'firstName',
        'lastName' => 'lastName',
        'title' => '',
        'company' => '',
        'deliveryInstruction' => '',
        'street' => 'street',
        'extraAddressLine' => 'streetNumber',
        'zipCode' => 'zipCode',
        'city' => 'city',
        'state' => '',
        'countryIso' => array('country', 'iso'),
        'phone' => 'phone',
        'mobile' => '',
        'fax' => 'fax',
        'eMail' => ''
    );
    
    /**
     * (non-PHPdoc)
     * @see \jtl\Connector\Shopware\Model\DataModel::map()
     */
    public function map($toWawi = false, \stdClass $obj = null)
    {
        if (isset($obj->salutation)) {
            $obj->salutation = Salutation::toConnector($obj->salutation);
        }

        return DataModel::map($toWawi, $obj, $this);
    }
}
