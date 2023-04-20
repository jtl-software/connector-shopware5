<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use jtl\Connector\Model\CustomerOrderShippingAddress as CustomerOrderShippingAddressModel;
use jtl\Connector\Shopware\Utilities\Salutation;

/**
 * CustomerOrderShippingAddress Model
 * @access public
 */
class CustomerOrderShippingAddress extends CustomerOrderShippingAddressModel
{
    protected $fields = array(
        'id' => 'id',
        'customerId' => 'customerId',
        'salutation' => 'salutation',
        'firstName' => 'firstName',
        'lastName' => 'lastName',
        'title' => 'title',
        'company' => 'company',
        'deliveryInstruction' => 'department',
        'street' => 'street',
        'zipCode' => 'zipCode',
        'city' => 'city',
        'state' => ['state', 'name'],
        'countryIso' => ['country', 'iso'],
        'phone' => 'phone',
        'mobile' => '',
        'fax' => '',
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

        if (isset($obj->additionalAddressLine1) || isset($obj->additionalAddressLine2)) {
            $this->setExtraAddressLine($this->formatExtraAddressLine($obj));
        }

        return DataModel::map($toWawi, $obj, $this);
    }

    /**
     * @param $obj
     * @return string
     */
    protected function formatExtraAddressLine($obj)
    {
        $extraAddressLines = [
            $obj->additionalAddressLine1,
            $obj->additionalAddressLine2
        ];

        $extraAddressLines = \array_values(\array_filter($extraAddressLines, function ($value) {
            return \strlen(\trim($value)) > 0;
        }));

        return \join("\r\n", $extraAddressLines);
    }
}
