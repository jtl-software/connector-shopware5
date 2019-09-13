<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */
namespace jtl\Connector\Shopware\Controller;

use jtl\Connector\Core\Utilities\Money;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Model\Identity;
use jtl\Connector\Payment\PaymentTypes;
use jtl\Connector\Result\Action;
use jtl\Connector\Shopware\Model\CustomerOrder as CustomerOrderModel;
use jtl\Connector\Shopware\Model\CustomerOrderItem;
use jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Shopware\Utilities\Payment as PaymentUtil;
use jtl\Connector\Shopware\Utilities\PaymentStatus as PaymentStatusUtil;
use jtl\Connector\Shopware\Utilities\Salutation;
use jtl\Connector\Shopware\Utilities\Status as StatusUtil;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Core\Rpc\Error;
use jtl\Connector\Core\Utilities\DataConverter;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Shopware\Utilities\IdConcatenator;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status;

/**
 * CustomerOrder Controller
 *
 * @access public
 */
class CustomerOrder extends DataController
{
    /**
     * Pull
     *
     * @param \jtl\Connector\Core\Model\QueryFilter $queryFilter
     * @return \jtl\Connector\Result\Action
     */
    public function pull(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $result = [];
            $limit = $queryFilter->isLimit() ? $queryFilter->getLimit() : 100;
            
            $shopMapper = Mmc::getMapper('Shop');
            $mapper = Mmc::getMapper('CustomerOrder');
            $productMapper = Mmc::getMapper('Product');
            $orders = $mapper->findAll($limit);
            
            // Check if PayPal Plus invoice is installed
            $usePPPInvoice = PaymentUtil::usePPPInvoice();
            
            // Check if PayPal Plus installment is installed
            $usePPPInstallment = PaymentUtil::usePPPInstallment();
            
            // Check if Heidelpay invoice is installed
            $useHeidelpayInvoice = PaymentUtil::useHeidelpayInvoice();
            
            // Check if PayPal Unified is installed
            $usePaypalUnified = PaymentUtil::usePaypalUnified();
            
            foreach ($orders as $orderSW) {
                try {

                    // CustomerOrders
                    /** @var \jtl\Connector\Shopware\Model\CustomerOrder $order */
                    $order = Mmc::getModel('CustomerOrder');
                    $order->map(true, DataConverter::toObject($orderSW, true));

                    /** @var Order $swOrderObj */
                    $swOrderObj = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')
                        ->findOneById($order->getId()->getEndpoint());

                    // PaymentModuleCode
                    $paymentModuleCode = PaymentUtil::map(null, $orderSW['payment']['name']);
                    $paymentModuleCode = ($paymentModuleCode !== null) ? $paymentModuleCode : $orderSW['payment']['name'];
                    $order->setPaymentModuleCode($paymentModuleCode);
                    
                    // Billsafe
                    $this->addBillsafe($paymentModuleCode, $orderSW, $order);
                    
                    // Paypal Plus invoice
                    if ($usePPPInvoice) {
                        $this->addPayPalPlusInvoice($paymentModuleCode, $orderSW, $order);
                    }
                    
                    // Paypal Plus installment
                    if ($usePPPInstallment) {
                        $this->addPayPalPlusInstallment($paymentModuleCode, $orderSW, $order);
                    }
                    
                    // Paypal Unified
                    if ($usePaypalUnified) {
                        $this->addPayPalUnified($paymentModuleCode, $orderSW, $order);
                    }
                    
                    // Heidelpay invoice
                    if ($useHeidelpayInvoice) {
                        $this->addHeidelpayInvoice($paymentModuleCode, $orderSW, $order);
                    }
                    
                    // CustomerOrderStatus
                    $customerOrderStatus = StatusUtil::map(null, $orderSW['status']);
                    if ($customerOrderStatus !== null) {
                        $order->setStatus($customerOrderStatus);
                    }
                    
                    // PaymentStatus
                    $paymentStatus = PaymentStatusUtil::map(null, $orderSW['cleared']);
                    if ($paymentStatus !== null) {
                        $order->setPaymentStatus($paymentStatus);
                    }
                    
                    // Locale
                    $shop = $shopMapper->find((int)$orderSW['languageIso']);
                    //$localeSW = LocaleUtil::get((int) $orderSW['languageIso']);
                    //if ($localeSW !== null) {
                    if ($shop !== null) {
                        //$order->setLanguageISO(LanguageUtil::map($localeSW->getLocale()));
                        $order->setLanguageISO(LanguageUtil::map($shop->getLocale()->getLocale()));
                    }
                    
                    foreach ($orderSW['details'] as $detailSW) {
                        
                        // Tax Free
                        if ((int)$orderSW['taxFree'] == 1) {
                            $detailSW['taxRate'] = 0.0;
                        }
                        
                        switch ((int)$orderSW['net']) {
                            case 0: // price is gross
                                $detailSW['priceGross'] = $detailSW['price'];
                                $detailSW['price'] = Money::AsNet($detailSW['price'], $detailSW['taxRate']);
                                break;
                            case 1: // price is net
                                $detailSW['priceGross'] = round(Money::AsGross($detailSW['price'],
                                    $detailSW['taxRate']), 4);
                                break;
                        }
                        
                        // Type (mode)
                        switch ((int)$detailSW['mode']) {
                            case 0:
                                $detailSW['type'] = CustomerOrderItem::TYPE_PRODUCT;
                                break;
                            case 2:
                                $detailSW['type'] = CustomerOrderItem::TYPE_COUPON;
                                break;
                            /*
                            case 3:
                                $detailSW['type'] = CustomerOrderItem::TYPE_DISCOUNT;
                                break;
                            */
                            case 4:
                                $detailSW['type'] = CustomerOrderItem::TYPE_SURCHARGE;
                                break;
                            default:
                                $detailSW['type'] = CustomerOrderItem::TYPE_PRODUCT;
                                break;
                        }
                        
                        $orderItem = Mmc::getModel('CustomerOrderItem');
                        $orderItem->map(true, DataConverter::toObject($detailSW, true));
                        
                        $detail = $productMapper->findDetailBy(['number' => $detailSW['articleNumber']]);
                        if ($detail !== null) {
                            //throw new \Exception(sprintf('Cannot find detail with number (%s)', $detailSW['articleNumber']));
                            $orderItem->setProductId(new Identity(IdConcatenator::link([
                                $detail->getId(),
                                $detailSW['articleId'],
                            ])));
                        }
                        
                        /*
                        if ($detail->getKind() == 2) {    // is Child
                            $orderItem->setProductId(new Identity(sprintf('%s_%s', $detail->getId(), $detailSW['articleId'])));
                        }
                        */
                        
                        $order->addItem($orderItem);
                    }
                    
                    $this->addPos($order, 'setBillingAddress', 'CustomerOrderBillingAddress', $orderSW['billing']);
                    $this->addPos($order, 'setShippingAddress', 'CustomerOrderShippingAddress', $orderSW['shipping']);
                    
                    // Salutation and Email
                    if ($order->getBillingAddress() !== null) {
                        $order->getBillingAddress()->setSalutation(Salutation::toConnector($orderSW['billing']['salutation']))
                            ->setEmail($orderSW['customer']['email']);

                        $vatNumber = $order->getBillingAddress()->getVatNumber();
                        if(strlen($vatNumber) > 20) {
                            $order->getBillingAddress()->setVatNumber(substr($vatNumber, 0, 20));
                        }
                    }
                    
                    if ($order->getShippingAddress() !== null) {
                        
                        // DHL Packstation
                        $dhlPropertyInfos = [
                            ['name' => 'Postnummer', 'prop' => 'swagDhlPostnumber', 'serialized' => false],
                            ['name' => 'Packstation', 'prop' => 'swagDhlPackstation', 'serialized' => true],
                            ['name' => 'Postoffice', 'prop' => 'swagDhlPostoffice', 'serialized' => true],
                        ];
                        
                        $dhlInfos = [];
                        foreach ($dhlPropertyInfos as $dhlPropertyInfo) {
                            $this->addDHLInfo($orderSW, $dhlInfos, $dhlPropertyInfo);
                        }
                        
                        $extraAddressLine = $order->getShippingAddress()->getExtraAddressLine();
                        if (count($dhlInfos) > 0) {
                            $extraAddressLine .= sprintf(' (%s)', implode(' - ', $dhlInfos));
                        }
                        
                        $order->getShippingAddress()->setExtraAddressLine($extraAddressLine)
                            ->setSalutation(Salutation::toConnector($orderSW['shipping']['salutation']))
                            ->setEmail($orderSW['customer']['email']);
                    }
                    
                    // Adding shipping item
                    $shippingPrice = (isset($orderSW['invoiceShippingNet'])) ? (float)$orderSW['invoiceShippingNet'] : 0.0;
                    $shippingPriceGross = (isset($orderSW['invoiceShipping'])) ? (float)$orderSW['invoiceShipping'] : 0.0;
                    $item = Mmc::getModel('CustomerOrderItem');
                    $item->setType(CustomerOrderItem::TYPE_SHIPPING)
                        ->setId(new Identity(sprintf('%s_ship', $orderSW['id'])))
                        ->setCustomerOrderId($order->getId())
                        ->setName('Shipping')
                        ->setPrice($shippingPrice)
                        ->setPriceGross($shippingPriceGross)
                        ->setQuantity(1)
                        ->setVat(self::calcShippingVat($order));
                    
                    $order->addItem($item);
                    
                    // Attributes
                    if (isset($orderSW['attribute']) && !is_null($orderSW['attribute'])) {
                        $excludes = ['id', 'orderId'];
                        
                        foreach ($orderSW['attribute'] as $key => $value) {
                            if (in_array($key, $excludes)) {
                                continue;
                            }
                            
                            if (is_null($value) || empty($value)) {
                                continue;
                            }
                            
                            $customerOrderAttr = Mmc::getModel('CustomerOrderAttr');
                            $customerOrderAttr->map(true, DataConverter::toObject($orderSW['attribute']));
                            $customerOrderAttr->setKey($key)
                                ->setValue((string)$value);
                            
                            $order->addAttribute($customerOrderAttr);
                        }
                    }
                    
                    /*
                    for ($i = 1; $i <= 6; $i++) {
                        if (isset($orderSW['attribute']["attribute{$i}"]) && strlen($orderSW['attribute']["attribute{$i}"]) > 0) {
                            $customerOrderAttr = Mmc::getModel('CustomerOrderAttr');
                            $customerOrderAttr->map(true, DataConverter::toObject($orderSW['attribute']));
                            $customerOrderAttr->setKey("attribute{$i}")
                                ->setValue((string) $orderSW['attribute']["attribute{$i}"]);

                            $order->addAttribute($customerOrderAttr);
                        }
                    }
                    */
                    
                    // Payment Data
                    if (isset($orderSW['customer']['paymentData']) && is_array($orderSW['customer']['paymentData'])) {
                        $customerOrderPaymentInfo = $order->getPaymentInfo();
                        if ($customerOrderPaymentInfo === null) {
                            $customerOrderPaymentInfo = Mmc::getModel('CustomerOrderPaymentInfo');
                            $customerOrderPaymentInfo->setCustomerOrderId($order->getId())
                                ->setAccountHolder(sprintf(
                                    '%s %s',
                                    $orderSW['billing']['firstName'],
                                    $orderSW['billing']['lastName']
                                ));
                        }
                        
                        foreach ($orderSW['customer']['paymentData'] as $dataSW) {
                            if (isset($dataSW['bic']) && strlen($dataSW['bic']) > 0
                                && isset($dataSW['iban']) && strlen($dataSW['iban']) > 0) {
                                $customerOrderPaymentInfo->setBic($dataSW['bic'])
                                    ->setIban($dataSW['iban']);
                                break;
                            }
                        }
                        
                        $order->setPaymentInfo($customerOrderPaymentInfo);
                    }

                    // Update order status

                    /** @deprecated Will be removed in a future connector release $valueOld */
                    $valueOld = Application()->getConfig()->get('customer_order_processing_after_pull', true);
                    $value = Application()->getConfig()->get('customer_order.pull.status_processing', $valueOld);
                    if ($swOrderObj->getOrderStatus()->getId() === Status::ORDER_STATE_OPEN && $value) {
                        $status = Shopware()->Models()->getRepository('Shopware\Models\Order\Status')->findOneById(Status::ORDER_STATE_IN_PROCESS);

                        $swOrderObj->setOrderStatus($status);
                        Shopware()->Models()->persist($swOrderObj);
                        Shopware()->Models()->flush();
                    }
                    
                    $result[] = $order;
                } catch (\Exception $exc) {
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
            }
            
            $action->setResult($result);
        } catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }
    
