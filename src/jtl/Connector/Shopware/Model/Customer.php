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
        'customerNumber' => ['billing', 'number'],
        //'password' => 'hashPassword',
        'salutation' => ['billing', 'salutation'],
        'title' => '',
        'firstName' => ['billing', 'firstName'],
        'lastName' => ['billing', 'lastName'],
        'company' => ['billing', 'company'],
        'street' => ['billing', 'street'],
        'deliveryInstruction' => ['billing', 'department'],
        'extraAddressLine' => ['billing', 'additionalAddressLine1'],
        'zipCode' => ['billing', 'zipCode'],
        'city' => ['billing', 'city'],
        'state' => '',
        'countryIso' => '',
        'phone' => ['billing', 'phone'],
        'mobile' => '',
        'fax' => ['billing', 'fax'],
        'eMail' => 'email',
        'vatNumber' => ['billing', 'vatId'],
        'websiteUrl' => '',
        'accountCredit' => '',
        'hasNewsletterSubscription' => 'newsletter',
        'birthday' => ['billing', 'birthday'],
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
