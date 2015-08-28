<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Model
 */

namespace jtl\Connector\Shopware\Model;

use \jtl\Connector\Model\DataModel as ConnectorDataModel;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;

/**
 * DataModel Class
 */
abstract class DataModel
{
    /**
     * Object Mapping
     *
     * @param boolean $toConnector
     */
    public static function map($toConnector = false, \stdClass $obj = null, ConnectorDataModel $original)
    {
        if (!$original instanceof ConnectorDataModel) {
            throw new \InvalidArgumentException('param original is not an instance of \jtl\Connector\Model\DataModel');
        }

        if ($toConnector && $obj === null) {
            throw new \InvalidArgumentException("The second parameter can't be null if the first is true");
        }
    
        if (!$toConnector) {
            $obj = new \stdClass();
        }

        // Get Value
        $getValue = function (array $platformFields, \stdClass $data) use (&$getValue) {
            if (count($platformFields) > 1) {
                $value = array_shift($platformFields);
                
                return is_object($data->{$value}) ? $getValue($platformFields, $data->{$value}) : $data->{$value};
            } else {
                $value = array_shift($platformFields);
                
                return $data->{$value};
            }
        };

        // Set Value
        $setValue = function (array $platformFields, $value, \stdClass $obj) use (&$setValue) {
            if (count($platformFields) > 1) {
                $field = array_shift($platformFields);

                if (!isset($obj->{$field})) {
                    $obj->{$field} = new \stdClass;
                }
                
                return $setValue($platformFields, $value, $obj->{$field});
            } else {
                $field = array_shift($platformFields);
                $obj->{$field} = $value;
                
                return $obj;
            }
        };

        // Typecast
        $typeCast = function (ConnectorDataModel $model, $property, $value) {
            if (($propertyInfo = $model->getModelType()->getProperty($property)) !== null) {
                if ($propertyInfo->isIdentity() && !($value instanceof Identity)) {
                    return new Identity($value);
                } else {
                    switch ($propertyInfo->getType()) {
                        case 'integer':
                        case 'int':
                            return (int) $value;
                        case 'float':
                        case 'double':
                            return (float) $value;
                        case 'string':
                            return trim((string) $value);
                        case 'boolean':
                        case 'bool':
                            return (bool) $value;
                    }
                }
            }

            return $value;
        };

        foreach ($original->getFields() as $connectorField => $platformField) {
            $property = ucfirst($connectorField);
            $setter = 'set' . $property;
            $getter = 'get' . $property;

            if ($connectorField !== 'languageISO' && !is_array($platformField) && strlen($platformField) == 0) {
                continue;
            }

            if ($toConnector) {
                if (is_array($platformField)) {
                    $value = $getValue($platformField, $obj);
                    $original->{$setter}($typeCast($original, $connectorField, $value));
                } elseif ($connectorField == 'languageISO' && strlen($platformField) == 0) {
                    $original->{$setter}(LanguageUtil::map(Shopware()->Locale()->toString()));
                } elseif ($connectorField == 'languageISO' && strlen($platformField) > 0) {
                    $original->{$setter}(LanguageUtil::map($obj->{$platformField}));
                } elseif (isset($obj->{$platformField})) {
                    $original->{$setter}($typeCast($original, $connectorField, $obj->{$platformField}));
                }
            } else {
                if (is_array($platformField)) {
                    // TODO: Date Check
                    $setValue($platformField, $original->{$getter}(), $obj);
                } elseif ($original->{$getter}() instanceof Identity) {
                    $obj->{$platformField} = $original->{$getter}()->getEndpoint();
                } elseif ($connectorField == 'languageISO' && strlen($platformField) == 0) {
                    $obj->{$platformField} = LanguageUtil::map(null, null, $original->{$getter}());
                } elseif (strlen($platformField) > 0) {
                    // TODO: Date Check
                    $obj->{$platformField} = $original->{$getter}();
                }
            }
        }

        if ($toConnector) {
            return true;
        } else {
            unset($obj->fields);
            return $obj;
        }
    }
    
    /**
     * Single Field Mapping
     *
     * @param string $fieldName
     * @param \jtl\Connector\Model\DataModel $original
     * @param boolean $toWawi
     * @return string|NULL
     */
    public static function getMappingField($fieldName, ConnectorDataModel &$original, $toWawi = false)
    {
        foreach ($original->getFields() as $shopField => $wawiField) {
            if ($toWawi) {
                if ($shopField === $fieldName) {
                    return $wawiField;
                }
            } else {
                if ($wawiField === $fieldName) {
                    return $shopField;
                }
            }
        }
        
        return null;
    }
}