    /**
     * Check if dhl postnumber, postoffice or packstation is available
     * Add it or our street information
     *
     * @param array $orderSW
     * @param array $dhlInfo
     * @param array $dhlInfoPropertyInfo
     */
    public function addDHLInfo(array $orderSW, array &$dhlInfos, array $dhlInfoPropertyInfo)
    {
        $property = $dhlInfoPropertyInfo['prop'];
        $name = $dhlInfoPropertyInfo['name'];
        
        if (isset($orderSW['customer']['defaultShippingAddress']['attribute'][$property])
            && $orderSW['customer']['defaultShippingAddress']['attribute'][$property] !== null
            && strlen($orderSW['customer']['defaultShippingAddress']['attribute'][$property]) > 0) {
            
            if ($dhlInfoPropertyInfo['serialized']) {
                $obj = @unserialize($orderSW['customer']['defaultShippingAddress']['attribute'][$property]);
                if ($obj !== false) {
                    $number = isset($obj->officeNumber) ? $obj->officeNumber : $obj->stationNumber;
                    if (strlen(trim($obj->zip)) > 0 && strlen(trim($obj->city)) > 0) {
                        $value = sprintf('%s %s, %s', $obj->zip, $obj->city, $number);
                        $dhlInfos[] = sprintf('%s: %s', $name, $value);
                    }
                }
            } else {
                $dhlInfos[] = sprintf('%s: %s', $name,
                    $orderSW['customer']['defaultShippingAddress']['attribute'][$property]);
            }
        }
    }
    
