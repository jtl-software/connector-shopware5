<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

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
         $sql = sprintf(
            'SELECT
                orders.id as id,
                orders.id as customerOrderId,
                "" as billingInfo,
                IF(orders.cleareddate IS NULL, now(), orders.cleareddate) as creationDate,
                orders.invoice_amount as totalSum,
                orders.transactionID as transactionId,
                m.name as name,
                m.description as description
            FROM s_order orders
            JOIN jtl_connector_link_order lo ON lo.order_id = orders.id
            JOIN s_core_paymentmeans m ON m.id = orders.paymentID
            LEFT JOIN jtl_connector_link_payment pl ON pl.order_id = orders.id
            WHERE pl.order_id IS NULL
            AND lo.order_id IS NOT NULL
            AND orders.cleared IN (%s)
            AND (LENGTH(orders.transactionID) > 0 OR orders.paymentID IN (%s))
            AND %s                
            LIMIT %d',
            UtilPayment::getAllowedPaymentClearedStates(true),
             join(',', self::getManualPaymentIds()),
            CustomerOrder::createOrderPullStartDateWhereClause(),
            $limit
        );

        return Shopware()->Db()->fetchAssoc($sql);
    }

    /**
     * Shop default: 2 - debit, 3 - cash, 4 - invoice, 5 - prepayment
     * @return int[]
     */
    protected static function getManualPaymentIds(): array
    {
        return [2, 3, 4, 5];
    }

    /**
     * @param int $limit
     * @return integer
     */
    public function fetchCount(int $limit = 100)
    {
        $sql = sprintf(
            'SELECT count(*) as count
             FROM s_order orders
             -- JOIN jtl_connector_link_order lo ON lo.order_id = orders.id            
             LEFT JOIN jtl_connector_link_payment pl ON pl.order_id = orders.id
             WHERE pl.order_id IS NULL
             -- AND lo.order_id IS NOT NULL
             AND orders.cleared IN (%s) 
             AND LENGTH(orders.transactionID) > 0
             AND %s',
            UtilPayment::getAllowedPaymentClearedStates(true),
            CustomerOrder::createOrderPullStartDateWhereClause()
        );

        return (int)Shopware()->Db()->fetchOne($sql);
    }
}
