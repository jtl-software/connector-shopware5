<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use Doctrine\ORM\Mapping as ORM;

/**
 * Image Model
 *
 *
 * @ORM\Table(name="s_media")
 * @ORM\Entity(repositoryClass="Repository")
 * @access public
 */
class Image extends \Shopware\Models\Media\Media
{
    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\ImageLinker", mappedBy="image")
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
     * @param SpecificLinker $linker the linker
     *
     * @return self
     */
    protected function setLinker(SpecificLinker $linker)
    {
        $this->linker = $linker;

        return $this;
    }
}