<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use jtl\Connector\Model\Identity;
use jtl\Connector\Result\Action;
use jtl\Connector\Core\Rpc\Error;
use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Core\Utilities\DataConverter;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;

/**
 * Manufacturer Controller
 * @access public
 */
class Manufacturer extends DataController
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
            $limit  = $queryFilter->isLimit() ? $queryFilter->getLimit() : 100;

            $mapper        = Mmc::getMapper('Manufacturer');
            $manufacturers = $mapper->findAll($limit);
            foreach ($manufacturers as $manufacturerSW) {
                try {
                    $manufacturer = Mmc::getModel('Manufacturer');
                    $manufacturer->map(true, DataConverter::toObject($manufacturerSW));

                    // ManufacturerI18n
                    $this->addPos($manufacturer, 'addI18n', 'ManufacturerI18n', $manufacturerSW);
                    if (isset($manufacturerSW['translations'])) {
                        foreach ($manufacturerSW['translations'] as $localeName => $translation) {
                            $manufacturerI18n = Mmc::getModel('ManufacturerI18n');
                            $manufacturerI18n->setLanguageISO(LanguageUtil::map($localeName))
                                ->setManufacturerId(new Identity($manufacturerSW['id']))
                                ->setDescription($translation['description'])
                                ->setMetaDescription($translation['metaDescription'])
                                ->setMetaKeywords($translation['metaKeywords'])
                                ->setTitleTag($translation['metaTitle']);

                            $manufacturer->addI18n($manufacturerI18n);
                        }
                    }

                    $result[] = $manufacturer;
                } catch (\Exception $exc) {
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
            }

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
