<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\FileUpload as FileUploadModel;

/**
 * FileUpload Model
 * @access public
 */
class FileUpload extends FileUploadModel
{
    protected $fields = array(
        'id' => '',
        'productId' => '',
        'name' => '',
        'description' => '',
        'fileType' => '',
        'isRequired' => ''
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
