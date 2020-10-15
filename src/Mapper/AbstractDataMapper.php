<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Core\Utilities\Singleton;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use Noodlehaus\ConfigInterface;
use Shopware\Components\DependencyInjection\Container;
use Shopware\Components\Model\ModelManager;


abstract class AbstractDataMapper extends Singleton
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var ModelManager
     */
    private $manager;

    /**
     * @var MapperFactory
     */
    private $mapperFactory;

    /**
     * AbstractDataMapper constructor.
     */
    protected function __construct()
    {
        $this->config = Application()->getConfig();
        $this->container = Shopware()->Container();
        $this->manager = ShopUtil::entityManager();
        $this->mapperFactory = new MapperFactory();
    }

    /**
     * @return ConfigInterface
     */
    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    /**
     * @param ConfigInterface $config
     * @return AbstractDataMapper
     */
    public function setConfig(ConfigInterface $config): AbstractDataMapper
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * @param Container $container
     * @return AbstractDataMapper
     */
    public function setContainer(Container $container): AbstractDataMapper
    {
        $this->container = $container;
        return $this;
    }

    /**
     * @return ModelManager
     */
    public function getManager(): ModelManager
    {
        return $this->manager;
    }

    /**
     * @param ModelManager $manager
     * @return AbstractDataMapper
     */
    public function setManager(ModelManager $manager): AbstractDataMapper
    {
        $this->manager = $manager;
        return $this;
    }

    /**
     * @return MapperFactory
     */
    public function getMapperFactory(): MapperFactory
    {
        return $this->mapperFactory;
    }

    /**
     * @param MapperFactory $mapperFactory
     * @return AbstractDataMapper
     */
    public function setMapperFactory(MapperFactory $mapperFactory): AbstractDataMapper
    {
        $this->mapperFactory = $mapperFactory;
        return $this;
    }

    /**
     * @param null $entity
     * @throws \Doctrine\DBAL\ConnectionException
     */
    protected function flush($entity = null)
    {
        $this->getManager()->getConnection()->beginTransaction();
        try {
            $this->getManager()->flush($entity);
            $this->getManager()->getConnection()->commit();
            $this->getManager()->clear();
        } catch (\Exception $e) {
            $this->getManager()->getConnection()->rollBack();
            throw new \Exception($e->getMessage(), 0, $e);
        }
    }
}
