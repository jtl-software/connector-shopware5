<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Model\CrossSelling as CrossSellingModel;
use jtl\Connector\Model\Identity;

class CrossSelling extends DataMapper
{
    public function findAll($limit = 100)
    {
        return Shopware()->Db()->fetchAll(
            'SELECT r.*
             FROM s_articles_relationships r
             JOIN
             (
                SELECT a.id
                FROM s_articles a
                LEFT JOIN jtl_connector_crossselling c ON c.product_id = a.id
                WHERE c.product_id IS NULL
                LIMIT ' . intval($limit) . '
             ) as ss ON ss.id = r.articleID
             ORDER BY r.articleID'
        );
    }

    public function fetchCount()
    {
        return (int) Shopware()->Db()->fetchOne(
            'SELECT count(*) AS count FROM s_articles_relationships r
            LEFT JOIN jtl_connector_crossselling c ON c.product_id = r.articleID
            WHERE c.product_id IS NULL'
        );
    }

    public function delete(CrossSellingModel $crossSelling)
    {
        $result = new CrossSellingModel;

        Shopware()->Db()->delete('s_articles_relationships', array(
            'articleID = ?' => $crossSelling->getProductId()->getEndpoint()
        ));

        // Result
        $result->setProductId(new Identity('', $crossSelling->getProductId()->getHost()));

        return $result;
    }

    public function save(CrossSellingModel $crossSelling)
    {
        $this->delete($crossSelling);
        foreach ($crossSelling->getItems() as $item) {
            if (count($item->getProductIds()) > 0) {
                $sql = 'INSERT INTO s_articles_relationships VALUES ';
                foreach ($item->getProductIds() as $i => $identity) {
                    if ($i > 0) {
                        $sql .= ', ';
                    }

                    $sql .= sprintf('(null, %s, %s)',
                        $crossSelling->getProductId()->getEndpoint(),
                        $identity->getEndpoint()
                    );
                }

                Shopware()->Db()->query($sql);
            }
        }
    }
}