<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use Doctrine\ORM\Mapping as ORM;

/**
 * Category Model
 *
 *
 * @ORM\Table(name="s_categories")
 * @ORM\Entity(repositoryClass="Repository")
 * @access public
 */
class Category extends \Shopware\Models\Category\Category
{
    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\CategoryLevel", mappedBy="category")
     **/
    protected $categoryLevel;

    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\CategoryLinker", mappedBy="category")
     **/
    protected $linker;

    /**
     * Gets the value of categoryLevel.
     *
     * @return CategoryLevel
     */
    public function getCategoryLevel()
    {
        return $this->categoryLevel;
    }

    /**
     * Sets the value of catLevel.
     *
     * @param CategoryLevel $catLevel
     * @return self
     */
    public function SetCategoryLevel(CategoryLevel $categoryLevel)
    {
        $this->categoryLevel = $categoryLevel;
        return $this;
    }

    /**
     * Gets the value of linker.
     *
     * @return jtl\Connector\Shopware\Model\Linker\CategoryLinker
     */
    public function getLinker()
    {
        return $this->linker;
    }

    /**
     * Sets the value of linker.
     *
     * @param CategoryLinker $linker the linker
     * @return self
     */
    protected function setLinker(CategoryLinker $linker)
    {
        $this->linker = $linker;

        return $this;
    }
}