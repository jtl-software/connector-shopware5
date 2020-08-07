<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */
namespace jtl\Connector\Shopware\Mapper;

use Doctrine\ORM\ORMException;
use jtl\Connector\Core\Exception\LanguageException;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Model\Category as JtlCategory;
use jtl\Connector\Model\Identity;
use jtl\Connector\Shopware\Model\CategoryAttr;
use jtl\Connector\Shopware\Utilities\CategoryMapping as CategoryMappingUtil;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use jtl\Connector\Shopware\Utilities\TranslatableAttributes;
use Shopware\Bundle\AttributeBundle\Service\TypeMapping;
use Shopware\Models\Category\Category as SwCategory;

class Category extends DataMapper
{
    protected static $parentCategoryIds = array();

    public function findOneBy(array $kv)
    {
        return ShopUtil::entityManager()->getRepository('Shopware\Models\Category\Category')->findOneBy($kv);
    }

    public function find($id)
    {
        return (intval($id) == 0) ? null : ShopUtil::entityManager()->find('Shopware\Models\Category\Category', $id);
    }

    public function findByNameAndLevel($name, $level, $parentId = null)
    {
        $sql = ' AND c.parent IS NULL';
        $params = array($name);
        if ($parentId !== null) {
            $sql = ' AND c.parent = ?';

            $params[] = $parentId;
        } elseif ($level == 1) {
            $id = Shopware()->Db()->fetchOne(
                'SELECT c.id FROM s_categories c WHERE c.parent IS NULL', []
            );

            if ((int)$id > 0) {
                $sql = ' AND c.parent = ' . intval($id);
            }
        }

        $id = Shopware()->Db()->fetchOne(
            'SELECT c.id
              FROM s_categories c
              WHERE c.description = ?' . $sql,
            $params
        );

        if ($id !== null && (int)$id > 0) {
            return $this->find($id);
        }

        return null;
    }