    /**
     * @param $paymentModuleCode
     * @param array $orderSW
     * @param CustomerOrderModel $order
     */
    protected function addBillsafe($paymentModuleCode, array $orderSW, CustomerOrderModel &$order)
    {
        if ($paymentModuleCode === PaymentTypes::TYPE_BILLSAFE
            && isset($orderSW['attribute']['swagBillsafeIban'])
            && isset($orderSW['attribute']['swagBillsafeBic'])) {
            $order->setPui(sprintf(
                'Bitte bezahlen Sie %s %s an folgendes Konto: %s Verwendungszweck: BTN %s',
                number_format((float)$orderSW['invoiceAmount'], 2),
                $order->getCurrencyIso(),
                sprintf('IBAN: %s, BIC: %s', $orderSW['attribute']['swagBillsafeIban'],
                    $orderSW['attribute']['swagBillsafeBic']),
                $orderSW['transactionId']
            ));
        }
    }
    
    /**
     * @param $paymentModuleCode
     * @param array $orderSW
     * @param CustomerOrderModel $order
     */
    protected function addPayPalPlusInvoice($paymentModuleCode, array $orderSW, CustomerOrderModel &$order)
    {
        if ($paymentModuleCode === PaymentTypes::TYPE_PAYPAL_EXPRESS) {
            
            // Invoice
            $result = Shopware()->Db()->fetchAll('SELECT * FROM s_payment_paypal_plus_payment_instruction WHERE ordernumber = ?',
                [
                    $orderSW['number'],
                ]);
            
            if (is_array($result) && count($result) > 0) {
                $order->setPui(sprintf(
                    'Bitte überweisen Sie %s %s bis %s an folgendes Konto: %s Verwendungszweck: %s',
                    number_format((float)$orderSW['invoiceAmount'], 2),
                    $order->getCurrencyIso(),
                    (new \DateTime($result[0]['payment_due_date']))->format('d.m.Y'),
                    sprintf(
                        'Empfänger: %s, Bank: %s, IBAN: %s, BIC: %s',
                        $result[0]['account_holder_name'],
                        $result[0]['bank_name'],
                        $result[0]['international_bank_account_number'],
                        $result[0]['bank_identifier_code']
                    ),
                    $result[0]['reference_number']
                ))
                    ->setPaymentModuleCode(PaymentTypes::TYPE_PAYPAL_PLUS);
            }
        }
    }
    
