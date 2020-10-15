<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Model\Currency as CurrencyModel;
use jtl\Connector\Model\Identity;
use Shopware\Models\Shop\Currency as CurrencySW;

class Currency extends AbstractDataMapper
{
    public function find($id, $array_hydration = false)
    {
        if ((int) $id == 0) {
            return null;
        }
        
        if ($array_hydration) {
            return $this->getManager()->createQueryBuilder()->select(
                    'currency'
                )
                ->from('Shopware\Models\Shop\Currency', 'currency')
                ->where('currency.id = :id')
                ->setParameter('id', $id)
                ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY)->getSingleResult();
        } else {
            return $this->getManager()->find('Shopware\Models\Shop\Currency', $id);
        }
    }

    public function findOneBy(array $kv)
    {
        return $this->getManager()->getRepository('Shopware\Models\Shop\Currency')->findOneBy($kv);
    }

    public function findAll($limit = 100, $count = false)
    {
        $query = $this->getManager()->createQueryBuilder()->select(
                'currency'
            )
            ->from('Shopware\Models\Shop\Currency', 'currency')
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        return $count ? $paginator->count() : iterator_to_array($paginator);
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function delete(CurrencyModel $currency)
    {
        $result = $currency;

        $this->deleteCurrencyData($currency);

        // Result
        $result->setId(new Identity('', $currency->getId()->getHost()));

        return $result;
    }

    public function save(CurrencyModel $currency)
    {
        $currencySW = null;
        $result = $currency;

        $this->prepareCurrencyAssociatedData($currency, $currencySW);

        $this->getManager()->persist($currencySW);
        $this->flush();

        // Result
        $result->setId(new Identity($currencySW->getId(), $currency->getId()->getHost()));

        return $result;
    }

    protected function deleteCurrencyData(CurrencyModel $currency)
    {
        if (strlen($currency->getIso()) > 0) {
            $currencySW = $this->findOneBy(array('currency' => strtoupper($currency->getIso())));
            if ($currencySW !== null) {
                $this->getManager()->remove($currencySW);
                $this->getManager()->flush($currencySW);
            }
        }
    }

    protected function prepareCurrencyAssociatedData(CurrencyModel $currency, CurrencySW &$currencySW = null)
    {
        if (strlen($currency->getIso()) > 0) {
            $currencySW = $this->findOneBy(array('currency' => strtoupper($currency->getIso())));
        }

        if ($currencySW === null) {
            $currencySW = new CurrencySW;
        }

        $symPos = $currency->getHasCurrencySignBeforeValue() ? 32 : 16;
        $currencySW->setCurrency(strtoupper($currency->getIso()))
            ->setName($currency->getName())
            ->setDefault((int) $currency->getIsDefault())
            ->setFactor($currency->getFactor())
            ->setSymbol($currency->getNameHtml())
            ->setSymbolPosition($symPos)
            ->setPosition(0);
    }
}