<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use \jtl\Connector\Result\Action;
use \jtl\Connector\Core\Rpc\Error;
use \jtl\Connector\Core\Utilities\DataInjector;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Core\Utilities\DataConverter;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Core\Model\QueryFilter;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;
use \jtl\Connector\Shopware\Utilities\Translation as TranslationUtil;

/**
 * GlobalData Controller
 * @access public
 */
class GlobalData extends DataController
{
    /**
     * Pull
     *
     * @param \jtl\Connector\Core\Model\QueryFilter $queryFilter
     * @return \jtl\Connector\Result\Action
     */
    public function pull(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);
        
        try {
            $result = array();
            $limit = $queryFilter->isLimit() ? $queryFilter->getLimit() : 100;

            $globalData = Mmc::getModel('GlobalData');

            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null);

            $builder = Shopware()->Models()->createQueryBuilder();

            foreach ($shops as $shop) {
                $shop['locale']['default'] = (intval($shop['default']) == 1);
                $shop['customerGroup']['localeName'] = $shop['locale']['locale'];

                // Languages
                $language = Mmc::getModel('Language');
                $language->map(true, DataConverter::toObject($shop['locale'], true));

                $globalData->addLanguage($language);

                // Currencies
                if (isset($shop['currencies']) && is_array($shop['currencies'])) {
                    foreach ($shop['currencies'] as $currencySW) {
                        $currencySW['default'] = (bool)$currencySW['default'];
                        $currencySW['hasCurrencySignBeforeValue'] = ($currencySW['position'] == 32) ? true : false;

                        $currency = Mmc::getModel('Currency');
                        $currency->map(true, DataConverter::toObject($currencySW, true));

                        $globalData->addCurrency($currency);
                    }
                }
            }

            // CustomerGroups
            $mapper = Mmc::getMapper('CustomerGroup');
            $customerGroupSWs = $mapper->findAll($limit);

            for ($i = 0; $i < count($customerGroupSWs); $i++) {
                $customerGroupSWs[$i]['taxInput'] = !(bool)$customerGroupSWs[$i]['taxInput'];
                $customerGroupSWs[$i]['isDefault'] = ($customerGroupSWs[$i]['id'] === Shopware()->Shop()->getCustomerGroup()->getId()) ? true : false;
            }

            DataInjector::inject(DataInjector::TYPE_ARRAY, $customerGroupSWs, 'localeName', Shopware()->Shop()->getLocale()->getLocale(), true);
            foreach ($customerGroupSWs as $customerGroupSW) {
                $customerGroup = Mmc::getModel('CustomerGroup');
                $customerGroup->map(true, DataConverter::toObject($customerGroupSW, true));

                $customerGroupI18n = Mmc::getModel('CustomerGroupI18n');
                $customerGroupI18n->map(true, DataConverter::toObject($customerGroupSW, true));

                $customerGroup->addI18n($customerGroupI18n);
                $globalData->addCustomerGroup($customerGroup);
            }

            // CustomerGroupAttrs

            // CrossSellingGroups

            // Units
            $mapper = Mmc::getMapper('Unit');
            $unitSWs = $mapper->findAll($limit);

            DataInjector::inject(DataInjector::TYPE_ARRAY, $unitSWs, 'localeName', Shopware()->Shop()->getLocale()->getLocale(), true);
            foreach ($unitSWs as $unitSW) {
                $unit = Mmc::getModel('Unit');
                $unit->map(true, DataConverter::toObject($unitSW, true));

                $unitI18n = Mmc::getModel('UnitI18n');
                $unitI18n->map(true, DataConverter::toObject($unitSW, true));

                $unit->addI18n($unitI18n);
                $globalData->addUnit($unit);
            }

            // Measurement Units
            $mapper = Mmc::getMapper('MeasurementUnit');
            $measurementUnitSWs = $mapper->findAll($limit);

            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null, null);

            $translationUtil = new TranslationUtil();
            $translations = array();
            foreach ($shops as $shop) {
                $translation = $translationUtil->read($shop['locale']['id'], 'config_units');
                if (!empty($translation)) {
                    $translations[$shop['locale']['locale']] = $translation;
                }
            }

            foreach ($measurementUnitSWs as $measurementUnitSW) {
                $measurementUnit = Mmc::getModel('MeasurementUnit');
                $measurementUnit->map(true, DataConverter::toObject($measurementUnitSW, true));

                if (count($translations) > 0) {
                    foreach ($translations as $localeName => $translation) {
                        foreach ($translation as $id => $trans) {
                            if ($id === $measurementUnitSW['id']) {
                                $measurementUnitI18n = Mmc::getModel('MeasurementUnitI18n');
                                $measurementUnitI18n->setLanguageISO(LanguageUtil::map($localeName))
                                    ->setMeasurementUnitId($measurementUnit->getId())
                                    ->setName($trans['description']);

                                $measurementUnit->addI18n($measurementUnitI18n);
                            }
                        }
                    }
                }

                $globalData->addMeasurementUnit($measurementUnit);
            }

            // TaxZones

            // TaxZoneCountries

            // TaxClasss

            // TaxRates
            $mapper = Mmc::getMapper('TaxRate');
            $taxSWs = $mapper->findAll($limit);

            foreach ($taxSWs as $taxSW) {
                $taxSW['tax'] = (float) $taxSW['tax'];
                $tax = Mmc::getModel('TaxRate');
                $tax->map(true, DataConverter::toObject($taxSW, true));

                $globalData->addTaxRate($tax);
            }

            // Shipping
            $mapper = Mmc::getMapper('Shipping');
            $shippingMethodSWs = $mapper->findAll($limit);

            foreach ($shippingMethodSWs as $shippingMethodSW) {
                $shippingMethod = Mmc::getModel('ShippingMethod');
                $shippingMethod->map(true, DataConverter::toObject($shippingMethodSW, true));

                $globalData->addShippingMethod($shippingMethod);
            }
            
            $result[] = $globalData;

            $action->setResult($result);
        } catch (\Exception $exc) {
            Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }
}