    /**
     * @param $paymentModuleCode
     * @param array $orderSW
     * @param CustomerOrderModel $order
     */
    protected function addPayPalPlusInstallment($paymentModuleCode, array $orderSW, CustomerOrderModel &$order)
    {
        if ($paymentModuleCode === PaymentTypes::TYPE_PAYPAL_EXPRESS) {
            
            // Installment
            $result = Shopware()->Db()->fetchAll('SELECT * FROM s_plugin_paypal_installments_financing WHERE orderNumber = ?',
                [
                    $orderSW['number'],
                ]);
            
            if (is_array($result) && count($result) > 0) {
                $order->setPui(sprintf(
                    'Vielen Dank das Sie sich für die Zahlungsart Ratenzahlung powered by PayPal entschieden haben. Sie Zahlen Ihre Bestellung in %s Monatsraten je %s %s ab. Die zusätzlichen Kosten für diesen Service belaufen sich auf %s %s (Umsatzsteuerfrei).',
                    $result[0]['term'],
                    number_format((float)$result[0]['monthlyPayment'], 2),
                    $order->getCurrencyIso(),
                    number_format((float)$result[0]['feeAmount'], 2),
                    $order->getCurrencyIso()
                ))
                    ->setPaymentModuleCode(PaymentTypes::TYPE_PAYPAL_PLUS);
            }
        }
    }
    
