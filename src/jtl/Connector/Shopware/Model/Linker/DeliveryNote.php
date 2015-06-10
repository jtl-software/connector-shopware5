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
 * @ORM\Table(name="s_order_documents")
 * @ORM\Entity(repositoryClass="Repository")
 * @access public
 */
class DeliveryNote extends \Shopware\Models\Order\Document\Document
{
    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\DeliveryNoteLinker", mappedBy="note")
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
     * @param DeliveryNoteLinker $linker the linker
     *
     * @return self
     */
    protected function setLinker(DeliveryNoteLinker $linker)
    {
        $this->linker = $linker;

        return $this;
    }
}