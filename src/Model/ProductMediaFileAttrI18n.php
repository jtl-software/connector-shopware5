<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductMediaFileAttrI18n as ProductMediaFileAttrI18nAttrModel;

/**
 * ProductMediaFileAttrI18nAttr Model
 * @access public
 */
class ProductMediaFileAttrI18n extends ProductMediaFileAttrI18nAttrModel
{
    protected $fields = array(
        'languageISO' => '',
        'name' => '',
        'value' => ''
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