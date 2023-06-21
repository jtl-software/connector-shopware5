<?php

namespace jtl\Connector\Shopware\Utilities;

/**
 * Class KeyValueAttributes
 * @package jtl\Connector\Shopware\Utilities
 */
class KeyValueAttributes extends Attributes
{
    /**
     * @param $key
     * @param $value
     */
    public function addAttribute($key, $value)
    {
        if (isset($this->attributes[$key])) {
            $attribute = $this->attributes[$key];
        } else {
            $class     = $this->attributeClass;
            $attribute = new $class();
            $attribute->setKey($key);
        }

        $attribute->setValue((string)$value);
        $this->attributes[$key] = $attribute;
    }

    /**
     * @param $key
     * @return string
     */
    public function getValue($key)
    {
        return isset($this->attributes[$key]) === false ? "" : $this->attributes[$key]->getValue();
    }
}
