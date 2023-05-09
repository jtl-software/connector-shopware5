<?php

/** @noinspection ReturnTypeCanBeDeclaredInspection */

/** @noinspection PhpMissingReturnTypeInspection */

/** @noinspection PhpMissingParamTypeInspection */

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Model\Identity;
use jtl\Connector\Model\Manufacturer as ManufacturerModel;
use jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use Shopware\Components\Api\Exception as ApiException;
use Shopware\Models\Article\Supplier as ManufacturerSW;

class Manufacturer extends DataMapper
{
    /**
     * @param array $kv
     *
     * @return object|\Shopware\Models\Article\Supplier|null
     */
    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository(ManufacturerSW::class)->findOneBy($kv);
    }

    /**
     * @param int $limit
     *
     * @return array|int
     * @throws \Exception
     */
    public function fetchCount($limit = 100)
    {
        return $this->findAll($limit, true);
    }

    /**
     * @param int  $limit
     * @param bool $count
     *
     * @return array|int
     * @throws \Exception
     */
    public function findAll($limit = 100, $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
            'supplier',
            'attribute'
        )
            //->from('Shopware\Models\Article\Supplier', 'supplier')
            //->leftJoin('jtl\Connector\Shopware\Model\ConnectorLink', 'link',
            // \Doctrine\ORM\Query\Expr\Join::WITH, 'supplier.id = link.endpointId AND link.type = 41')
                      ->from(__CLASS__, 'supplier')
                      ->leftJoin('supplier.linker', 'linker')
                      ->leftJoin('supplier.attribute', 'attribute')
                      ->where('linker.hostId IS NULL')
                      ->andWhere('supplier.name != :name')
                      ->setParameter('name', '_')
                      ->setFirstResult(0)
                      ->setMaxResults($limit)
            //->getQuery();
                      ->getQuery()->setHydrationMode(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, $fetchJoinCollection = true);

        if ($count) {
            return $paginator->count();
        }

        $manufacturers = \iterator_to_array($paginator);

        $shopMapper = Mmc::getMapper('Shop');
        $shops      = $shopMapper->findAll(null, null);

        $translationService = ShopUtil::translationService();
        foreach ($manufacturers as $i => $iValue) {
            foreach ($shops as $shop) {
                $translation = $translationService->read($shop['id'], 'supplier', $iValue['id']);
                if (!empty($translation)) {
                    $translation['shopId']                                        = $shop['id'];
                    $manufacturers[$i]['translations'][$shop['locale']['locale']] = $translation;
                }
            }
        }

        return $manufacturers;
    }

    /**
     * @return \Zend_Db_Statement_Pdo
     * @throws \Zend_Db_Statement_Exception
     * @noinspection PhpUnused
     */
    public function deleteSuperfluous()
    {
        return \Shopware()->Db()->query('DELETE s
                                        FROM s_articles_supplier s
                                        LEFT JOIN s_articles a ON a.supplierID = s.id
                                        WHERE a.id IS NULL');
    }

    /**
     * @param \jtl\Connector\Model\Manufacturer $manufacturer
     *
     * @return \jtl\Connector\Model\Manufacturer
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \jtl\Connector\Core\Exception\LanguageException
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function save(ManufacturerModel $manufacturer)
    {
        $manufacturerSW = null;
        $result         = new ManufacturerModel();

        $this->prepareManufacturerAssociatedData($manufacturer, $manufacturerSW);
        $this->prepareI18nAssociatedData($manufacturer, $manufacturerSW);

        $violations = $this->Manager()->validate($manufacturerSW);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($manufacturerSW);
        $this->flush();

        $this->saveTranslatation($manufacturer, $manufacturerSW);

        $manufacturerIdentity = $manufacturer->getId();
        if ($manufacturerIdentity === null) {
            throw new \RuntimeException('Identity for manufacturer is missing.');
        }
        // Result
        $result->setId(new Identity($manufacturerSW->getId(), $manufacturerIdentity->getHost()));

        return $result;
    }

    /**
     * @param \jtl\Connector\Model\Manufacturer      $manufacturer
     * @param \Shopware\Models\Article\Supplier|null $manufacturerSW
     *
     * @return void
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \RuntimeException
     * @noinspection ReferencingObjectsInspection
     */
    protected function prepareManufacturerAssociatedData(
        ManufacturerModel &$manufacturer,
        ManufacturerSW    &$manufacturerSW = null
    ) {
        $manufacturerIdentity = $manufacturer->getId();
        if ($manufacturerIdentity === null) {
            throw new \RuntimeException('Identity for manufacturer is missing.');
        }
        $manufacturerId = ($manufacturerIdentity->getEndpoint() !== '')
            ? (int)$manufacturerIdentity->getEndpoint()
            : null;

        if ($manufacturerId !== null && $manufacturerId > 0) {
            $manufacturerSW =  $this->find($manufacturerId);
        }

        if ($manufacturerSW  === null) {
            $manufacturerSW = new ManufacturerSW();
        }

        $manufacturerSW->setName($manufacturer->getName())
                       ->setLink($manufacturer->getWebsiteUrl());
    }

    /**
     * @param \jtl\Connector\Model\Manufacturer $manufacturer
     * @param \Shopware\Models\Article\Supplier $manufacturerSW
     *
     * @return void
     * @throws \jtl\Connector\Core\Exception\LanguageException
     * @noinspection ReferencingObjectsInspection
     */
    protected function prepareI18nAssociatedData(ManufacturerModel &$manufacturer, ManufacturerSW &$manufacturerSW)
    {
        foreach ($manufacturer->getI18ns() as $i18n) {
            if (ShopUtil::isShopwareDefaultLanguage($i18n->getLanguageISO())) {
                $manufacturerSW->setDescription($i18n->getDescription());
                $manufacturerSW->setMetaTitle($i18n->getTitleTag());
                $manufacturerSW->setMetaDescription($i18n->getMetaDescription());
                $manufacturerSW->setMetaKeywords($i18n->getMetaKeywords());
            }
        }
    }

    /**
     * @param \jtl\Connector\Model\Manufacturer $manufacturer
     * @param \Shopware\Models\Article\Supplier $manufacturerSW
     *
     * @return void
     * @throws \jtl\Connector\Core\Exception\LanguageException
     * @throws \Exception
     * @noinspection SpellCheckingInspection
     */
    public function saveTranslatation(ManufacturerModel $manufacturer, ManufacturerSW $manufacturerSW)
    {
        foreach ($manufacturer->getI18ns() as $i18n) {
            if (ShopUtil::isShopwareDefaultLanguage($i18n->getLanguageISO()) === false) {
                $iso      = $i18n->getLanguageISO();
                $locale   = LanguageUtil::map(null, null, $iso);
                $language = LocaleUtil::extractLanguageIsoFromLocale($locale);

                $translationService = ShopUtil::translationService();
                $shopMapper         = Mmc::getMapper('Shop');
                $shops              = $shopMapper->findByLanguageIso($language);

                foreach ($shops as $shop) {
                    $translationService->delete($shop->getId(), 'supplier', $manufacturerSW->getId());
                    $translationService->write(
                        $shop->getId(),
                        'supplier',
                        $manufacturerSW->getId(),
                        [
                            'metaTitle'       => $i18n->getTitleTag(),
                            'description'     => $i18n->getDescription(),
                            'metaDescription' => $i18n->getMetaDescription(),
                            'metaKeywords'    => $i18n->getMetaKeywords()
                        ]
                    );
                }
            }
        }
    }

    /**
     * @param \jtl\Connector\Model\Manufacturer $manufacturer
     *
     * @return \jtl\Connector\Model\Manufacturer
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \RuntimeException
     */
    public function delete(ManufacturerModel $manufacturer)
    {
        $result = new ManufacturerModel();

        $this->deleteManufacturerData($manufacturer);

        $manufacturerIdentity = $manufacturer->getId();
        if ($manufacturerIdentity === null) {
            throw new \RuntimeException('Identity for manufacturer is missing.');
        }
        // Result
        $result->setId(new Identity('', $manufacturerIdentity->getHost()));

        return $result;
    }

    /**
     * @param \jtl\Connector\Model\Manufacturer $manufacturer
     *
     * @return void
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \RuntimeException
     */
    protected function deleteManufacturerData(ManufacturerModel $manufacturer)
    {
        $manufacturerIdentity = $manufacturer->getId();
        if ($manufacturerIdentity === null) {
            throw new \RuntimeException('Identity for manufacturer is missing.');
        }
        $manufacturerId = ($manufacturerIdentity->getEndpoint() !== '')
            ? (int)$manufacturerIdentity->getEndpoint()
            : null;

        if ($manufacturerId !== null && $manufacturerId > 0) {
            $manufacturerSW = $this->find((int)$manufacturerId);
            if ($manufacturerSW !== null) {
                $this->deleteTranslation($manufacturerSW);

                $this->Manager()->remove($manufacturerSW);
                $this->Manager()->flush($manufacturerSW);
            }
        }
    }

    /**
     * @param scalar $id
     *
     * @return object|\Shopware\Models\Article\Supplier|null
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function find($id)
    {
        return ((int)$id === 0) ? null : $this->Manager()->find(ManufacturerSW::class, $id);
    }

    /**
     * @param \Shopware\Models\Article\Supplier $manufacturerSW
     *
     * @return void
     */
    public function deleteTranslation(ManufacturerSW $manufacturerSW)
    {
        ShopUtil::translationService()->deleteAll('supplier', $manufacturerSW->getId());
    }
}