    public function findAll($limit = 100, $count = false)
    {
        $query = ShopUtil::entityManager()->createQueryBuilder()->select(
            'category',
            'attribute',
            'customergroup'
        )
            ->from('jtl\Connector\Shopware\Model\Linker\Category', 'category')
            ->leftJoin('category.linker', 'linker')
            ->leftJoin('category.attribute', 'attribute')
            ->leftJoin('category.customerGroups', 'customergroup')
            ->where('linker.hostId IS NULL')
            ->setFirstResult(0)
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        if ($count) {
            return ($paginator->count() - 1);
        }

        $categories = iterator_to_array($paginator);

        $shopMapper = Mmc::getMapper('Shop');
        $shops = $shopMapper->findAll(null, null);

        $translationService = ShopUtil::translationService();
        for ($i = 0; $i < count($categories); $i++) {
            foreach ($shops as $shop) {
                $translation = $translationService->read($shop['id'], 'category', $categories[$i]['id']);
                $translation = array_merge($translation, $translationService->read($shop['id'], 's_categories_attributes', $categories[$i]['id']));

                if (!empty($translation)) {
                    $translation['shopId'] = $shop['id'];
                    $categories[$i]['translations'][$shop['locale']['locale']] = $translation;
                }
            }
        }

        return $categories;
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function delete(JtlCategory $jtlCategory)
    {
        $result = new JtlCategory;

        /** @deprecated Will be removed in a future connector release  $mappingOld */
        $mappingOld = Application()->getConfig()->get('category_mapping', false);
        if (Application()->getConfig()->get('category.mapping', $mappingOld)) {
            $this->deleteCategoryMappingData($jtlCategory);
        }

        $this->deleteCategoryData($jtlCategory);

        // Result
        $result->setId(new Identity('', $jtlCategory->getId()->getHost()));

        return $result;
    }

    public function save(JtlCategory $jtlCategory)
    {
        $swCategory = null;
        $result = $jtlCategory;
        $translations = [];

        if ($jtlCategory->getParentCategoryId() !== null && isset(self::$parentCategoryIds[$jtlCategory->getParentCategoryId()->getHost()])) {
            $jtlCategory->getParentCategoryId()->setEndpoint(self::$parentCategoryIds[$jtlCategory->getParentCategoryId()->getHost()]);
        }

        $this->prepareCategoryAssociatedData($jtlCategory, $swCategory);
        $this->prepareI18nAssociatedData($jtlCategory, $swCategory);
        $this->prepareAttributeAssociatedData($jtlCategory, $swCategory, $translations);
        $this->prepareInvisibilityAssociatedData($jtlCategory, $swCategory);

        // Save Category
        ShopUtil::entityManager()->persist($swCategory);
        ShopUtil::entityManager()->flush();

        $this->saveTranslations($jtlCategory, $swCategory->getId(), $translations);

        /** @deprecated Will be removed in a future connector release  $mappingOld */
        $mappingOld = Application()->getConfig()->get('category_mapping', false);
        if (Application()->getConfig()->get('category.mapping', $mappingOld)) {
            $this->prepareCategoryMapping($jtlCategory, $swCategory);
        }

        if ($swCategory !== null && $swCategory->getId() > 0) {
            self::$parentCategoryIds[$jtlCategory->getId()->getHost()] = $swCategory->getId();
        }

        // Result
        $result->setId(new Identity($swCategory->getId(), $jtlCategory->getId()->getHost()));

        return $result;
    }

    protected function deleteCategoryData(JtlCategory $jtlCategory)
    {
        $categoryId = (strlen($jtlCategory->getId()->getEndpoint()) > 0) ? (int)$jtlCategory->getId()->getEndpoint() : null;

        if ($categoryId !== null && $categoryId > 0) {
            $categorySW = $this->find((int)$categoryId);

            if ($categorySW !== null && Shopware()->Shop() !== null && Shopware()->Shop()->getCategory() !== null) {
                // if category is a main subshop root category
                if ($categorySW->getId() == Shopware()->Shop()->getCategory()->getId()) {
                    Shopware()->Db()->query('UPDATE s_core_shops SET category_id = NULL WHERE id = ?', array(Shopware()->Shop()->getId()));
                }

                ShopUtil::entityManager()->remove($categorySW);
                ShopUtil::entityManager()->flush($categorySW);
            }
        }
    }

    protected function deleteCategoryMappingData(JtlCategory $jtlCategory)
    {
        foreach (CategoryMappingUtil::findAllCategoriesByMappingParent($jtlCategory->getId()->getEndpoint()) as $categorySW) {
            ShopUtil::entityManager()->remove($categorySW);
            ShopUtil::entityManager()->flush($categorySW);
        }
    }

    protected function prepareCategoryAssociatedData(JtlCategory $jtlCategory, SwCategory &$swCategory = null)
    {
        $categoryId = (strlen($jtlCategory->getId()->getEndpoint()) > 0) ? (int)$jtlCategory->getId()->getEndpoint() : null;
        $parentId = (strlen($jtlCategory->getParentCategoryId()->getEndpoint()) > 0) ? $jtlCategory->getParentCategoryId()->getEndpoint() : null;

        if ($categoryId !== null && $categoryId > 0) {
            $swCategory = $this->find($categoryId);
            if ($swCategory !== null && $swCategory->getLevel() > 0 && $parentId === null) {
                $parentId = $swCategory->getParent()->getId();
            }
        }

        // Try via name
        if (is_null($swCategory)) {
            $name = null;
            foreach ($jtlCategory->getI18ns() as $i18n) {
                if (LanguageUtil::map(null, null, $i18n->getLanguageISO()) === Shopware()->Shop()->getLocale()->getLocale()) {
                    $name = $i18n->getName();
                    break;
                }
            }

            if (!is_null($name)) {
                $swCategory = $this->findByNameAndLevel($name, ($jtlCategory->getLevel() + 1), $parentId);
            }
        }

        if (is_null($swCategory)) {
            $swCategory = new SwCategory;
        }

        $parentSW = null;
        if (!is_null($parentId)) {
            $parentSW = $this->find((int)$parentId);
        } else {
            $parentSW = $this->findOneBy(array('parent' => null));
        }

        if ($parentSW) {
            $swCategory->setParent($parentSW);
        }

        $swCategory->setActive($jtlCategory->getIsActive());
        $swCategory->setPosition($jtlCategory->getSort());
    }

    protected function prepareI18nAssociatedData(JtlCategory $jtlCategory, SwCategory $swCategory)
    {
        // I18n
        $exists = false;
        foreach ($jtlCategory->getI18ns() as $i18n) {
            if (LanguageUtil::map(null, null, $i18n->getLanguageISO()) === Shopware()->Shop()->getLocale()->getLocale()) {
                $exists = true;

                $swCategory->setName($i18n->getName());
                $swCategory->setMetaDescription($i18n->getMetaDescription());
                $swCategory->setMetaKeywords($i18n->getMetaKeywords());
                $swCategory->setMetaTitle($i18n->getTitleTag());
                $swCategory->setCmsText($i18n->getDescription());

                ShopUtil::entityManager()->persist($swCategory);
                ShopUtil::entityManager()->flush();
            }
        }

        if (!$exists) {
            throw new \Exception(sprintf('Main Shop locale (%s) does not exists in category languages', Shopware()->Shop()->getLocale()->getLocale()));
        }
    }

    /**
     * @param JtlCategory $jtlCategory
     * @param SwCategory $swCategory
     * @param string[] $translations
     * @param string|null $langIso
     * @throws LanguageException
     * @throws ORMException
     */
    protected function prepareAttributeAssociatedData(JtlCategory $jtlCategory, SwCategory $swCategory, array &$translations, $langIso = null)
    {
        if (is_null($langIso)) {
            $langIso = LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale());
        }

        // Attribute
        $swAttribute = $swCategory->getAttribute();
        if ($swAttribute === null) {
            $swAttribute = new \Shopware\Models\Attribute\Category();
            $swAttribute->setCategory($swCategory);

            ShopUtil::entityManager()->persist($swAttribute);
        }

        $attributes = [];
        $mappings = [];
        $categoryAttributes = [];
        foreach ($jtlCategory->getAttributes() as $jtlAttribute) {
            if ($jtlAttribute->getIsCustomProperty()) {
                continue;
            }

            foreach ($jtlAttribute->getI18ns() as $attributeI18n) {
                if ($attributeI18n->getLanguageISO() === $langIso) {

                    if(CategoryAttr::isSpecialAttribute($attributeI18n->getName())){

                        // Active fix
                        $allowedActiveValues = array('0', '1', 0, 1, false, true);
                        if (in_array(strtolower($attributeI18n->getName()), [CategoryAttr::IS_ACTIVE, 'isactive'])
                            && in_array($attributeI18n->getValue(), $allowedActiveValues, true)) {
                            $swCategory->setActive((bool)$attributeI18n->getValue());
                        }

                        // Cms Headline
                        if (in_array(strtolower($attributeI18n->getName()), [CategoryAttr::CMS_HEADLINE, 'cmsheadline'])) {
                            $swCategory->setCmsHeadline($attributeI18n->getValue());
                            foreach ($jtlAttribute->getI18ns() as $i18n) {
                                if ($i18n->getLanguageISO() === $langIso) {
                                    continue;
                                }
                                $translations[$i18n->getLanguageISO()]['category']['cmsheadline'] = $i18n->getValue();
                            }
                        }

                        if ($attributeI18n->getName() === CategoryAttr::IS_BLOG) {
                            $swCategory->setBlog((bool)$attributeI18n->getValue());
                        }

                        if ($attributeI18n->getName() === CategoryAttr::LIMIT_TO_SHOPS) {
                            $swCategory->setShops($attributeI18n->getValue());
                        }

                        continue;
                    }

                    $mappings[$attributeI18n->getName()] = $jtlAttribute->getId()->getHost();
                    $attributes[$attributeI18n->getName()] = $jtlAttribute;

                    $categoryAttributes[$attributeI18n->getName()] = $attributeI18n->getValue();
                }
            }
        }

        /** @deprecated Will be removed in future connector releases $nullUndefinedAttributesOld */
        $nullUndefinedAttributesOld = (bool)Application()->getConfig()->get('null_undefined_category_attributes_during_push', true);
        $nullUndefinedAttributes = (bool)Application()->getConfig()->get('category.push.null_undefined_attributes', $nullUndefinedAttributesOld);

        $swAttributesList = Shopware()->Container()->get('shopware_attribute.crud_service')->getList('s_categories_attributes');

        foreach ($swAttributesList as $tSwAttribute) {
            $result = TranslatableAttributes::setAttribute($tSwAttribute, $swAttribute, $categoryAttributes, $nullUndefinedAttributes);
            if ($result === true) {
                $translations = self::createAttributeTranslations($attributes[$tSwAttribute->getColumnName()], $tSwAttribute->getColumnName(), $translations, [$langIso]);
            }
        }

        ShopUtil::entityManager()->persist($swAttribute);

        $swCategory->setAttribute($swAttribute);
    }

