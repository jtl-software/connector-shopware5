<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Core\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Connector\Model\Unit as UnitModel;
use \jtl\Connector\Shopware\Model\Linker\Unit as UnitSW;
use \jtl\Connector\Shopware\Model\Linker\UnitI18n as UnitI18nSW;
use \jtl\Connector\Model\Identity;
use \Doctrine\Common\Collections\ArrayCollection;

class Unit extends AbstractDataMapper
{
    public function find($id)
    {
        return (intval($id) == 0) ? null : $this->getManager()->getRepository('jtl\Connector\Shopware\Model\Linker\Unit')->find($id);
    }

    public function findI18n($unitId, $languageIso)
    {
        return $this->getManager()->getRepository('jtl\Connector\Shopware\Model\Linker\UnitI18n')->findOneBy(array('unit_id' => $unitId, 'languageIso' => $languageIso));
    }
    
    public function findAll($limit = 100, $count = false)
    {
        $query = $this->getManager()->createQueryBuilder()->select(
                'unit'
            )
            ->from('jtl\Connector\Shopware\Model\Linker\Unit', 'unit')
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        return $count ? $paginator->count() : iterator_to_array($paginator);
    }

    public function findOneBy(array $kv)
    {
        return $this->getManager()->getRepository('jtl\Connector\Shopware\Model\Linker\Unit')->findOneBy($kv);
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function delete(UnitModel $unit)
    {
        $result = new UnitModel;

        $this->deleteUnitData($unit);

        // Result
        $result->setId(new Identity('', $unit->getId()->getHost()));

        return $result;
    }

    public function save(UnitModel $unit)
    {
        $unitSW = null;
        $result = new UnitModel;

        $this->prepareUnitAssociatedData($unit, $unitSW);
        $this->prepareUnitI18nAssociatedData($unit, $unitSW);

        $violations = $this->getManager()->validate($unitSW);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->getManager()->persist($unitSW);
        $this->flush();

        // Result
        $result->setId(new Identity($unitSW->getId(), $unit->getId()->getHost()));

        return $result;
    }

    protected function deleteUnitData(UnitModel $unit)
    {
        $unitSW = $this->findOneBy(array('hostId' => $unit->getId()->getHost()));

        if ($unitSW !== null) {
            $this->getManager()->remove($unitSW);
            $this->getManager()->flush($unitSW);
        }
    }

    protected function prepareUnitAssociatedData(UnitModel $unit, UnitSW &$unitSW = null)
    {
        $unitSW = $this->findOneBy(array('hostId' => $unit->getId()->getHost()));

        if ($unitSW === null) {
            $unitSW = new UnitSW;
            $unitSW->setHostId($unit->getId()->getHost());
            $this->getManager()->persist($unitSW);
        }
    }

    protected function prepareUnitI18nAssociatedData(UnitModel $unit, UnitSW &$unitSW)
    {
        $collection = new ArrayCollection();
        foreach ($unit->getI18ns() as $i18n) {
            $unitI18nSW = null;
            if ((int) $unit->getId()->getEndpoint() > 0) {
                $unitI18nSW = $this->findI18n($unit->getId()->getEndpoint(), $i18n->getLanguageISO());
            }

            if ($unitI18nSW === null) {
                $unitI18nSW = new UnitI18nSW();
                $this->getManager()->persist($unitI18nSW);
            }

            $unitI18nSW->setLanguageIso($i18n->getLanguageISO())
                ->setName($i18n->getName())
                ->setUnit($unitSW);

            $collection->add($unitI18nSW);
        }

        $unitSW->setI18ns($collection);
    }
}
