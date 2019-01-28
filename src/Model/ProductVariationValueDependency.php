<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductVariationValueDependency as ProductVariationValueDependencyModel;

/**
 * ProductVariationValueDependency Model
 * @access public
 */
class ProductVariationValueDependency extends ProductVariationValueDependencyModel
{
    protected $fields = array(
        'productVariationValueId' => '',
        'productVariationValueTargetId' => ''
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
