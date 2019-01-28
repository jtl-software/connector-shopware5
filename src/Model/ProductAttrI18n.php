<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductAttrI18n as ProductAttrI18nModel;

/**
 * ProductAttrI18n Model
 * @access public
 */
class ProductAttrI18n extends ProductAttrI18nModel
{
    protected $fields = array(
        "languageISO" => '',
        "productAttrId" => 'id',
        "key" => '',
        "value" => ''
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
