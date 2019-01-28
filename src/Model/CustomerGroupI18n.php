<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerGroupI18n as CustomerGroupI18nModel;

/**
 * CustomerGroupI18n Model
 * @access public
 */
class CustomerGroupI18n extends CustomerGroupI18nModel
{
    protected $fields = array(
        'languageISO' => 'localeName',
        'customerGroupId' => 'id',
        'name' => 'name'
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
