<?php
namespace jtl\Connector\Shopware\Utilities;

/**
 * Class Attributes
 * @package jtl\Connector\Shopware\Utilities
 */
abstract class Attributes
{
    /**
     * @var string
     */
    protected $attributeClass = "";

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * Attributes constructor.
     * @param $attributeClass
     */
    public function __construct($attributeClass)
    {
        $this->attributeClass = $attributeClass;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return array_values($this->attributes);
    }

    /**
     * @param $key
     * @return bool|mixed
     */
    public function getAttributeByKey($key)
    {
        return isset($this->attributes[$key]) ? $this->attributes[$key] : false;
    }
}