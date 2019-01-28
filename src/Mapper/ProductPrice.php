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
        return ((int) $id == 0) ? null : $this->Manager()->find('Shopware\Models\Article\Price', $id);
    }

    public function save(array $prices)
    {
        $productId = 0;
        $productPrices = [];
        $priceCount = count($prices);
        $productSW = null;
        $detailSW = null;
        foreach ($prices as $i => $price) {
            $productPrices[] = $price;

            if ($productId !== $price->getProductId()->getEndpoint()) {
                $productId = $price->getProductId()->getEndpoint();

                if ($i != 0) {
                    $collection = self::buildCollection($productPrices, $productSW, $detailSW);
                    if (count($collection) > 0 && !is_null($productSW) && !is_null($detailSW)) {
                        $this->Manager()->flush();
                    }

                    $productPrices = [];
                }
            }

            if (($i + 1) == $priceCount) {
                $collection = self::buildCollection($productPrices, $productSW, $detailSW);
                if (count($collection) > 0 && !is_null($productSW) && !is_null($detailSW)) {
                    $this->Manager()->flush();
                }
            }
        }

        return $prices;
    }

    /**
     * @param array \jtl\Connector\Model\ProductPrice $productPrices
     * @param \Shopware\Models\Article\Article $productSW
     * @param \Shopware\Models\Article\Detail $detailSW
     * @param float $recommendedRetailPrice
     */
    public static function buildCollection(
        array $productPrices,
        ArticleSW &$productSW = null,
        DetailSW &$detailSW = null,
        $recommendedRetailPrice = null
    ) {
        // Price
        $collection = [];
        $pricesPerGroup = [];

        // build prices per customer group
        foreach ($productPrices as $productPrice) {
            $groupId = (int) $productPrice->getCustomerGroupId()->getEndpoint();

            Logger::write(sprintf('prices (group id: %s): %s', $groupId, $productPrice->toJson()), Logger::DEBUG, 'prices');

            if (!array_key_exists($groupId, $pricesPerGroup)) {
                $pricesPerGroup[$groupId] = null;
            }

            $pricesPerGroup[$groupId] = $productPrice;
        }

        // Search default Vk price
        $defaultPrice = null;
        if (array_key_exists(0, $pricesPerGroup)) {
            //$price = $pricesPerGroup[0][0];
            $price = $pricesPerGroup[0];
            if (count($price->getItems()) == 1) {
                // Set default quantity
                $items = $price->getItems();
                $items[0]->setQuantity(1);
                $price->setItems($items);

                $defaultPrice = $price;
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

        $tmpItems = $defaultPrice->getItems();
        Logger::write(sprintf(
            'default Vk price ... net price: %s - json: %s',
            $tmpItems[0]->getNetPrice(),
            $defaultPrice->toJson()
        ), Logger::DEBUG, 'prices');

        // Only default?
        $defaultCGId = Shopware()->Shop()->getCustomerGroup()->getId();
        if (count($pricesPerGroup) == 1 && isset($pricesPerGroup[0])) {
            $pricesPerGroup[$defaultCGId] = $pricesPerGroup[0];
        }
    
        // Work Around thx @Frank
        // Customer Group 1 (default) is missing
        // Customer Group 2 is present
        // Customer Group 0 is present
        if (!isset($pricesPerGroup[$defaultCGId]) && isset($pricesPerGroup[0])) {
            $pricesPerGroup[$defaultCGId] = $pricesPerGroup[0];
        }

        // find sw product and detail
        if (is_null($productSW) || is_null($detailSW)) {
            if (strlen($defaultPrice->getProductId()->getEndpoint()) > 0) {
                list ($detailId, $productId) = IdConcatenator::unlink($defaultPrice->getProductId()->getEndpoint());

                $productMapper = Mmc::getMapper('Product');
                $productSW = $productMapper->find($productId);
                if (is_null($productSW)) {
                    Logger::write(sprintf('Could not find any product for endpoint (%s)', $defaultPrice->getProductId()->getEndpoint()), Logger::WARNING, 'database');

                    return $collection;
                }

                $detailSW = $productMapper->findDetail($detailId);
                if (is_null($detailSW)) {
                    Logger::write(sprintf('Could not find any detail for endpoint (%s)', $defaultPrice->getProductId()->getEndpoint()), Logger::WARNING, 'database');

                    return $collection;
                }
            } else {
                Logger::write('Could not find any product for default price', Logger::WARNING, 'database');

                return $collection;
            }
        }

        // Find pseudoprice
        if ($productSW->getId() > 0 && $detailSW->getId() > 0 && is_null($recommendedRetailPrice)) {
            $recommendedRetailPrice = Shopware()->Db()->fetchOne(
                'SELECT if(pseudoprice, pseudoprice, 0.0) FROM s_articles_prices WHERE articleID = ? AND articledetailsID = ? AND `from` = 1',
                array($productSW->getId(), $detailSW->getId())
            );
        }

        $sql = "DELETE FROM s_articles_prices WHERE articleID = ? AND articledetailsID = ?";
        Shopware()->Db()->query($sql, array($productSW->getId(), $detailSW->getId()));

        foreach ($pricesPerGroup as $groupId => $price) {
        //foreach ($pricesPerGroup as $groupId => $prices) {
            if ($groupId == 0) {
                continue;
            }

            //foreach ($prices as $price) {
                $customerGroupSW = CustomerGroupUtil::get((int) $groupId);

                if (is_null($customerGroupSW)) {
                    Logger::write(sprintf('Could not find any customer group with id (%s)', $groupId), Logger::WARNING, 'database');

                    continue;
                }

                $priceItems = $price->getItems();

                // Check if at least one element with quantity 1 is present
                $isPresent = ProductPriceController::isDefaultQuantityPresent($priceItems);

                // If not, insert default Vk
                if (!$isPresent) {
                    $defaultPriceItems = $defaultPrice->getItems();
                    array_unshift($priceItems, $defaultPriceItems[0]);
                }

                $itemCount = count($priceItems);
                $firstPrice = null;
                foreach ($priceItems as $i => $priceItem) {
                    $priceSW = null;
                    $firstPriceItem = null;
                    $quantity = ($priceItem->getQuantity() > 0) ? $priceItem->getQuantity() : 1;
                    if (strlen($price->getProductId()->getEndpoint()) > 0) {
                        list ($detailId, $productId) = IdConcatenator::unlink($price->getProductId()->getEndpoint());

                        $priceSW = Shopware()->Models()->getRepository('Shopware\Models\Article\Price')->findOneBy(array(
                            'articleId' => (int) $productId,
                            'articleDetailsId' => (int) $detailId,
                            'from' => $quantity
                        ));
                    }

                    if (is_null($priceSW)) {
                        $priceSW = new \Shopware\Models\Article\Price;
                    }

                    $priceSW->setArticle($productSW)
                        ->setCustomerGroup($customerGroupSW)
                        ->setFrom($quantity)
                        ->setPrice($priceItem->getNetPrice())
                        ->setPseudoPrice($recommendedRetailPrice)
                        ->setDetail($detailSW);

                    if ($quantity == 1) {
                        //$priceSW->setPseudoPrice($recommendedRetailPrice);
                        $firstPriceItem = clone $priceItem;
                    }

                    // percent
                    if ($i > 0 && !is_null($firstPriceItem)) {
                        $priceSW->setPercent(number_format(abs((1 - $priceItem->getNetPrice() / $firstPriceItem->getNetPrice()) * 100), 2));
                    }

                    if ($itemCount > 0 && ($i + 1) < $itemCount && $priceItems[($i + 1)]->getQuantity() > 0) {
                        $priceSW->setTo($priceItems[($i + 1)]->getQuantity() - 1);
                    }

                    Logger::write(sprintf(
                        'group: %s - quantity: %s, net price: %s, (a %s/d %s)',
                        $customerGroupSW->getKey(),
                        $quantity,
                        $priceItem->getNetPrice(),
                        $productSW->getId(),
                        $detailSW->getId()
                    ), Logger::DEBUG, 'prices');

                    Shopware()->Models()->persist($priceSW);
                    $collection[] = $priceSW;
                }
            //}
        }

        return $collection;
    }
}
