<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\SpecialPrice as SpecialPriceModel;

/**
 * SpecialPrice Model
 * @access public
 */
class SpecialPrice extends SpecialPriceModel
{
    protected $fields = array(
        'customerGroupId' => 'customerGroupId',
        'productSpecialPriceId' => 'groupId',
        'priceNet' => 'priceNet'
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
