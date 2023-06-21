<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use Doctrine\ORM\ORMException;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Model\Customer as CustomerModel;
use jtl\Connector\Model\CustomerAttr;
use jtl\Connector\Model\Identity;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Shopware\Utilities\Salutation as SalutationUtil;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use jtl\Connector\Shopware\Utilities\TranslatableAttributes;
use Shopware\Components\Api\Exception as ApiException;
use Shopware\Models\Attribute\Customer as CustomerAttribute;
use Shopware\Models\Attribute\User;
use Shopware\Models\Customer\Address as AddressSW;
use Shopware\Models\Customer\Customer as CustomerSW;

class Customer extends DataMapper
{
    public function find($id)
    {
        return (\intval($id) == 0) ? null : $this->Manager()->find('Shopware\Models\Customer\Customer', $id);
    }

    public function findAll($limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
            'customer',
            'billing',
            'shipping',
            'customerAttributes',
            'customergroup',
            'attribute',
            'shop',
            'locale'
        )
            //->from('Shopware\Models\Customer\Customer', 'customer')
            //->leftJoin('jtl\Connector\Shopware\Model\ConnectorLink', 'link',
            // \Doctrine\ORM\Query\Expr\Join::WITH, 'customer.id = link.endpointId AND link.type = 16')
            ->from('jtl\Connector\Shopware\Model\Linker\Customer', 'customer')
            ->leftJoin('customer.linker', 'linker')
            ->leftJoin('customer.defaultBillingAddress', 'billing')
            ->leftJoin('customer.defaultShippingAddress', 'shipping')
            ->leftJoin('customer.attribute', 'customerAttributes')
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

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        //$res = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        //return $count ? count($res) : $res;

