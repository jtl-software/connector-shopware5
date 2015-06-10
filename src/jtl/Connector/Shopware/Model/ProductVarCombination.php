<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductVarCombination as ProductVarCombinationModel;

/**
 * ProductVarCombination Model
 * @access public
 */
class ProductVarCombination extends ProductVarCombinationModel
{
    protected $fields = array(
        'productId' => '',
        'productVariationId' => '',
        'productVariationValueId' => ''
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
