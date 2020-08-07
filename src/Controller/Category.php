<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use jtl\Connector\Model\CategoryI18n;
use jtl\Connector\Result\Action;
use jtl\Connector\Core\Rpc\Error;
use jtl\Connector\Shopware\Model\CategoryAttr;
use jtl\Connector\Shopware\Model\CategoryAttrI18n;
use jtl\Connector\Shopware\Utilities\CategoryMapping as CategoryMappingUtil;
use jtl\Connector\Shopware\Utilities\Html;
use jtl\Connector\Shopware\Utilities\Shop;
use jtl\Connector\Shopware\Utilities\Str;
use jtl\Connector\Shopware\Utilities\TranslatableAttributes;
use Shopware\Models\Category\Category as CategoryShopware;
use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Core\Utilities\DataConverter;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Model\Identity;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Shopware\Utilities\IdConcatenator;

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

                    /** @var \jtl\Connector\Shopware\Model\Category $category */
                    $category = Mmc::getModel('Category');
                    $category->map(true, DataConverter::toObject($categorySW, true));

                    // Attribute Translation
                    if (isset($categorySW['translations'])) {
                        foreach ($categorySW['translations'] as $localeName => $translation) {
                            $category->addI18n($this->createI18nFromTranslation($translation, LanguageUtil::map($localeName)));
                        }
                    }

                    /** @var \Shopware\Models\Category\Category $categoryObj */
                    $categoryObj = Shopware()->Models()->getRepository('Shopware\Models\Category\Category')
                        ->findOneById($categorySW['id']);

                    // Attributes
                    $translatableAttributes = new TranslatableAttributes(CategoryAttr::class, CategoryAttrI18n::class);

                    $languageIso = LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale());

                    if (isset($categorySW['attribute']) && is_array($categorySW['attribute'])) {
                        $ignoreAttributes = ['id', 'categoryId'];
                        foreach ($categorySW['attribute'] AS $name => $value) {

                            if (empty($value) || in_array($name, $ignoreAttributes)) {
                                continue;
                            }

                            $attrId = IdConcatenator::link(array($categorySW['attribute']['id'], $name));

                            $translatableAttributes->addAttribute($attrId);
                            $translatableAttributes->addAttributeTranslation($attrId, $name, $value, $languageIso);
                            if(is_array($categorySW['translations'])) {
                                $translatableAttributes->addTranslations($attrId, $name, $categorySW['translations']);
                            }

                        }
                    }
                    $category->setAttributes($translatableAttributes->getAttributes());
                    $category->addAttribute(
                        (new \jtl\Connector\Model\CategoryAttr())
                            ->setId(new Identity(CategoryAttr::IS_BLOG))
                            ->addI18n(
                                (new \jtl\Connector\Model\CategoryAttrI18n())
                                    ->setLanguageISO($languageIso)
                                    ->setName(CategoryAttr::IS_BLOG)
                                    ->setValue($categorySW['blog'] === true ? '1' : '0')
                            )
                    );
                    $category->addAttribute(
                        (new \jtl\Connector\Model\CategoryAttr())
                            ->setId(new Identity(CategoryAttr::LIMIT_TO_SHOPS))
                            ->addI18n(
                                (new \jtl\Connector\Model\CategoryAttrI18n())
                                    ->setLanguageISO($languageIso)
                                    ->setName(CategoryAttr::LIMIT_TO_SHOPS)
                                    ->setValue($categorySW['shops'])
                            )
                    );

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
                    if (isset($categorySW['cmsText'])) {
                        $categorySW['cmsText'] = Html::replacePathsWithFullUrl($categorySW['cmsText'], Shop::getUrl());
                    }
                    $this->addPos($category, 'addI18n', 'CategoryI18n', $categorySW);

                    // Other languages
                    /** @deprecated Will be removed in a future connector release  $mappingOld */
                    $mappingOld = Application()->getConfig()->get('category_mapping', false);
                    if (Application()->getConfig()->get('category.mapping', $mappingOld)) {
                        $mappings = CategoryMappingUtil::findAllCategoryMappingByParent($category->getId()->getEndpoint());
                        foreach ($mappings as $mapping) {
                            $mapping['category']['id'] = $category->getId()->getEndpoint();
                            $mapping['category']['localeName'] = LanguageUtil::map(null, null, $mapping['lang']);
                            $this->addPos($category, 'addI18n', 'CategoryI18n', $mapping['category']);
                        }
                    }

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

    /**
     * @param string $data
     * @param $langIso
     * @return CategoryI18n
     */
    protected function createI18nFromTranslation(array $data, $langIso)
    {
        $propertyMappings = [
            'description' => 'name',
            //'cmsheadline' => $i18n->get,
            'cmstext' => 'description',
            'metatitle' => 'titleTag',
            'metakeywords' => 'metaKeywords',
            'metadescription' => 'metaDescription'
        ];

        $i18n = (new CategoryI18n())->setLanguageISO($langIso);
        foreach ($propertyMappings as $swProp => $jtlProp) {
            if (isset($data[$swProp])) {
                $setter = 'set' . ucfirst($jtlProp);
                $value = $data[$swProp];
                if($jtlProp === 'description'){
                    $value = Html::replacePathsWithFullUrl($value, Shop::getUrl());
                }

                $i18n->{$setter}($value);
            }
        }

        return $i18n;
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
