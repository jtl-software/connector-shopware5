<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use Doctrine\ORM\Mapping as ORM;

/**
 * Customer Model
 *
 *
 * @ORM\Table(name="s_user")
 * @ORM\Entity(repositoryClass="Repository")
 * @access public
 */
class Customer extends \Shopware\Models\Customer\Customer
{
    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\CustomerLinker", mappedBy="customer")
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
     * @param CustomerLinker $linker the linker
     *
     * @return self
     */
    protected function setLinker(CustomerLinker $linker)
    {
        $this->linker = $linker;

        return $this;
    }
}