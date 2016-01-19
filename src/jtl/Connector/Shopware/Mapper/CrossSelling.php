<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Model\CrossSelling as CrossSellingModel;
use \jtl\Connector\Shopware\Model\CrossSellingGroup as CrossSellingGroupModel;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\IdConcatenator;
use jtl\Connector\Shopware\Utilities\CrossSellingGroup as CrossSellingGroupUtil;

class CrossSelling extends DataMapper
{
    public function findAll($limit = 100)
    {
        return Shopware()->Db()->fetchAll(
            'SELECT *
             FROM
             (
                 SELECT r.*, ss.detailId, if (a.configurator_set_id > 0, d.id, a.main_detail_id) as relatedDetailId, gi.group_id
                 FROM s_articles_relationships r
                 JOIN
                 (
                    SELECT a.id, if (a.configurator_set_id > 0, d.id, a.main_detail_id) as detailId
                    FROM s_articles a
                    LEFT JOIN jtl_connector_crossselling c ON c.product_id = a.id
                    LEFT JOIN s_articles_details d ON d.articleID = a.id
                        AND d.kind = 0
                    WHERE c.product_id IS NULL
                 ) as ss ON ss.id = r.articleID
                 JOIN s_articles a ON a.id = r.relatedarticle
                 LEFT JOIN s_articles_details d ON d.articleID = r.relatedarticle
                     AND d.kind = 0
                 JOIN jtl_connector_crosssellinggroup_i18n gi ON gi.languageISO = \'ger\' AND gi.name = \'' . CrossSellingGroupModel::RELATED . '\'
                 UNION
                 SELECT s.*, ss.detailId, if (a.configurator_set_id > 0, d.id, a.main_detail_id) as relatedDetailId, gi.group_id
                 FROM s_articles_similar s
                 JOIN
                 (
                    SELECT a.id, if (a.configurator_set_id > 0, d.id, a.main_detail_id) as detailId
                    FROM s_articles a
                    LEFT JOIN jtl_connector_crossselling c ON c.product_id = a.id
                    LEFT JOIN s_articles_details d ON d.articleID = a.id
                        AND d.kind = 0
                    WHERE c.product_id IS NULL
                 ) as ss ON ss.id = s.articleID
                 JOIN s_articles a ON a.id = s.relatedarticle
                 LEFT JOIN s_articles_details d ON d.articleID = s.relatedarticle
                     AND d.kind = 0
                 JOIN jtl_connector_crosssellinggroup_i18n gi ON gi.languageISO = \'ger\' AND gi.name = \'' . CrossSellingGroupModel::SIMILAR . '\'
             ) a
             ORDER BY articleID
             LIMIT ' . intval($limit)
        );
    }

    public function fetchCount()
    {
        return (int) Shopware()->Db()->fetchOne(
            'SELECT sum(count) as count
            FROM
            (
                SELECT count(*) AS count FROM s_articles_relationships r
                LEFT JOIN jtl_connector_crossselling c ON c.product_id = r.articleID
                JOIN jtl_connector_crosssellinggroup_i18n gi ON gi.languageISO = \'ger\' AND gi.name = \'' . CrossSellingGroupModel::RELATED . '\'
                WHERE c.product_id IS NULL
                UNION
                SELECT count(*) AS count FROM s_articles_similar s
                LEFT JOIN jtl_connector_crossselling c ON c.product_id = s.articleID
                JOIN jtl_connector_crosssellinggroup_i18n gi ON gi.languageISO = \'ger\' AND gi.name = \'' . CrossSellingGroupModel::SIMILAR . '\'
                WHERE c.product_id IS NULL
            ) a'
        );
    }

    public function delete(CrossSellingModel $crossSelling)
    {
        $result = new CrossSellingModel;

        Shopware()->Db()->delete('s_articles_relationships', array(
            'articleID = ?' => $crossSelling->getProductId()->getEndpoint()
        ));

        Shopware()->Db()->delete('s_articles_similar', array(
            'articleID = ?' => $crossSelling->getProductId()->getEndpoint()
        ));

        // Result
        $result->setProductId(new Identity('', $crossSelling->getProductId()->getHost()));

        return $result;
    }

    public function save(CrossSellingModel $crossSelling)
    {
        $this->delete($crossSelling);
        list ($sDetailId, $sProductId) = IdConcatenator::unlink($crossSelling->getProductId()->getEndpoint());
        foreach ($crossSelling->getItems() as $item) {
            if (count($item->getProductIds()) > 0) {
                $isValid = false;
                $group = CrossSellingGroupUtil::get($item->getCrossSellingGroupId()->getEndpoint());
                $table = ($group !== null) ? $group->getTable() : 's_articles_relationships';
                $sql = sprintf('INSERT INTO %s VALUES ', $table);

                foreach ($item->getProductIds() as $i => $identity) {
                    if (strlen($crossSelling->getProductId()->getEndpoint()) > 0 && strlen($identity->getEndpoint()) > 0) {
                        $isValid = true;

                        if ($i > 0) {
                            $sql .= ', ';
                        }

                        // s = source - d = destination
                        list ($sDetailId, $sProductId) = IdConcatenator::unlink($crossSelling->getProductId()->getEndpoint());
                        list ($dDetailId, $dProductId) = IdConcatenator::unlink($identity->getEndpoint());

                        $sql .= sprintf('(null, %s, %s)',
                            $sProductId,
                            $dProductId
                        );

                        try {
                            Shopware()->Db()->delete(
                                $table,
                                array('articleID = ?' => $sProductId, 'relatedarticle = ?' => $dProductId)
                            );
                        } catch (\Exception $e) {
                            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
                        }
                    }
                }

                if ($isValid) {
                    try {
                        Shopware()->Db()->query($sql);
                    } catch (\Exception $e) {
                        Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
                    }
                }
            }
        }

        $crossSelling->getId()->setEndpoint($sProductId);

        return $crossSelling;
    }
}