    protected function prepareInvisibilityAssociatedData(JtlCategory $jtlCategory, SwCategory $swCategory)
    {
        // Invisibility
        $customerGroupsSW = new \Doctrine\Common\Collections\ArrayCollection;
        $customerGroupMapper = Mmc::getMapper('CustomerGroup');
        foreach ($jtlCategory->getInvisibilities() as $invisibility) {
            $customerGroupSW = $customerGroupMapper->find($invisibility->getCustomerGroupId()->getEndpoint());
            if ($customerGroupSW) {
                $customerGroupsSW->add($customerGroupSW);
            }
        }

        $swCategory->setCustomerGroups($customerGroupsSW);
    }

    public function prepareCategoryMapping(JtlCategory $jtlCategory, SwCategory $swCategory)
    {
        foreach ($jtlCategory->getI18ns() as $i18n) {
            if (strlen($i18n->getLanguageISO()) > 0 && LanguageUtil::map(null, null, $i18n->getLanguageISO()) !== Shopware()->Shop()->getLocale()->getLocale()) {
                $categoryMappingSW = CategoryMappingUtil::findCategoryMappingByParent($swCategory->getId(), $i18n->getLanguageISO());

                if (is_null($categoryMappingSW)) {
                    $categoryMappingSW = new SwCategory();
                }

                $parentCategorySW = null;
                $parentCategoryMappingSW = CategoryMappingUtil::findCategoryMappingByParent($swCategory->getParent()->getId(), $i18n->getLanguageISO());
                if (!is_null($parentCategoryMappingSW)) {
                    $parentCategorySW = $parentCategoryMappingSW;
                } else {
                    $rootCategorySW = $this->findOneBy(array('parent' => null));
                    $parentCategorySW = $this->find($swCategory->getParent()->getId());
                    if (is_null($parentCategorySW) || $rootCategorySW->getId() != $parentCategorySW->getId()) {
                        continue;
                    }
                }

                $categoryMappingSW->setParent($parentCategorySW);
                $categoryMappingSW->setPosition($jtlCategory->getSort());

                $categoryMappingSW->setName($i18n->getName());
                $categoryMappingSW->setPosition($jtlCategory->getSort());
                $categoryMappingSW->setMetaDescription($i18n->getMetaDescription());
                $categoryMappingSW->setMetaKeywords($i18n->getMetaKeywords());
                $categoryMappingSW->setMetaTitle($i18n->getTitleTag());
                //$categoryMappingSW->setCmsHeadline($i18n->getMetaKeywords());
                $categoryMappingSW->setCmsText($i18n->getDescription());

                $translations = [];
                $this->prepareAttributeAssociatedData($jtlCategory, $categoryMappingSW, $translations, $i18n->getLanguageISO());

                $categoryMappingSW->setCustomerGroups($swCategory->getCustomerGroups());

                ShopUtil::entityManager()->persist($categoryMappingSW);
                ShopUtil::entityManager()->flush($categoryMappingSW);

                CategoryMappingUtil::saveCategoryMapping($swCategory->getId(), $i18n->getLanguageISO(), $categoryMappingSW->getId());
            }
        }
    }

