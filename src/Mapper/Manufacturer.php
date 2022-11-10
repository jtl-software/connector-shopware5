<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package   jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\TransactionRequiredException;
use jtl\Connector\Core\Exception\LanguageException;
use jtl\Connector\Model\Manufacturer as ManufacturerModel;
use Shopware\Components\Api\Exception as ApiException;
use jtl\Connector\Model\Identity;
use Shopware\Models\Article\Supplier as ManufacturerSW;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;

class Manufacturer extends DataMapper
{
    /**
     * @param array $kv
     *
     * @return mixed|object|null
     */
    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository(ManufacturerSW::class)->findOneBy($kv);
    }

    /**
     * @param int $limit
     *
     * @return int
     * @throws \Exception
     */
    public function fetchCount(int $limit = 100): int
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
    public function findAll(int $limit = 100, bool $count = false)
    {
        $query = $this->Manager()->createQueryBuilder()->select(
            'supplier',
            'attribute'
        )
            //->from('Shopware\Models\Article\Supplier', 'supplier')
            /*->leftJoin(
                'jtl\Connector\Shopware\Model\ConnectorLink',
                'link',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'supplier.id = link.endpointId AND link.type = 41'
            )*/
                      ->from(__CLASS__, 'supplier')
                      ->leftJoin('supplier.linker', 'linker')
                      ->leftJoin('supplier.attribute', 'attribute')
                      ->where('linker.hostId IS NULL')
                      ->andWhere('supplier.name != :name')
                      ->setParameter('name', '_')
                      ->setFirstResult(0)
                      ->setMaxResults($limit)
            //->getQuery();
                      ->getQuery()->setHydrationMode(AbstractQuery::HYDRATE_ARRAY);

        $paginator = new Paginator($query, true);

        if ($count === true) {
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
     */
    public function deleteSuperfluous(): \Zend_Db_Statement_Pdo
    {
        return \Shopware()->Db()->query(
            'DELETE s
                                        FROM s_articles_supplier s
                                        LEFT JOIN s_articles a ON a.supplierID = s.id
                                        WHERE a.id IS NULL'
        );
    }

    /**
     * @param ManufacturerModel $manufacturer
     *
     * @return ManufacturerModel
     * @throws ApiException\ValidationException|LanguageException|ORMException|\RuntimeException|\Exception
     */
    public function save(ManufacturerModel $manufacturer): ManufacturerModel
    {
        $manufacturerSW = null;
        $result         = new ManufacturerModel();

        $manufacturerSW = $this->prepareManufacturerAssociatedData($manufacturer, $manufacturerSW);
        $this->prepareI18nAssociatedData($manufacturer, $manufacturerSW);

        $violations = $this->Manager()->validate($manufacturerSW);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($manufacturerSW);
        $this->flush();

        $this->saveTranslatation($manufacturer, $manufacturerSW);

        // Result
        $result->setId(new Identity($manufacturerSW->getId(), $this->checkNull($manufacturer->getId())->getHost()));

        return $result;
    }

    /**
     * @param ManufacturerModel   $manufacturer
     * @param ManufacturerSW|null $manufacturerSW
     *
     * @return ManufacturerSW
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    protected function prepareManufacturerAssociatedData(
        ManufacturerModel $manufacturer,
        ?ManufacturerSW   $manufacturerSW = null
    ): ManufacturerSW {
        $manufacturerId = ($this->checkNull($manufacturer->getId())->getEndpoint() !== '')
            ? (int)$this->checkNull($manufacturer->getId())->getEndpoint()
            : null;

        if ($manufacturerId !== null && $manufacturerId > 0) {
            $foundManufacturer = $this->find($manufacturerId);
            if ($foundManufacturer instanceof ManufacturerSW) {
                $manufacturerSW = $foundManufacturer;
            }
        }

        if ($manufacturerSW === null) {
            $manufacturerSW = new ManufacturerSW();
        }

        $manufacturerSW->setName($manufacturer->getName())
                       ->setLink($manufacturer->getWebsiteUrl());

        return $manufacturerSW;
    }

    /**
     * @param mixed|null $value
     *
     * @return mixed
     */
    private function checkNull($value)
    {
        if ($value === null) {
            throw new \RuntimeException('Value must not be null.');
        }

        return $value;
    }

    /**
     * @param mixed $id
     *
     * @return mixed|object|ManufacturerSW|null
     * @throws ORMException|TransactionRequiredException|OptimisticLockException
     */
    public function find($id)
    {
        return ((int)$id === 0) ? null : $this->Manager()->find(ManufacturerSW::class, $id);
    }

    /**
     * @param ManufacturerModel $manufacturer
     * @param ManufacturerSW    $manufacturerSW
     *
     * @return void
     * @throws LanguageException
     */
    protected function prepareI18nAssociatedData(ManufacturerModel $manufacturer, ManufacturerSW $manufacturerSW): void
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
     * @param ManufacturerModel $manufacturer
     * @param ManufacturerSW    $manufacturerSW
     *
     * @return void
     * @throws LanguageException|\Exception
     */
    public function saveTranslatation(ManufacturerModel $manufacturer, ManufacturerSW $manufacturerSW): void
    {
        foreach ($manufacturer->getI18ns() as $i18n) {
            if (ShopUtil::isShopwareDefaultLanguage($i18n->getLanguageISO()) !== false) {
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
     * @param ManufacturerModel $manufacturer
     *
     * @return ManufacturerModel
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function delete(ManufacturerModel $manufacturer): ManufacturerModel
    {
        $result = new ManufacturerModel();

        $this->deleteManufacturerData($manufacturer);

        // Result
        $result->setId(new Identity('', $this->checkNull($manufacturer->getId())->getHost()));

        return $result;
    }

    /**
     * @param ManufacturerModel $manufacturer
     *
     * @return void
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    protected function deleteManufacturerData(ManufacturerModel $manufacturer): void
    {
        $manufacturerId = ($this->checkNull($manufacturer->getId())->getEndpoint() !== '')
            ? (int)$this->checkNull($manufacturer->getId())->getEndpoint()
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
     * @param ManufacturerSW $manufacturerSW
     *
     * @return void
     */
    public function deleteTranslation(ManufacturerSW $manufacturerSW): void
    {
        ShopUtil::translationService()->deleteAll('supplier', $manufacturerSW->getId());
    }
}
