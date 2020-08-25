<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Model\CustomerGroup as CustomerGroupModel;
use \jtl\Connector\Model\Identity;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use \Shopware\Models\Customer\Group as CustomerGroupSW;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;

class CustomerGroup extends DataMapper
{
    protected $groupKeyTables = array(
        's_user' => 'customergroup',
        's_articles_prices' => 'pricegroup',
        's_article_configurator_template_prices' => 'customer_group_key',
        's_core_customerpricegroups' => 'name',
        's_campaigns_mailings' => 'customergroup'
    );

    public function find($id)
    {
        return (intval($id) == 0) ? null : $this->Manager()->getRepository('Shopware\Models\Customer\Group')->find($id);
    }

    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Customer\Group')->findOneBy($kv);
    }

    public function findAll($limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
            'customergroup',
            'attribute'
        )
            ->from('Shopware\Models\Customer\Group', 'customergroup')
            ->leftJoin('customergroup.attribute', 'attribute')
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        return $count ? $paginator->count() : iterator_to_array($paginator);
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function delete(CustomerGroupModel $customerGroup)
    {
        $result = new CustomerGroupModel;

        $this->deleteCustomerGroupData($customerGroup);

        // Result
        $result->setId(new Identity('', $customerGroup->getId()->getHost()));

        return $result;
    }

    public function save(CustomerGroupModel $customerGroup)
    {
        $customerGroupSW = null;
        $result = new CustomerGroupModel;

        $this->prepareCustomerGroupAssociatedData($customerGroup, $customerGroupSW);
        $this->prepareI18nAssociatedData($customerGroup, $customerGroupSW);

        // Save
        $this->Manager()->persist($customerGroupSW);
        $this->Manager()->flush();

        $result->setId(new Identity($customerGroupSW->getId(), $customerGroup->getId()->getHost()));

        return $result;
    }

    protected function deleteCustomerGroupData(CustomerGroupModel &$customerGroup)
    {
        $customerGroupId = (strlen($customerGroup->getId()->getEndpoint()) > 0) ? (int)$customerGroup->getId()->getEndpoint() : null;

        if ($customerGroupId !== null && $customerGroupId > 0) {
            $customerGroupSW = $this->find((int) $customerGroupId);
            if ($customerGroupSW !== null) {
                $this->Manager()->remove($customerGroupSW);
                $this->Manager()->flush($customerGroupSW);
            }
        }
    }

    protected function prepareCustomerGroupAssociatedData(CustomerGroupModel &$customerGroup, CustomerGroupSW &$customerGroupSW = null)
    {
        $customerGroupId = (strlen($customerGroup->getId()->getEndpoint()) > 0) ? (int) $customerGroup->getId()->getEndpoint() : null;

        if ($customerGroupId !== null && $customerGroupId > 0) {
            $customerGroupSW = $this->find($customerGroupId);
        }

        if ($customerGroupSW === null) {
            $customerGroupSW = new CustomerGroupSW;
        }

        $customerGroupSW->setDiscount($customerGroup->getDiscount())
            ->setTax(!$customerGroup->getApplyNetPrice())
            ->setMode(0)
            ->setDiscount($customerGroup->getDiscount())
            ->setTaxInput(!$customerGroup->getApplyNetPrice());
    }

    protected function prepareI18nAssociatedData(CustomerGroupModel &$customerGroup, CustomerGroupSW &$customerGroupSW)
    {
        // I18n
        foreach ($customerGroup->getI18ns() as $i18n) {
            if (ShopUtil::isShopwareDefaultLanguage($i18n->getLanguageISO())) {

                // EK fix, thanks Shopware :/
                $groupKey = ($customerGroupSW->getKey() === 'EK') ? $customerGroupSW->getKey() : substr($i18n->getName(), 0, 5);

                // If Update => update foreign tables
                if ($customerGroupSW->getId() > 0) {
                    foreach ($this->groupKeyTables as $table => $field) {
                        Shopware()->Db()->query(
                            sprintf('UPDATE %s SET %s = ? WHERE %s = ?', $table, $field, $field),
                            array($groupKey, $customerGroupSW->getKey())
                        );
                    }
                }

                $customerGroupSW->setKey($groupKey);
                $customerGroupSW->setName($i18n->getName());
            }
        }
    }
}
