<?php
namespace jtl\Connector\Shopware\Utilities;

use jtl\Connector\Core\Exception\LanguageException;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Model\Identity;
use Shopware\Bundle\AttributeBundle\Service\ConfigurationStruct;
use Shopware\Bundle\AttributeBundle\Service\TypeMapping;
use Shopware\Components\Model\ModelEntity;

/**
 * Class TranslatableAttributes
 * @package jtl\Connector\Shopware\Utilities
 */
class TranslatableAttributes extends Attributes
{
    /**
     * @var string
     */
    protected $translatableClass = "";

    /**
     * TranslatableAttributes constructor.
     * @param $attributeClass
     * @param $translatableClass
     */
    public function __construct($attributeClass, $translatableClass)
    {
        $this->translatableClass = $translatableClass;
        parent::__construct($attributeClass);
    }

    /**
     * @param $id
     * @param bool $isTranslated
     * @return mixed
     */
    public function addAttribute($id, $isTranslated = false)
    {
        if (isset($this->attributes[$id])) {
            $attribute = $this->attributes[$id];
        } else {
            $attribute = new $this->attributeClass;
            $attribute->setId(new Identity($id));
        }

        $attribute->setIsTranslated($isTranslated);
        $this->attributes[$id] = $attribute;

        return $attribute;
    }

    /**
     * @param $id
     * @param $name
     * @param $value
     * @param $languageIso
     */
    public function addAttributeTranslation($id, $name, $value, $languageIso = "")
    {
        if ($attribute = $this->getAttributeByKey($id)) {
            $attributeI18n = new $this->translatableClass;
            $attributeI18n->setName($name);
            $attributeI18n->setValue((string)$value);
            $attributeI18n->setLanguageISO($languageIso);

            $attribute->addI18n($attributeI18n);
        }
    }

    /**
     * @param $attrId
     * @param $attrName
     * @param array $translations
     * @throws LanguageException
     */
    public function addTranslations($attrId, $attrName, array $translations)
    {
        if (isset($translations)) {
            foreach ($translations as $localeName => $translation) {
                $index = sprintf('__attribute_%s', Str::snake($attrName, '_'));
                if (!isset($translation[$index])) {
                    continue;
                }

                $this->addAttribute($attrId, true);
                $this->addAttributeTranslation(
                    $attrId,
                    $attrName,
                    $translation[$index],
                    LanguageUtil::map($localeName)
                );
            }
        }
    }

    /**
     * @param ConfigurationStruct $tSwAttribute
     * @param ModelEntity $swAttribute
     * @param array $attributes
     * @param bool $nullUndefinedAttributes
     * @return bool
     */
    public static function setAttribute(ConfigurationStruct $tSwAttribute, ModelEntity $swAttribute, array &$attributes, bool $nullUndefinedAttributes): bool
    {
        if (!$tSwAttribute->isIdentifier()) {
            $setter = sprintf('set%s', ucfirst(Str::camel($tSwAttribute->getColumnName())));
            if (isset($attributes[$tSwAttribute->getColumnName()]) && method_exists($swAttribute, $setter)) {
                $value = $attributes[$tSwAttribute->getColumnName()];
                if (in_array($tSwAttribute->getColumnType(), [TypeMapping::TYPE_DATE, TypeMapping::TYPE_DATETIME])) {
                    try {
                        $value = new \DateTime($value);
                    } catch (\Throwable $ex) {
                        $value = null;
                        Logger::write(ExceptionFormatter::format($ex), Logger::ERROR, 'global');
                    }
                }
                $swAttribute->{$setter}($value);
                unset($attributes[$tSwAttribute->getColumnName()]);

                return true;
            } elseif ($nullUndefinedAttributes && method_exists($swAttribute, $setter)) {
                $swAttribute->{$setter}(null);
            }
        }

        return false;
    }
}