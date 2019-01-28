<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductMediaFileI18n as ProductMediaFileI18nModel;

/**
 * ProductMediaFileI18n Model
 * @access public
 */
class ProductMediaFileI18n extends ProductMediaFileI18nModel
{
    protected $fields = array(
        'productMediaFileId' => '',
        'languageISO' => '',
        'name' => '',
        'description' => ''
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
