<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use Doctrine\ORM\Mapping as ORM;

/**
 * DeliveryNote Model
 *
 *
 * @ORM\Table(name="jtl_connector_link_note")
 * @ORM\Entity
 * @access public
 */
class DeliveryNoteLinker
{
    /**
     * @var integer
     *
     * @ORM\Column(name="host_id", type="integer", nullable=false)
     */
    protected $hostId;

    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\DeliveryNote", inversedBy="linker")
     * @ORM\JoinColumn(name="note_id", referencedColumnName="id")
     * @ORM\Id
     */
    protected $note;

    /**
     * Gets the value of note.
     *
     * @return string
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * Sets the value of note.
     *
     * @param note $note
     *
     * @return self
     */
    public function setNote(DeliveryNote $note)
    {
        $this->note = $note;

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
