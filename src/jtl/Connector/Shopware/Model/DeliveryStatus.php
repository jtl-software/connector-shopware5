<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\DeliveryStatus as DeliveryStatusModel;

/**
 * DeliveryStatus Model
 * @access public
 */
class DeliveryStatus extends DeliveryStatusModel
{
    protected $fields = array(
        'id' => '',
        'languageISO' => '',
        'name' => ''
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
