<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use Shopware\Models\Category\Category as CategorySW;

class CategoryMapping
{
    /**
     * @param string $id
     * @return CategorySW|null
     */
    public static function find($id)
    {
        return (intval($id) == 0) ? null : Shopware()->Models()->find('Shopware\Models\Category\Category', $id);
    }
    
    /**
     * @param integer $parentId
     * @param string $iso
     * @return CategorySW
     */
    public static function findCategoryMappingByParent($parentId, $iso)
    {
        $categoryId = Shopware()->Db()->fetchOne(
            'SELECT category_id FROM jtl_connector_category WHERE parent_id = ? AND lang = ?',
            array($parentId, $iso)
        );
        
        return self::find($categoryId);
    }
    
    /**
     * @param integer $id
     * @return CategorySW
     */
    public static function findCategoryMapping($id)
    {
        $categoryId = Shopware()->Db()->fetchOne(
            'SELECT category_id FROM jtl_connector_category WHERE category_id = ?',
            array($id)
        );
        
        return self::find($categoryId);
    }
    
    /**
     * @param integer $parentId
     * @return CategorySW[]
     */
    public static function findAllCategoriesByMappingParent($parentId)
    {
        $result = array();
        
        $categoryIds = Shopware()->Db()->fetchAll(
            'SELECT category_id FROM jtl_connector_category WHERE parent_id = ?',
            array($parentId)
        );
        
        foreach ($categoryIds as $categoryId) {
            $categorySW = self::find((int) $categoryId['category_id']);
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
    public static function findAllCategoryMappingByParent($parentId)
    {
        /*
        return Shopware()->Db()->fetchAssoc(
            'SELECT * FROM jtl_connector_category WHERE parent_id = ?',
            array($parentId)
        );
        */
        
        $query = Shopware()->Models()->createQueryBuilder()->select(
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
    
    /**
     * @param integer $parentId
     * @param string $iso
     */
    public static function deleteCategoryMapping($parentId, $iso)
    {
        Shopware()->Db()->delete('jtl_connector_category', array(
            'parent_id = ?' => $parentId,
            'lang = ?' => $iso
        ));
    }
    
    /**
     * @param integer $parentId
     * @param string $iso
     * @param integer $id
     */
    public static function saveCategoryMapping($parentId, $iso, $id)
    {
        self::deleteCategoryMapping($parentId, $iso);
        
        $sql = '
            INSERT IGNORE INTO jtl_connector_category
            (
                parent_id, lang, category_id
            )
            VALUES (?,?,?)
        ';
        
        Shopware()->Db()->query($sql, array($parentId, $iso, $id));
    }
}