    /**
     * @param $paymentModuleCode
     * @param array $orderSW
     * @param CustomerOrderModel $order
     */
    protected function addPayPalUnified($paymentModuleCode, array $orderSW, CustomerOrderModel &$order)
    {
        if ($paymentModuleCode === 'SwagPaymentPayPalUnified' || $paymentModuleCode === 'SwagPaymentPayPalUnifiedInstallments'
            && isset($orderSW['attribute']['swagPaypalUnifiedPaymentType'])) {
            
            switch ($orderSW['attribute']['swagPaypalUnifiedPaymentType']) {
                case 'PayPalExpress':
                    $paymentModuleCode = PaymentTypes::TYPE_PAYPAL_EXPRESS;
                    break;
                case 'PayPalClassic':
                    $paymentModuleCode = PaymentTypes::TYPE_PAYPAL;
                    break;
                case 'PayPalPlus':
                    $paymentModuleCode = PaymentTypes::TYPE_PAYPAL_PLUS;
                    break;
                case 'PayPalPlusInvoice':
                    $paymentModuleCode = PaymentTypes::TYPE_PAYPAL_PLUS;
                    
                    // Invoice
                    $result = Shopware()->Db()
                        ->fetchAll('SELECT *
                                    FROM swag_payment_paypal_unified_payment_instruction
                                    WHERE order_number = ?',
                            [
                                $orderSW['number'],
                            ]);
                    
                    if (is_array($result) && count($result) > 0) {
                        $order->setPui(sprintf(
                            'Bitte überweisen Sie %s %s bis %s an folgendes Konto: %s Verwendungszweck: %s',
                            number_format((float)$orderSW['invoiceAmount'], 2),
                            $order->getCurrencyIso(),
                            (new \DateTime($result[0]['due_date']))->format('d.m.Y'),
                            sprintf(
                                'Empfänger: %s, Bank: %s, IBAN: %s, BIC: %s',
                                $result[0]['account_holder'],
                                $result[0]['bank_name'],
                                $result[0]['iban'],
                                $result[0]['bic']
                            ),
                            $result[0]['reference']
                        ));
                    }
                    
                    break;
                case 'PayPalInstallments':
                    $paymentModuleCode = PaymentTypes::TYPE_PAYPAL_PLUS;
                    
                    // Installment
                    $result = Shopware()->Db()
                        ->fetchAll('SELECT *
                                FROM swag_payment_paypal_unified_financing_information
                                WHERE payment_id = ?',
                            [
                                $orderSW['temporaryId'],
                            ]);
                    
                    if (is_array($result) && count($result) > 0) {
                        $order->setPui(sprintf(
                            'Vielen Dank das Sie sich für die Zahlungsart Ratenzahlung
                             powered by PayPal entschieden haben. Sie Zahlen Ihre Bestellung in %s Monatsraten
                              je %s %s ab. Die zusätzlichen Kosten für diesen Service belaufen sich auf %s %s
                              (Umsatzsteuerfrei).',
                            $result[0]['term'],
                            number_format((float)$result[0]['monthly_payment'], 2),
                            $order->getCurrencyIso(),
                            number_format((float)$result[0]['fee_amount'], 2),
                            $order->getCurrencyIso()
                        ));
                    }
                    
                    break;
                default:
                    $paymentModuleCode = PaymentTypes::TYPE_PAYPAL;
                    break;
            }
            
            $order->setPaymentModuleCode($paymentModuleCode);
        }
    }
    
