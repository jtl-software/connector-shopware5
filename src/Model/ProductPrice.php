<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductPrice as ProductPriceModel;

/**
 * ProductPrice Model
 * @access public
 */
class ProductPrice extends ProductPriceModel
{
    protected $fields = array(
        'customerGroupId' => 'customerGroupId',
        'productId' => 'articleId'
    );

    public function __clone()
    {
        $this->customerGroupId = clone $this->customerGroupId;
        $this->customerId = clone $this->customerId;
        $this->id = clone $this->id;
        $this->productId = clone $this->productId;

        $items = array();
        foreach ($this->items as $item) {
            $items[] = clone $item;
        }

        $this->items = $items;
    }
    
    /**
     * (non-PHPdoc)
     * @see \jtl\Connector\Shopware\Model\DataModel::map()
     */
    public function map($toWawi = false, \stdClass $obj = null)
    {
        return DataModel::map($toWawi, $obj, $this);
    }
}
