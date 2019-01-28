<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use Doctrine\ORM\Mapping as ORM;

/**
 * CustomerOrder Model
 *
 *
 * @ORM\Table(name="s_order")
 * @ORM\Entity(repositoryClass="Repository")
 * @access public
 */
class CustomerOrder extends \Shopware\Models\Order\Order
{
    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\CustomerOrderLinker", mappedBy="order")
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
     * @param CustomerOrderLinker $linker the linker
     *
     * @return self
     */
    protected function setLinker(CustomerOrderLinker $linker)
    {
        $this->linker = $linker;

        return $this;
    }
}