<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Model\GlobalData as GlobalDataModel;

class GlobalData extends DataMapper
{
    public function save(GlobalDataModel $globalData)
    {
        $result = new GlobalDataModel;

        // Companies
        /*
        $configMapper = Mmc::getMapper('Config');
        foreach ($globalData->getCompanies() as $company) {
            $configMapper->update(array('name', 'company'), $company->getName(), Shopware()->Shop()->getId());
            $configMapper->update(array('name', 'address'), $company->getStreet(), Shopware()->Shop()->getId());
            $configMapper->update(array('name', 'mail'), $company->getEMail(), Shopware()->Shop()->getId());
            $configMapper->update(array('name', 'taxNumber'), $company->getTaxIdNumber(), Shopware()->Shop()->getId());
            $configMapper->update(array('name', 'vatcheckadvancednumber'), $company->getVatNumber(), Shopware()->Shop()->getId());
        }
        */

        // Currencies
        $currencyMapper = Mmc::getMapper('Currency');
        foreach ($globalData->getCurrencies() as $currency) {
            $currencyResult = $currencyMapper->save($currency);
            $result->addCurrency($currencyResult);
        }

        // CustomerGroups
        $customerGroupMapper = Mmc::getMapper('CustomerGroup');
        foreach ($globalData->getCustomerGroups() as $customerGroup) {
            $customerGroupResult = $customerGroupMapper->save($customerGroup);
            $result->addCustomerGroup($customerGroupResult);
        }

        // Units
        $unitMapper = Mmc::getMapper('Unit');
        foreach ($globalData->getUnits() as $unit) {
            $unitResult = $unitMapper->save($unit);
            $result->addUnit($unitResult);
        }

        // MeasurementUnits
        $measurementUnitMapper = Mmc::getMapper('MeasurementUnit');
        foreach ($globalData->getMeasurementUnits() as $measurementUnit) {
            $measurementUnitResult = $measurementUnitMapper->save($measurementUnit);
            $result->addMeasurementUnit($measurementUnitResult);
        }
        
        // TaxRates
        $taxRateMapper = Mmc::getMapper('TaxRate');
        foreach ($globalData->getTaxRates() as $taxRate) {
            $taxRateResult = $taxRateMapper->save($taxRate);
            $result->addTaxRate($taxRateResult);
        }
        
        return $result->getPublic();
    }
}
