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
    const IS_ACTIVE = 'active';
    const CMS_HEADLINE = 'cms_headline';
    const IS_BLOG = 'is_blog';
    const LIMIT_TO_SHOPS = 'limit_to_shops';
    const LINK_TARGET = 'link_target';

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

    /**
     * @return string[]
     */
    public static function getSpecialAttributes()
    {
        return [
            self::IS_ACTIVE,
            'isactive',
            self::CMS_HEADLINE,
            'cmsheadline',
            self::IS_BLOG,
            self::LIMIT_TO_SHOPS
        ];
    }

    /**
     * @param $attributeName
     * @return boolean
     */
    public static function isSpecialAttribute($attributeName)
    {
        return in_array(strtolower($attributeName), self::getSpecialAttributes());
    }
}
