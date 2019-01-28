<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use Doctrine\ORM\Mapping as ORM;

/**
 * UnitI18n Model
 *
 *
 * @ORM\Table(name="jtl_connector_unit_i18n")
 * @ORM\Entity
 * @access public
 */
class UnitI18n
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
     * @var integer $unit_id
     *
     * @ORM\Column(name="unit_id", type="integer", nullable=false)
     */
    protected $unit_id;

    /**
     * @var string $languageIso
     *
     * @ORM\Column(name="languageIso", type="string", length=255, nullable=false)
     */
    protected $languageIso;

    /**
     * @var string $name
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    protected $name;

    /**
     * @var \jtl\Connector\Shopware\Model\Linker\Unit $unit
     *
     * @ORM\ManyToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\Unit", inversedBy="i18ns")
     * @ORM\JoinColumn(name="unit_id", referencedColumnName="id")
     */
    protected $unit;

    /**
     * Set id
     *
     * @param integer
     * @return UnitI18n
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
     * Set languageIso
     *
     * @param string
     * @return UnitI18n
     */
    public function setLanguageIso($languageIso)
    {
        $this->languageIso = $languageIso;
        return $this;
    }

    /**
     * Get languageIso
     *
     * @return string
     */
    public function getLanguageIso()
    {
        return $this->languageIso;
    }

    /**
     * Set name
     *
     * @param string
     * @return UnitI18n
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set unit
     *
     * @param Unit
     * @return UnitI18n
     */
    public function setUnit(Unit $unit)
    {
        $this->unit = $unit;
        return $this;
    }

    /**
     * Get unit
     *
     * @return string
     */
    public function getUnit()
    {
        return $this->unit;
    }
}