        return $count ? ($paginator->count()) : \iterator_to_array($paginator);
    }

    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    public function delete(CustomerModel $customer)
    {
        $result = new CustomerModel();

        $this->deleteCustomerData($customer);

        // Result
        $result->setId(new Identity('', $customer->getId()->getHost()));

        return $result;
    }

    public function save(CustomerModel $customer)
    {
        $customerSW = null;
        $addressSW  = null;
        $result     = new CustomerModel();

        try {
            $this->prepareCustomerAssociatedData($customer, $customerSW, $addressSW);
            $this->prepareCustomerGroupAssociatedData($customer, $customerSW);
            $this->prepareAttributeAssociatedData($customer, $customerSW);

            $violations = $this->Manager()->validate($customerSW);
            if ($violations->count() > 0) {
                throw new ApiException\ValidationException($violations);
            }

            $this->prepareBillingAssociatedData($customer, $customerSW, $addressSW);

            $this->Manager()->persist($customerSW);
            $this->Manager()->persist($addressSW);
            $this->flush();
        } catch (\Exception $e) {
            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
        }

        // Result
        $result->setId(new Identity('', $customer->getId()->getHost()));

        if (!\is_null($customerSW)) {
            $result->getId()->setEndpoint($customerSW->getId());
        }

        return $result;
    }

    protected function deleteCustomerData(CustomerModel &$customer)
    {
        $customerId = (\strlen($customer->getId()->getEndpoint()) > 0) ? (int)$customer->getId()->getEndpoint() : null;

        if ($customerId !== null && $customerId > 0) {
            $customerSW = $this->find((int) $customerId);
            if ($customerSW !== null) {
                $this->Manager()->remove($customerSW);
                $this->Manager()->flush($customerSW);
            }
        }
    }

    /**
     * @param CustomerModel $jtlCustomer
     * @param CustomerSW $swCustomer
     * @throws ORMException
     */
    protected function prepareAttributeAssociatedData(CustomerModel $jtlCustomer, CustomerSW $swCustomer)
    {
        /** @var $swAttribute CustomerAttribute */
        $swAttribute = $swCustomer->getAttribute();
        if ($swAttribute === null) {
            $swAttribute = new \Shopware\Models\Attribute\Customer();
            $swAttribute->setCustomer($swCustomer);
            ShopUtil::entityManager()->persist($swAttribute);
        }

        $jtlAttributes = [];
        /** @var CustomerAttr $attribute */
        foreach ($jtlCustomer->getAttributes() as $attribute) {
            if ($attribute->getKey() !== "") {
                $jtlAttributes[$attribute->getKey()] = $attribute->getValue();
            }
        }

        if (!empty($jtlAttributes)) {
            $nullUndefinedAttributes = (bool)\Application()->getConfig()->get(
                'customer.push.null_undefined_attributes',
                true
            );
            $swAttributesList        = \Shopware()->Container()->get('shopware_attribute.crud_service')
                ->getList('s_user_attributes');

            foreach ($swAttributesList as $attribute) {
                TranslatableAttributes::setAttribute(
                    $attribute,
                    $swAttribute,
                    $jtlAttributes,
                    $nullUndefinedAttributes
                );
            }

            ShopUtil::entityManager()->persist($swAttribute);
        }
    }

    protected function prepareCustomerAssociatedData(
        CustomerModel &$customer,
        CustomerSW &$customerSW = null,
        AddressSW &$addressSW = null
    ) {
        $customerId = (\strlen($customer->getId()->getEndpoint()) > 0) ? (int)$customer->getId()->getEndpoint() : null;

        if (!\is_null($customerId) && $customerId > 0) {
            $customerSW = $this->find($customerId);
        }

        // Try to find customer with email
        if (\is_null($customerSW)) {
            $customerSW = $this->Manager()->getRepository('Shopware\Models\Customer\Customer')
                ->findOneBy(array('email' => $customer->getEMail()));
        }

        if (!\is_null($customerSW)) {
            $addressSW = $customerSW->getDefaultBillingAddress();
        }

        if (\is_null($customerSW)) {
            throw new \Exception(\sprintf(
                'Customer (E-Mail: %s | HostId: %s) could not be found',
                $customer->getEMail(),
                $customer->getId()->getHost()
            ));
        }

        if (\is_null($addressSW)) {
            $addressSW = new AddressSW();
            $customerSW->setDefaultBillingAddress($addressSW);
        }

        /**
         * 0 => normal account ("don't create customer account" wasn't checked)<br>
         * 1 => hidden account ("don't create customer account" was checked)
         */
        $accountmode = $customer->getHasCustomerAccount() ? 0 : 1;

        $customerSW->setEmail($customer->getEMail())
            ->setActive($customer->getIsActive())
            ->setNewsletter(\intval($customer->getHasNewsletterSubscription()))
            ->setFirstLogin($customer->getCreationDate())
            ->setAccountMode($accountmode)
            ->setTitle($customer->getTitle());

        $customerSW->setFirstname($customer->getFirstName());
        $customerSW->setLastname($customer->getLastName());
        $customerSW->setSalutation(SalutationUtil::toEndpoint($customer->getSalutation()));
        $customerSW->setBirthday($customer->getBirthday());
    }

    protected function prepareCustomerGroupAssociatedData(CustomerModel &$customer, CustomerSW &$customerSW)
    {
        // CustomerGroup
        $customerGroupMapper = Mmc::getMapper('CustomerGroup');
        $customerGroupSW     = $customerGroupMapper->find($customer->getCustomerGroupId()->getEndpoint());
        if ($customerGroupSW) {
            $customerSW->setGroup($customerGroupSW);
        }
    }

    protected function prepareBillingAssociatedData(
        CustomerModel &$customer,
        CustomerSW &$customerSW,
        AddressSW &$addressSW
    ) {
        // Billing
        if (!$addressSW) {
            $addressSW = new AddressSW();
        }

        $addressSW->setCompany($customer->getCompany());
        $addressSW->setDepartment($customer->getDeliveryInstruction());
        $addressSW->setSalutation(SalutationUtil::toEndpoint($customer->getSalutation()));
        $addressSW->setFirstName($customer->getFirstName());
        $addressSW->setLastName($customer->getLastName());
        $addressSW->setStreet($customer->getStreet());
        $addressSW->setZipCode($customer->getZipCode());
        $addressSW->setCity($customer->getCity());
        $addressSW->setPhone($customer->getPhone());
        $addressSW->setVatId($customer->getVatNumber());
        $addressSW->setCustomer($customerSW);
        $addressSW->setAdditionalAddressLine1($customer->getExtraAddressLine());
        $addressSW->setTitle($customer->getTitle());

        /*
        $ref = new \ReflectionClass($addressSW);
        $prop = $ref->getProperty('user_id');
        $prop->setAccessible(true);
        $prop->setValue($addressSW, $customerSW->getId());
        */

        $stateSW = $this->Manager()->getRepository('Shopware\Models\Country\State')->findOneBy([
            'name' => $customer->getState(),
            'active' => true
        ]);

        if ($stateSW) {
            $addressSW->setState($stateSW);
        }

        /** @var \Shopware\Models\Country\Country $countrySW */
        $countrySW = $this->Manager()->getRepository('Shopware\Models\Country\Country')
            ->findOneBy(['iso' => $customer->getCountryIso()]);
        if ($countrySW) {
            $addressSW->setCountry($countrySW);
        }
    }
}
