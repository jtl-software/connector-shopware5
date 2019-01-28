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
        'shortDescription' => '',
        'deliveryStatus' => '',
        'measurementUnitName' => '',
        'metaDescription' => 'description',
        'metaKeywords' => '',
        'titleTag' => '',
        'unitName' => 'packUnit'
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
