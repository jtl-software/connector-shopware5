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
    const ADDITIONAL_TEXT = 'additional_text';
    const IMAGE_CONFIGURATION_IGNORES = 'sw_image_config_ignores';
    const IS_ACTIVE = 'active';
    const IS_MAIN = 'is_main';
    const PSEUDO_SALES = 'sw_pseudo_sales';
    const SEND_NOTIFICATION = 'sw_send_notification';
    const SHIPPING_FREE = 'shipping_free';
    const CUSTOM_PRODUCTS_TEMPLATE = 'custom_products_template';

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
            'isActive',
            self::IS_MAIN,
            self::PSEUDO_SALES,
            self::SEND_NOTIFICATION,
            self::SHIPPING_FREE,
            'ShippingFree',
        ];
    }

    /**
     * @param $attributeName
     * @return boolean
     */
    public static function isSpecialAttribute($attributeName)
    {
        return in_array($attributeName, self::getSpecialAttributes());
    }
}
