<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Utilities\Seo;
use jtl\Connector\Model\Specific as SpecificModel;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Model\Identity;
use jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use Shopware\Models\Property\Option as OptionSW;
use Shopware\Models\Property\Value as ValueSW;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;

class Specific extends AbstractDataMapper
{
    public function find($id)
    {
        return (intval($id) == 0) ? null : $this->getManager()->getRepository('Shopware\Models\Property\Option')->find($id);
    }

    public function findOneBy(array $kv)
    {
        return $this->getManager()->getRepository('Shopware\Models\Property\Option')->findOneBy($kv);
    }

    public function findValue($id)
    {
        return (intval($id) == 0) ? null : $this->getManager()->getRepository('Shopware\Models\Property\Value')->find($id);
    }

    public function findValueBy(array $kv)
    {
        return $this->getManager()->getRepository('Shopware\Models\Property\Value')->findOneBy($kv);
    }

    public function findAll($limit = 100, $count = false)
    {
        $query = $this->getManager()->createQueryBuilder()->select(
                'option',
                'values'
            )
            //->from('Shopware\Models\Property\Option', 'option')
            //->leftJoin('jtl\Connector\Shopware\Model\ConnectorLink', 'link', \Doctrine\ORM\Query\Expr\Join::WITH, 'option.id = link.endpointId AND link.type = 77')
            ->from('jtl\Connector\Shopware\Model\Linker\Specific', 'option')
            ->leftJoin('option.linker', 'linker')
            ->leftJoin('option.values', 'values')
            ->where('linker.hostId IS NULL')
            ->setFirstResult(0)
            ->setMaxResults($limit)
            //->getQuery();
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        //$options = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        if ($count) {
            //return count($options);
            return $paginator->count();
        } else {
            $options = iterator_to_array($paginator);

            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null, null);

            $translationService = ShopUtil::translationService();
            for ($i = 0; $i < count($options); $i++) {
                foreach ($shops as $shop) {
                    $translationOption = $translationService->read($shop['locale']['id'], 'propertyoption', $options[$i]['id']);
                    if (!empty($translationOption)) {
                        $translationOption['shopId'] = $shop['id'];
                        $translationOption['name'] = $translationOption['optionName'];
                        $options[$i]['translations'][$shop['locale']['locale']] = $translationOption;
                    }

                    for ($j = 0; $j < count($options[$i]['values']); $j++) {
                        foreach ($shops as $shop) {
                            $translationValue = $translationService->read($shop['locale']['id'], 'propertyvalue', $options[$i]['values'][$j]['id']);
                            if (!empty($translationValue)) {
                                $translationValue['shopId'] = $shop['id'];
                                $translationValue['name'] = $translationValue['optionValue'];
                                $options[$i]['values'][$j]['translations'][$shop['locale']['locale']] = $translationValue;
                            }
                        }
                    }
                }
            }

