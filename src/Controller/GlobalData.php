<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use jtl\Connector\Result\Action;
use jtl\Connector\Core\Rpc\Error;
use jtl\Connector\Core\Utilities\DataInjector;

use jtl\Connector\Shopware\Mapper\CrossSellingGroup;
use jtl\Connector\Shopware\Mapper\Currency;
use jtl\Connector\Shopware\Mapper\CustomerGroup;
use jtl\Connector\Shopware\Mapper\MeasurementUnit;
use jtl\Connector\Shopware\Mapper\Shipping;
use jtl\Connector\Shopware\Mapper\Shop;
use jtl\Connector\Shopware\Mapper\TaxRate;
use jtl\Connector\Shopware\Mapper\Unit;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Core\Utilities\DataConverter;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use Shopware\Models\Tax\Rule;

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
            $limit = null;

            /** @var \jtl\Connector\Shopware\Model\GlobalData $globalData */
            $globalData = Mmc::getModel('GlobalData');

            /** @var Shop $shopMapper */
            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null);

            // Currency helpers
            $uniqueCurrencyIds = [];
            $buildCurrency = function (&$globalData, $id, &$uniqueCurrencyIds) {
                $id = (int)$id;

                if (in_array($id, $uniqueCurrencyIds)) {
                    return;
                }

                $uniqueCurrencyIds[] = $id;

                try {
                    /** @var Currency $currencyMapper */
                    $currencyMapper = Mmc::getMapper('Currency');
                    $currencySW = $currencyMapper->find($id, true);

                    if (!is_null($currencySW)) {
                        $currencySW['default'] = (bool)$currencySW['default'];
                        $currencySW['hasCurrencySignBeforeValue'] = ($currencySW['position'] == 32) ? true : false;

                        /** @var \jtl\Connector\Shopware\Model\Currency $currency */
                        $currency = Mmc::getModel('Currency');
                        $currency->map(true, DataConverter::toObject($currencySW, true));

                        $globalData->addCurrency($currency);
                    }
                } catch (\Exception $e) {
                    Logger::write(ExceptionFormatter::format($e, 'Currency'), Logger::ERROR, 'controller');
                }
            };

            $languageIso = [];
            $languages = [];

            foreach ($shops as $shop) {
                $shop['locale']['default'] = (intval($shop['default']) == 1);
                $shop['customerGroup']['localeName'] = $shop['locale']['locale'];

                // Languages
                $language = Mmc::getModel('Language');
                $language->map(true, DataConverter::toObject($shop['locale'], true));

                if (!in_array($language->getLanguageISO(), $languageIso)) {
                    $languageIso[] = $language->getLanguageISO();
                    $languages[] = $language;
                }

                // Currencies
                if (isset($shop['currencies']) && is_array($shop['currencies']) && count($shop['currencies']) > 0) {
                    foreach ($shop['currencies'] as $currencySW) {
                        $buildCurrency($globalData, $currencySW['id'], $uniqueCurrencyIds);
                    }
                } else {
                    $shopSW = $shopMapper->find($shop['id']);
                    if (!is_null($shopSW) && !is_null($shopSW->getCurrency())) {
                        $buildCurrency($globalData, $shopSW->getCurrency()->getId(), $uniqueCurrencyIds);
                    }
                }
            }

            foreach ($languages as $language) {
                $globalData->addLanguage($language);
            }

            /** @var CustomerGroup $mapper */
            $mapper = Mmc::getMapper('CustomerGroup');
            $customerGroupSWs = $mapper->findAll($limit);

            for ($i = 0; $i < count($customerGroupSWs); $i++) {
                $customerGroupSWs[$i]['taxInput'] = !(bool)$customerGroupSWs[$i]['taxInput'];
                $customerGroupSWs[$i]['isDefault'] = ($customerGroupSWs[$i]['id'] === Shopware()->Shop()->getCustomerGroup()->getId()) ? true : false;
            }

            DataInjector::inject(DataInjector::TYPE_ARRAY, $customerGroupSWs, 'localeName', Shopware()->Shop()->getLocale()->getLocale(), true);
            foreach ($customerGroupSWs as $customerGroupSW) {
                try {
                    $customerGroup = Mmc::getModel('CustomerGroup');
                    $customerGroup->map(true, DataConverter::toObject($customerGroupSW, true));

                    $customerGroupI18n = Mmc::getModel('CustomerGroupI18n');
                    $customerGroupI18n->map(true, DataConverter::toObject($customerGroupSW, true));

                    $customerGroup->addI18n($customerGroupI18n);
                    $globalData->addCustomerGroup($customerGroup);
                } catch (\Exception $e) {
                    Logger::write(ExceptionFormatter::format($e, 'CustomerGroup'), Logger::ERROR, 'controller');
                }
            }

            // CustomerGroupAttrs

            /** @var CrossSellingGroup $crossSellingGroupMapper */
            $crossSellingGroupMapper = Mmc::getMapper('CrossSellingGroup');
            $crossSellingGroupSWs = $crossSellingGroupMapper->fetchAll($limit);
            foreach ($crossSellingGroupSWs as $crossSellingGroupSW) {
                $crossSellingGroup = Mmc::getModel('CrossSellingGroup');
                $crossSellingGroup->map(true, DataConverter::toObject($crossSellingGroupSW, true));

                $crossSellingGroup->getId()->setHost((int)$crossSellingGroupSW['host_id']);

                foreach ($crossSellingGroupSW['i18ns'] as $crossSellingGroupI18nSW) {
                    try {
                        $crossSellingGroupI18n = Mmc::getModel('CrossSellingGroupI18n');
                        $crossSellingGroupI18n->map(true, DataConverter::toObject($crossSellingGroupI18nSW, true));

                        $crossSellingGroupI18n->getCrossSellingGroupId()->setHost($crossSellingGroup->getId()->getHost());

                        $crossSellingGroup->addI18n($crossSellingGroupI18n);
                    } catch (\Exception $e) {
                        Logger::write(ExceptionFormatter::format($e, 'CrossSellingGroup'), Logger::ERROR, 'controller');
                    }
                }

                if (count($crossSellingGroup->getI18ns()) == 0) {
                    throw new \Exception('Could not find any crossSelling language');
                }

                $globalData->addCrossSellingGroup($crossSellingGroup);
            }

            /** @var Unit $mapper */
            $mapper = Mmc::getMapper('Unit');
            $unitSWs = $mapper->findAll($limit);

            DataInjector::inject(DataInjector::TYPE_ARRAY, $unitSWs, 'localeName', Shopware()->Shop()->getLocale()->getLocale(), true);
            foreach ($unitSWs as $unitSW) {
                try {
                    $unit = Mmc::getModel('Unit');
                    $unit->map(true, DataConverter::toObject($unitSW, true));

                    $unitI18n = Mmc::getModel('UnitI18n');
                    $unitI18n->map(true, DataConverter::toObject($unitSW, true));

                    $unit->addI18n($unitI18n);
                    $globalData->addUnit($unit);
                } catch (\Exception $e) {
                    Logger::write(ExceptionFormatter::format($e, 'Unit'), Logger::ERROR, 'controller');
                }
            }

            /** @var MeasurementUnit $mapper */
            $mapper = Mmc::getMapper('MeasurementUnit');
            $measurementUnitSWs = $mapper->findAll($limit);

            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null, null);

            $translationService = ShopUtil::translationService();

            $translations = array();
            foreach ($shops as $shop) {
                $translation = $translationService->read($shop['id'], 'config_units');
                if (!empty($translation)) {
                    $translations[$shop['locale']['locale']] = $translation;
                }
            }

            foreach ($measurementUnitSWs as $measurementUnitSW) {
                $measurementUnit = Mmc::getModel('MeasurementUnit');
                $measurementUnit->map(true, DataConverter::toObject($measurementUnitSW, true));

                $measurementUnitI18n = Mmc::getModel('MeasurementUnitI18n');
                $measurementUnitI18n->map(true, DataConverter::toObject($measurementUnitSW, true));
                $measurementUnitI18n->setLanguageISO(LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale()));

                $measurementUnit->addI18n($measurementUnitI18n);

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

            /** @var TaxRate $mapper */
            $mapper = Mmc::getMapper('TaxRate');
            $taxSWs = $mapper->findAll($limit);

            foreach ($taxSWs as $taxSW) {
                $taxSW['tax'] = (float)$taxSW['tax'];
                $tax = Mmc::getModel('TaxRate');
                $tax->map(true, DataConverter::toObject($taxSW, true));

                $globalData->addTaxRate($tax);
            }

            /** @var Shipping $mapper */
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
