<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Formatter\ExceptionFormatter;

class Payment extends DataMapper
{
    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('jtl\Connector\Shopware\Model\Linker\Payment')->findOneBy($kv);
    }

    public function find($id)
    {
        return (intval($id) == 0) ? null : $this->Manager()->find('jtl\Connector\Shopware\Model\Linker\Payment', $id);
    }

    public function findAllNative($limit = 100)
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
        
        return Shopware()->Db()->fetchAssoc(
            'SELECT p.id, p.customerOrderId,
              p.billingInfo, p.creationDate, p.totalSum, p.transactionId, m.name as paymentModuleCode
            FROM jtl_connector_payment p
            JOIN s_order o ON o.id = p.customerOrderId
            JOIN s_core_paymentmeans m ON m.id = o.paymentID
            LEFT JOIN jtl_connector_link_payment pl ON pl.payment_id = p.id
            WHERE pl.payment_id IS NULL
            ' . $where . '
            limit ' . $limit
        );
    }

    /*
    public function findAll($limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
            'payment'
        )
            ->from('jtl\Connector\Shopware\Model\Linker\Payment', 'payment')
            ->leftJoin('payment.linker', 'linker')
            ->where('linker.hostId IS NULL')
            ->setFirstResult(0)
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        //->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        return $count ? ($paginator->count()) : iterator_to_array($paginator);
    }
    */

    public function fetchCount($limit = 100)
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
        
        return (int) Shopware()->Db()->fetchOne(
            'SELECT count(*) as count
            FROM jtl_connector_payment p
            JOIN s_order o ON o.id = p.customerOrderId
            JOIN s_core_paymentmeans m ON m.id = o.paymentID
            LEFT JOIN jtl_connector_link_payment pl ON pl.payment_id = p.id
            WHERE pl.payment_id IS NULL' . $where
        );

        //return $this->findAll($limit, true);
    }
}