<?php
namespace jtl\Connector\Shopware\Mapper;

class MapperFactory
{
    public const
        NAMESPACE_MAPPER = "\\jtl\\Connector\\Shopware\\Mapper\\";

    /**
     * @param string $class
     * @param boolean $useNamespace
     * @return string
     * @throws \Exception
     */
    public function getMapper(string $class, bool $useNamespace = false)
    {
        if (class_exists(self::NAMESPACE_MAPPER . $class)) {
            if ($useNamespace) {
                return self::NAMESPACE_MAPPER . $class;
            } else {
                $class = self::NAMESPACE_MAPPER . $class;

                return $class::getInstance();
            }
        }

        throw new \Exception("Mapper '" . self::NAMESPACE_MAPPER . $class . "' not found");
    }
}