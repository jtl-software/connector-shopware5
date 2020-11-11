<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\CustomerOrderItem as CustomerOrderItemModel;
use \jtl\Connector\Core\Utilities\Money;
use jtl\Connector\Shopware\Utilities\Plugin;
use SwagCustomProducts\Components\Services\BasketManagerInterface;

/**
 * CustomerOrderItem Model
 * @access public
 */
class CustomerOrderItem extends CustomerOrderItemModel
{
    protected $type = CustomerOrderItemModel::TYPE_PRODUCT;

    protected $fields = array(
        'id' => 'id',
        'productId' => 'articleId',
        'shippingClassId' => '',
        'customerOrderId' => 'orderId',
        'name' => 'articleName',
        'sku' => 'articleNumber',
        'price' => 'price',
        'priceGross' => 'priceGross',
        'vat' => 'taxRate',
        'quantity' => 'quantity',
        'type' => 'type',
        'unique' => '',
        'configItemId' => ''
    );

    /**
     * (non-PHPdoc)
     * @see \jtl\Connector\Shopware\Model\DataModel::map()
     */
    public function map($toWawi = false, \stdClass $obj = null)
    {
        //$obj->price = Money::AsNet($obj->price, $obj->taxRate);

        if (Plugin::isCustomProductsActive() && $toWawi === true) {
            $this->addCustomProductOptions($obj);
        }

        return DataModel::map($toWawi, $obj, $this);
    }

    /**
     * @param $swDetail
     */
    protected function addCustomProductOptions($swDetail)
    {
        $customProductsService = Shopware()->Container()->get('custom_products.custom_products_option_repository');

        $configHash = $swDetail->attribute->swagCustomProductsConfigurationHash ?? null;
        $productMode = $swDetail->attribute->swagCustomProductsMode ?? null;

        if (!is_null($configHash) && $productMode == BasketManagerInterface::MODE_OPTION) {
            $customProductsOptions = $customProductsService->getOptionsFromHash($configHash);
            if (is_array($customProductsOptions)) {
                foreach ($customProductsOptions as $customProductsOption) {
                    if ($customProductsOption['label'] === $swDetail->articleName) {
                        if (is_array($customProductsOption['value'])) {
                            $note = join(', ', array_map(function ($singleValue) {
                                return $singleValue['label'];
                            }, $customProductsOption['value']));
                        } else {
                            $note = $customProductsOption['value'];
                        }
                        $this->setNote(sprintf("Custom Products: %s", $note));
                        break;
                    }
                }
            }
        }
    }
}