            return $options;
        }
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function delete(SpecificModel $specific)
    {
        $result = new SpecificModel;

        $this->deleteSpecificData($specific);

        // Result
        $result->setId(new Identity('', $specific->getId()->getHost()));

        return $result;
    }

    public function save(SpecificModel $specific)
    {
        $optionSW = null;
        $result = $specific;

        $this->prepareSpecificAssociatedData($specific, $optionSW);
        $this->prepareI18nAssociatedData($specific, $optionSW);
		$this->prepareValueAssociatedData($specific, $optionSW);

        // Save
		$this->getManager()->persist($optionSW);
		$this->getManager()->flush();

        $this->deleteTranslationData($optionSW);
        $this->saveTranslationData($specific, $optionSW);
		
        // Result
        foreach ($optionSW->getValues() as $valueSW) {
            $values = $result->getValues();
            foreach ($values as &$value) {
                foreach ($value->getI18ns() as $valueI18n) {
                    if (ShopUtil::isShopwareDefaultLanguage($valueI18n->getLanguageISO())
                        && $valueI18n->getValue() === $valueSW->getValue()) {

                        $value->getId()->setEndpoint($valueSW->getId());
                    }
                }
            }
        }

        $result->setId(new Identity($optionSW->getId(), $specific->getId()->getHost()));

        return $result;
    }

    protected function deleteSpecificData(SpecificModel $specific)
    {
        $specificId = (strlen($specific->getId()->getEndpoint()) > 0) ? (int)$specific->getId()->getEndpoint() : null;

        if ($specificId !== null && $specificId > 0) {
            $optionSW = $this->find($specificId);
            if ($optionSW !== null) {
                foreach ($optionSW->getValues() as $valueSW) {
                    $this->deleteValueTranslationData($valueSW);
                    $this->getManager()->remove($valueSW);
                }

                $this->deleteTranslationData($optionSW);
                $this->getManager()->remove($optionSW);

                foreach ($optionSW->getGroups() as $groupSW) {
                    if (count($groupSW->getOptions()) == 1) {
                        $sql = "UPDATE s_articles SET filtergroupID = null WHERE filtergroupID = ?";
                        Shopware()->Db()->query($sql, array($groupSW->getId()));
                        
                        $this->getManager()->remove($groupSW);
                    }
                }

                $this->getManager()->flush();
            }
        }
    }

    protected function prepareSpecificAssociatedData(SpecificModel $specific, OptionSW &$optionSW = null)
    {
        $specificId = (strlen($specific->getId()->getEndpoint()) > 0) ? (int)$specific->getId()->getEndpoint() : null;

        if ($specificId !== null && $specificId > 0) {
            $optionSW = $this->find($specificId);
        }


        if ($optionSW === null) {
            $name = null;
            foreach ($specific->getI18ns() as $i18n) {
                if (ShopUtil::isShopwareDefaultLanguage($i18n->getLanguageISO())) {
                    $name = $i18n->getName();
                }
            }

            if ($name !== null) {
                $optionSW = $this->findOneBy(array('name' => $name));
            }
        }

        if ($optionSW === null) {
            $optionSW = new OptionSW;
            $this->getManager()->persist($optionSW);
        }

        $optionSW->setFilterable($specific->getIsGlobal());
    }

    protected function prepareI18nAssociatedData(SpecificModel $specific, OptionSW &$optionSW)
    {
        // SpecificI18n
        foreach ($specific->getI18ns() as $i18n) {
            if (ShopUtil::isShopwareDefaultLanguage($i18n->getLanguageISO())) {
                $optionSW->setName($i18n->getName());
            }
        }
    }

    protected function prepareValueAssociatedData(SpecificModel $specific, OptionSW &$optionSW)
    {
        // SpecificValues
        $optionSW->getValues()->clear();
        $values = array();
        $seo = new Seo();
        foreach ($specific->getValues() as $specificValue) {
            $valueSW = null;

            if (strlen($specificValue->getId()->getEndpoint()) > 0) {
                $valueSW = $this->findValue(intval($specificValue->getId()->getEndpoint()));
            }

            // SpecificValueI18n
            $value = null;
            foreach ($specificValue->getI18ns() as $i18n) {
                if (ShopUtil::isShopwareDefaultLanguage($i18n->getLanguageISO())) {
                    $value = $i18n->getValue();
                }
            }

            // Check
            if ($value === null || in_array(strtolower($seo->replaceDiacritics($value)), $values)) {
                continue;
            }

            $values[] = strtolower($seo->replaceDiacritics($value));

            if ($valueSW === null && $optionSW->getId() > 0) {
                $valueSW = $this->findValueBy(array('option' => $optionSW->getId(), 'value' => $value));
            }
            
            if ($valueSW === null) {
                $valueSW = new ValueSW($optionSW, $value);
            } else {
                $valueSW->setValue($value);
            }

            $valueSW->setPosition($specificValue->getSort())
                ->setOption($optionSW);

            $this->getManager()->persist($valueSW);
            $optionSW->getValues()->add($valueSW);
        }
    }

    protected function saveTranslationData(SpecificModel $specific, OptionSW &$optionSW)
    {
        // SpecificI18n
        $translationService = ShopUtil::translationService();
        $shopMapper = Mmc::getMapper('Shop');
        foreach ($specific->getI18ns() as $i18n) {
            $locale = LocaleUtil::getByKey(LanguageUtil::map(null,null, $i18n->getLanguageISO()));

            if ($locale === null) {
                Logger::write(sprintf('Could not find any locale for (%s)', $i18n->getLanguageISO()), Logger::WARNING, 'database');
                continue;
            }

            $language = LocaleUtil::extractLanguageIsoFromLocale($locale->getLocale());
            $shops = $shopMapper->findByLanguageIso($language);

            if (ShopUtil::isShopwareDefaultLanguage($i18n->getLanguageISO()) === false) {
                foreach ($shops as $shop) {
                    $translationService->write(
                        $shop->getId(),
                        'propertyoption',
                        $optionSW->getId(),
                        array(
                            'optionName' => $i18n->getName()
                        )
                    );
                }
            }
        }

        foreach ($optionSW->getValues() as $valueSW) {
            foreach ($specific->getValues() as $value) {
                $valueId = null;
                foreach ($value->getI18ns() as $valueI18n) {
                    if ($valueI18n->getValue() === $valueSW->getValue()
                        && ShopUtil::isShopwareDefaultLanguage($valueI18n->getLanguageISO())) {

                        $valueId = $valueSW->getId();
                        continue;
                    }

                    if ($valueId !== null && ShopUtil::isShopwareDefaultLanguage($valueI18n->getLanguageISO()) === false) {
                        $locale = LocaleUtil::getByKey(LanguageUtil::map(null,null, $valueI18n->getLanguageISO()));
                        if ($locale !== null) {
                            $language = LocaleUtil::extractLanguageIsoFromLocale($locale->getLocale());
                            $shops = $shopMapper->findByLanguageIso($language);

                            foreach ($shops as $shop) {
                                $translationService->write(
                                    $shop->getId(),
                                    'propertyvalue',
                                    $valueId,
                                    array(
                                        'optionValue' => $valueI18n->getValue()
                                    )
                                );
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param OptionSW $optionSW
     */
    protected function deleteTranslationData(OptionSW &$optionSW)
    {
        $translationService = ShopUtil::translationService();
        $translationService->deleteAll('propertyoption', $optionSW->getId());

        foreach ($optionSW->getValues() as $valueSW) {
            $translationService->deleteAll('propertyvalue', $valueSW->getId());
        }
    }

    /**
     * @param ValueSW $valueSW
     */
    protected function deleteValueTranslationData(ValueSW &$valueSW)
    {
        ShopUtil::translationService()->deleteAll('propertyvalue', $valueSW->getId());
    }
}
