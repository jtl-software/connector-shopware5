<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Utilities;

use \jtl\Connector\Payment\PaymentTypes;

final class Payment
{
    private static $_mappings = array(
        PaymentTypes::TYPE_DIRECT_DEBIT => 'debit',
        PaymentTypes::TYPE_CASH_ON_DELIVERY => 'cash',
        PaymentTypes::TYPE_INVOICE => 'invoice',
        PaymentTypes::TYPE_PREPAYMENT => 'prepayment',
        PaymentTypes::TYPE_PAYPAL_EXPRESS => 'paypal',
        PaymentTypes::TYPE_SOFORT => 'sofortbanking',
        PaymentTypes::TYPE_BILLSAFE => 'billsafe_invoice',
        PaymentTypes::TYPE_HEIDELPAY => [
            'hgw_iv',
            'hgw_papg'
        ]
    );

    public static function map($paymentModuleCode = null, $swCode = null)
    {
        if ($paymentModuleCode !== null && isset(self::$_mappings[$paymentModuleCode])) {
            return self::$_mappings[$paymentModuleCode];
        } elseif ($swCode !== null) {
            foreach (self::$_mappings as $key => $value) {
                if (is_array($value)) {
                    if (array_search($swCode, $value, true) !== false) {
                        return $key;
                    }
                }

                if ($value === $swCode) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * @param $paymentModuleCode
     * @return bool
     */
    public static function isPayPalUnifiedType($paymentModuleCode)
    {
        return ($paymentModuleCode === 'SwagPaymentPayPalUnified' ||
            $paymentModuleCode === 'SwagPaymentPayPalUnifiedInstallments');
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
}
