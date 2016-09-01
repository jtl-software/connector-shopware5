<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Result\Action;
use \jtl\Connector\Core\Rpc\Error;
use \jtl\Connector\Core\Model\QueryFilter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Core\Utilities\DataConverter;
use \jtl\Connector\Core\Utilities\DataInjector;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Core\Exception\ControllerException;
use \jtl\Connector\Shopware\Utilities\CustomerGroup as CustomerGroupUtil;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;
use \jtl\Connector\Shopware\Utilities\IdConcatenator;
use jtl\Connector\Shopware\Utilities\VariationType;

/**
 * Product Controller
 * @access public
 */
class Product extends DataController
{
    /**
     * Pull
     *
     * @param \jtl\Connector\Core\Model\QueryFilter $queryFilter
     * @return \jtl\Connector\Result\Action
     */
    public function pull(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $result = array();
            $limit = $queryFilter->isLimit() ? $queryFilter->getLimit() : 100;
            $mapper = Mmc::getMapper('Product');

            $products = $mapper->findAll($limit);
            
            foreach ($products as $productSW) {
                try {
                    $isDetail = (isset($productSW['article']['configuratorSetId']) && (int) $productSW['article']['configuratorSetId'] > 0 && $productSW['kind'] != 0);
                    $product = $this->buildProduct($productSW, $isDetail);

                    if ($product !== null) {
                        $result[] = $product;
                    }

                } catch (\Exception $exc) {
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
            }

            $action->setResult($result);
        } catch (\Exception $exc) {
            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }

    protected function buildProduct(array &$data, $isDetail = false)
    {
        if (!isset($data['article']) || $data['article'] === null) {
            Logger::write(sprintf('Missing article data on product with sku (%s) and detail id (%s)',
                $data['number'],
                $data['id']
                ), Logger::WARNING, 'controller');

            return null;
        }

        foreach (array_keys($data['article']) as $key) {
            if (!isset($data[$key])) {
                $data[$key] = $data['article'][$key];
            }
        }

        $data['detailId'] = $data['id'];
        $data['id'] = IdConcatenator::link(array($data['id'], $data['articleId']));
        $article = $data['article'];
        $data['isMasterProduct'] = (isset($data['configuratorSetId']) && (int)$data['configuratorSetId'] > 0 && (int) $data['kind'] == 0);

        unset($data['article']);

        if ($isDetail) {
            $mapper = Mmc::getMapper('Product');
            $parentDetailId = $mapper->getParentDetailId((int) $data['articleId']);
            $data['masterProductId'] = IdConcatenator::link(array($parentDetailId, $data['articleId']));

            $variationName = $data['additionalText'];
            if (strlen(trim($variationName)) == 0) {
                foreach ($data['configuratorOptions'] as $i => $option) {
                    $space = ($i > 0) ? ' ' : '';
                    $variationName .= sprintf('%s%s', $space, $option['name']);
                }
            }

            $data['name'] = sprintf('%s %s', $data['name'], $variationName);
        }

        $data['tax']['tax'] = floatval($data['tax']['tax']);
        $data['mainDetail']['weight'] = floatval($data['mainDetail']['weight']);

        $product = Mmc::getModel('Product');
        $product->map(true, DataConverter::toObject($data, true));

        $stockLevel = Mmc::getModel('ProductStockLevel');
        $stockLevel->map(true, DataConverter::toObject($data, true));

        // Stock
        $product->setConsiderStock(true)
            ->setPermitNegativeStock((bool) !$data['lastStock']);

        $product->setStockLevel($stockLevel);

        // ProductI18n
        $this->addPos($product, 'addI18n', 'ProductI18n', $data);
        if (isset($data['translations'])) {
            foreach ($data['translations'] as $localeName => $translation) {
                $productI18n = Mmc::getModel('ProductI18n');
                $productI18n->setLanguageISO(LanguageUtil::map($localeName))
                    ->setProductId($product->getId())
                    ->setName($translation['name'])
                    ->setDescription($translation['descriptionLong']);

                $productI18n->setUnitName($translation['packUnit']);

                $product->addI18n($productI18n);
            }
        }

        // ProductPrice
        $recommendedRetailPrice = 0.0;
        $purchasePrice = 0.0;
        $customerGroupCache = null;
        $productPriceId = new Identity(IdConcatenator::link(array($product->getId()->getEndpoint(), 0)));
        $productPrice = Mmc::getModel('ProductPrice');
        $productPrice->setProductId($product->getId())
            ->setId($productPriceId);
        
        // BasePrice
        if ($product->getBasePriceQuantity() > 0 && $product->getMeasurementQuantity() > 0) {
            $product->setConsiderBasePrice(true);
            $product->setBasePriceDivisor($product->getMeasurementQuantity() * $product->getBasePriceQuantity());
        }

        $defaultPrice = null;
        for ($i = 0; $i < count($data['prices']); $i++) {
            $customerGroup = CustomerGroupUtil::getByKey($data['prices'][$i]['customerGroupKey']);

            if ($customerGroup === null) {
                //throw new ControllerException(sprintf('Could not find any customer group with key (%s)', $data['prices'][$i]['customerGroupKey']));
                $customerGroup = Shopware()->Shop()->getCustomerGroup();
            }

            $productPriceItem = Mmc::getModel('ProductPriceItem');
            $productPriceItem->setNetPrice($data['prices'][$i]['price'])
                ->setQuantity($data['prices'][$i]['from'])
                ->setProductPriceId($productPriceId);

            if ($customerGroupCache !== null && $customerGroupCache !== $customerGroup->getId()) {
                $product->addPrice($productPrice);

                $productPriceId = new Identity(IdConcatenator::link(array($product->getId()->getEndpoint(), $i)));
                $productPrice = Mmc::getModel('ProductPrice');
                $productPrice->setProductId($product->getId())
                    ->setCustomerGroupId(new Identity($customerGroup->getId()))
                    ->setId($productPriceId);

                if ($isDetail) {
                    $productPrice->setProductId(new Identity(IdConcatenator::link(array($data['prices'][$i]['id'],
                        $data['prices'][$i]['articleId']))));
                }
            }

            $productPrice->addItem($productPriceItem)
                ->setCustomerGroupId(new Identity($customerGroup->getId()));

            // Search default product price
            if ($customerGroup->getId() == Shopware()->Shop()->getCustomerGroup()->getId() && (int) $data['prices'][$i]['from'] == 1) {
                $recommendedRetailPrice = (double) $data['prices'][$i]['pseudoPrice'];
                $purchasePrice = (double) $data['prices'][$i]['basePrice'];
                $defaultPrice = clone $productPrice;
                $defaultPrice->setCustomerGroupId(new Identity('0', 0))
                    ->setCustomerId(new Identity('0', 0));
                $arr = $defaultPrice->getItems();
                $arr[0]->setQuantity(0);
                $defaultPrice->setItems(array($arr[0]));
            }

            $customerGroupCache = $customerGroup->getId();
        }

        $product->addPrice($productPrice);

        // add default price
        if ($defaultPrice !== null) {
            $product->addPrice($defaultPrice)
                ->setRecommendedRetailPrice($recommendedRetailPrice)
                ->setPurchasePrice($purchasePrice);
        } else {
            Logger::write(sprintf('Could not find any default price for product (%s, %s)', 
                $product->getId()->getEndpoint(),
                $product->getId()->getHost()
            ), Logger::WARNING, 'controller');
        }

        // ProductSpecialPrice
        if ($data['priceGroupActive'] && $data['priceGroup'] !== null) {
            DataInjector::inject(DataInjector::TYPE_ARRAY, $data['priceGroup'], array('articleId', 'active'), array($product->getId()->getEndpoint(), true));
            $productSpecialPrice = Mmc::getModel('ProductSpecialPrice');
            $productSpecialPrice->map(true, DataConverter::toObject($data['priceGroup'], true));

            // SpecialPrices
            $exists = false;
            foreach ($data['priceGroup']['discounts'] as $discount) {
                if (intval($discount['start']) != 1) {
                    continue;
                }

                $customerGroup = CustomerGroupUtil::get($discount['customerGroupId']);
                if ($customerGroup === null) {
                    //throw new ControllerException(sprintf('Could not find any customer group with id (%s)', $discount['customerGroupId']));
                    $customerGroup = Shopware()->Shop()->getCustomerGroup();
                }

                $price = null;
                $priceCount = count($data['prices']);

                if ($priceCount == 1) {
                    $price = reset($data['prices']);
                } elseif ($priceCount > 1) {
                    foreach ($data['prices'] as $mainPrice) {
                        if ($mainPrice['customerGroupKey'] === $customerGroup->getKey()) {
                            $price = $mainPrice;

                            break;
                        }
                    }
                } else {
                    Logger::write(sprintf('Could not find any price for customer group (%s)', $customerGroup->getKey()), Logger::WARNING, 'controller');

                    continue;
                }

                // Calling shopware core method
                $discountPriceNet = Shopware()->Modules()->Articles()->sGetPricegroupDiscount(
                    $customerGroup->getKey(),
                    $discount['groupId'],
                    $price['price'],
                    1,
                    false
                );

                $productSpecialPriceItem = Mmc::getModel('ProductSpecialPriceItem');
                $productSpecialPriceItem->setCustomerGroupId(new Identity($discount['customerGroupId']))
                    ->setProductSpecialPriceId(new Identity($discount['groupId']))
                    ->setPriceNet($discountPriceNet);

                $productSpecialPrice->addItem($productSpecialPriceItem);
                $exists = true;
            }

            if ($exists) {
                $product->addSpecialPrice($productSpecialPrice);
            }
        }

        // Product2Categories
        if (isset($data['categories'])) {
            DataInjector::inject(DataInjector::TYPE_ARRAY, $data['categories'], 'articleId', $product->getId()->getEndpoint(), true);
            $this->addPos($product, 'addCategory', 'Product2Category', $data['categories'], true);
        }

        // Attributes
        for ($i = 1; $i <= 20; $i++) {
            if (isset($data['attribute']["attr{$i}"]) && strlen($data['attribute']["attr{$i}"]) > 0) {
                $attrId = IdConcatenator::link(array($data['attribute']['id'], $i));

                $productAttr = Mmc::getModel('ProductAttr');
                $productAttr->map(true, DataConverter::toObject($data['attribute']));
                $productAttr->setId(new Identity($attrId))
                    ->setProductId($product->getId());

                $productAttrI18n = Mmc::getModel('ProductAttrI18n');
                $productAttrI18n->map(true, DataConverter::toObject($data['attribute'], true));
                $productAttrI18n->setProductAttrId($productAttr->getId());
                $productAttrI18n->setName("attr{$i}")
                    ->setValue($data['attribute']["attr{$i}"]);

                $productAttr->addI18n($productAttrI18n);
                $product->addAttribute($productAttr);
            }
        }

        // ProductInvisibility
        if (isset($data['customerGroups'])) {
            DataInjector::inject(DataInjector::TYPE_ARRAY, $data['customerGroups'], 'articleId', $product->getId()->getEndpoint(), true);
            $this->addPos($product, 'addInvisibility', 'ProductInvisibility', $data['customerGroups'], true);
        }

        // ProductVariation
        if (isset($data['configuratorSetId']) && (int)$data['configuratorSetId'] > 0) {
            $groups = array();
            $options = array();
            if ($isDetail && isset($data['configuratorOptions']) && count($data['configuratorOptions']) > 0) {
                $groups = array_map(function($value) { return $value['groupId']; }, $data['configuratorOptions']);
                $options = array_map(function($value) { return $value['id']; }, $data['configuratorOptions']);
            }

            $configuratorSetMapper = Mmc::getMapper('ConfiguratorSet');
            $configuratorSets = $configuratorSetMapper->findByProductId($data['articleId']);
            if (is_array($configuratorSets) && count($configuratorSets) > 0) {
                foreach ($configuratorSets as $cs) {
                    $typeSW = (int) $cs['configuratorSet']['type'];

                    // ProductVariationI18n
                    foreach ($cs['configuratorSet']['groups'] as $group) {
                        $groupId = $group['id'];
                        $group['localeName'] = Shopware()->Shop()->getLocale()->getLocale();
                        $group['id'] = IdConcatenator::link(array($product->getId()->getEndpoint(), $group['id']));
                        $group['articleId'] = $product->getId()->getEndpoint();

                        if ($isDetail && !in_array($groupId, $groups)) {
                            continue;
                        }

                        $productVariation = Mmc::getModel('ProductVariation');
                        $productVariation->map(true, DataConverter::toObject($group, true));

                        $productVariation->setType(VariationType::map(null, $typeSW));

                        // Main Language
                        $productVariationI18n = Mmc::getModel('ProductVariationI18n');
                        $productVariationI18n->setLanguageISO(LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale()))
                            ->setProductVariationId(new Identity($group['id']))
                            ->setName($group['name']);

                        $productVariation->addI18n($productVariationI18n);

                        if (isset($group['translations'])) {
                            foreach ($group['translations'] as $localeName => $translation) {
                                $productVariationI18n = Mmc::getModel('ProductVariationI18n');
                                $productVariationI18n->setLanguageISO(LanguageUtil::map($localeName))
                                    ->setProductVariationId(new Identity($group['id']))
                                    ->setName($translation['name']);

                                $productVariation->addI18n($productVariationI18n);
                            }
                        }

                        // ProductVariationValueI18n
                        foreach ($cs['configuratorSet']['options'] as $option) {
                            if ($option['groupId'] != $groupId) {
                                continue;
                            }

                            $id = $option['id'];
                            $option['id'] = IdConcatenator::link(array($product->getId()->getEndpoint(), $option['groupId'], $option['id']));
                            $option['groupId'] = IdConcatenator::link(array($product->getId()->getEndpoint(), $option['groupId']));

                            if ($isDetail && !in_array($id, $options)) {
                                continue;
                            }

                            $productVariationValue = Mmc::getModel('ProductVariationValue');
                            $productVariationValue->map(true, DataConverter::toObject($option, true));

                            /*
                            $productVarCombination = Mmc::getModel('ProductVarCombination');
                            $productVarCombination->setProductId($product->getId())
                                ->setProductVariationId(new Identity($option['groupId']))
                                ->setProductVariationValueId(new Identity($option['id']));

                            $product->addVarCombination($productVarCombination);
                            */

                            // Main Language
                            $productVariationValueI18n = Mmc::getModel('ProductVariationValueI18n');
                            $productVariationValueI18n->setLanguageISO(LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale()))
                                ->setProductVariationValueId(new Identity($option['id']))
                                ->setName($option['name']);

                            $productVariationValue->addI18n($productVariationValueI18n);

                            if (isset($option['translations'])) {
                                foreach ($option['translations'] as $localeName => $translation) {
                                    $productVariationValueI18n = Mmc::getModel('ProductVariationValueI18n');
                                    $productVariationValueI18n->setLanguageISO(LanguageUtil::map($localeName))
                                        ->setProductVariationValueId(new Identity($option['id']))
                                        ->setName($translation['name']);

                                    $productVariationValue->addI18n($productVariationValueI18n);
                                }
                            }

                            $productVariation->addValue($productVariationValue);
                        }

                        $product->addVariation($productVariation);
                    }
                }
            }
        }

