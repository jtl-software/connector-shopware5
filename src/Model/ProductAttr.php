<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */
namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ProductAttr as ProductAttrModel;

/**
 * ProductAttr Model
 * @access public
 */
class ProductAttr extends ProductAttrModel
{
    public const
        ADDITIONAL_TEXT = 'additional_text',
        IMAGE_CONFIGURATION_IGNORES = 'image_config_ignores',
        IS_ACTIVE = 'active',
        IS_MAIN = 'main',
        PSEUDO_SALES = 'pseudo_sales',
        SEND_NOTIFICATION = 'send_notification',
        SHIPPING_FREE = 'shipping_free',
        CUSTOM_PRODUCTS_TEMPLATE = 'custom_products_template',
        PRICE_GROUP_ID = 'price_group_id';

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
            'sw_image_config_ignores',
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
            self::PRICE_GROUP_ID
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
