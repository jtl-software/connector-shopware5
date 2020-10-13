<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Application\Application;
use \jtl\Connector\Core\Utilities\Singleton;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use Noodlehaus\ConfigInterface;
use Shopware\Components\Model\ModelManager;


abstract class AbstractDataMapper extends Singleton
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var \Shopware\Components\DependencyInjection\Container
     */
    protected $container;

    /**
     * @var ModelManager
     */
    protected $manager;

    /**
     * @var MapperFactory
     */
    protected $factory;

    protected function __construct()
    {
        $this->config = Application()->getConfig();
        $this->container = Shopware()->Container();
        $this->manager = ShopUtil::entityManager();
        $this->factory = new MapperFactory();
    }

    protected function Manager()
    {
        return $this->manager;
    }

    /**
     * @param object $entity
     * @throws \Exception
     */
    protected function flush($entity = null)
    {
        $this->Manager()->getConnection()->beginTransaction();
        try {
            $this->Manager()->flush($entity);
            $this->Manager()->getConnection()->commit();
            $this->Manager()->clear();
        } catch (\Exception $e) {
            $this->Manager()->getConnection()->rollBack();
            throw new \Exception($e->getMessage(), 0, $e);
        }
    }
}
