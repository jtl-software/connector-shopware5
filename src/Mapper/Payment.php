<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Shopware\Utilities\Payment as UtilPayment;

class Payment extends DataMapper
{
    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('jtl\Connector\Shopware\Model\Linker\Payment')->findOneBy($kv);
    }

    public function find(?int $id)
    {
        return (intval($id) == 0) ? null : $this->Manager()->find('jtl\Connector\Shopware\Model\Linker\Payment', $id);
    }

    public function findAllNative(int $limit = 100)
    {
        // Customer Order pull start date
        $where = '';
        try {
            $startDateOld = Application()->getConfig()->get('customer_order_pull_start_date', null);
            $startDate = Application()->getConfig()->get('customer_order.pull.start_date', $startDateOld);
            if (!is_null($startDate)) {
                $dateTime = new \DateTime($startDate);
                $where = sprintf(' AND o.orderTime >= \'%s\'', $dateTime->format('Y-m-d H:i:s'));
            }
        } catch (\Exception $e) {
            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'config');
        }

        $sql = sprintf(
            'SELECT
                o.id as id,
                o.id as customerOrderId,
                "" as billingInfo,
                IF(o.cleareddate IS NULL, now(), o.cleareddate) as creationDate,
                o.invoice_amount as totalSum,
                o.transactionID as transactionId,
                m.name AS paymentModuleCode
            FROM s_order o
            JOIN jtl_connector_link_order lo ON lo.order_id = o.id
            JOIN s_core_paymentmeans m ON m.id = o.paymentID
            LEFT JOIN jtl_connector_link_payment pl ON pl.order_id = o.id
            WHERE pl.order_id IS NULL
            AND lo.order_id IS NOT NULL
            AND LENGTH(o.transactionID) > 0 AND o.cleared IN (%s)%s                
            LIMIT %d',
            UtilPayment::getAllowedPaymentClearedStates(true), $where, $limit);

        return Shopware()->Db()->fetchAssoc($sql);
    }

    public function fetchCount(int $limit = 100)
    {
        $where = '';
        try {
            $startDateOld = Application()->getConfig()->get('customer_order_pull_start_date', null);
            $startDate = Application()->getConfig()->get('customer_order.pull.start_date', $startDateOld);
            if (!is_null($startDate)) {
                $dateTime = new \DateTime($startDate);
                $where = sprintf(' AND o.orderTime >= \'%s\'', $dateTime->format('Y-m-d H:i:s'));
            }
        } catch (\Exception $e) {
            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'config');
        }

        $sql = sprintf(
            'SELECT count(*) as count
             FROM s_order o            
             JOIN jtl_connector_link_order lo ON lo.order_id = o.id            
             LEFT JOIN jtl_connector_link_payment pl ON pl.order_id = o.id
             WHERE pl.order_id IS NULL
             AND lo.order_id IS NOT NULL
             AND o.cleared IN (%s) AND LENGTH(o.transactionID) > 0%s',
            UtilPayment::getAllowedPaymentClearedStates(true), $where
        );

        return (int)Shopware()->Db()->fetchOne($sql);
    }
}
