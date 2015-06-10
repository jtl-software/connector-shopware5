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
 * @ORM\Table(name="s_articles_details")
 * @ORM\Entity(repositoryClass="Repository")
 * @access public
 */
class Detail extends \Shopware\Models\Article\Detail
{
    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\DetailLinker", mappedBy="detail")
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
     * @param ProductLinker $linker the linker
     *
     * @return self
     */
    protected function setLinker(ProductLinker $linker)
    {
        $this->linker = $linker;

        return $this;
    }
}