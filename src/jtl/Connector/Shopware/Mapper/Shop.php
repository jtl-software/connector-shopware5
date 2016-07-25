<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Core\Logger\Logger;

class Shop extends DataMapper
{
    public function find($id)
    {
        return (intval($id) == 0) ? null : $this->Manager()->find('Shopware\Models\Shop\Shop', $id);
    }

    public function findByLocale($locale)
    {
        return $this->Manager()->createQueryBuilder()->select(
                'shop',
                'locale'
            )
            ->from('Shopware\Models\Shop\Shop', 'shop')
            ->leftJoin('shop.locale', 'locale')
            ->where('locale.locale = :locale')
            ->setParameter('locale', $locale)
            ->getQuery()->getResult();
    }
    
    public function findAll($limit = 100, $count = false, $array_hydration = true)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'shop',
                'locale',
                'category',
                'currencies'
            )
            ->from('Shopware\Models\Shop\Shop', 'shop')
            ->leftJoin('shop.locale', 'locale')
            ->leftJoin('shop.category', 'category')
            ->leftJoin('shop.currencies', 'currencies')
            ->setFirstResult(0)
            ->setMaxResults($limit)
            ->getQuery();
        
        if ($array_hydration) {
            $query->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        }

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        return $count ? $paginator->count() : iterator_to_array($paginator);
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function duplicateLocalizationsExist()
    {
        $res = Shopware()->Db()->fetchOne('SELECT id FROM s_core_shops GROUP BY locale_id HAVING count(*) > 1');

        return ($res !== false);
    }

    public function save(array $data, $namespace = '\Shopware\Models\Shop\Shop')
    {
        Logger::write(print_r($data, 1), Logger::DEBUG, 'database');
        
        return parent::save($data, $namespace);
    }
}
