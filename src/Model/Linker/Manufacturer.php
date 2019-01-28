<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use Doctrine\ORM\Mapping as ORM;

/**
 * Manufacturer Model
 *
 *
 * @ORM\Table(name="s_articles_supplier")
 * @ORM\Entity(repositoryClass="Repository")
 * @access public
 */
class Manufacturer extends \Shopware\Models\Article\Supplier
{
    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\ManufacturerLinker", mappedBy="manufacturer")
     **/
    protected $linker;

    /**
     * Gets the value of linker.
     *
     * @return mixed
     */
    public function getLinker()
    {
        return $this->linker;
    }

    /**
     * Sets the value of linker.
     *
     * @param ManufacturerLinker $linker the linker
     *
     * @return self
     */
    protected function setLinker(ManufacturerLinker $linker)
    {
        $this->linker = $linker;

        return $this;
    }
}