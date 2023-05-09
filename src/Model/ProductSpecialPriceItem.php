<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use jtl\Connector\Model\ProductSpecialPriceItem as ProductSpecialPriceItemModel;

/**
 * ProductSpecialPriceItem Model
 * @access public
 */
class ProductSpecialPriceItem extends ProductSpecialPriceItemModel
{
    protected $fields = array(
        'customerGroupId' => '',
        'productSpecialPriceId' => '',
        'priceNet' => ''
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
