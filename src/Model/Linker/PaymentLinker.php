<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use Doctrine\ORM\Mapping as ORM;

/**
 * PaymentLinker Model
 *
 *
 * @ORM\Table(name="jtl_connector_link_payment")
 * @ORM\Entity
 * @access public
 */
class PaymentLinker
{
    /**
     * @var integer
     *
     * @ORM\Column(name="host_id", type="integer", nullable=false)
     */
    protected $hostId;

    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\Payment", inversedBy="linker")
     * @ORM\JoinColumn(name="payment_id", referencedColumnName="id")
     * @ORM\Id
     */
    protected $payment;

    /**
     * Gets the value of payment.
     *
     * @return string
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * Sets the value of payment.
     *
     * @param Payment $payment
     *
     * @return self
     */
    public function setPayment(Payment $payment)
    {
        $this->payment = $payment;

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