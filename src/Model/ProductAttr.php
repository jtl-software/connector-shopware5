<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use jtl\Connector\Model\ProductAttr as ProductAttrModel;

/**
 * ProductAttr Model
 * @access public
 */
class ProductAttr extends ProductAttrModel
{
    public const ADDITIONAL_TEXT             = 'additional_text';
    public const IMAGE_CONFIGURATION_IGNORES = 'sw_image_config_ignores';
    public const IS_ACTIVE                   = 'active';
    public const IS_MAIN                     = 'main';
    public const PSEUDO_SALES                = 'pseudo_sales';
    public const SEND_NOTIFICATION           = 'send_notification';
    public const SHIPPING_FREE               = 'shipping_free';
    public const CUSTOM_PRODUCTS_TEMPLATE    = 'custom_products_template';
    public const PRICE_GROUP_ID              = 'price_group_id';
    public const MAX_PURCHASE                = 'max_purchase';
    public const DEFAULT_REGULATION_PRICE_ID = 'regulation_price';
    public const SUFFIX_REGULATION_PRICE_ID  = '_regulation_price';
    public const MAIN_CATEGORY_ID            = 'main_category_id';

    protected $fields = array(
        'id' => 'id',
        'productId' => 'articleId',
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
            self::ADDITIONAL_TEXT,
            self::IMAGE_CONFIGURATION_IGNORES,
            self::IS_ACTIVE,
            'isactive',
            self::IS_MAIN,
            'is_main',
            self::PSEUDO_SALES,
            'sw_pseudo_sales',
            self::SEND_NOTIFICATION,
            'sw_send_notification',
            self::SHIPPING_FREE,
            'shippingfree',
            self::CUSTOM_PRODUCTS_TEMPLATE,
            self::PRICE_GROUP_ID,
            self::MAX_PURCHASE
        ];
    }

    /**
     * @param $attributeName
     * @return boolean
     */
    public static function isSpecialAttribute($attributeName)
    {
        return \in_array(\strtolower($attributeName), self::getSpecialAttributes());
    }
}
