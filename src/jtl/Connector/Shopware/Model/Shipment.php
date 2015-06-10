<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Shipment as ShipmentModel;

/**
 * Shipment Model
 * @access public
 */
class Shipment extends ShipmentModel
{
    protected $fields = array(
        'id' => '',
        'deliveryNoteId' => '',
        'carrierName' => '',
        'trackingUrl' => '',
        'identCode' => '',
        'creationDate' => '',
        'note' => ''
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
