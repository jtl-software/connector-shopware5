<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Shopware\Utilities\IdConcatenator;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Utilities\CustomerGroup as CustomerGroupUtil;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Shopware\Controller\ProductPrice as ProductPriceController;
use \Shopware\Models\Article\Article as ArticleSW;
use \Shopware\Models\Article\Detail as DetailSW;

class ProductPrice extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->find('Shopware\Models\Article\Price', $id);
    }

    public function save(array $prices)
    {
        $productId = 0;
        $productPrices = array();
        $priceCount = count($prices);
        $productSW = null;
        $detailSW = null;
        foreach ($prices as $i => $price) {
            $productPrices[] = $price;

            if ($productId !== $price->getProductId()->getEndpoint()) {
                $productId = $price->getProductId()->getEndpoint();

                if ($i != 0) {
                    $collection = self::buildCollection($productPrices, $productSW, $detailSW);
                    if (count($collection) > 0 && $productSW !== null && $detailSW !== null) {
                        $this->Manager()->flush();
                    }

                    $productPrices = array();
                }
            }

            if (($i + 1) == $priceCount) {
                $collection = self::buildCollection($productPrices, $productSW, $detailSW);
                if (count($collection) > 0 && $productSW !== null && $detailSW !== null) {
                    $this->Manager()->flush();
                }
            }
        }

        return true;
    }

    /**
     * @param array \jtl\Connector\Model\ProductPrice $productPrices
     * @param \Shopware\Models\Article\Article $productSW
     * @param \Shopware\Models\Article\Detail $detailSW
     */
    public static function buildCollection(array $productPrices, ArticleSW &$productSW = null, DetailSW &$detailSW = null)
    {
        // Price
        $collection = array();
        $pricesPerGroup = array();

        // build prices per customer group
        foreach ($productPrices as $productPrice) {
            $groupId = intval($productPrice->getCustomerGroupId()->getEndpoint());
            
            if (!array_key_exists($groupId, $pricesPerGroup)) {
                $pricesPerGroup[$groupId] = array();
            }

            $pricesPerGroup[$groupId][] = $productPrice;
        }

        // Search default Vk price
        $detaultPrice = null;
        if (array_key_exists(0, $pricesPerGroup)) {
            $price = $pricesPerGroup[0][0];
            if (count($price->getItems())  == 1) {
                // Set default quantity
                $items = $price->getItems();
                $items[0]->setQuantity(1);
                $price->setItems($items);

                $detaultPrice = $price;
            } else {
                Logger::write(sprintf('Default Price for product (%s, %s) != 1 item',
                    $price->getProductId()->getEndpoint(),
                    $price->getProductId()->getHost()
                ), Logger::WARNING, 'controller');

                return $collection;
            }
        } else {
            Logger::write('Could not find any default price', Logger::WARNING, 'database');

            return $collection;
        }

        // Only default?
        if (count($pricesPerGroup) == 1 && isset($pricesPerGroup[0])) {
            $defaultCGId = Shopware()->Shop()->getCustomerGroup()->getId();
            $pricesPerGroup[$defaultCGId] = $pricesPerGroup[0];
        }

        // find sw product and detail
        if ($productSW === null || $detailSW === null) {
            if (strlen($detaultPrice->getProductId()->getEndpoint()) > 0) {
                list ($detailId, $productId) = IdConcatenator::unlink($detaultPrice->getProductId()->getEndpoint());

                $productMapper = Mmc::getMapper('Product');
                $productSW = $productMapper->find($productId);
                if ($productSW === null) {
                    Logger::write(sprintf('Could not find any product for endpoint (%s)', $detaultPrice->getProductId()->getEndpoint()), Logger::WARNING, 'database');

                    return $collection;
                }

                $detailSW = $productMapper->findDetail($detailId);
                if ($detailSW === null) {
                    Logger::write(sprintf('Could not find any detail for endpoint (%s)', $detaultPrice->getProductId()->getEndpoint()), Logger::WARNING, 'database');

                    return $collection;
                }
            } else {
                Logger::write('Could not find any product for default price', Logger::WARNING, 'database');

                return $collection;
            }
        }

        $sql = "DELETE FROM s_articles_prices WHERE articleID = ? AND articledetailsID = ?";
        Shopware()->Db()->query($sql, array($productSW->getId(), $detailSW->getId()));

        foreach ($pricesPerGroup as $groupId => $prices) {
            if ($groupId == 0) {
                continue;
            }

            foreach ($prices as $price) {
                $customerGroupSW = CustomerGroupUtil::get(intval($groupId));
                if ($customerGroupSW === null) {
                    Logger::write(sprintf('Could not find any customer group with id (%s)', $groupId), Logger::WARNING, 'database');

                    continue;
                }

                $priceItems = $price->getItems();

                // Check if at least one element with quantity 1 is present
                $isPresent = ProductPriceController::isDefaultQuantityPresent($priceItems);

                // If not, insert default Vk                
                if (!$isPresent) {
                    $defaultPriceItems = $detaultPrice->getItems();
                    array_unshift($priceItems, $defaultPriceItems[0]);
                }

                $itemCount = count($priceItems);
                $firstPrice = null;
                foreach ($priceItems as $i => $priceItem) {
                    $priceSW = null;
                    $quantity = ($priceItem->getQuantity() > 0) ? $priceItem->getQuantity() : 1;
                    if (strlen($price->getProductId()->getEndpoint()) > 0) {
                        list ($detailId, $productId) = IdConcatenator::unlink($price->getProductId()->getEndpoint());

                        $priceSW = Shopware()->Models()->getRepository('Shopware\Models\Article\Price')->findOneBy(array(
                            'articleId' => (int) $productId,
                            'articleDetailsId' => (int) $detailId,
                            'from' => $quantity
                        ));
                    }

                    if ($priceSW === null) {
                        $priceSW = new \Shopware\Models\Article\Price;
                    }

                    $priceSW->setArticle($productSW)
                        ->setCustomerGroup($customerGroupSW)
                        ->setFrom($quantity)
                        ->setPrice($priceItem->getNetPrice())
                        ->setDetail($detailSW);

                    if ($quantity == 1) {
                        $firstPriceItem = clone $priceItem;
                    }

                    // percent
                    if ($i > 0 && $firstPriceItem !== null) {
                        $priceSW->setPercent(number_format(abs((1 - $priceItem->getNetPrice() / $firstPriceItem->getNetPrice()) * 100), 2));
                    }

                    if ($itemCount > 0 && ($i + 1) < $itemCount && $priceItems[($i + 1)]->getQuantity() > 0) {
                        $priceSW->setTo($priceItems[($i + 1)]->getQuantity() - 1);
                    }

                    Shopware()->Models()->persist($priceSW);
                    $collection[] = $priceSW;
                }
            }
        }

        return $collection;
    }
}
