<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerAttr as CustomerAttrModel;

/**
 * CustomerAttr Model
 * @access public
 */
class CustomerAttr extends CustomerAttrModel
{
    protected $fields = array(
        //'id' => 'id',
        'customerId' => '',
        'key' => '',
        'value' => ''
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
