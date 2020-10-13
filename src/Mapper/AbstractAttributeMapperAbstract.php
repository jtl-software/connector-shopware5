<?php
namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Shopware\Utilities\Str;
use Shopware\Bundle\AttributeBundle\Service\ConfigurationStruct;
use Shopware\Bundle\AttributeBundle\Service\TypeMapping;
use Shopware\Components\Model\ModelEntity;

abstract class AbstractAttributeMapperAbstract extends AbstractDataMapper
{

    /**
     * @param ConfigurationStruct $tSwAttribute
     * @param ModelEntity $swAttribute
     * @param array $attributes
     * @param bool $nullUndefinedAttributes
     * @return bool
     */
    public function setSwAttribute(ConfigurationStruct $tSwAttribute, ModelEntity $swAttribute, array &$attributes, bool $nullUndefinedAttributes)
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