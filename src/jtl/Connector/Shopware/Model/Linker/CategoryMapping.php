<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use Doctrine\ORM\Mapping as ORM;
use \Shopware\Models\Category\Category as CategorySW;

/**
 * CategoryMapping Model
 *
 *
 * @ORM\Table(name="jtl_connector_category")
 * @ORM\Entity
 * @access public
 */
class CategoryMapping
{
    /**
     * @var string
     *
     * @ORM\Column(name="lang", type="string", nullable=false)
     */
    protected $lang;

    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\Category", inversedBy="categoryMapping")
     * @ORM\JoinColumn(name="category_id", referencedColumnName="id")
     * @ORM\Id
     */
    protected $category;

    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\Category", inversedBy="categoryMapping")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     * @ORM\Id
     */
    protected $parent;

    /**
     * @return string
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @param string $lang
     */
    public function setLang($lang)
    {
        $this->lang = $lang;
        return $this;
    }

    /**
     * @return Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param CategorySW $category
     */
    public function setCategory(CategorySW $category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param CategorySW $parent
     */
    public function setParent(CategorySW $parent)
    {
        $this->parent = $parent;
        return $this;
    }
}
