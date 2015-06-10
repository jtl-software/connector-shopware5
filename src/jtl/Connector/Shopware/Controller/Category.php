<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Result\Action;
use \jtl\Connector\Core\Rpc\Error;
use \Shopware\Models\Category\Category as CategoryShopware;
use \jtl\Connector\Core\Model\QueryFilter;
use \jtl\Connector\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;
use \jtl\Connector\Shopware\Utilities\IdConcatenator;

/**
 * Category Controller
 * @access public
 */
class Category extends DataController
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

            $mapper = Mmc::getMapper('Category');
            $categories = $mapper->findAll($limit);

            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null);
            
            $rootCategories = array();
            $rootCategoryIds = array();
            foreach ($shops as $shop) {
                $rootCategory = Shopware()->Models()->getRepository('Shopware\Models\Category\Category')
                        ->findOneById($shop['category']['id']);

                if ($rootCategory !== null) {
                    $rootCategories[$shop['locale']['locale']] = $rootCategory;
                    $rootCategoryIds[] = $rootCategory->getId();
                }
            }

            $rootId = 1;
            foreach ($categories as $categorySW) {
                try {
                    // don't add root category
                    if ($categorySW['parentId'] === null) {
                        //$rootId = $categorySW['id'];

                        continue;
                    }

                    if ($categorySW['parentId'] === $rootId) {
                        $categorySW['parentId'] = null;
                    }

                    $category = Mmc::getModel('Category');
                    $category->map(true, DataConverter::toObject($categorySW, true));

                    $categoryObj = Shopware()->Models()->getRepository('Shopware\Models\Category\Category')
                        ->findOneById($categorySW['id']);

                    // Level
                    //$category->setLevel($categoryObj->getLevel() - 1);
                    $category->setLevel((int) $categorySW['categoryLevel']['level'] - 1);

                    // Attributes
                    if (isset($categorySW['attribute']) && is_array($categorySW['attribute'])) {
                        for ($i = 1; $i <= 6; $i++) {
                            if (isset($categorySW['attribute']["attribute{$i}"]) && strlen(trim($categorySW['attribute']["attribute{$i}"]))) {
                                $attrId = IdConcatenator::link(array($categorySW['attribute']['id'], $i));

                                $categoryAttr = Mmc::getModel('CategoryAttr');
                                $categoryAttr->map(true, DataConverter::toObject($categorySW['attribute'], true));
                                $categoryAttr->setId(new Identity($attrId));

                                $categoryAttrI18n = Mmc::getModel('CategoryAttrI18n');
                                $categoryAttrI18n->map(true, DataConverter::toObject($categorySW['attribute'], true));
                                $categoryAttrI18n->setLanguageISO(LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale()))
                                    ->setName("attribute{$i}")
                                    ->setValue($categorySW['attribute']["attribute{$i}"])
                                    ->setCategoryAttrId(new Identity($attrId));

                                $categoryAttr->addI18n($categoryAttrI18n);

                                $category->addAttribute($categoryAttr);
                            }
                        }
                    }

                    // Invisibility
                    if (isset($categorySW['customerGroups']) && is_array($categorySW['customerGroups'])) {
                        foreach ($categorySW['customerGroups'] as $customerGroup) {
                            $categoryInvisibility = Mmc::getModel('CategoryInvisibility');
                            $categoryInvisibility->setCustomerGroupId(new Identity($customerGroup['id']))
                                ->setCategoryId($category->getId());

                            $category->addInvisibility($categoryInvisibility);
                        }
                    }

                    // CategoryI18n
                    if ($categoryObj->getParent() === null) {
                        $categorySW['localeName'] = Shopware()->Locale()->toString();
                    } elseif (in_array($categoryObj->getId(), $rootCategoryIds)) {
                        foreach ($rootCategories as $localeName => $rootCategory) {
                            if ($categoryObj->getId() == $rootCategory->getId()) {
                                $categorySW['localeName'] = $localeName;
                            }
                        }
                    } else {
                        foreach ($rootCategories as $localeName => $rootCategory) {
                            if ($this->isChildOf($categoryObj, $rootCategory)) {
                                $categorySW['localeName'] = $localeName;
                                break;
                            }
                        }
                    }

                    // Fallback
                    if (!isset($categorySW['localeName']) || strlen($categorySW['localeName']) == 0) {
                        $categorySW['localeName'] = Shopware()->Shop()->getLocale()->getLocale();
                    }

                    $this->addPos($category, 'addI18n', 'CategoryI18n', $categorySW);

                    // Default locale hack
                    if ($categorySW['localeName'] != Shopware()->Shop()->getLocale()->getLocale()) {
                        $categorySW['localeName'] = Shopware()->Shop()->getLocale()->getLocale();
                        $this->addPos($category, 'addI18n', 'CategoryI18n', $categorySW);
                    }

                    //$result[] = $category->getPublic();
                    $result[] = $category;
                } catch (\Exception $exc) {
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
            }

            // Sort by level
            /*
            usort($result, function($a, $b) {
                return $a->getLevel() > $b->getLevel();
            });
            */

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

    protected function isChildOf(CategoryShopware $category, CategoryShopware $parent)
    {
        if (!($category->getParent() instanceof CategoryShopware)) {
            return false;
        }

        if ($category->getParent()->getId() === $parent->getId()) {
            return true;
        }

        return $this->isChildOf($category->getParent(), $parent);
    }
}
