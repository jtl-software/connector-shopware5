<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Core\Logger\Logger;

/**
 * ProductPrice Controller
 * @access public
 */
class ProductPrice extends DataController
{
    /**
     * @param array \jtl\Connector\Model\ProductPrice $prices
     */
    public static function getDefaultPrice(array $prices)
    {
        // Search default Vk price
        $detaultPrice = null;
        foreach ($prices as $price) {
            if ($price->getCustomerGroupId()->getHost() == 0) {
                if (count($price->getItems()) != 1) {
                    Logger::write(sprintf('Default Price for product (%s, %s) != 1 item',
                        $price->getProductId()->getEndpoint(),
                        $price->getProductId()->getHost()
                    ), Logger::WARNING, 'controller');

                    break;
                }

                // Set default quantity
                $items = $price->getItems();
                $items[0]->setQuantity(1);
                $price->setItems($items);

                $detaultPrice = $price;
                break;
            }
        }

        return $detaultPrice;
    }

    /**
     * @param array \jtl\Connector\Model\ProductPriceItem $priceItems
     */
    public static function isDefaultQuantityPresent(array $priceItems)
    {
        $isPresent = false;
        foreach ($priceItems as $priceItem) {
            if ($priceItem->getQuantity() == 0 || $priceItem->getQuantity() == 1) {
                $isPresent = true;
                break;
            }
        }

        return $isPresent;
    }
}