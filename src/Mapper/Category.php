<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */
namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Shopware\Model\CategoryAttr;
use jtl\Connector\Shopware\Utilities\I18n;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Model\Category as JtlCategory;
use jtl\Connector\Model\Identity;
use jtl\Connector\Shopware\Utilities\Str;
use Shopware\Models\Category\Category as SwCategory;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Shopware\Utilities\CategoryMapping as CategoryMappingUtil;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;

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
            'categoryLevel',
            'attribute',
            'customergroup'
        )
            ->from('jtl\Connector\Shopware\Model\Linker\Category', 'category')
            ->leftJoin('category.linker', 'linker')
            ->join('category.categoryLevel', 'categoryLevel')
            //->leftJoin('jtl\Connector\Shopware\Model\ConnectorLink', 'link', \Doctrine\ORM\Query\Expr\Join::WITH, 'category.id = link.endpointId AND link.type = 0')
            ->leftJoin('category.attribute', 'attribute')
            ->leftJoin('category.customerGroups', 'customergroup')
            ->where('linker.hostId IS NULL')
            ->orderBy('categoryLevel.level', 'ASC')
            ->setFirstResult(0)
            ->setMaxResults($limit)
            //->getQuery();
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        //$res = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        //return $count ? count($res) : $res;
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

    public function fetchCountForLevel($level)
    {
        return (int)Shopware()->Db()->fetchOne('SELECT count(*) FROM jtl_connector_category_level WHERE level = ?', array($level));
    }

    public function delete(JtlCategory $category)
    {
        $result = new JtlCategory;

        /** @deprecated Will be removed in a future connector release  $mappingOld */
        $mappingOld = Application()->getConfig()->get('category_mapping', false);
        if (Application()->getConfig()->get('category.mapping', $mappingOld)) {
            $this->deleteCategoryMappingData($category);
        }

        $this->deleteCategoryData($category);

        // Result
        $result->setId(new Identity('', $category->getId()->getHost()));

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

        $this->updateCategoryLevelTable();

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

//        $jtlCategoryI18n = Mmc::getModel('CategoryI18n');
//        $jtlCategoryI18n->setCategoryId($result->getId())
//            ->setLanguageISO(LanguageUtil::map(null, null, Shopware()->Shop()->getLocale()->getLocale()));

//        $result->addI18n($jtlCategoryI18n);

        return $result;
    }

    protected function deleteCategoryData(JtlCategory $category)
    {
        $categoryId = (strlen($category->getId()->getEndpoint()) > 0) ? (int)$category->getId()->getEndpoint() : null;

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

    protected function deleteCategoryMappingData(JtlCategory $category)
    {
        foreach (CategoryMappingUtil::findAllCategoriesByMappingParent($category->getId()->getEndpoint()) as $categorySW) {
            ShopUtil::entityManager()->remove($categorySW);
            ShopUtil::entityManager()->flush($categorySW);
        }
    }

    protected function prepareCategoryAssociatedData(JtlCategory $category, SwCategory &$categorySW = null)
    {
        $categoryId = (strlen($category->getId()->getEndpoint()) > 0) ? (int)$category->getId()->getEndpoint() : null;
        $parentId = (strlen($category->getParentCategoryId()->getEndpoint()) > 0) ? $category->getParentCategoryId()->getEndpoint() : null;

        if ($categoryId !== null && $categoryId > 0) {
            $categorySW = $this->find($categoryId);
            if ($categorySW !== null && $categorySW->getLevel() > 0 && $parentId === null) {
                $parentId = $categorySW->getParent()->getId();
            }
        }

        // Try via name
        if (is_null($categorySW)) {
            $name = null;
            foreach ($category->getI18ns() as $i18n) {
                if (LanguageUtil::map(null, null, $i18n->getLanguageISO()) === Shopware()->Shop()->getLocale()->getLocale()) {
                    $name = $i18n->getName();
                    break;
                }
            }

            if (!is_null($name)) {
                $categorySW = $this->findByNameAndLevel($name, ($category->getLevel() + 1), $parentId);
            }
        }

        if (is_null($categorySW)) {
            $categorySW = new SwCategory;
        }

        $parentSW = null;
        if (!is_null($parentId)) {
            $parentSW = $this->find((int)$parentId);
        } else {
            $parentSW = $this->findOneBy(array('parent' => null));
        }

        if ($parentSW) {
            $categorySW->setParent($parentSW);
        }

        $categorySW->setActive($category->getIsActive());
        $categorySW->setPosition($category->getSort());
    }

    protected function prepareI18nAssociatedData(JtlCategory $category, SwCategory &$categorySW)
    {
        // I18n
        $exists = false;
        foreach ($category->getI18ns() as $i18n) {
            if (LanguageUtil::map(null, null, $i18n->getLanguageISO()) === Shopware()->Shop()->getLocale()->getLocale()) {
                $exists = true;

                $categorySW->setName($i18n->getName());
                $categorySW->setMetaDescription($i18n->getMetaDescription());
                $categorySW->setMetaKeywords($i18n->getMetaKeywords());
                $categorySW->setMetaTitle($i18n->getTitleTag());
                $categorySW->setCmsText($i18n->getDescription());

                ShopUtil::entityManager()->persist($categorySW);
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
     * @throws \jtl\Connector\Core\Exception\LanguageException
     */
    protected function prepareAttributeAssociatedData(JtlCategory $jtlCategory, SwCategory &$swCategory, array &$translations, $langIso = null)
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
        foreach ($jtlCategory->getAttributes() as $jtlAttribute) {
            if ($jtlAttribute->getIsCustomProperty()) {
                continue;
            }

            foreach ($jtlAttribute->getI18ns() as $attributeI18n) {
                if ($attributeI18n->getLanguageISO() === $langIso) {

                    // Active fix
                    $allowedActiveValues = array('0', '1', 0, 1, false, true);
                    if (in_array(strtolower($attributeI18n->getName()), [CategoryAttr::IS_ACTIVE, 'isactive'])
                        && in_array($attributeI18n->getValue(), $allowedActiveValues, true)) {
                        $swCategory->setActive((bool)$attributeI18n->getValue());

                        continue;
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

                        continue;
                    }

                    $mappings[$attributeI18n->getName()] = $jtlAttribute->getId()->getHost();
                    $attributes[$attributeI18n->getName()] = $jtlAttribute;
                }
            }
        }

        /** @deprecated Will be removed in future connector releases $nullUndefinedAttributesOld */
        $nullUndefinedAttributesOld = (bool)Application()->getConfig()->get('null_undefined_category_attributes_during_push', true);
        $nullUndefinedAttributes = (bool)Application()->getConfig()->get('category.push.null_undefined_attributes', $nullUndefinedAttributesOld);

        // Reset
        $used = [];
        $swAttributesList = Shopware()->Container()->get('shopware_attribute.crud_service')->getList('s_categories_attributes');
        foreach ($swAttributesList as $tSwAttribute) {
            if (!$tSwAttribute->isIdentifier()) {
                $setter = sprintf('set%s', ucfirst(Str::camel($tSwAttribute->getColumnName())));
                if (isset($attributes[$tSwAttribute->getColumnName()]) && method_exists($swAttribute, $setter)) {
                    //$swAttribute->{$setter}($attributes[$tSwAttribute->getColumnName()]);
                    $jtlAttrI18n = I18n::find(ShopUtil::locale()->getLocale(), $attributes[$tSwAttribute->getColumnName()]->getI18ns());
                    $swAttribute->{$setter}($jtlAttrI18n->getValue());
                    $used[] = $tSwAttribute->getColumnName();
                    $translations = self::createAttributeTranslations($attributes[$tSwAttribute->getColumnName()], $tSwAttribute->getColumnName(), $translations, [$langIso]);
                    unset($attributes[$tSwAttribute->getColumnName()]);
                } elseif ($nullUndefinedAttributes && method_exists($swAttribute, $setter)) {
                    $swAttribute->{$setter}(null);
                }
            }
        }

        for ($i = 4; $i <= 20; $i++) {
            $attr = "attr{$i}";
            if (in_array($attr, $used) || $i == 17) {
                continue;
            }

            $setter = "setAttribute{$i}";
            if (!method_exists($swAttribute, $setter)) {
                continue;
            }

            $index = null;
            foreach ($attributes as $key => $jtlAttribute) {
                $jtlAttrI18n = I18n::find(ShopUtil::locale()->getLocale(), $jtlAttribute->getI18ns());
                $swAttribute->{$setter}($jtlAttrI18n->getValue());
                $translations = self::createAttributeTranslations($jtlAttribute, 'attribute' . $i, $translations, [$langIso]);
                unset($attributes[$key]);
                break;
            }

            if (count($attributes) == 0) {
                break;
            }
        }

        ShopUtil::entityManager()->persist($swAttribute);

        $swCategory->setAttribute($swAttribute);
    }

    protected function prepareInvisibilityAssociatedData(JtlCategory $category, SwCategory &$categorySW)
    {
        // Invisibility
        $customerGroupsSW = new \Doctrine\Common\Collections\ArrayCollection;
        $customerGroupMapper = Mmc::getMapper('CustomerGroup');
        foreach ($category->getInvisibilities() as $invisibility) {
            $customerGroupSW = $customerGroupMapper->find($invisibility->getCustomerGroupId()->getEndpoint());
            if ($customerGroupSW) {
                $customerGroupsSW->add($customerGroupSW);
            }
        }

        $categorySW->setCustomerGroups($customerGroupsSW);
    }

    public function updateCategoryLevelTable(array $parentIds = null, $level = 0)
    {
        $where = 'WHERE s.parent IS NULL';
        if ($parentIds === null) {
            $parentIds = array();
            Shopware()->Db()->query('TRUNCATE TABLE jtl_connector_category_level');
        } else {
            $where = 'WHERE s.parent IN (' . implode(',', $parentIds) . ')';
            $parentIds = array();
        }

        $categories = Shopware()->Db()->fetchAssoc(
            "SELECT s.id
             FROM s_categories s
             LEFT JOIN jtl_connector_category m ON m.category_id = s.id
             {$where}
                AND m.category_id IS NULL"
        );

        if (count($categories) > 0) {
            foreach ($categories as $category) {
                $parentIds[] = (int)$category['id'];

                $sql = '
                    INSERT IGNORE INTO jtl_connector_category_level
                    (
                        category_id, level
                    )
                    VALUES (?,?)
                ';

                Shopware()->Db()->query($sql, array((int)$category['id'], $level));
            }

            $this->updateCategoryLevelTable($parentIds, $level + 1);
        }
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
     * @throws \jtl\Connector\Core\Exception\LanguageException
     */
    protected function saveTranslations(JtlCategory $jtlCategory, int $swCategoryId, array $translations)
    {
        $transUtil = new \Shopware_Components_Translation();

        foreach ($jtlCategory->getI18ns() as $i18n) {
            $langIso2B = $i18n->getLanguageISO();
            $langIso1 = LanguageUtil::convert(null, $langIso2B);
            if ($langIso2B === LanguageUtil::map(ShopUtil::locale()->getLocale())) {
                continue;
            }

            /** @var \Shopware\Models\Shop\Shop[] $shops */
            $shops = ShopUtil::entityManager()->getRepository(\Shopware\Models\Shop\Shop::class)->findAll();
            foreach ($shops as $shop) {
                if (strpos($shop->getLocale()->getLocale(), $langIso1) === false) {
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

                $transUtil->write($shop->getId(), 'category', $swCategoryId, $categoryTranslation);
                if (isset($translations[$langIso2B]['attributes'])) {
                    $attributeTranslations = $translations[$langIso2B]['attributes'];
                    /** @deprecated Will be removed in future connector releases $nullUndefinedAttributesOld */
                    $nullUndefinedAttributesOld = (bool)Application()->getConfig()->get('null_undefined_category_attributes_during_push', true);
                    $nullUndefinedAttributes = (bool)Application()->getConfig()->get('category.push.null_undefined_attributes', $nullUndefinedAttributesOld);

                    $merge = !$nullUndefinedAttributes;
                    if ($merge) {
                        $attributeTranslations = array_merge($transUtil->read($shop->getId(), 's_categories_attributes', $swCategoryId), $attributeTranslations);
                    }
                    $transUtil->write($shop->getId(), 's_categories_attributes', $swCategoryId, $attributeTranslations);
                }
            }
        }
    }

    /**
     * @param CategoryAttr $jtlAttribute
     * @param $swAttributeName
     * @param array $data
     * @param array $ignoreLanguages
     * @return array
     * @throws \jtl\Connector\Core\Exception\LanguageException
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
