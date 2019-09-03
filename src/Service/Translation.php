<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Service;

use Doctrine\DBAL\Connection;
use Shopware\Components\DependencyInjection\Container;

;

class Translation extends \Shopware_Components_Translation
{
    /**
     * @var Connection
     */
    protected $connection;

    public function __construct(Connection $connection, Container $container)
    {
        parent::__construct($connection, $container);
        $this->connection = $connection;
    }


    /**
     * @param string $type
     * @param integer $key
     */
    public function deleteAll($type, $key)
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->delete('s_core_translations')
            ->andWhere('objecttype = :objectType')
            ->setParameter('objectType', $type)
            ->andWhere('objectkey = :objectKey')
            ->setParameter('objectKey', $key);

        $result = $queryBuilder->execute();
    }
}
