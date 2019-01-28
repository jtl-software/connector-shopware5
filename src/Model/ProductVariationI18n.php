<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductVariationI18n as ProductVariationI18nModel;

/**
 * ProductVariationI18n Model
 * @access public
 */
class ProductVariationI18n extends ProductVariationI18nModel
{
    protected $fields = array(
        'languageISO' => 'localeName',
        'productVariationId' => 'id',
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
