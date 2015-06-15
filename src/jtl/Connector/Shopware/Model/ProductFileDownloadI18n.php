<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductFileDownloadI18n as ProductFileDownloadI18nModel;

/**
 * ProductFileDownloadI18n Model
 * @access public
 */
class ProductFileDownloadI18n extends ProductFileDownloadI18nModel
{
    protected $fields = array(
        'description' => '',
        'languageISO' => '',
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
