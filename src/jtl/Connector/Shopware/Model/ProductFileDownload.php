<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductFileDownload as ProductFileDownloadModel;

/**
 * ProductFileDownload Model
 * @access public
 */
class ProductFileDownload extends ProductFileDownloadModel
{
    protected $fields = array(
        'productId' => 'articleId',
        'creationDate' => '',
        'maxDays' => '',
        'maxDownloads' => '',
        'path' => 'file',
        'previewPath' => '',
        'sort' => ''
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
