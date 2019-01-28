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
 * @ORM\Table(name="jtl_connector_link_order")
 * @ORM\Entity
 * @access public
 */
class CustomerOrderLinker
{
    /**
     * @var integer
     *
     * @ORM\Column(name="host_id", type="integer", nullable=false)
     */
    protected $hostId;

    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\CustomerOrder", inversedBy="linker")
     * @ORM\JoinColumn(name="order_id", referencedColumnName="id")
     * @ORM\Id
     */
    protected $order;

    /**
     * Gets the value of order.
     *
     * @return string
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Sets the value of order.
     *
     * @param order $order
     *
     * @return self
     */
    public function setOrder(CustomerOrder $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Gets the value of hostId.
     *
     * @return integer
     */
    public function getHostId()
    {
        return $this->hostId;
    }

    /**
     * Sets the value of hostId.
     *
     * @param integer $hostId the host id
     *
     * @return self
     */
    public function setHostId($hostId)
    {
        $this->hostId = $hostId;

        return $this;
    }
}
