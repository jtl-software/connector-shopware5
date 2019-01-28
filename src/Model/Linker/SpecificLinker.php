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
 * @ORM\Table(name="jtl_connector_link_specific")
 * @ORM\Entity
 * @access public
 */
class SpecificLinker
{
    /**
     * @var integer
     *
     * @ORM\Column(name="host_id", type="integer", nullable=false)
     */
    protected $hostId;

    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\Specific", inversedBy="linker")
     * @ORM\JoinColumn(name="specific_id", referencedColumnName="id")
     * @ORM\Id
     */
    protected $specific;

    /**
     * Gets the value of specific.
     *
     * @return string
     */
    public function getSpecific()
    {
        return $this->specific;
    }

    /**
     * Sets the value of Specific.
     *
     * @param Specific $specific
     *
     * @return self
     */
    public function setSpecific(Specific $specific)
    {
        $this->specific = $specific;

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
