<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Model\TaxRate as TaxRateModel;
use \Shopware\Models\Tax\Tax as TaxRateSW;
use \jtl\Connector\Model\Identity;

class TaxRate extends AbstractDataMapper
{
    public function find($id)
    {
        return (intval($id) == 0) ? null : $this->getManager()->getRepository('Shopware\Models\Tax\Tax')->find($id);
    }

    public function findOneBy(array $kv)
    {
        return $this->getManager()->getRepository('Shopware\Models\Tax\Tax')->findOneBy($kv);
    }
    
    public function findAll($limit = 100, $count = false)
    {
        $query = $this->getManager()->createQueryBuilder()->select(
                'tax'
            )
            ->from('Shopware\Models\Tax\Tax', 'tax')
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        return $count ? $paginator->count() : iterator_to_array($paginator);
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

        $this->getManager()->persist($taxRateSW);
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
                $this->getManager()->remove($taxRateSW);
                $this->getManager()->flush($taxRateSW);
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
