<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use jtl\Connector\Payment\PaymentTypes;
use Shopware\Models\Order\Status;

final class Payment
{
    private static $paymentTypeMappings = [
        PaymentTypes::TYPE_DIRECT_DEBIT => 'debit',
        PaymentTypes::TYPE_CASH_ON_DELIVERY => 'cash',
        PaymentTypes::TYPE_INVOICE => 'invoice',
        PaymentTypes::TYPE_PREPAYMENT => 'prepayment',
        PaymentTypes::TYPE_PAYPAL_EXPRESS => 'paypal',
        PaymentTypes::TYPE_SOFORT => 'sofortbanking',
        PaymentTypes::TYPE_BILLSAFE => 'billsafe_invoice',
        PaymentTypes::TYPE_HEIDELPAY => [
            'hgw_iv',
            'hgw_papg',
            'hgw_ivb2b',
            'unzerPaymentGiropay',
            'unzerPaymentInvoiceSecured',
            'unzerPaymentCreditCard'
        ]
    ];

    /**
     * @param string|null $jtlModuleCode
     * @param string|null $swModuleCode
     * @param string|null $paymentName
     * @return string
     */
    public static function map(string $jtlModuleCode = null, string $swModuleCode = null, string $paymentName = null): string
    {
        if (is_null($jtlModuleCode) && is_null($swModuleCode) && is_null($paymentName)) {
            return 'unknown';
        }

        $paymentTypeMappings = self::getPaymentTypeMappings();

        if ($jtlModuleCode !== null && isset($paymentTypeMappings[$jtlModuleCode])) {
            return $paymentTypeMappings[$jtlModuleCode];
        } elseif ($swModuleCode !== null) {
            foreach ($paymentTypeMappings as $key => $value) {
                if (is_array($value)) {
                    if (array_search($swModuleCode, $value, true) !== false) {
                        return $key;
                    }
                }

                if ($value === $swModuleCode) {
                    return $key;
                }
            }
        }

        return $paymentName ?? $jtlModuleCode ?? $swModuleCode;
    }

    /**
     * @param $paymentModuleCode
     * @return bool
     */
    public static function isPayPalUnifiedType($paymentModuleCode)
    {
        return ($paymentModuleCode === 'SwagPaymentPayPalUnified' ||
            $paymentModuleCode === 'SwagPaymentPayPalUnifiedInstallments' ||
            $paymentModuleCode === 'SwagPaymentPayPalUnifiedPayUponInvoice');
    }

    /**
     * @param $swagPayPalUnifiedPaymentType
     * @return string
     */
    public static function mapPayPalUnified($swagPayPalUnifiedPaymentType)
    {
        switch ($swagPayPalUnifiedPaymentType) {
            case 'PayPalExpress':
                $paymentModuleCode = PaymentTypes::TYPE_PAYPAL_EXPRESS;
                break;
            case 'PayPalPlus':
            case 'PayPalPlusInvoice':
            case 'PayPalPayUponInvoiceV2':
            case 'PayPalInstallments':
                $paymentModuleCode = PaymentTypes::TYPE_PAYPAL_PLUS;
                break;
            case 'PayPalClassic':
            default:
                $paymentModuleCode = PaymentTypes::TYPE_PAYPAL;
                break;
        }

        return $paymentModuleCode;
    }

    /**
     * Check if PayPal Plus invoice is installed
     * @return bool
     */
    public static function usePPPInvoice()
    {
        $mysql_result = Shopware()->Db()->fetchAll('SHOW TABLES LIKE \'s_payment_paypal_plus_payment_instruction\'');

        return is_array($mysql_result) && count($mysql_result) > 0;
    }

    /**
     * Check if PayPal Plugin >= 1.0.5 is installed
     * @return bool
     */
    public static function usePaypalUnified()
    {
        $mysql_result = Shopware()->Db()->fetchAll('SHOW TABLES LIKE \'swag_payment_paypal_unified_%\'');

        return is_array($mysql_result) && count($mysql_result) > 0;
    }

    /**
     * Check if PayPal Plus installment is installed
     * @return bool
     */
    public static function usePPPInstallment()
    {
        $mysql_result = Shopware()->Db()->fetchAll('SHOW TABLES LIKE \'s_plugin_paypal_installments_financing\'');

        return is_array($mysql_result) && count($mysql_result) > 0;
    }

    /**
     * Check if Heidelpay invoice is installed
     * @return bool
     */
    public static function useHeidelpayInvoice()
    {
        $mysql_result = Shopware()->Db()->fetchAll('SHOW TABLES LIKE \'s_plugin_hgw_transactions\'');

        return is_array($mysql_result) && count($mysql_result) > 0;
    }

    /**
     * @param bool $asString
     * @return array|string
     */
    public static function getAllowedPaymentClearedStates(bool $asString = false)
    {
        $defaultState = [
            Status::PAYMENT_STATE_COMPLETELY_PAID
        ];

        $allowedClearedStates = array_unique(array_merge(
            Application()->getConfig()->get('payment.pull.allowed_cleared_states', []),
            $defaultState
        ));

        return $asString === true ? join(',', $allowedClearedStates) : $allowedClearedStates;
    }

    /**
     * @return array
     */
    public static function getPaymentTypeMappings(): array
    {
        return array_replace_recursive(
            self::$paymentTypeMappings,
            Application()->getConfig()->get('payment_type_mappings', [])
        );
    }
}