    /**
     * @param JtlCategory $jtlCategory
     * @param int $swCategoryId
     * @param string[] $translations
     * @throws \Zend_Db_Adapter_Exception
     * @throws LanguageException
     */
    protected function saveTranslations(JtlCategory $jtlCategory, $swCategoryId, array $translations)
    {
        $translationService = ShopUtil::translationService();

        foreach ($jtlCategory->getI18ns() as $i18n) {
            $langIso2B = $i18n->getLanguageISO();
            $langIso1 = LanguageUtil::convert(null, $langIso2B);
            if ($langIso2B === LanguageUtil::map(ShopUtil::locale()->getLocale())) {
                continue;
            }

            /** @var \Shopware\Models\Shop\Shop[] $shops */
            $shops = ShopUtil::entityManager()->getRepository(\Shopware\Models\Shop\Shop::class)->findAll();
            foreach ($shops as $shop) {
                if (strpos($shop->getLocale()->getLocale(), $langIso1) !== 0) {
                    continue;
                }

                $categoryTranslation = array_filter([
                    'description' => $i18n->getName(),
                    //'cmsheadline' => $i18n->get,
                    'cmstext' => $i18n->getDescription(),
                    'metatitle' => $i18n->getTitleTag(),
                    'metakeywords' => $i18n->getMetaKeywords(),
                    'metadescription' => $i18n->getMetaDescription()
                ], function ($value) {
                    return !empty($value);
                });

                if (isset($translations[$langIso2B]['category'])) {
                    $categoryTranslation = array_merge($categoryTranslation, $translations[$langIso2B]['category']);
                }

                $translationService->write($shop->getId(), 'category', $swCategoryId, $categoryTranslation);
                if (isset($translations[$langIso2B]['attributes'])) {
                    $attributeTranslations = $translations[$langIso2B]['attributes'];
                    /** @deprecated Will be removed in future connector releases $nullUndefinedAttributesOld */
                    $nullUndefinedAttributesOld = (bool)Application()->getConfig()->get('null_undefined_category_attributes_during_push', true);
                    $nullUndefinedAttributes = (bool)Application()->getConfig()->get('category.push.null_undefined_attributes', $nullUndefinedAttributesOld);

                    $merge = !$nullUndefinedAttributes;
                    if ($merge) {
                        $attributeTranslations = array_merge($translationService->read($shop->getId(), 's_categories_attributes', $swCategoryId), $attributeTranslations);
                    }
                    $translationService->write($shop->getId(), 's_categories_attributes', $swCategoryId, $attributeTranslations);
                }
            }
        }
    }

    /**
     * @param \jtl\Connector\Model\CategoryAttr $jtlAttribute
     * @param $swAttributeName
     * @param array $data
     * @param array $ignoreLanguages
     * @return array
     */
    public static function createAttributeTranslations(\jtl\Connector\Model\CategoryAttr $jtlAttribute, $swAttributeName, array $data = [], array $ignoreLanguages = [])
    {
        foreach ($jtlAttribute->getI18ns() as $i18n) {
            if (in_array($i18n->getLanguageISO(), $ignoreLanguages)) {
                continue;
            }
            $data[$i18n->getLanguageISO()]['attributes']['__attribute_' . $swAttributeName] = $i18n->getValue();
        }

        return $data;
    }
}
