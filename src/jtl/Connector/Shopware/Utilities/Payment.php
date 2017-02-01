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
        PaymentTypes::TYPE_BILLSAFE => 'billsafe_invoice'
    );

    public static function map($paymentModuleCode = null, $swCode = null)
    {
        if ($paymentModuleCode !== null && isset(self::$_mappings[$paymentModuleCode])) {
            return self::$_mappings[$paymentModuleCode];
        } elseif ($swCode !== null) {
            if (($connectorType = array_search($swCode, self::$_mappings)) !== false) {
                return $connectorType;
            }
        }

        return null;
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
     * Check if PayPal Plus installment is installed
     * @return bool
     */
    public static function usePPPInstallment()
    {
        $mysql_result = Shopware()->Db()->fetchAll('SHOW TABLES LIKE \'s_plugin_paypal_installments_financing\'');
        
        return is_array($mysql_result) && count($mysql_result) > 0;
    }
}
