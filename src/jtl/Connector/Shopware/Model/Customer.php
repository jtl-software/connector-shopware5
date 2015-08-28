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
    protected $fields = array(
        'id' => 'id',
        'customerGroupId' => array('group', 'id'),
        //'languageISO' => array('languageSubShop', 'locale', 'locale'),
        'customerNumber' => array('billing', 'number'),
        //'password' => 'hashPassword',
        'salutation' => array('billing', 'salutation'),
        'title' => '',
        'firstName' => array('billing', 'firstName'),
        'lastName' => array('billing', 'lastName'),
        'company' => array('billing', 'company'),
        'street' => array('billing', 'street'),
        'deliveryInstruction' => array('billing', 'department'),
        'extraAddressLine' => '',
        'zipCode' => array('billing', 'zipCode'),
        'city' => array('billing', 'city'),
        'state' => '',
        'countryIso' => '',
        'phone' => array('billing', 'phone'),
        'mobile' => '',
        'fax' => array('billing', 'fax'),
        'eMail' => 'email',
        'vatNumber' => array('billing', 'vatId'),
        'websiteUrl' => '',
        'accountCredit' => '',
        'hasNewsletterSubscription' => 'newsletter',
        //'birthday' => array('billing', 'birthday'),
        'discount' => '',
        'origin' => '',
        'creationDate' => 'firstLogin',
        'modified' => '',
        'isActive' => 'active',
        'isFetched' => '',
        'hasCustomerAccount' => ''
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
