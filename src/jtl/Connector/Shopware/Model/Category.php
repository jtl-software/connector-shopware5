<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\Category as CategoryModel;

/**
 * Category Model
 * @access public
 */
class Category extends CategoryModel
{
    protected $fields = array(
        'id' => 'id',
        'parentCategoryId' => 'parentId',
        'sort' => 'position',
        'level' => array('categoryLevel', 'level'),
        'isActive' => 'active'
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
