<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Core\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Connector\Model\Specific as SpecificModel;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Utilities\Translation as TranslationUtil;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use \Shopware\Models\Property\Option as OptionSW;
use \Shopware\Models\Property\Value as ValueSW;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;

class Specific extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Property\Option')->find($id);
    }

    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Property\Option')->findOneBy($kv);
    }

    public function findValue($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Property\Value')->find($id);
    }

    public function findValueBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Property\Value')->findOneBy($kv);
    }

    public function findAll($limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
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

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = false);

        //$options = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        if ($count) {
            //return count($options);
            return $paginator->count();
        } else {
            $options = iterator_to_array($paginator);

            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null, null);

            $translation = new TranslationUtil;
            for ($i = 0; $i < count($options); $i++) {
                foreach ($shops as $shop) {
                    $translationOption = $translation->read($shop['locale']['id'], 'propertyoption', $options[$i]['id']);
                    if (!empty($translationOption)) {
                        $translationOption['shopId'] = $shop['id'];
                        $translationOption['name'] = $translationOption['optionName'];
                        $options[$i]['translations'][$shop['locale']['locale']] = $translationOption;
                    }

                    for ($j = 0; $j < count($options[$i]['values']); $j++) {
                        foreach ($shops as $shop) {
                            $translationValue = $translation->read($shop['locale']['id'], 'propertyvalue', $options[$i]['values'][$j]['id']);
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
        $this->Manager()->persist($optionSW);
        $this->Manager()->flush();

        $this->deleteTranslationData($optionSW);
        $this->saveTranslationData($specific, $optionSW);

        // Result
        foreach ($optionSW->getValues() as $valueSW) {
            $values = $result->getValues();
            foreach ($values as &$value) {
                foreach ($value->getI18ns() as $valueI18n) {
                    if ($valueI18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())
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
            $specificSW = $this->find($specificId);
            if ($specificSW !== null) {
                foreach ($specificSW->getValues() as $valueSW) {
                    $this->deleteValueTranslationData($valueSW);
                    $this->Manager()->remove($valueSW);
                }

                $this->deleteTranslationData($optionSW);
                $this->Manager()->remove($specificSW);
                $this->Manager()->flush();
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
                if ($i18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                    $name = $i18n->getName();
                }
            }

            if ($name !== null) {
                $optionSW = $this->findOneBy(array('name' => $name));
            }
        }

        if ($optionSW === null) {
            $optionSW = new OptionSW;
            $this->Manager()->persist($optionSW);
        }

        $optionSW->setFilterable(true);
    }

    protected function prepareI18nAssociatedData(SpecificModel $specific, OptionSW &$optionSW)
    {
        // SpecificI18n
        foreach ($specific->getI18ns() as $i18n) {
            if ($i18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                $optionSW->setName($i18n->getName());
            }
        }
    }

    protected function prepareValueAssociatedData(SpecificModel $specific, OptionSW &$optionSW)
    {
        // SpecificValues
        $optionSW->getValues()->clear();
        $values = array();
        foreach ($specific->getValues() as $specificValue) {
            $valueSW = null;

            if (strlen($specificValue->getId()->getEndpoint()) > 0) {
                $valueSW = $this->findValue(intval($specificValue->getId()->getEndpoint()));
            }

            // SpecificValueI18n
            $value = null;
            foreach ($specificValue->getI18ns() as $i18n) {
                if ($i18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                    $value = $i18n->getValue();
                }
            }

            // Check
            if ($value === null || in_array($value, $values)) {
                continue;
            }

            $values[] = $value;

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

            $this->Manager()->persist($valueSW);
            $optionSW->getValues()->add($valueSW);
        }
    }

    protected function saveTranslationData(SpecificModel $specific, OptionSW &$optionSW)
    {
        // SpecificI18n
        $translation = new TranslationUtil;
        foreach ($specific->getI18ns() as $i18n) {
            $locale = LocaleUtil::getByKey(LanguageUtil::map(null,null, $i18n->getLanguageISO()));

            if ($locale === null) {
                Logger::write(sprintf('Could not find any locale for (%s)', $i18n->getLanguageISO()), Logger::WARNING, 'database');

                continue;
            }

            if ($i18n->getLanguageISO() !== LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                $translation->write(
                    $locale->getId(),
                    'propertyoption',
                    $optionSW->getId(),
                    array(
                        'optionName' => $i18n->getName()
                    )
                );
            }
        }

        foreach ($optionSW->getValues() as $valueSW) {
            foreach ($specific->getValues() as $value) {
                $valueId = null;
                foreach ($value->getI18ns() as $valueI18n) {
                    if ($valueI18n->getValue() === $valueSW->getValue()
                        && $valueI18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {

                        $valueId = $valueSW->getId();
                        continue;
                    }

                    if ($valueId !== null && $valueI18n->getLanguageISO() !== LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                        $locale = LocaleUtil::getByKey(LanguageUtil::map(null,null, $valueI18n->getLanguageISO()));
                        if ($locale !== null) {
                            $translation->write(
                                $locale->getId(),
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

    protected function deleteTranslationData(OptionSW &$optionSW)
    {
        $translation = new TranslationUtil;
        $translation->delete('propertyoption', $optionSW->getId());

        foreach ($optionSW->getValues() as $valueSW) {
            $translation->delete('propertyvalue', $valueSW->getId());
        }
    }

    protected function deleteValueTranslationData(ValueSW &$valueSW)
    {
        $translation = new TranslationUtil;
        $translation->delete('propertyvalue', $valueSW->getId());
    }
}
