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
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\CategoryMapping", mappedBy="category")
     **/
    protected $categoryMapping;

    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\CategoryLinker", mappedBy="category")
     **/
    protected $linker;


    /**
     * Gets the value of categoryMapping.
     *
     * @return CategoryMapping
     */
    public function getCategoryMapping()
    {
        return $this->categoryMapping;
    }

    /**
     * Sets the value of $categoryMapping.
     *
     * @param CategoryMapping $categoryMapping
     * @return self
     */
    public function setCategoryMapping(CategoryMapping $categoryMapping)
    {
        $this->categoryMapping = $categoryMapping;
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
