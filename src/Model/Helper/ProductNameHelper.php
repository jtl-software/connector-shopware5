<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model\Helper;

use JMS\Serializer\Annotation as Serializer;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;

/**
 * ProductName Model
 * @Serializer\AccessType("public_method")
 * @access public
 */
class ProductNameHelper
{
    protected static $cache = array();

    /**
     * @Serializer\Type("string")
     * @Serializer\SerializedName("productName")
     * @Serializer\Accessor(getter="getProductName",setter="setProductName")
     */
    protected $productName = '';

     /**
     * @Serializer\Type("string")
     * @Serializer\SerializedName("additionalName")
     * @Serializer\Accessor(getter="getAdditionalName",setter="setAdditionalName")
     */
    protected $additionalName = '';

     /**
     * @Serializer\Type("array<string>")
     * @Serializer\SerializedName("valueNames")
     * @Serializer\Accessor(getter="getValueNames",setter="setValueNames")
     */
    protected $valueNames = array();

    /**
     * Gets the value of productName.
     *
     * @return string
     */
    public function getProductName()
    {
        return $this->productName;
    }

    /**
     * Sets the value of productName.
     *
     * @param Identity Reference to product $productName the product name
     *
     * @return self
     */
    public function setProductName($productName)
    {
        $this->productName = $productName;

        return $this;
    }

    /**
     * @return bool
     */
    public function isProductName()
    {
        return (strlen($this->productName) > 0);
    }

    /**
     * Gets the value of additionalName.
     *
     * @return string
     */
    public function getAdditionalName()
    {
        return $this->additionalName;
    }

    /**
     * Sets the value of additionalName.
     *
     * @param string $additionalName the additional name
     *
     * @return self
     */
    public function setAdditionalName($additionalName)
    {
        $this->additionalName = $additionalName;

        return $this;
    }

    /**
     * Gets the value of valueNames.
     *
     * @return array
     */
    public function getValueNames()
    {
        return $this->valueNames;
    }

    /**
     * Sets the value of valueNames.
     *
     * @param array $valueNames the value names
     *
     * @return self
     */
    public function setValueNames(array $valueNames)
    {
        $this->valueNames = $valueNames;

        return $this;
    }

    public function addValueName($valueName)
    {
        $this->valueNames[] = $valueName;

        if ($this->isProductName()) {
            $this->setAdditionalName(implode(' / ', $this->getValueNames()));
            //$this->setProductName(str_replace($this->getAdditionalName(), '', $this->getProductName()));
        }
    }

    public static function build(\jtl\Connector\Model\Product $product, $languageIso = null)
    {
        $lang = LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale());
        if ($languageIso !== null) {
            $lang = $languageIso;
        }

        $index = $product->getId()->getHost();
        if (isset(self::$cache[$index][$lang])) {
            return self::$cache[$index][$lang];
        }

        $helper = new ProductNameHelper();
        foreach ($product->getI18ns() as $productI18n) {
            if ($productI18n->getLanguageISO() === $lang) {
                $helper->setProductName($productI18n->getName());
            }
        }

        foreach ($product->getVariations() as $variation) {
            foreach ($variation->getValues() as $variationValue) {
                foreach ($variationValue->getI18ns() as $variationValueI18n) {
                    if ($variationValueI18n->getLanguageISO() === $lang) {
                        $helper->addValueName($variationValueI18n->getName());
                    }
                }
            }
        }

        if (!array_key_exists($index, self::$cache)) {
            self::$cache[$index] = array();
        }

        self::$cache[$index][$lang] = $helper;

        return $helper;
    }
}
