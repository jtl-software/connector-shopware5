<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductI18n as ProductI18nModel;

/**
 * ProductI18n Model
 * @access public
 */
class ProductI18n extends ProductI18nModel
{
    protected $fields = array(
        'languageISO' => '',
        'productId' => 'id',
        'name' => 'name',
        'urlPath' => '',
        'description' => 'descriptionLong',
        'shortDescription' => 'description',
        'deliveryStatus' => '',
        'measurementUnitName' => '',
        'metaDescription' => '',
        'metaKeywords' => '',
        'titleTag' => '',
        'unitName' => ''
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
