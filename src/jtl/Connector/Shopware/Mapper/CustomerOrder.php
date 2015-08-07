<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Payment\PaymentTypes;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Connector\Model\CustomerOrder as CustomerOrderModel;
use \jtl\Connector\Model\CustomerOrderItem;
use \Shopware\Models\Order\Order as OrderSW;
use \Shopware\Models\Order\Detail as OrderDetailSW;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Core\Utilities\Money;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Utilities\Payment as PaymentUtil;
use \jtl\Connector\Shopware\Utilities\Status as StatusUtil;
use \jtl\Connector\Shopware\Utilities\PaymentStatus as PaymentStatusUtil;
use \jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use \jtl\Connector\Shopware\Utilities\Salutation;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;

class CustomerOrder extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Order\Order')->find($id);
    }

    public function findStatus($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Order\Status')->find($id);
    }

    public function findAll($limit = 100, $count = false, $from = null, $until = null)
    {
        $query = $this->Manager()->createQueryBuilder()->select(array(
                'orders',
                'customer',
                'debit',
                'attribute',
                'details',
                'tax',
                'billing',
                'shipping',
                'countryS',
                'countryB',
                'history',
                'payment',
                'dispatch'
            ))
            //->from('Shopware\Models\Order\Order', 'orders')
            //->leftJoin('jtl\Connector\Shopware\Model\ConnectorLink', 'link', \Doctrine\ORM\Query\Expr\Join::WITH, 'orders.id = link.endpointId AND link.type = 21')
            ->from('jtl\Connector\Shopware\Model\Linker\CustomerOrder', 'orders')
            ->leftJoin('orders.linker', 'linker')
            ->leftJoin('orders.customer', 'customer')
            ->leftJoin('customer.debit', 'debit')
            ->leftJoin('orders.attribute', 'attribute')
            ->leftJoin('orders.details', 'details')
            ->leftJoin('details.tax', 'tax')
            ->leftJoin('orders.billing', 'billing')
            ->leftJoin('orders.shipping', 'shipping')
            ->leftJoin('billing.country', 'countryS')
            ->leftJoin('shipping.country', 'countryB')
            ->leftJoin('orders.history', 'history')
            ->leftJoin('orders.payment', 'payment')
            ->leftJoin('orders.dispatch', 'dispatch')
            ->where('linker.hostId IS NULL')
            ->andWhere('orders.status != -1')
            ->orderBy('history.changeDate', 'ASC')
            ->setFirstResult(0)
            ->setMaxResults($limit)
            //->getQuery();
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        //$res = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        //return $count ? count($res) : $res;

        return $count ? ($paginator->count()) : iterator_to_array($paginator);
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function delete(CustomerOrderModel $customerOrder)
    {
        $result = new CustomerOrderModel;

        $this->deleteOrderData($customerOrder);

        // Result
        $result->setId(new Identity('', $customerOrder->getId()->getHost()));

        return $result;
    }

    public function save(CustomerOrderModel $customerOrder)
    {
        $orderSW = null;
        $result = new CustomerOrderModel;

        $this->prepareOrderAssociatedData($customerOrder, $orderSW);
        $this->prepareCustomerAssociatedData($customerOrder, $orderSW);
        $this->prepareCurrencyFactorAssociatedData($customerOrder, $orderSW);
        $this->preparePaymentAssociatedData($customerOrder, $orderSW);
        $this->prepareDispatchAssociatedData($customerOrder, $orderSW);
        $this->prepareLocaleAssociatedData($customerOrder, $orderSW);
        $this->prepareStatusAssociatedData($customerOrder, $orderSW);
        $this->prepareShippingAssociatedData($customerOrder, $orderSW);
        $this->prepareBillingAssociatedData($customerOrder, $orderSW);
        $this->prepareItemsAssociatedData($customerOrder, $orderSW);
        $this->preparePaymentInfoAssociatedData($customerOrder, $orderSW);

        // Save Order
        $this->Manager()->persist($orderSW);
        $this->Manager()->flush();

        // CustomerOrderAttr
        
        $result->setId(new Identity($orderSW->getId(), $customerOrder->getId()->getHost()));

        return $result;
    }

    protected function deleteOrderData(CustomerOrderModel &$customerOrder)
    {
        $orderId = (strlen($customerOrder->getId()->getEndpoint()) > 0) ? (int)$customerOrder->getId()->getEndpoint() : null;

        if ($orderId !== null && $orderId > 0) {
            $orderSW = $this->find((int) $orderId);
            if ($orderSW !== null) {
                $this->removeItems($orderSW);
                $this->removeBilling($orderSW);
                $this->removeShipping($orderSW);

                $this->Manager()->remove($orderSW);
                $this->Manager()->flush();
            }
        }
    }

    protected function removeItems(OrderSW $orderSW)
    {
        foreach ($orderSW->getDetails() as $detailSW) {
            $this->Manager()->remove($detailSW);
        }
    }

    protected function removeBilling(OrderSW $orderSW)
    {
        $this->Manager()->remove($orderSW->getBilling());
    }

    protected function removeShipping(OrderSW $orderSW)
    {
        $this->Manager()->remove($orderSW->getShipping());
    }

    protected function prepareOrderAssociatedData(CustomerOrderModel $customerOrder, OrderSW &$orderSW = null)
    {
        $orderId = (strlen($customerOrder->getId()->getEndpoint()) > 0) ? (int)$customerOrder->getId()->getEndpoint() : null;

        if ($orderId !== null && $orderId > 0) {
            $orderSW = $this->find($orderId);
        } elseif (strlen($customerOrder->getOrderNumber()) > 0) {
            $orderSW = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')->findOneBy(array('number' => $customerOrder->getOrderNumber()));
        }

        if ($orderSW === null) {
            $orderSW = new OrderSW;
        }

        $orderSW->setNumber($customerOrder->getOrderNumber())
            ->setInvoiceAmount(Money::AsGross($customerOrder->getTotalSum(), \jtl\Connector\Shopware\Controller\CustomerOrder::calcShippingVat($customerOrder)))
            ->setInvoiceAmountNet($customerOrder->getTotalSum())
            ->setOrderTime($customerOrder->getCreationDate())
            ->setCustomerComment($customerOrder->getNote())
            ->setNet(0)
            ->setTrackingCode($customerOrder->getTracking())
            ->setCurrency($customerOrder->getCurrencyIso())
            ->setRemoteAddress($customerOrder->getIp())
            ->setTemporaryId('')
            ->setTransactionId('')
            ->setComment('')
            ->setInternalComment('')
            ->setReferer('');

        $ref = new \ReflectionClass($orderSW);

        // shopId
        $prop = $ref->getProperty('shopId');
        $prop->setAccessible(true);
        $prop->setValue($orderSW, Shopware()->Shop()->getId());

        // partnerId
        $prop = $ref->getProperty('partnerId');
        $prop->setAccessible(true);
        $prop->setValue($orderSW, '');

        /*
        ->setHistory()
        ->setAttribute()
        ->setPartner()
        ->setDocuments()
        ->setLanguageSubShop()
        ->setPaymentInstances();
        */
    }

    protected function prepareCustomerAssociatedData(CustomerOrderModel $customerOrder, OrderSW &$orderSW)
    {
        // Customer
        $customerMapper = Mmc::getMapper('Customer');
        $customer = $customerMapper->find($customerOrder->getCustomerId()->getEndpoint());
        if ($customer === null) {
            throw new \Exception(sprintf('Customer with id (%s) not found', $customerOrder->getCustomerId()->getEndpoint()));
        }

        $orderSW->setCustomer($customer);
    }

    protected function prepareCurrencyFactorAssociatedData(CustomerOrderModel $customerOrder, OrderSW &$orderSW)
    {
        // CurrencyFactor
        $currencySW = $this->Manager()->getRepository('Shopware\Models\Shop\Currency')->findOneBy(array('currency' => $customerOrder->getCurrencyIso()));
        if ($currencySW === null) {
            throw new \Exception(sprintf('Currency with iso (%s) not found', $customerOrder->getCurrencyIso()));
        }

        $orderSW->setCurrencyFactor($currencySW->getFactor());
    }

    protected function preparePaymentAssociatedData(CustomerOrderModel $customerOrder, OrderSW &$orderSW)
    {
        // Payment
        $paymentName = PaymentUtil::map($customerOrder->getPaymentModuleCode());
        if ($paymentName === null) {
            throw new \Exception(sprintf('Payment with code (%s) not found', $customerOrder->getPaymentModuleCode()));
        }

        $paymentSW = $this->Manager()->getRepository('Shopware\Models\Payment\Payment')->findOneBy(array('name' => $paymentName));
        if ($paymentSW === null) {
            throw new \Exception(sprintf('Payment with name (%s) not found', $paymentName));
        }

        $orderSW->setPayment($paymentSW);
    }

    protected function prepareDispatchAssociatedData(CustomerOrderModel $customerOrder, OrderSW &$orderSW)
    {
        $dispatchSW = $this->Manager()->getRepository('Shopware\Models\Dispatch\Dispatch')->find($customerOrder->getShippingMethodCode());
        if ($dispatchSW === null) {
            throw new \Exception(sprintf('Dispatch with code (%s) not found', $customerOrder->getShippingMethodCode()));
        }

        $orderSW->setDispatch($dispatchSW);
    }

    protected function prepareLocaleAssociatedData(CustomerOrderModel $customerOrder, OrderSW &$orderSW)
    {
        // Locale
        $localeSW = LocaleUtil::getByKey(LanguageUtil::map(null,null, $customerOrder->getLanguageISO()));
        if ($localeSW === null) {
            throw new \Exception(sprintf('Language Iso with iso (%s) not found', $customerOrder->getLanguageISO()));
        }

        $orderSW->setLanguageIso($localeSW->getId());
    }

    protected function prepareStatusAssociatedData(CustomerOrderModel $customerOrder, OrderSW &$orderSW)
    {
        // Order Status
        $status = StatusUtil::map($customerOrder->getStatus());
        if ($status === null) {
            throw new \Exception(sprintf('Order status with status (%s) not found', $customerOrder->getStatus()));
        }

        $statusSW = $this->Manager()->getRepository('Shopware\Models\Order\Status')->findOneBy(array('id' => $status));
        if ($statusSW === null) {
            throw new \Exception(sprintf('Order status with id (%s) not found', $status));
        }

        // Payment Status
        $paymentStatus = PaymentStatusUtil::map($customerOrder->getPaymentStatus());
        if ($paymentStatus === null) {
            throw new \Exception(sprintf('Payment status with status (%s) not found', $customerOrder->getPaymentStatus()));
        }

        $paymentStatusSW = $this->Manager()->getRepository('Shopware\Models\Order\Status')->findOneBy(array('id' => $paymentStatus));
        if ($paymentStatusSW === null) {
            throw new \Exception(sprintf('Payment status with id (%s) not found', $paymentStatus));
        }

        $orderSW->setPaymentStatus($paymentStatusSW);
        $orderSW->setOrderStatus($statusSW);
    }

    protected function prepareShippingAssociatedData(CustomerOrderModel $customerOrder, OrderSW &$orderSW)
    {
        foreach ($customerOrder->getShippingAddress() as $shippingAddress) {
            $shippingSW = null;
            $id = (strlen($shippingAddress->getId()->getEndpoint()) > 0) ? (int)$shippingAddress->getId()->getEndpoint() : null;

            if (strlen($id) > 0) {
                $shippingSW = $this->Manager()->getRepository('Shopware\Models\Order\Shipping')->find((int)$id);
            }

            if ($shippingSW === null) {
                $shippingSW = new \Shopware\Models\Order\Shipping;
            }

            $countrySW = $this->Manager()->getRepository('Shopware\Models\Country\Country')->findOneBy(array('iso' => $shippingAddress->getCountryIso()));
            if ($countrySW === null) {
                throw new \Exception(sprintf('Country with iso (%s) not found', $shippingAddress->getCountryIso()));
            }

            $shippingSW->setCompany($shippingAddress->getCompany())
                ->setSalutation(Salutation::toEndpoint($shippingAddress->getSalutation()))
                ->setFirstName($shippingAddress->getFirstName())
                ->setLastName($shippingAddress->getLastName())
                ->setStreet($shippingAddress->getStreet())
                ->setZipCode($shippingAddress->getZipCode())
                ->setCity($shippingAddress->getCity());
                //->setAttribute();
            
            $shippingSW->setCountry($countrySW);
            $shippingSW->setOrder($orderSW);
            $shippingSW->setCustomer($orderSW->getCustomer());

            $orderSW->setShipping($shippingSW);
        }
    }

    protected function prepareBillingAssociatedData(CustomerOrderModel $customerOrder, OrderSW &$orderSW)
    {
        foreach ($customerOrder->getBillingAddress() as $billingAddress) {
            $billingSW = null;
            $id = (strlen($billingAddress->getId()->getEndpoint()) > 0) ? (int)$billingAddress->getId()->getEndpoint() : null;

            if (strlen($id) > 0) {
                $billingSW = $this->Manager()->getRepository('Shopware\Models\Order\Billing')->find((int)$id);
            }

            if ($billingSW === null) {
                $billingSW = new \Shopware\Models\Order\Billing;
            }

            $countrySW = $this->Manager()->getRepository('Shopware\Models\Country\Country')->findOneBy(array('iso' => $billingAddress->getCountryIso()));
            if ($countrySW === null) {
                throw new \Exception(sprintf('Country with iso (%s) not found', $billingAddress->getCountryIso()));
            }

            $billingSW->setCompany($billingAddress->getCompany())
                ->setSalutation(Salutation::toEndpoint($billingAddress->getSalutation()))
                ->setFirstName($billingAddress->getFirstName())
                ->setLastName($billingAddress->getLastName())
                ->setStreet($billingAddress->getStreet())
                ->setZipCode($billingAddress->getZipCode())
                ->setCity($billingAddress->getCity())
                ->setCountry($countrySW);
                //->setAttribute();

            $billingSW->setCustomer($orderSW->getCustomer());
            $billingSW->setOrder($orderSW);

            $orderSW->setBilling($billingSW);
        }
    }

    protected function prepareItemsAssociatedData(CustomerOrderModel $customerOrder, OrderSW &$orderSW)
    {
        foreach ($orderSW->getDetails() as $detailSW) {
            $this->Manager()->remove($detailSW);
        }

        $taxFree = 1;
        $invoiceShipping = 0.0;
        $invoiceShippingNet = 0.0;
        $detailsSW = new \Doctrine\Common\Collections\ArrayCollection();
        foreach ($customerOrder->getItems() as $item) {
            switch ($item->getType()) {
                case CustomerOrderItem::TYPE_PRODUCT:
                    $this->prepareItemAssociatedData($item, $orderSW, $detailsSW);
                    break;
                case CustomerOrderItem::TYPE_SHIPPING:
                    $invoiceShipping += ($item->getVat() > 0) ? Money::AsGross($item->getPrice(), $item->getVat()) : $item->getPrice();
                    $invoiceShippingNet += $item->getPrice();
                    break;
            }

            if ($item->getVat() > 0) {
                $taxFree = 0;
            }
        }
        
        $orderSW->setInvoiceShipping($invoiceShipping)
            ->setInvoiceShippingNet($invoiceShippingNet)
            ->setTaxFree($taxFree)
            ->setDetails($detailsSW);
    }

    protected function prepareItemAssociatedData(CustomerOrderModel &$item, OrderSW &$orderSW, \Doctrine\Common\Collections\ArrayCollection &$detailsSW)
    {
        $detailSW = null;

        $id = (strlen($item->getId()->getEndpoint()) > 0) ? (int)$item->getId()->getEndpoint() : null;

        if ($id !== null) {
            $detailSW = $this->Manager()->getRepository('Shopware\Models\Order\Detail')->find($id);
        }

        if ($detailSW === null) {
            $detailSW = new OrderDetailSW;
        }

        $taxRateMapper = Mmc::getMapper('TaxRate');
        $taxRateSW = $taxRateMapper->findOneBy(array('tax' => $item->getVat()));
        if ($taxRateSW === null) {
            throw new \Exception(sprintf('Tax with rate (%s) not found', $item->getVat()));
        }

        $price = ($item->getVat() > 0) ? Money::AsGross($item->getPrice(), $item->getVat()) : $item->getPrice();

        $productId = (strlen($item->getProductId()->getEndpoint()) > 0) ? $item->getProductId()->getEndpoint() : null;
        if ($this->isChild($item)) {
            list($detailId, $articleId) = explode('_', $productId);
            $productId = $articleId;
        }

        $detailSW->setNumber($orderSW->getNumber())
            ->setArticleId((int)$productId)
            ->setPrice($price)
            ->setQuantity($item->getQuantity())
            ->setArticleName($item->getName())
            ->setShipped(0)
            ->setShippedGroup(0)
            //->setReleaseDate()
            ->setMode(0)
            ->setEsdArticle(0)
            ->setReleaseDate(new \DateTime('0000-00-00'));
            //->setConfig();

        $detailSW->setTaxRate($item->getVat());
        $detailSW->setArticleNumber($item->getSku());
        //$detailSW->setAttribute();
        //$detailSW->setEsd();
        $detailSW->setTax($taxRateSW);
        $detailSW->setOrder($orderSW);
        //$detailSW->setStatus(0);
        
        $ref = new \ReflectionClass($detailSW);

        // shopId
        $itemStatus = 0;
        switch ($orderSW->getOrderStatus()->getId()) {
            case CustomerOrderModel::STATUS_PROCESSING:
                $itemStatus = 1;
                break;
            case CustomerOrderModel::STATUS_CANCELLED:
                $itemStatus = 2;
                break;
            case CustomerOrderModel::STATUS_COMPLETED:
                $itemStatus = 3;
                break;
        }

        $prop = $ref->getProperty('statusId');
        $prop->setAccessible(true);
        $prop->setValue($detailSW, $itemStatus);

        $this->Manager()->persist($detailSW);

        $detailsSW->add($detailSW);
    }

    protected function preparePaymentInfoAssociatedData(CustomerOrderModel $customerOrder)
    {
        if ($customerOrder->getPaymentModuleCode() === PaymentTypes::TYPE_DIRECT_DEBIT && $customerOrder->getPaymentInfo() !== null) {
            $customerMapper = Mmc::getMapper('Customer');
            $customer = $customerMapper->find($customerOrder->getCustomerId()->getEndpoint());

            if ($customer !== null) {
                $debitSW = $customer->getDebit();
                if ($debitSW === null) {
                    $debitSW = new \Shopware\Models\Customer\Debit();
                }

                $debitSW->setAccount($customerOrder->getPaymentInfo()->getAccountNumber())
                    ->setBankCode($customerOrder->getPaymentInfo()->getBankCode())
                    ->setBankName($customerOrder->getPaymentInfo()->getBankName())
                    ->setAccountHolder($customerOrder->getPaymentInfo()->getAccountHolder())
                    ->setCustomer($customer);

                $this->Manager()->persist($debitSW);
            }
        }
    }

    public function isChild(CustomerOrderItem &$customerOrderItem)
    {
        return (strlen($customerOrderItem->getProductId()->getEndpoint()) > 0 && strpos($customerOrderItem->getProductId()->getEndpoint(), '_') !== false);
    }
}
