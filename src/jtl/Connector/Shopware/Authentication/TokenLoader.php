<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Authentication;

use \jtl\Connector\Authentication\ITokenLoader;
use \jtl\Connector\Core\Exception\ConfigException;

class TokenLoader implements ITokenLoader
{
    public function load()
    {
        $token = null;
        
        $form = Shopware()->Models()->getRepository('Shopware\Models\Config\Form')->findOneBy(array('name' => 'jtlconnector'));
        if ($form === null) {
            throw new ConfigException('Could not find any config form with name (jtlconnector)');
        }

        $element = $form->getElement('auth_token');
        if ($element === null) {
            throw new ConfigException('Could not find any config element with name (auth_token)');
        }

        $values = $element->getValues();
        if ($values !== null && count($values) == 1) {
            $token = $values[0]->getValue();
            //throw new ConfigException('Config element has no values');
        } else {
            $token = $element->getValue();
        }
        
        if ($token === null || strlen(trim($token)) == 0) {
            throw new ConfigException('Config token value is empty');
        }

        return trim($token);

        //return Application()->getConnector()->getConfig()->read('auth_token');
    }
}