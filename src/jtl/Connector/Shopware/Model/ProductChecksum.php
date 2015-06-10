<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductChecksum as ProductChecksumModel;

/**
 * ProductChecksum Model
 * @access public
 */
class ProductChecksum extends ProductChecksumModel
{
    protected $fields = array(
        'endpoint' => '',
        'host' => '',
        'hasChanged' => '',
        'type' => ''
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
