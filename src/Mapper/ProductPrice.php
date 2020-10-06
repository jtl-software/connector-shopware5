<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Model\ProductPriceItem;
use \jtl\Connector\Shopware\Utilities\IdConcatenator;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Utilities\CustomerGroup as CustomerGroupUtil;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Shopware\Controller\ProductPrice as ProductPriceController;
use \Shopware\Models\Article\Article as ArticleSW;
use \Shopware\Models\Article\Detail as DetailSW;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use Shopware\Models\Article\Price;

class ProductPrice extends DataMapper
{
    public function find($id)
    {
        return ((int)$id == 0) ? null : $this->Manager()->find('Shopware\Models\Article\Price', $id);
    }

    /**
     * @param \jtl\Connector\Model\ProductPrice[] $prices
     * @return array
     */
    public function save(array $prices)
    {
        $sortedPrices = [];
        foreach ($prices as $i => $price) {
            $sortedPrices[$price->getProductId()->getHost()][] = $price;
        }

        foreach(array_values($sortedPrices) as $i => $productPrices) {
            $collection = self::buildCollection($productPrices);
            if(count($collection) > 0) {
                /** @var ArticleSW $article */
                $article = $collection[0]->getArticle();
                $article->setChanged();
                ShopUtil::entityManager()->persist($article);
            }

            if (($i % 50) === 49) {
                ShopUtil::entityManager()->flush();
            }
        }

        ShopUtil::entityManager()->flush();

        return $prices;
    }

    /**
     * @param array \jtl\Connector\Model\ProductPrice $productPrices
     * @param \Shopware\Models\Article\Article $article
     * @param \Shopware\Models\Article\Detail $detail
     * @param float $recommendedRetailPrice
     */
    public static function buildCollection(
        array $productPrices,
        ArticleSW &$article = null,
        DetailSW &$detail = null,
        $recommendedRetailPrice = null
    )
    {
        // Price
        $collection = [];
        $pricesPerGroup = [];

        // build prices per customer group
        foreach ($productPrices as $productPrice) {
            $groupId = (int)$productPrice->getCustomerGroupId()->getEndpoint();

            $priceItems = $productPrice->getItems();
            usort($priceItems, function(ProductPriceItem $a, ProductPriceItem $b) {
                return $a->getQuantity() - $b->getQuantity();
            });

            $productPrice->setItems($priceItems);
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
        $defaultCGId = ShopUtil::get()->Shop()->getCustomerGroup()->getId();
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
        if (is_null($article) || is_null($detail)) {
            if (strlen($defaultPrice->getProductId()->getEndpoint()) > 0) {
                list ($detailId, $productId) = IdConcatenator::unlink($defaultPrice->getProductId()->getEndpoint());

                $productMapper = Mmc::getMapper('Product');
                $article = $productMapper->find($productId);
                if (is_null($article)) {
                    Logger::write(sprintf('Could not find any product for endpoint (%s)', $defaultPrice->getProductId()->getEndpoint()), Logger::WARNING, 'database');

                    return $collection;
                }

                $detail = $productMapper->findDetail($detailId);
                if (is_null($detail)) {
                    Logger::write(sprintf('Could not find any detail for endpoint (%s)', $defaultPrice->getProductId()->getEndpoint()), Logger::WARNING, 'database');

                    return $collection;
                }
            } else {
                Logger::write('Could not find any product for default price', Logger::WARNING, 'database');

                return $collection;
            }
        }

        // Find pseudoprice
        if ($article->getId() > 0 && $detail->getId() > 0 && is_null($recommendedRetailPrice)) {
            $recommendedRetailPrice = ShopUtil::get()->Db()->fetchOne(
                'SELECT if(pseudoprice, pseudoprice, 0.0) FROM s_articles_prices WHERE articleID = ? AND articledetailsID = ? AND `from` = 1',
                array($article->getId(), $detail->getId())
            );
        }

        $sql = "DELETE FROM s_articles_prices WHERE articleID = ? AND articledetailsID = ?";
        ShopUtil::get()->Db()->query($sql, array($article->getId(), $detail->getId()));

        foreach ($pricesPerGroup as $groupId => $price) {
            //foreach ($pricesPerGroup as $groupId => $prices) {
            if ($groupId == 0) {
                continue;
            }

            //foreach ($prices as $price) {
            $customerGroupSW = CustomerGroupUtil::get((int)$groupId);

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

                    $priceSW = ShopUtil::entityManager()->getRepository(Price::class)->findOneBy(array(
                        'articleId' => (int)$productId,
                        'articleDetailsId' => (int)$detailId,
                        'from' => $quantity
                    ));
                }

                if (is_null($priceSW)) {
                    $priceSW = new \Shopware\Models\Article\Price;
                }

                $pseudoPrice = 0.;
                if (!is_null($recommendedRetailPrice) && $recommendedRetailPrice > $priceItem->getNetPrice()) {
                    $pseudoPrice = $recommendedRetailPrice;
                }

                $priceSW->setArticle($article)
                    ->setCustomerGroup($customerGroupSW)
                    ->setFrom($quantity)
                    ->setPrice($priceItem->getNetPrice())
                    ->setPseudoPrice($pseudoPrice)
                    ->setDetail($detail);

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
                    $article->getId(),
                    $detail->getId()
                ), Logger::DEBUG, 'prices');

                ShopUtil::entityManager()->persist($priceSW);
                $collection[] = $priceSW;
            }
            //}
        }

        return $collection;
    }
}
