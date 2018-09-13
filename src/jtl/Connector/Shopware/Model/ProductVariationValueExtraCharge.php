<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductVariationValueExtraCharge as ProductVariationValueExtraChargeModel;

/**
 * ProductVariationValueExtraCharge Model
 * @access public
 */
class ProductVariationValueExtraCharge extends ProductVariationValueExtraChargeModel
{
    protected $fields = array(
        'customerGroupId' => '',
        'productVariationValueId' => '',
        'extraChargeNet' => ''
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
