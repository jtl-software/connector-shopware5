<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\FileDownloadI18n as FileDownloadI18nModel;

/**
 * FileDownloadI18n Model
 * @access public
 */
class FileDownloadI18n extends FileDownloadI18nModel
{
    protected $fields = array(
        'fileDownloadId' => '',
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
