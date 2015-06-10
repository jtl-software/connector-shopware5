<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use Doctrine\ORM\Mapping as ORM;

/**
 * Specific Model
 *
 *
 * @ORM\Table(name="s_filter_options")
 * @ORM\Entity(repositoryClass="Repository")
 * @access public
 */
class Specific extends \Shopware\Models\Property\Option
{
    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\SpecificLinker", mappedBy="specific")
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