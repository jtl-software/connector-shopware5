<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CategoryAttr as CategoryAttrModel;

/**
 * CategoryAttr Model
 * @access public
 */
class CategoryAttr extends CategoryAttrModel
{
    const IS_ACTIVE = 'isActive';
    const CMS_HEADLINE = 'cmsHeadline';

    protected $fields = array(
        'id' => 'id',
        'categoryId' => 'categoryId',
        'isTranslated' => '',
        'isCustomProperty' => ''
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
