<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductVariationValueI18n as ProductVariationValueI18nModel;

/**
 * ProductVariationValueI18n Model
 * @access public
 */
class ProductVariationValueI18n extends ProductVariationValueI18nModel
{
    protected $fields = array(
        'languageISO' => 'localeName',
        'productVariationValueId' => 'id',
        'name' => 'name'
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
