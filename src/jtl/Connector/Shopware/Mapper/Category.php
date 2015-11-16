<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Shopware\Model\CategoryAttr;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Model\Category as CategoryModel;
use \jtl\Connector\Model\Identity;
use \Shopware\Components\Api\Exception as ApiException;
use \Shopware\Models\Category\Category as CategorySW;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;

class Category extends DataMapper
{
    protected static $parentCategoryIds = array();

    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Category\Category')->findOneBy($kv);
    }

    public function find($id)
    {
        return $this->Manager()->find('Shopware\Models\Category\Category', $id);
    }

    public function findByNameAndLevel($name, $parentId = null)
    {
        $sql = ' AND c.parent IS NULL';
        $params = array($name);
        if ($parentId !== null) {
            $sql = ' AND c.parent = ?';
            $params[] = $parentId;
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

    /**
     * @param integer $parentId
     * @param string $iso
     * @return Shopware\Models\Category\Category
     */
    public function findCategoryMappingByParent($parentId, $iso)
    {
        $categoryId = Shopware()->Db()->fetchOne(
            'SELECT category_id FROM jtl_connector_category WHERE parent_id = ? AND lang = ?',
            array($parentId, $iso)
        );

        return $this->find($categoryId);
    }

    /**
     * @param integer $id
     * @return Shopware\Models\Category\Category
     */
    public function findCategoryMapping($id)
    {
        $categoryId = Shopware()->Db()->fetchOne(
            'SELECT category_id FROM jtl_connector_category WHERE category_id = ?',
            array($id)
        );

        return $this->find($categoryId);
    }

    public function findAllCategoriesByMappingParent($parentId)
    {
        $result = array();

        $categoryIds = Shopware()->Db()->fetchAll(
            'SELECT category_id FROM jtl_connector_category WHERE parent_id = ?',
            array($parentId)
        );

        foreach ($categoryIds as $categoryId) {
            $categorySW = $this->find((int) $categoryId['category_id']);
            if ($categorySW) {
                $result[] = $categorySW;
            }
        }

        return $result;
    }

    /**
     * @param integer $parentId
     * @return jtl\Connector\Shopware\Model\Linker\CategoryMapping[]
     */
    public function findAllCategoryMappingByParent($parentId)
    {
        /*
        return Shopware()->Db()->fetchAssoc(
            'SELECT * FROM jtl_connector_category WHERE parent_id = ?',
            array($parentId)
        );
        */

        $query = $this->Manager()->createQueryBuilder()->select(
            'mapping',
            'category',
            'parent'
        )
            ->from('jtl\Connector\Shopware\Model\Linker\CategoryMapping', 'mapping')
            ->join('mapping.category', 'category')
            ->leftJoin('mapping.parent', 'parent')
            ->where('mapping.parent = :parent')
            ->setParameter('parent', $parentId)
            //->getQuery()->getResult();
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = false);

        return iterator_to_array($paginator);
    }

    public function deleteCategoryMapping($parentId, $iso)
    {
        Shopware()->Db()->delete('jtl_connector_category', array(
            'parent_id = ?' => $parentId,
            'lang = ?' => $iso
        ));
    }

    public function saveCategoryMapping($parentId, $iso, $id)
    {
        $this->deleteCategoryMapping($parentId, $iso);

        $sql = '
            INSERT IGNORE INTO jtl_connector_category
            (
                parent_id, lang, category_id
            )
            VALUES (?,?,?)
        ';

        Shopware()->Db()->query($sql, array($parentId, $iso, $id));
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

        return $count ? ($paginator->count() - 1) : iterator_to_array($paginator);
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function fetchCountForLevel($level)
    {
        return (int) Shopware()->Db()->fetchOne('SELECT count(*) FROM jtl_connector_category_level WHERE level = ?', array($level));
    }

    public function delete(CategoryModel $category)
    {
        $result = new CategoryModel;

        if (Application()->getConfig()->read('category_mapping')) {
            $this->deleteCategoryMappingData($category);
        }

        $this->deleteCategoryData($category);

        // Result
        $result->setId(new Identity('', $category->getId()->getHost()));

        return $result;
    }

    public function save(CategoryModel $category)
    {
        $categorySW = null;
        $result = $category;

        if ($category->getParentCategoryId() !== null && isset(self::$parentCategoryIds[$category->getParentCategoryId()->getHost()])) {
            $category->getParentCategoryId()->setEndpoint(self::$parentCategoryIds[$category->getParentCategoryId()->getHost()]);
        }

        $this->prepareCategoryAssociatedData($category, $categorySW);
        $this->prepareI18nAssociatedData($category, $categorySW);
        $this->prepareAttributeAssociatedData($category, $categorySW);
        $this->prepareInvisibilityAssociatedData($category, $categorySW);

        // Save Category
        $this->Manager()->persist($categorySW);
        $this->flush();

        $this->updateCategoryLevelTable();

        if (Application()->getConfig()->read('category_mapping')) {
            $this->prepareCategoryMapping($category, $categorySW);
        }

        if ($categorySW !== null && $categorySW->getId() > 0) {
            self::$parentCategoryIds[$category->getId()->getHost()] = $categorySW->getId();
        }

        // Result
        $result->setId(new Identity($categorySW->getId(), $category->getId()->getHost()));

        $categoryI18n = Mmc::getModel('CategoryI18n');
        $categoryI18n->setCategoryId($result->getId())
            ->setLanguageISO(LanguageUtil::map(null, null, Shopware()->Shop()->getLocale()->getLocale()));

        $result->addI18n($categoryI18n);

        return $result;
    }

    protected function deleteCategoryData(CategoryModel $category)
    {
        $categoryId = (strlen($category->getId()->getEndpoint()) > 0) ? (int)$category->getId()->getEndpoint() : null;

        if ($categoryId !== null && $categoryId > 0) {
            $categorySW = $this->find((int) $categoryId);

            if ($categorySW !== null && Shopware()->Shop() !== null && Shopware()->Shop()->getCategory() !== null) {
                // if category is a main subshop root category
                if ($categorySW->getId() == Shopware()->Shop()->getCategory()->getId()) {
                    Shopware()->Db()->query('UPDATE s_core_shops SET category_id = NULL WHERE id = ?', array(Shopware()->Shop()->getId()));
                }

                $this->Manager()->remove($categorySW);
                $this->Manager()->flush($categorySW);
            }
        }
    }

    protected function deleteCategoryMappingData(CategoryModel $category)
    {
        foreach ($this->findAllCategoriesByMappingParent($category->getId()->getEndpoint()) as $categorySW) {
            $this->Manager()->remove($categorySW);
            $this->Manager()->flush($categorySW);
        }
    }

    protected function prepareCategoryAssociatedData(CategoryModel $category, CategorySW &$categorySW = null)
    {
        $categoryId = (strlen($category->getId()->getEndpoint()) > 0) ? (int)$category->getId()->getEndpoint() : null;
        $parentId = (strlen($category->getParentCategoryId()->getEndpoint()) > 0) ? $category->getParentCategoryId()->getEndpoint() : null;

        if ($categoryId !== null && $categoryId > 0) {
            $categorySW = $this->find($categoryId);
            if ($categorySW->getLevel() > 0 && $parentId === null) {
                $parentId = $categorySW->getParent()->getId();
            }
        }

        // Try via name
        if ($categorySW === null) {
            $name = null;
            foreach ($category->getI18ns() as $i18n) {
                if (LanguageUtil::map(null, null, $i18n->getLanguageISO()) === Shopware()->Shop()->getLocale()->getLocale()) {
                    $name = $i18n->getName();
                    break;
                }
            }

            if ($name !== null) {
                $categorySW = $this->findByNameAndLevel($name, $parentId);
            }
        }

        if ($categorySW === null) {
            $categorySW = new CategorySW;
        }

        $parentSW = null;
        if ($parentId !== null) {
            $parentSW = $this->find((int) $parentId);
        } else {
            $parentSW = $this->findOneBy(array('parent' => null));
        }

        if ($parentSW) {
            $categorySW->setParent($parentSW);
        }

        $categorySW->setActive($category->getIsActive());
        $categorySW->setPosition($category->getSort());
        $categorySW->setNoViewSelect(false);
    }

    protected function prepareI18nAssociatedData(CategoryModel $category, CategorySW &$categorySW)
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

                $this->Manager()->persist($categorySW);
                $this->Manager()->flush();
            }
        }

        if (!$exists) {
            throw new \Exception(sprintf('Main Shop locale (%s) does not exists in category languages', Shopware()->Shop()->getLocale()->getLocale()));
        }
    }

    protected function prepareAttributeAssociatedData(CategoryModel $category, CategorySW &$categorySW)
    {
        $attributeSW = $categorySW->getAttribute();
        if ($attributeSW === null) {
            $attributeSW = new \Shopware\Models\Attribute\Category();
            $attributeSW->setCategory($categorySW);

            $this->Manager()->persist($attributeSW);
        }

        $i = 0;
        foreach ($category->getAttributes() as $attribute) {
            if (!$attribute->getIsCustomProperty()) {
                $i++;
                foreach ($attribute->getI18ns() as $attributeI18n) {

                    // Active fix
                    $allowedActiveValues = array('0', '1', 0, 1, false, true);
                    if (strtolower($attributeI18n->getName()) === strtolower(CategoryAttr::IS_ACTIVE)
                        && in_array($attributeI18n->getValue(), $allowedActiveValues, true)) {
                        $categorySW->setActive((bool) $attributeI18n->getValue());
                    }

                    if ($attributeI18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                        $setter = "setAttribute{$i}";

                        if (method_exists($attributeSW, $setter)) {
                            $attributeSW->{$setter}($attributeI18n->getValue());
                        }

                        // Cms Headline
                        if (strtolower($attributeI18n->getName()) === strtolower(CategoryAttr::CMS_HEADLINE)) {
                            $categorySW->setCmsHeadline($attributeI18n->getValue());
                        }
                    }
                }
            }
        }

        $categorySW->setAttribute($attributeSW);
    }

    protected function prepareInvisibilityAssociatedData(CategoryModel $category, CategorySW &$categorySW)
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

    public function prepareCategoryMapping(CategoryModel $category, CategorySW $categorySW)
    {
        foreach ($category->getI18ns() as $i18n) {
            if (strlen($i18n->getLanguageISO()) > 0 && LanguageUtil::map(null, null, $i18n->getLanguageISO()) !== Shopware()->Shop()->getLocale()->getLocale()) {
                $categoryMappingSW = $this->findCategoryMappingByParent($categorySW->getId(), $i18n->getLanguageISO());

                if ($categoryMappingSW === null) {
                    $categoryMappingSW = new CategorySW();

                    //$parentCategorySW = $categorySW->getParent();
                    $parentCategorySW = null;
                    $parentCategoryMappingSW = $this->findCategoryMappingByParent($categorySW->getParent()->getId(), $i18n->getLanguageISO());
                    if ($parentCategoryMappingSW !== null) {
                        $parentCategorySW = $parentCategoryMappingSW;
                    } else {
                        $parentCategorySW = $this->findOneBy(array('parent' => null));
                    }

                    $categoryMappingSW->setParent($parentCategorySW);
                    $categoryMappingSW->setPosition($category->getSort());
                    $categoryMappingSW->setNoViewSelect(false);
                }

                $categoryMappingSW->setName($i18n->getName());
                $categoryMappingSW->setPosition($category->getSort());
                $categoryMappingSW->setMetaDescription($i18n->getMetaDescription());
                $categoryMappingSW->setMetaKeywords($i18n->getMetaKeywords());
                //$categoryMappingSW->setCmsHeadline($i18n->getMetaKeywords());
                //$categoryMappingSW->setCmsText($i18n->getDescription());

                $this->prepareAttributeAssociatedData($category, $categoryMappingSW);
                $categoryMappingSW->setCustomerGroups($categorySW->getCustomerGroups());

                $this->Manager()->persist($categoryMappingSW);
                $this->Manager()->flush($categoryMappingSW);

                $this->saveCategoryMapping($categorySW->getId(), $i18n->getLanguageISO(), $categoryMappingSW->getId());
            }
        }
    }
}
