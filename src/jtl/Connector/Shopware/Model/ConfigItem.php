<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\ConfigItem as ConfigItemModel;

/**
 * ConfigItem Model
 * @access public
 */
class ConfigItem extends ConfigItemModel
{
    protected $fields = array(
        'id' => '',
        'configGroupId' => '',
        'productId' => '',
        'type' => '',
        'isPreSelected' => '',
        'isRecommended' => '',
        'inheritProductName' => '',
        'inheritProductPrice' => '',
        'showDiscount' => '',
        'showSurcharge' => '',
        'ignoreMultiplier' => '',
        'minQuantity' => '',
        'maxQuantity' => '',
        'initialQuantity' => '',
        'sort' => '',
        'vat' => ''
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