        if (!$isDetail) {
            // ProductSpecific
            if (isset($data['propertyValues'])) {
                foreach ($data['propertyValues'] as $value) {
                    $productSpecific = Mmc::getModel('ProductSpecific');
                    $productSpecific->setId(new Identity($value['optionId']))
                        ->setProductId(new Identity($value['id']))
                        ->setSpecificValueId(new Identity($value['id']));

                    $product->addSpecific($productSpecific);
                }
            }

            // Downloads
            foreach ($data['downloads'] as $i=> $downloadSW) {
                $productMediaFile = Mmc::getModel('ProductMediaFile');
                $productMediaFile->map(true, DataConverter::toObject($downloadSW));
                $productMediaFile->setProductId($product->getId())
                    ->setSort($i)
                    ->setType('.*');

                $productMediaFileI18n = Mmc::getModel('ProductMediaFileI18n');
                $productMediaFileI18n->setLanguageISO(LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale()))
                    ->setName($downloadSW['name']);

                $productMediaFile->addI18n($productMediaFileI18n);

                $product->addMediaFile($productMediaFile);
            }

            // Links
            foreach ($data['links'] as $i=> $linkSW) {
                $productMediaFile = Mmc::getModel('ProductMediaFile');
                $productMediaFile->map(true, DataConverter::toObject($linkSW));
                $productMediaFile->setProductId($product->getId())
                    ->setUrl($linkSW['link'])
                    ->setSort($i)
                    ->setType('.*');

                $productMediaFileI18n = Mmc::getModel('ProductMediaFileI18n');
                $productMediaFileI18n->setLanguageISO(LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale()))
                    ->setName($linkSW['name']);

                $productMediaFile->addI18n($productMediaFileI18n);

                $product->addMediaFile($productMediaFile);
            }
        }
        
        return $product;
    }
}
