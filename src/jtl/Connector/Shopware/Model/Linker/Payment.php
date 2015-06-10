<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Linker;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use jtl\Connector\Model\Identity;

/**
 * Payment Model
 *
 *
 * @ORM\Table(name="jtl_connector_payment")
 * @ORM\Entity
 * @access public
 */
class Payment extends \jtl\Connector\Model\Payment
{
    /**
     * @var Identity
     * @Serializer\Type("jtl\Connector\Model\Identity")
     * @Serializer\SerializedName("customerOrderId")
     * @Serializer\Accessor(getter="getCustomerOrderId",setter="setCustomerOrderId")
     * @ORM\Column(name="customerOrderId", type="integer", nullable=false)
     */
    protected $customerOrderId = null;

    /**
     * @var Identity
     * @Serializer\Type("jtl\Connector\Model\Identity")
     * @Serializer\SerializedName("id")
     * @Serializer\Accessor(getter="getId",setter="setId")
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id = null;

    /**
     * @var string
     * @Serializer\Type("string")
     * @Serializer\SerializedName("billingInfo")
     * @Serializer\Accessor(getter="getBillingInfo",setter="setBillingInfo")
     * @ORM\Column(name="billingInfo", type="string", length=255, nullable=true)
     */
    protected $billingInfo = '';

    /**
     * @var DateTime
     * @Serializer\Type("DateTime")
     * @Serializer\SerializedName("creationDate")
     * @Serializer\Accessor(getter="getCreationDate",setter="setCreationDate")
     * @ORM\Column(name="creationDate", type="datetime", nullable=false)
     */
    protected $creationDate = null;

    /**
     * @var string
     * @Serializer\Type("string")
     * @Serializer\SerializedName("paymentModuleCode")
     * @Serializer\Accessor(getter="getPaymentModuleCode",setter="setPaymentModuleCode")
     * @ORM\Column(name="paymentModuleCode", type="string", length=255, nullable=false)
     */
    protected $paymentModuleCode = '';

    /**
     * @var double
     * @Serializer\Type("double")
     * @Serializer\SerializedName("totalSum")
     * @Serializer\Accessor(getter="getTotalSum",setter="setTotalSum")
     * @ORM\Column(name="totalSum", type="float", nullable=false)
     */
    protected $totalSum = 0.0;

    /**
     * @var string
     * @Serializer\Type("string")
     * @Serializer\SerializedName("transactionId")
     * @Serializer\Accessor(getter="getTransactionId",setter="setTransactionId")
     * @ORM\Column(name="transactionId", type="string", length=255, nullable=true)
     */
    protected $transactionId = '';

    /**
     * @ORM\OneToOne(targetEntity="jtl\Connector\Shopware\Model\Linker\PaymentLinker", mappedBy="payment")
     * @Serializer\Exclude
     **/
    protected $linker;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id = new Identity();
        $this->customerOrderId = new Identity();
    }

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
    protected function setLinker(PaymentLinker $linker)
    {
        $this->linker = $linker;

        return $this;
    }
}