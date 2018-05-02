<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Customer as CustomerModel;

/**
 * Customer Model
 * @access public
 */
class Customer extends CustomerModel
{
    protected $fields = [
        'id' => 'id',
        'customerGroupId' => ['group', 'id'],
        //'languageISO' => ['languageSubShop', 'locale', 'locale'],
        'customerNumber' => 'number',
        //'password' => 'hashPassword',
        'salutation' => ['defaultBillingAddress', 'salutation'],
        'title' => 'title',
        'firstName' => ['defaultBillingAddress', 'firstName'],
        'lastName' => ['defaultBillingAddress', 'lastName'],
        'company' => ['defaultBillingAddress', 'company'],
        'street' => ['defaultBillingAddress', 'street'],
        'deliveryInstruction' => ['defaultBillingAddress', 'department'],
        'extraAddressLine' => ['defaultBillingAddress', 'additionalAddressLine1'],
        'zipCode' => ['defaultBillingAddress', 'zipCode'],
        'city' => ['defaultBillingAddress', 'city'],
        'state' => '',
        'countryIso' => '',
        'phone' => ['defaultBillingAddress', 'phone'],
        'mobile' => '',
        'note' => 'internalcomment',
        'fax' => '',
        'eMail' => 'email',
        'vatNumber' => ['defaultBillingAddress', 'vatId'],
        'websiteUrl' => '',
        'accountCredit' => '',
        'hasNewsletterSubscription' => 'newsletter',
        'birthday' => '',
        'discount' => '',
        'origin' => '',
        'creationDate' => 'firstLogin',
        'modified' => '',
        'isActive' => 'active',
        'isFetched' => '',
        'hasCustomerAccount' => ''
    ];
    
    /**
     * (non-PHPdoc)
     * @see \jtl\Connector\Shopware\Model\DataModel::map()
     */
    public function map($toWawi = false, \stdClass $obj = null)
    {
        return DataModel::map($toWawi, $obj, $this);
    }
}
