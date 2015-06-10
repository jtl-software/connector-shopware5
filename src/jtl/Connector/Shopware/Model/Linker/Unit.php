<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use Doctrine\ORM\Mapping as ORM,
    Doctrine\Common\Collections\ArrayCollection;

/**
 * Unit Model
 *
 *
 * @ORM\Table(name="jtl_connector_unit")
 * @ORM\Entity
 * @access public
 */
class Unit
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var integer $hostId
     *
     * @ORM\Column(name="host_id", type="integer", nullable=false)
     */
    protected $hostId;

    /**
     * @ORM\OneToMany(targetEntity="jtl\Connector\Shopware\Model\Linker\UnitI18n", mappedBy="unit", orphanRemoval=true, cascade={"persist"})
     * @var ArrayCollection
     */
    protected $i18ns;

    /**
     * Class constructor. Initials the array collections.
     */
    public function __construct()
    {
        $this->i18ns = new ArrayCollection();
    }

    /**
     * Set id
     *
     * @param integer
     * @return Unit
     */
    public function setId($id)
    {
        $this->id = (int) $id;
        return $this;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set hostId
     *
     * @param integer
     * @return Unit
     */
    public function setHostId($hostId)
    {
        $this->hostId = (int) $hostId;        
        return $this;
    }

    /**
     * Get hostId
     *
     * @return integer
     */
    public function getHostId()
    {
        return $this->hostId;
    }

    /**
     * Set i18ns
     *
     * @param ArrayCollection
     * @return Unit
     */
    public function setI18ns(ArrayCollection $i18ns)
    {
        $this->i18ns = $i18ns;
        return $this;
    }

    /**
     * Get i18ns
     *
     * @return ArrayCollection
     */
    public function getI18ns()
    {
        return $this->i18ns;
    }
}