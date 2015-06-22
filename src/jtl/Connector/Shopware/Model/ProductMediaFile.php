<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductMediaFile as ProductMediaFileModel;

/**
 * ProductMediaFile Model
 * @access public
 */
class ProductMediaFile extends ProductMediaFileModel
{
    protected $fields = array(
        'id' => '',
        'productId' => '',
        'path' => '',
        'url' => 'file',
        'mediaFileCategory' => '',
        'type' => '',
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
