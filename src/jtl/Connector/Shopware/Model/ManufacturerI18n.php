<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ManufacturerI18n as ManufacturerI18nModel;

/**
 * ManufacturerI18n Model
 * @access public
 */
class ManufacturerI18n extends ManufacturerI18nModel
{
    protected $fields = array(
        'manufacturerId' => 'id',
        'languageISO' => '',
        'description' => 'description',
        'metaDescription' => '',
        'metaKeywords' => '',
        'titleTag' => ''
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
