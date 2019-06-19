<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Shopware\Model\CategoryAttr;
use jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Model\Category as JtlCategory;
use jtl\Connector\Model\Identity;
use jtl\Connector\Shopware\Utilities\Str;
use Shopware\Models\Category\Category as SwCategory;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Shopware\Utilities\CategoryMapping as CategoryMappingUtil;
use jtl\Connector\Shopware\Utilities\Translation as TranslationUtil;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;

class Category extends DataMapper
{
    protected static $parentCategoryIds = array();

    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Category\Category')->findOneBy($kv);
    }

    public function find($id)
    {
        return (intval($id) == 0) ? null : $this->Manager()->find('Shopware\Models\Category\Category', $id);
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

            if ((int) $id > 0) {
                $sql = ' AND c.parent = ' . intval($id);
            }
        }

        $id = Shopware()->Db()->fetchOne(
            'SELECT c.id
              FROM s_categories c
              WHERE c.description = ?' . $sql,
            $params
        );

        if ($id !== null && (int) $id > 0) {
            return $this->find($id);
        }

        return null;
    }

    public function findAll($limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
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
        if($count) {
            return ($paginator->count() - 1);
        }

        $categories = iterator_to_array($paginator);

        $shopMapper = Mmc::getMapper('Shop');
        $shops = $shopMapper->findAll(null, null);

        $translationUtil = new TranslationUtil();
        for ($i = 0; $i < count($categories); $i++) {
            foreach ($shops as $shop) {
                $translation = $translationUtil->read($shop['id'], 's_categories_attributes', $categories[$i]['id']);
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
        return (int) Shopware()->Db()->fetchOne('SELECT count(*) FROM jtl_connector_category_level WHERE level = ?', array($level));
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

        if ($jtlCategory->getParentCategoryId() !== null && isset(self::$parentCategoryIds[$jtlCategory->getParentCategoryId()->getHost()])) {
            $jtlCategory->getParentCategoryId()->setEndpoint(self::$parentCategoryIds[$jtlCategory->getParentCategoryId()->getHost()]);
        }

        $this->prepareCategoryAssociatedData($jtlCategory, $swCategory);
        $this->prepareI18nAssociatedData($jtlCategory, $swCategory);
        $this->prepareAttributeAssociatedData($jtlCategory, $swCategory);
        $this->prepareInvisibilityAssociatedData($jtlCategory, $swCategory);

        // Save Category
        $this->Manager()->persist($swCategory);
        ShopUtil::entityManager()->persist($swCategory);
        ShopUtil::entityManager()->flush();

        if(version_compare(ShopUtil::version(), '5.5', '>=')) {
            $this->saveCategoryTranslations($jtlCategory, $swCategory->getId());
        }

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
            $categorySW = $this->find((int) $categoryId);

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
            $parentSW = $this->find((int) $parentId);
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
    
    protected function prepareAttributeAssociatedData(JtlCategory $category, SwCategory &$categorySW, $iso = null)
    {
        if (is_null($iso)) {
            $iso = LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale());
        }
        
        // Attribute
        $attributeSW = $categorySW->getAttribute();
        if ($attributeSW === null) {
            $attributeSW = new \Shopware\Models\Attribute\Category();
            $attributeSW->setCategory($categorySW);
        
            ShopUtil::entityManager()->persist($attributeSW);
        }
        
        $attributes = [];
        $mappings = [];
        foreach ($category->getAttributes() as $attribute) {
            if ($attribute->getIsCustomProperty()) {
                continue;
            }
            
            foreach ($attribute->getI18ns() as $attributeI18n) {
                if ($attributeI18n->getLanguageISO() === $iso) {
    
                    // Active fix
                    $allowedActiveValues = array('0', '1', 0, 1, false, true);
                    if (strtolower($attributeI18n->getName()) === strtolower(CategoryAttr::IS_ACTIVE)
                        && in_array($attributeI18n->getValue(), $allowedActiveValues, true)) {
                        $categorySW->setActive((bool) $attributeI18n->getValue());
                        
                        continue;
                    }
    
                    // Cms Headline
                    if (strtolower($attributeI18n->getName()) === strtolower(CategoryAttr::CMS_HEADLINE)) {
                        $categorySW->setCmsHeadline($attributeI18n->getValue());
    
                        continue;
                    }
                    
                    $mappings[$attributeI18n->getName()] = $attribute->getId()->getHost();
                    $attributes[$attributeI18n->getName()] = $attributeI18n->getValue();
                }
            }
        }

        /** @deprecated Will be removed in future connector releases $nullUndefinedAttributesOld */
        $nullUndefinedAttributesOld = (bool)Application()->getConfig()->get('null_undefined_category_attributes_during_push', true);
        $nullUndefinedAttributes = (bool)Application()->getConfig()->get('category.push.null_undefined_attributes', $nullUndefinedAttributesOld);

        // Reset
        $used = [];
        $sw_attributes = Shopware()->Container()->get('shopware_attribute.crud_service')->getList('s_categories_attributes');
        foreach ($sw_attributes as $sw_attribute) {
            if (!$sw_attribute->isIdentifier()) {
                $setter = sprintf('set%s', ucfirst(Str::camel($sw_attribute->getColumnName())));
                if (isset($attributes[$sw_attribute->getColumnName()]) && method_exists($attributeSW, $setter)) {
                    $attributeSW->{$setter}($attributes[$sw_attribute->getColumnName()]);
                    $used[] = $sw_attribute->getColumnName();
                    unset($attributes[$sw_attribute->getColumnName()]);
                } else if ($nullUndefinedAttributes && method_exists($attributeSW, $setter)) {
                    $attributeSW->{$setter}(null);
                }
            }
        }
        
        for ($i = 4; $i <= 20; $i++) {
            $attr = "attr{$i}";
            if (in_array($attr, $used) || $i == 17) {
                continue;
            }
            
            $setter = "setAttribute{$i}";
            if (!method_exists($attributeSW, $setter)) {
                continue;
            }
            
            $index = null;
            foreach ($attributes as $key => $value) {
                $attributeSW->{$setter}($value);
                unset($attributes[$key]);
                break;
            }
            
            if (count($attributes) == 0) {
                break;
            }
        }
        
        ShopUtil::entityManager()->persist($attributeSW);
    
        $categorySW->setAttribute($attributeSW);
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
                $parentIds[] = (int) $category['id'];

                $sql = '
                    INSERT IGNORE INTO jtl_connector_category_level
                    (
                        category_id, level
                    )
                    VALUES (?,?)
                ';

                Shopware()->Db()->query($sql, array((int) $category['id'], $level));
            }

            $this->updateCategoryLevelTable($parentIds, $level + 1);
        }
    }

    public function prepareCategoryMapping(JtlCategory $category, SwCategory $categorySW)
    {
        foreach ($category->getI18ns() as $i18n) {
            if (strlen($i18n->getLanguageISO()) > 0 && LanguageUtil::map(null, null, $i18n->getLanguageISO()) !== Shopware()->Shop()->getLocale()->getLocale()) {
                $categoryMappingSW = CategoryMappingUtil::findCategoryMappingByParent($categorySW->getId(), $i18n->getLanguageISO());
                
                if (is_null($categoryMappingSW)) {
                    $categoryMappingSW = new SwCategory();
                }
    
                $parentCategorySW = null;
                $parentCategoryMappingSW = CategoryMappingUtil::findCategoryMappingByParent($categorySW->getParent()->getId(), $i18n->getLanguageISO());
                if (!is_null($parentCategoryMappingSW)) {
                    $parentCategorySW = $parentCategoryMappingSW;
                } else {
                    $rootCategorySW = $this->findOneBy(array('parent' => null));
                    $parentCategorySW = $this->find($categorySW->getParent()->getId());
                    if (is_null($parentCategorySW) || $rootCategorySW->getId() != $parentCategorySW->getId()) {
                        continue;
                    }
                }

                $categoryMappingSW->setParent($parentCategorySW);
                $categoryMappingSW->setPosition($category->getSort());

                $categoryMappingSW->setName($i18n->getName());
                $categoryMappingSW->setPosition($category->getSort());
                $categoryMappingSW->setMetaDescription($i18n->getMetaDescription());
                $categoryMappingSW->setMetaKeywords($i18n->getMetaKeywords());
                $categoryMappingSW->setMetaTitle($i18n->getTitleTag());
                //$categoryMappingSW->setCmsHeadline($i18n->getMetaKeywords());
                $categoryMappingSW->setCmsText($i18n->getDescription());

                $this->prepareAttributeAssociatedData($category, $categoryMappingSW, $i18n->getLanguageISO());
                
                $categoryMappingSW->setCustomerGroups($categorySW->getCustomerGroups());

                ShopUtil::entityManager()->persist($categoryMappingSW);
                ShopUtil::entityManager()->flush($categoryMappingSW);

                CategoryMappingUtil::saveCategoryMapping($categorySW->getId(), $i18n->getLanguageISO(), $categoryMappingSW->getId());
            }
        }
    }

    /**
     * @param JtlProduct $jtlCategory
     * @param int $swCategoryId
     * @return string[]
     * @throws \jtl\Connector\Core\Exception\LanguageException
     */
    protected function saveCategoryTranslations(JtlCategory $jtlCategory, int $swCategoryId)
    {
        /** @var \jtl\Connector\Shopware\Mapper\Shop $shopMapper */
        $shopMapper = Mmc::getMapper('Shop');
        $transUtil = new \Shopware_Components_Translation();

        $data = [];
        foreach($jtlCategory->getI18ns() as $i18n) {
            $langIso2B = $i18n->getLanguageISO();
            $langIso1 = LanguageUtil::convert(null, $langIso2B);

            if ($langIso2B === LanguageUtil::map(ShopUtil::locale()->getLocale())) {
                continue;
            }

            /** @var \Shopware\Models\Shop\Shop[] $shops */
            $shops = ShopUtil::entityManager()->getRepository(\Shopware\Models\Shop\Shop::class)->findAll();
            foreach($shops as $shop) {
                if(strpos($shop->getLocale()->getLocale(), $langIso1) === false) {
                    continue;
                }

                $translation = array_filter([
                    'description' => $i18n->getName(),
                    //'cmsheadline' => $i18n->get,
                    'cmstext' => $i18n->getDescription(),
                    'metatitle' => $i18n->getTitleTag(),
                    'metakeywords' => $i18n->getMetaKeywords(),
                    'metadescription' => $i18n->getMetaDescription()
                ],
                    function ($value) {
                        return !empty($value);
                    }
                );

                $transUtil->write($shop->getId(), 'category', $swCategoryId, $translation);
            }
        }
    }
}
