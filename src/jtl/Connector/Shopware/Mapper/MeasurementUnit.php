<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Model\MeasurementUnit as MeasurementUnitModel;
use \Shopware\Models\Article\Unit as UnitSW;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;
use \jtl\Connector\Shopware\Utilities\Translation as TranslationUtil;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;

class MeasurementUnit extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Article\Unit')->find($id);
    }
    
    public function findAll($limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'unit'
            )
            ->from('Shopware\Models\Article\Unit', 'unit')
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = false);

        return $count ? $paginator->count() : iterator_to_array($paginator);
    }

    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Article\Unit')->findOneBy($kv);
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function delete(MeasurementUnitModel $unit)
    {
        $result = new MeasurementUnitModel;

        $this->deleteUnitData($unit);

        // Result
        $result->setId(new Identity('', $unit->getId()->getHost()));

        return $result;
    }

    public function save(MeasurementUnitModel $unit)
    {
        $unitSW = null;
        $result = new MeasurementUnitModel;

        //$name = $this->findName($unit);
        $this->prepareUnitAssociatedData($unit, $unitSW);

        $this->Manager()->persist($unitSW);
        $this->flush($unitSW);

        $this->saveTranslationData($unit, $unitSW);

        // Result
        $result->setId(new Identity($unitSW->getId(), $unit->getId()->getHost()));

        return $result;
    }

    protected function deleteUnitData(MeasurementUnitModel $unit)
    {
        $unitSW = $this->findOneBy(array('unit' => $unit->getCode()));
        if ($unitSW !== null) {
            $this->Manager()->remove($unitSW);
            $this->Manager()->flush($unitSW);
        }

        /*
        $unitId = (strlen($unit->getId()->getEndpoint()) > 0) ? (int)$unit->getId()->getEndpoint() : null;

        if ($unitId !== null && $unitId > 0) {
            $unitSW = $this->find((int) $unitId);
            if ($unitSW !== null) {
                $this->Manager()->remove($unitSW);
                $this->Manager()->flush($unitSW);
            }
        }
        */
    }

    protected function prepareUnitAssociatedData(MeasurementUnitModel $unit, UnitSW &$unitSW = null)
    {
        $unitSW = $this->findOneBy(array('unit' => $unit->getCode()));

        if ($unitSW === null) {
            $unitSW = new UnitSW;
        }

        foreach ($unit->getI18ns() as $i18n) {
            if ($i18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                $unitSW->setName($i18n->getName());
            }
        }

        $unitSW->setUnit($unit->getCode());
    }

    protected function saveTranslationData(MeasurementUnitModel $unit, UnitSW $unitSW)
    {
        $translationUtil = new TranslationUtil();
        foreach ($unit->getI18ns() as $i18n) {
            if ($i18n->getLanguageISO() !== LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                $locale = LocaleUtil::getByKey(LanguageUtil::map(null, null, $i18n->getLanguageISO()));

                if ($locale === null) {
                    Logger::write(sprintf('Could not find any locale for (%s)', $i18n->getLanguageISO()), Logger::WARNING, 'database');

                    continue;
                }

                $translationUtil->write(
                    $locale->getId(),
                    'config_units',
                    $unitSW->getId(),
                    array(
                        'name' => $i18n->getName(),
                        'unit' => ''
                    ),
                    true
                );
            }
        }
    }

    protected function findName(MeasurementUnitModel $unit)
    {
        $name = '';
        foreach ($unit->getI18ns() as $i18n) {
            if ($i18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                $name = $i18n->getName();
            }
        }

        return $name;
    }
}
