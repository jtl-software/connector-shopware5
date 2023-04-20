<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use Doctrine\ORM\Query\Expr;
use jtl\Connector\Core\Logger\Logger;

class Locale extends DataMapper
{
    public function find($id)
    {
        return (\intval($id) == 0) ? null : $this->Manager()->find('Shopware\Models\Shop\Locale', $id);
    }

    public function findByLocale(string $key): array
    {
        $query = $this->Manager()->createQueryBuilder()->select(
            'shop',
            'locale'
        )
            ->from('Shopware\Models\Shop\Shop', 'shop')
            ->join('shop.locale', 'locale')
            ->where((new Expr())->like('locale.locale', ':locale'))
            ->setParameter('locale', $key . '%')
            ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        $shops   = \iterator_to_array($paginator);
        $locales = [];
        foreach ($shops as $shop) {
            $locales[] = $shop->getLocale();
        }

        return $locales;
    }

    public function findAll($count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
            'shop',
            'locale'
        )
            ->from('Shopware\Models\Shop\Shop', 'shop')
            ->join('shop.locale', 'locale')
            ->getQuery();

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        if ($count) {
            return $paginator->count();
        } else {
            $shops   = \iterator_to_array($paginator);
            $locales = array();
            foreach ($shops as $shop) {
                $locales[] = $shop->getLocale();
            }

            return $locales;
        }
    }

    public function fetchCount()
    {
        return $this->findAll(true);
    }

    public function save($data)
    {
        throw new \Exception('Not implemented');
    }
}
