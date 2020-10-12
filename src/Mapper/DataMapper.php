<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Core\Utilities\Singleton;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;


abstract class DataMapper extends Singleton
{
    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    protected $manager;

    /**
     * @var MapperFactory
     */
    protected $factory;

    protected function __construct()
    {
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
