<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Model\MeasurementUnit as MeasurementUnitModel;
use jtl\Connector\Shopware\Utilities\Mmc;
use \Shopware\Models\Article\Unit as UnitSW;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;

class MeasurementUnit extends AbstractDataMapper
{
    public function find($id)
    {
        return (intval($id) == 0) ? null : $this->Manager()->getRepository('Shopware\Models\Article\Unit')->find($id);
    }
    
    public function findAll($limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'unit'
            )
            ->from('Shopware\Models\Article\Unit', 'unit')
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

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
            if (ShopUtil::isShopwareDefaultLanguage($i18n->getLanguageISO())) {
                $unitSW->setName($i18n->getName());
            }
        }

        $unitSW->setUnit($unit->getCode());
    }

    protected function saveTranslationData(MeasurementUnitModel $unit, UnitSW $unitSW)
    {
        $translationService = ShopUtil::translationService();
        $translationService->deleteAll('config_units', $unitSW->getId());

        foreach ($unit->getI18ns() as $i18n) {
            $iso = $i18n->getLanguageISO();
            if (ShopUtil::isShopwareDefaultLanguage($iso) !== false) {
                $locale = LanguageUtil::map(null, null, $iso);

                $language = LocaleUtil::extractLanguageIsoFromLocale($locale);
                $shopMapper = Mmc::getMapper('Shop');
                $shops = $shopMapper->findByLanguageIso($language);

                foreach ($shops as $shop) {
                    $translationService->write(
                        $shop->getId(),
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
    }

    protected function findName(MeasurementUnitModel $unit)
    {
        $name = '';
        foreach ($unit->getI18ns() as $i18n) {
            if (ShopUtil::isShopwareDefaultLanguage($i18n->getLanguageISO())) {
                $name = $i18n->getName();
            }
        }

        return $name;
    }
}