    /**
     * @param $paymentModuleCode
     * @param array $orderSW
     * @param CustomerOrderModel $order
     */
    protected function addHeidelpayInvoice($paymentModuleCode, array $orderSW, CustomerOrderModel &$order)
    {
        if ($paymentModuleCode === PaymentTypes::TYPE_HEIDELPAY) {
            
            // Invoice
            if (strlen(strip_tags($orderSW['comment'])) > 10) {
                $order->setPui(html_entity_decode(strip_tags($orderSW['comment'])));
            } else { // Fallback
                $shortid = Shopware()->Db()->fetchOne('SELECT shortid FROM s_plugin_hgw_transactions WHERE transactionid = ?',
                    [
                        $orderSW['transactionId'],
                    ]);
                
                if (empty($shortid) || is_null($shortid)) {
                    return;
                }
                
                $order->setPui(sprintf(
                    'Bitte überweisen Sie uns den Betrag von %s %s auf folgendes Konto: Kontoinhaber: Heidelberger Payment GmbH Konto-Nr.: 5320130 Bankleitzahl: 37040044 IBAN: DE89370400440532013000 BIC: COBADEFFXXX Geben Sie als Verwendungszweck bitte ausschließlich diese Identifikationsnummer an: %s',
                    number_format((float)$orderSW['invoiceAmount'], 2),
                    $order->getCurrencyIso(),
                    $shortid
                ));
            }
        }
    }
    
    public static function calcShippingVat(\jtl\Connector\Shopware\Model\CustomerOrder &$order)
    {
        return max(array_map(function ($item) {
            return $item->getVat();
        }, $order->getItems()));
    }
}
