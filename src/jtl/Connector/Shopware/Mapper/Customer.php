<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Model\Customer as CustomerModel;
use \Shopware\Components\Api\Exception as ApiException;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Utilities\Salutation;
use \Shopware\Models\Customer\Customer as CustomerSW;
use \Shopware\Models\Customer\Billing as BillingSW;

class Customer extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->find('Shopware\Models\Customer\Customer', $id);
    }

    public function findAll($limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
                'customer',
                'billing',
                'shipping',
                'customergroup',
                'attribute',
                'shop',
                'locale'
            )
            //->from('Shopware\Models\Customer\Customer', 'customer')
            //->leftJoin('jtl\Connector\Shopware\Model\ConnectorLink', 'link', \Doctrine\ORM\Query\Expr\Join::WITH, 'customer.id = link.endpointId AND link.type = 16')
            ->from('jtl\Connector\Shopware\Model\Linker\Customer', 'customer')
            ->leftJoin('customer.linker', 'linker')
            ->leftJoin('customer.billing', 'billing')
            ->leftJoin('customer.shipping', 'shipping')
            ->leftJoin('customer.group', 'customergroup')
            ->leftJoin('billing.attribute', 'attribute')
            ->leftJoin('customer.languageSubShop', 'shop')
            ->leftJoin('shop.locale', 'locale')
            ->where('linker.hostId IS NULL')
            ->orderBy('customer.firstLogin', 'ASC')
            ->setFirstResult(0)
            ->setMaxResults($limit)
            //->getQuery();
            ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = false);

        //$res = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        //return $count ? count($res) : $res;

        return $count ? ($paginator->count()) : iterator_to_array($paginator);
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function delete(CustomerModel $customer)
    {
        $result = new CustomerModel;

        $this->deleteCustomerData($customer);

        // Result
        $result->setId(new Identity('', $customer->getId()->getHost()));

        return $result;
    }

    public function save(CustomerModel $customer)
    {
        $customerSW = null;
        $billingSW = null;
        $result = new CustomerModel;

        $this->prepareCustomerAssociatedData($customer, $customerSW, $billingSW);        
        $this->prepareCustomerGroupAssociatedData($customer, $customerSW);

        $violations = $this->Manager()->validate($customerSW);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->prepareBillingAssociatedData($customer, $customerSW, $billingSW);

        $this->Manager()->persist($customerSW);
        $this->Manager()->persist($billingSW);
        $this->flush();

        // Result
        $result->setId(new Identity($customerSW->getId(), $customer->getId()->getHost()));

        return $result;
    }

    protected function deleteCustomerData(CustomerModel &$customer)
    {
        $customerId = (strlen($customer->getId()->getEndpoint()) > 0) ? (int)$customer->getId()->getEndpoint() : null;

        if ($customerId !== null && $customerId > 0) {
            $customerSW = $this->find((int) $customerId);
            if ($customerSW !== null) {
                $this->Manager()->remove($customerSW);
                $this->Manager()->flush($customerSW);
            }
        }
    }

    protected function prepareCustomerAssociatedData(CustomerModel &$customer, CategorySW &$customerSW = null, BillingSW &$billingSW = null)
    {
        $customerId = (strlen($customer->getId()->getEndpoint()) > 0) ? (int)$customer->getId()->getEndpoint() : null;

        if ($customerId !== null && $customerId > 0) {
            $customerSW = $this->find($customerId);
            $billingSW = $this->Manager()->getRepository('Shopware\Models\Customer\Billing')->findOneBy(array('customerId' => $customerId));
        }

        if ($customerSW === null) {
            $customerSW = new CustomerSW;
        }

        $customerSW->setEmail($customer->getEMail())
            ->setActive($customer->getIsActive())
            ->setNewsletter(intval($customer->getHasNewsletterSubscription()))
            ->setFirstLogin($customer->getCreationDate());
    }

    protected function prepareCustomerGroupAssociatedData(CustomerModel &$customer, CategorySW &$customerSW)
    {
        // CustomerGroup
        $customerGroupMapper = Mmc::getMapper('CustomerGroup');
        $customerGroupSW = $customerGroupMapper->find($customer->getCustomerGroupId()->getEndpoint());
        if ($customerGroupSW) {
            $customerSW->setGroup($customerGroupSW);
        }
    }

    protected function prepareBillingAssociatedData(CustomerModel &$customer, CategorySW &$customerSW, BillingSW &$billingSW)
    {
        // Billing
        if (!$billingSW) {
            $billingSW = new BillingSW;
        }

        $billingSW->setCompany($customer->getCompany())
            ->setSalutation(Salutation::toEndpoint($customer->getSalutation()))
            ->setNumber($customer->getCustomerNumber())
            ->setFirstName($customer->getFirstName())
            ->setLastName($customer->getLastName())
            ->setStreet($customer->getStreet())
            ->setZipCode($customer->getZipCode())
            ->setCity($customer->getCity())
            ->setPhone($customer->getPhone())
            ->setFax($customer->getFax())
            ->setVatId($customer->getVatNumber())
            ->setBirthday($customer->getBirthday())
            ->setCustomer($customerSW);

        $ref = new \ReflectionClass($billingSW);
        $prop = $ref->getProperty('customerId');
        $prop->setAccessible(true);
        $prop->setValue($billingSW, $customerSW->getId());

        $countrySW = $this->Manager()->getRepository('Shopware\Models\Country\Country')->findOneBy(array('iso' => $customer->getCountryIso()));
        if ($countrySW) {
            $billingSW->setCountryId($countrySW->getId());
        }
    }
}
