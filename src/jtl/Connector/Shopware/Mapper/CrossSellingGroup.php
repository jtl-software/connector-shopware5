<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Core\Utilities\DataConverter;
use jtl\Connector\Model\CrossSellingGroup as CrossSellingGroupModel;
use jtl\Connector\Model\Identity;
use jtl\Connector\Shopware\Utilities\Mmc;

class CrossSellingGroup extends DataMapper
{
    public function find($id, $loadI18n = false)
    {
        $id = intval($id);
        if ($id > 0) {
            $res = Shopware()->Db()->fetchRow('SELECT * FROM jtl_connector_crosssellinggroup WHERE id = ' . $id);

            if (is_array($res)) {
                $group = Mmc::getModel('CrossSellingGroup');
                $group->setId(new Identity($res['id'], $res['host_id']));

                if ($loadI18n) {
                    $i18ns = Shopware()->Db()->fetchAll('SELECT * FROM jtl_connector_crosssellinggroup_i18n WHERE group_id = ?', $res['id']);
                    if (is_array($i18ns) && count($i18ns) > 0) {
                        foreach ($i18ns as $i18n) {
                            $groupI18n = Mmc::getModel('CrossSellingGroupI18n');
                            $groupI18n->map(true, DataConverter::toObject($i18n, true));

                            $group->addI18n($groupI18n);
                        }
                    }
                }

                return $group;
            }
        }

        return null;
    }

    public function findByHostId($hostId)
    {
        $hostId = intval($hostId);
        $group = null;
        if ($hostId > 0) {
            $groupId = (int) Shopware()->Db()->fetchOne(
                'SELECT id FROM jtl_connector_crosssellinggroup WHERE host_id = ?',
                [$hostId]
            );

            if ($groupId > 0) {
                $group['id'] = $groupId;
                $group['host_id'] = $hostId;
                $group['i18ns'] = Shopware()->Db()->fetchAll(
                    'SELECT * FROM jtl_connector_crosssellinggroup_i18n WHERE group_id = ?',
                    $groupId
                );
            }

            return $group;
        }

        return $group;
    }

    public function findHostId($id)
    {
        $id = intval($id);
        if ($id > 0) {
            $hostId = Shopware()->Db()->fetchOne(
                'SELECT host_id FROM jtl_connector_crosssellinggroup WHERE id = ?',
                [$id]
            );

            return (intval($hostId) > 0) ? (int) $hostId : null;
        }

        return null;
    }

    public function fetchAll($limit = 100)
    {
        $groups = Shopware()->Db()->fetchAll(
            'SELECT * FROM jtl_connector_crosssellinggroup LIMIT ' . intval($limit)
        );

        if (is_array($groups) && count($groups) > 0) {
            foreach ($groups as &$group) {
                $group['i18ns'] = Shopware()->Db()->fetchAll(
                    'SELECT * FROM jtl_connector_crosssellinggroup_i18n WHERE group_id = ' . intval($group['id'])
                );
            }
        }

        return $groups;
    }

    public function fetchCount()
    {
        return 0;
    }

    public function delete(CrossSellingGroupModel $crossSellingGroup)
    {
        return $this->deleteIntern($crossSellingGroup->getId()->getHost());
    }

    protected function deleteIntern($hostId)
    {
        $hostId = intval($hostId);
        if ($hostId > 0) {
            return Shopware()->Db()->query(
                'DELETE g, i
                FROM jtl_connector_crosssellinggroup g
                LEFT JOIN jtl_connector_crosssellinggroup_i18n i ON i.group_id = g.id
                WHERE g.host_id = ?',
                [$hostId]
            );
        }

        return false;
    }

    public function save(CrossSellingGroupModel $crossSellingGroup)
    {
        try {
            $this->deleteIntern($crossSellingGroup->getId()->getHost());

            Shopware()->Db()->insert('jtl_connector_crosssellinggroup', [
                'host_id' => $crossSellingGroup->getId()->getHost()
            ]);

            $crossSellingGroupId = Shopware()->Db()->lastInsertId();
            if ($crossSellingGroupId > 0) {
                foreach ($crossSellingGroup->getI18ns() as $i18n) {
                    Shopware()->Db()->insert('jtl_connector_crosssellinggroup_i18n', [
                        'group_id' => $crossSellingGroupId,
                        'languageIso' => $i18n->getLanguageISO(),
                        'name' => $i18n->getName(),
                        'description' =>  $i18n->getDescription()
                    ]);
                }
            }
        } catch (\Exception $e) {
            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
        }

        return $crossSellingGroup;
    }
}