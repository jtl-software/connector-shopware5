<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CrossSelling as CrossSellingModel;

/**
 * CrossSelling Model
 * @access public
 */
class CrossSelling extends CrossSellingModel
{
    protected $fields = array(
        'id' => '',
        'crossSellingProductId' => '',
        'crossSellingGroupId' => '',
        'productId' => ''
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
