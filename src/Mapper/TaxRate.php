<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Model\TaxRate as TaxRateModel;
use \Shopware\Models\Tax\Tax as TaxRateSW;
use \jtl\Connector\Model\Identity;

class TaxRate extends DataMapper
{
    public function find($id)
    {
        return (intval($id) == 0) ? null : $this->Manager()->getRepository('Shopware\Models\Tax\Tax')->find($id);
    }

    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Tax\Tax')->findOneBy($kv);
    }

    /**
     * @param int|null $limit
     * @param bool $count
     * @return array|int
     */
    public function findAll(?int $limit = 100, bool $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'tax'
            )
            ->from('Shopware\Models\Tax\Tax', 'tax')
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, true);

        $return = $count ? $paginator->count() : iterator_to_array($paginator);

        $query = Shopware()->Models()->createQueryBuilder()->select(
            'tax'
        )
            ->from('Shopware\Models\Tax\Rule', 'tax')
            ->groupBy('tax.tax')
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, true);

        if($count){
            $return = $return + $paginator->count();
        } else {
            $return = array_merge($return, iterator_to_array($paginator));
        }

        return $return;
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function delete(TaxRateModel $taxRate)
    {
        $result = new TaxRateModel;

        $this->deleteTaxRateData($unit);

        // Result
        $result->setId(new Identity('', $taxRate->getId()->getHost()));

        return $result;
    }

    public function save(TaxRateModel $taxRate)
    {
        $taxRateSW = null;
        $result = new TaxRateModel;

        $this->prepareTaxRateAssociatedData($taxRate, $taxRateSW);

        $this->Manager()->persist($taxRateSW);
        $this->flush($taxRateSW);

        // Result
        $result->setId(new Identity($taxRateSW->getId(), $taxRate->getId()->getHost()));

        return $result;
    }

    protected function deleteTaxRateData(TaxRateModel $taxRate)
    {
        $taxRateId = (strlen($taxRate->getId()->getEndpoint()) > 0) ? (int)$taxRate->getId()->getEndpoint() : null;

        if ($taxRateId !== null && $taxRateId > 0) {
            $taxRateSW = $this->find((int) $taxRateId);
            if ($taxRateSW !== null) {
                $this->Manager()->remove($taxRateSW);
                $this->Manager()->flush($taxRateSW);
            }
        }
    }

    protected function prepareTaxRateAssociatedData(TaxRateModel $taxRate, TaxRateSW &$taxRateSW = null)
    {
        $taxRateId = (strlen($taxRate->getId()->getEndpoint()) > 0) ? (int)$taxRate->getId()->getEndpoint() : null;

        if ($taxRateId !== null && $taxRateId > 0) {
            $taxRateSW = $this->find($taxRateId);
        }

        if ($taxRateSW === null) {
            $taxRateSW = $this->findOneBy(array('tax' => $taxRate->getRate()));
        }

        if ($taxRateSW === null) {
            $taxRateSW = new TaxRateSW;
        }

        $taxRateSW->setTax($taxRate->getRate())
            ->setName($taxRate->getRate());
    }
}
