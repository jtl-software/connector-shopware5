<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Utilities
 */

namespace jtl\Connector\Shopware\Service;

use Doctrine\DBAL\Connection;;

class Translation extends \Shopware_Components_Translation
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @param string $type
     * @param integer $key
     */
    public function deleteAll($type, $key)
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->delete('s_core_translations');

        $queryBuilder
            ->andWhere('objecttype = :objectType')
            ->setParameter('objectType', $type)
            ->andWhere('objectkey = :objectKey')
            ->setParameter('objectKey', $key);

        $queryBuilder->execute();
    }
}
