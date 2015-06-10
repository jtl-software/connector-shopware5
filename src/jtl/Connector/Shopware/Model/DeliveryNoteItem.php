<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\DeliveryNoteItem as DeliveryNoteItemModel;

/**
 * DeliveryNoteItem Model
 * @access public
 */
class DeliveryNoteItem extends DeliveryNoteItemModel
{
    protected $fields = array(
        'id' => '',
        'customerOrderItemId' => '',
        'quantity' => '',
        'warehouseId' => '',
        'serialNumber' => '',
        'batchNumber' => '',
        'bestBeforeDate' => '',
        'deliveryNoteId' => ''
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
