<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use \jtl\Connector\Result\Action;
use \jtl\Connector\Core\Rpc\Error;
use \jtl\Connector\Core\Model\QueryFilter;
use \jtl\Connector\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Utilities\Salutation;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;

/**
 * Customer Controller
 * @access public
 */
class Customer extends DataController
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

            $mapper = Mmc::getMapper('Customer');
            $customers = $mapper->findAll($limit);

            foreach ($customers as $customerSW) {
                try {
                    $customerSW['newsletter'] = (bool)$customerSW['newsletter'];
                    $customer = Mmc::getModel('Customer');
                    $customer->map(true, DataConverter::toObject($customerSW, true));
    
                    /**
                     * 0 => normal account ("don't create customer account" wasn't checked)<br>
                     * 1 => hidden account ("don't create customer account" was checked)
                     */
                    $customer->setHasCustomerAccount((int) $customerSW['accountMode'] == 0);

                    $country = Shopware()->Models()->getRepository('Shopware\Models\Country\Country')
                        ->findOneById($customerSW['billing']['countryId']);

                    $iso = ($country !== null) ? $country->getIso() : 'DE';
                    
                    // Birthday
                    try {
                        if (isset($customerSW['birthday']) && !($customerSW['birthday'] instanceof \DateTime)
                            && strlen($customerSW['birthday']) > 0
                            && $customerSW['birthday'] !== '0000-00-00') {
                            $customer->setBirthday(new \DateTime($customerSW['birthday']));
                        } elseif (isset($customerSW['birthday']) && $customerSW['birthday'] instanceof \DateTime
                            && $customerSW['birthday'] != new \DateTime('0000-00-00')) {
                            $customer->setBirthday($customerSW['birthday']);
                        }
                    } catch (\Exception $e) { }

                    // Salutation
                    $customer->setSalutation(Salutation::toConnector($customer->getSalutation()))
                        ->setCountryIso($iso)
                        ->setLanguageISO(LanguageUtil::map($customerSW['languageSubShop']['locale']['locale']));

                    // Attributes
                    if (isset($customerSW['billing']['attribute']) && is_array($customerSW['billing']['attribute'])) {
                        for ($i = 1; $i <= 6; $i++) {
                            if (isset($customerSW['billing']['attribute']["text{$i}"]) && strlen(trim($customerSW['billing']['attribute']["text{$i}"]))) {
                                $customerAttr = Mmc::getModel('CustomerAttr');
                                $customerAttr->map(true, DataConverter::toObject($customerSW['billing']['attribute']));
                                $customerAttr->setCustomerId($customer->getId())
                                    ->setKey("text{$i}")
                                    ->setValue((string) $customerSW['billing']['attribute']["text{$i}"]);

                                $customer->addAttribute($customerAttr);
                            }
                        }
                    }

                    //$result[] = $customer->getPublic();
                    $result[] = $customer;
                } catch (\Exception $exc) {
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');
                }
            }

            $action->setResult($result);
        } catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }
}
