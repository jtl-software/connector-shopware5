<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

class Payment extends DataMapper
{
    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('jtl\Connector\Shopware\Model\Linker\Payment')->findOneBy($kv);
    }

    public function find($id)
    {
        return $this->Manager()->find('jtl\Connector\Shopware\Model\Linker\Payment', $id);
    }

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
        //->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        return $count ? ($paginator->count()) : iterator_to_array($paginator);
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }
}