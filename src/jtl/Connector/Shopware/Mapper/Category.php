<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Model\Category as CategoryModel;
use \jtl\Connector\Model\Identity;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Connector\Core\Logger\Logger;
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

    public function findAll($limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'category',
                'categoryLevel',
                'attribute',
                'customergroup'
            )
            //->from('Shopware\Models\Category\Category', 'category')
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

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = false);

        //$res = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        //return $count ? count($res) : $res;

        return $count ? ($paginator->count() - 1) : iterator_to_array($paginator);
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function delete(CategoryModel $category)
    {
        $result = new CategoryModel;

        $this->deleteCategoryData($category);

        // Result
        $result->setId(new Identity('', $category->getId()->getHost()));

        return $result;
    }

    public function save(CategoryModel $category)
    {
        $categorySW = null;
        //$result = new CategoryModel;
        $result = $category;

        if ($category->getParentCategoryId() !== null && isset(self::$parentCategoryIds[$category->getParentCategoryId()->getHost()])) {
            $category->getParentCategoryId()->setEndpoint(self::$parentCategoryIds[$category->getParentCategoryId()->getHost()]);
        }

        $this->prepareCategoryAssociatedData($category, $categorySW);
        $this->prepareI18nAssociatedData($category, $categorySW);
        $this->prepareAttributeAssociatedData($category, $categorySW);
        $this->prepareInvisibilityAssociatedData($category, $categorySW);

        $violations = $this->Manager()->validate($categorySW);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        // Save Category
        $this->Manager()->persist($categorySW);
        $this->flush();

        $this->updateCategoryLevelTable();

        if ($categorySW !== null) {
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

    protected function prepareCategoryAssociatedData(CategoryModel $category, CategorySW &$categorySW = null)
    {
        $categoryId = (strlen($category->getId()->getEndpoint()) > 0) ? (int)$category->getId()->getEndpoint() : null;
        $parentId = (strlen($category->getParentCategoryId()->getEndpoint()) > 0) ? $category->getParentCategoryId()->getEndpoint() : null;

        if ($categoryId !== null && $categoryId > 0) {
            $categorySW = $this->find($categoryId);
            if ($categorySW->getLevel() > 0) {
                $parentId = $categorySW->getParent()->getId();
            }
        }

        if ($categorySW === null) {
            $categorySW = new CategorySW;
        }

        $parentSW = null;
        if ($parentId !== null) {
            $parentSW = $this->find($parentId);
        } else {
            $parentSW = $this->findOneBy(array('parent' => null));
        }

        if ($parentSW) {
            $categorySW->setParent($parentSW);
        }

        $categorySW->setPosition(1);
        $categorySW->setNoViewSelect(false);
    }

    protected function prepareI18nAssociatedData(CategoryModel $category, CategorySW &$categorySW)
    {
        //$shopMapper = Mmc::getMapper('Shop');
        //$shops = $shopMapper->findAll(null, null);

        // I18n
        foreach ($category->getI18ns() as $i18n) {
            if (LanguageUtil::map(null, null, $i18n->getLanguageISO()) == Shopware()->Shop()->getLocale()->getLocale()) {
                $categorySW->setName($i18n->getName());
                $categorySW->setMetaDescription($i18n->getMetaDescription());
                $categorySW->setMetaKeywords($i18n->getMetaKeywords());
                $categorySW->setCmsHeadline($i18n->getName());
                $categorySW->setCmsText($i18n->getDescription());
            }
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

        foreach ($category->getAttributes() as $i => $attribute) {
            $i++;
            foreach ($attribute->getI18ns() as $attributeI18n) {
                if ($attributeI18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                    $setter = "setAttribute{$i}";

                    if (method_exists($attributeSW, $setter)) {
                        $attributeSW->{$setter}($attributeI18n->getValue());
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
        $where = 'WHERE parent IS NULL';
        if ($parentIds === null) {
            $parentIds = array();
            Shopware()->Db()->query('TRUNCATE TABLE jtl_connector_category_level');
        } else {
            $where = 'WHERE parent IN (' . implode(',', $parentIds) . ')';
            $parentIds = array();
        }

        $categories = Shopware()->Db()->fetchAssoc('SELECT id FROM s_categories ' . $where);

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
}
