<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductAttr as ProductAttrModel;

/**
 * ProductAttr Model
 * @access public
 */
class ProductAttr extends ProductAttrModel
{
    const IS_ACTIVE = 'isActive';
    const SHIPPING_FREE = 'ShippingFree';
    const SEND_NOTIFICATION = 'sw_send_notification';
    const PSEUDO_SALES = 'sw_pseudo_sales';

    protected $fields = array(
        'id' => 'id',
        'productId' => 'articleId',
        'isTranslated' => '',
        'isCustomProperty' => ''
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
