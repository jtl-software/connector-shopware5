<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Core\Logger\Logger;

class Config extends DataMapper
{
    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Config\Element')->findOneBy($kv);
    }

    public function get($key)
    {
        return Shopware()->Config()->get($key);
    }

    public function update(array $kv, $data, $shopId)
    {
        $element = $this->findOneBy($kv);
        if ($element) {
            foreach ($element->getValues() as $value) {
                if ($value->getShop()->getId() == $shopId) {
                    $value->setValue(serialize($data));

                    $this->Manager()->persist($value);
                    $this->Manager()->flush();
                }
            }
        }
    }
}
