<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\TransactionRequiredException;
use jtl\Connector\Core\Exception\LanguageException;
use jtl\Connector\Model\TaxRate;
use jtl\Connector\Shopware\Utilities\I18n;
use jtl\Connector\Shopware\Utilities\ProductAttribute;
use jtl\Connector\Shopware\Model\ProductVariation;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Model\Product as JtlProduct;
use jtl\Connector\Model\ProductChecksum;
use jtl\Connector\Shopware\Utilities\TranslatableAttributes;
use jtl\Connector\Shopware\Utilities\VariationType;
use jtl\Connector\Core\Exception\DatabaseException;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Model\Identity;
use jtl\Connector\Shopware\Utilities\CustomerGroup as CustomerGroupUtil;
use Doctrine\Common\Collections\ArrayCollection;
use jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use Shopware\Models\Article\Detail as SwDetail;
use Shopware\Models\Article\Article as SwArticle;
use Shopware\Models\Article\Download as SwDownload;
use Shopware\Models\Article\Link as SwLink;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Shopware\Utilities\IdConcatenator;
use jtl\Connector\Shopware\Model\Helper\ProductNameHelper;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Linker\ChecksumLinker;
use jtl\Connector\Shopware\Mapper\ProductPrice as ProductPriceMapper;
use jtl\Connector\Shopware\Model\ProductAttr;
use jtl\Connector\Shopware\Utilities\CategoryMapping as CategoryMappingUtil;
use Shopware\Models\Attribute\Article;
use \Shopware\Models\Price\Group as SwGroup;
use Shopware\Models\Plugin\Plugin;
use Shopware\Models\Property\Group;
use Shopware\Models\Property\Option;
use Shopware\Models\Property\Value;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use Shopware\Models\Tax\Rule;
use Shopware\Models\Tax\Tax;
use SwagCustomProducts\Models\Template;

class Product extends DataMapper
{
    public const
        KIND_VALUE_PARENT = 3,
        KIND_VALUE_DEFAULT = 2,
        KIND_VALUE_MAIN = 1;

    public const
        ATTRIBUTE_ARTICLE_SEARCH_KEYWORDS = 'jtl_search_keywords';

    /**
     * @var array
     */
    protected static $masterProductIds = [];

    /**
     * @var boolean
     */
    protected $setMainDetailActive = false;

    /**
     * @return \Doctrine\ORM\EntityRepository
     */
    public function getRepository()
    {
        return Shopware()->Models()->getRepository('Shopware\Models\Article\Article');
    }

    /**
     * @param integer $id
     * @return null|SwArticle
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function find($id)
    {
        return (intval($id) == 0) ? null : ShopUtil::entityManager()->find('Shopware\Models\Article\Article', $id);
    }

    /**
     * @param integer $id
     * @return null|SwDetail
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function findDetail($id)
    {
        return (intval($id) == 0) ? null : ShopUtil::entityManager()->find('Shopware\Models\Article\Detail', $id);
    }

    /**
     * @param array $kv
     * @return null|SwDetail
     */
    public function findDetailBy(array $kv)
    {
        return ShopUtil::entityManager()->getRepository('Shopware\Models\Article\Detail')->findOneBy($kv);
    }


    public function findAll($limit = 100, $count = false)
    {
        if ($count) {
            $query = ShopUtil::entityManager()->createQueryBuilder()->select('detail')
                ->from('jtl\Connector\Shopware\Model\Linker\Detail', 'detail')
                ->leftJoin('detail.linker', 'linker')
                ->where('linker.hostId IS NULL')
                ->getQuery();

            $paginator = new Paginator($query, $fetchJoinCollection = true);

            return $paginator->count();
        }

        /** @var \Doctrine\ORM\Query $query */
        $query = ShopUtil::entityManager()->createQueryBuilder()->select(
            'detail',
            'article',
            'unit',
            'tax',
            'categories',
            'maindetail',
            'detailprices',
            'prices',
            'links',
            'attribute',
            'downloads',
            'supplier',
            'pricegroup',
            'discounts',
            'customergroups',
            'configuratorOptions',
            'propertyvalues',
            '(CASE WHEN detail.kind = 3 THEN 0 ELSE detail.kind END) AS HIDDEN sort'
        )
            ->from('jtl\Connector\Shopware\Model\Linker\Detail', 'detail')
            ->leftJoin('detail.linker', 'linker')
            ->leftJoin('detail.article', 'article')
            ->leftJoin('detail.prices', 'detailprices')
            ->leftJoin('detail.unit', 'unit')
            ->leftJoin('article.tax', 'tax')
            ->leftJoin('article.categories', 'categories')
            ->leftJoin('article.mainDetail', 'maindetail')
            ->leftJoin('maindetail.prices', 'prices')
            ->leftJoin('article.links', 'links')
            //->leftJoin('article.attribute', 'attribute', \Doctrine\ORM\Query\Expr\Join::WITH, 'attribute.articleDetailId = detail.id')
            ->leftJoin('detail.attribute', 'attribute')
            ->leftJoin('article.downloads', 'downloads')
            ->leftJoin('article.supplier', 'supplier')
            ->leftJoin('article.priceGroup', 'pricegroup')
            ->leftJoin('pricegroup.discounts', 'discounts')
            ->leftJoin('article.customerGroups', 'customergroups')
            ->leftJoin('detail.configuratorOptions', 'configuratorOptions')
            ->leftJoin('article.propertyValues', 'propertyvalues')
            ->where('linker.hostId IS NULL')
            ->orderBy('sort', 'ASC')
            ->setFirstResult(0)
            ->setMaxResults($limit)
            ->getQuery()->setHydrationMode(AbstractQuery::HYDRATE_ARRAY);

        $paginator = new Paginator($query, $fetchJoinCollection = true);

        $products = iterator_to_array($paginator);

        $shopMapper = Mmc::getMapper('Shop');
        $shops = $shopMapper->findAll(null, null);

        $translationService = ShopUtil::translationService();
        for ($i = 0; $i < count($products); $i++) {
            foreach ($shops as $shop) {
                $translation = $translationService->read($shop['id'], 'article', $products[$i]['articleId']);
                if ($this->isDetailData($products[$i]) && $products[$i]['kind'] === self::KIND_VALUE_DEFAULT) {
                    $translation = array_merge($translation, $translationService->read($shop['id'], 'variant', $products[$i]['id']));
                }

                if (!empty($translation)) {
                    $translation['shopId'] = $shop['id'];
                    if (!isset($products[$i]['translations'][$shop['locale']['locale']])) {
                        $products[$i]['translations'][$shop['locale']['locale']] = $translation;
                    }
                }
            }
        }

        return $products;
    }

    /**
     * @return integer
     */
    public function fetchCount()
    {
        return (int)Shopware()->Db()->fetchOne(
            'SELECT count(*)
                FROM s_articles_details d
                LEFT JOIN jtl_connector_link_detail l ON l.product_id = d.articleID
                    AND l.detail_id = d.id
                WHERE l.host_id IS NULL'
        );
    }

    /**
     * @param integer $swProductId
     * @return integer
     */
    public function fetchDetailCount(int $swProductId)
    {
        return Shopware()->Db()->fetchOne(
            'SELECT count(*) FROM s_articles_details WHERE articleID = ?',
            array($swProductId)
        );
    }

    /**
     * @param $swDetailId
     * @return int
     */
    public function deleteDetail(int $swDetailId)
    {
        return Shopware()->Db()->delete('s_articles_details', array('id = ?' => $swDetailId));
    }

    /**
     * @param integer $swProductId
     * @return integer
     */
    public function getParentDetailId(int $swProductId)
    {
        return (int)Shopware()->Db()->fetchOne(
            'SELECT id FROM s_articles_details WHERE articleID = ? AND kind = ' . self::KIND_VALUE_PARENT,
            array($swProductId)
        );
    }

    public function delete(JtlProduct $jtlProduct)
    {
        $result = new JtlProduct();

        $this->deleteProductData($jtlProduct);

        // Result
        $result->setId(new Identity('', $jtlProduct->getId()->getHost()));

        return $result;
    }

    public function save(JtlProduct $jtlProduct)
    {
        /** @var SwArticle $swArticle */
        $swArticle = null;

        /** @var SwDetail $swDetail */
        $swDetail = null;
        //$result = new ProductModel();
        $result = $jtlProduct;
        $attrMappings = [];

        /*
        Logger::write(sprintf('>>> Product with id (%s, %s), masterProductId (%s, %s), manufacturerId (%s, %s)',
            $product->getId()->getEndpoint(),
            $product->getId()->getHost(),
            $product->getMasterProductId()->getEndpoint(),
            $product->getMasterProductId()->getHost(),
            $product->getManufacturerId()->getEndpoint(),
            $product->getManufacturerId()->getHost()
        ), Logger::DEBUG, 'database');
        */

        try {
            if ($this->isJtlChild($jtlProduct)) {
                if (isset(self::$masterProductIds[$jtlProduct->getMasterProductId()->getHost()])) {
                    $jtlProduct->getMasterProductId()->setEndpoint(self::$masterProductIds[$jtlProduct->getMasterProductId()->getHost()]);
                }

                $this->prepareChildAssociatedData($jtlProduct, $swArticle, $swDetail);
                $this->prepareDetailAssociatedData($jtlProduct, $swArticle, $swDetail, true);
                $this->prepareAttributeAssociatedData($jtlProduct, $swArticle, $swDetail, $attrMappings, true);
                $this->preparePriceAssociatedData($jtlProduct, $swArticle, $swDetail);
                $this->prepareUnitAssociatedData($jtlProduct, $swDetail);
                $this->prepareMeasurementUnitAssociatedData($jtlProduct, $swDetail);

                // First Child
                if (is_null($swArticle->getMainDetail()) || $swArticle->getMainDetail()->getKind() === self::KIND_VALUE_PARENT) {
                    $swArticle->setMainDetail($swDetail);
                }

                $this->prepareDetailVariationAssociatedData($jtlProduct, $swDetail);

                $autoMainDetailSelection = (bool)Application()->getConfig()->get('product.push.article_detail_preselection', false);
                if ($autoMainDetailSelection && !$this->isSuitableForMainDetail($swArticle->getMainDetail())) {
                    $this->selectSuitableMainDetail($swArticle);
                }

            } else {
                $this->prepareProductAssociatedData($jtlProduct, $swArticle, $swDetail);
                $this->prepareCategoryAssociatedData($jtlProduct, $swArticle);
                $this->prepareInvisibilityAssociatedData($jtlProduct, $swArticle);
                $this->prepareTaxAssociatedData($jtlProduct, $swArticle);
                $this->prepareManufacturerAssociatedData($jtlProduct, $swArticle);
                // $this->prepareSpecialPriceAssociatedData($product, $productSW); Can not be fully supported

                $this->prepareDetailAssociatedData($jtlProduct, $swArticle, $swDetail);
                $this->prepareVariationAssociatedData($jtlProduct, $swArticle);
                $this->prepareSpecificAssociatedData($jtlProduct, $swArticle, $swDetail);
                $this->prepareAttributeAssociatedData($jtlProduct, $swArticle, $swDetail, $attrMappings);
                $this->preparePriceAssociatedData($jtlProduct, $swArticle, $swDetail);
                $this->prepareUnitAssociatedData($jtlProduct, $swDetail);
                $this->prepareMeasurementUnitAssociatedData($jtlProduct, $swDetail);
                $this->prepareMediaFileAssociatedData($jtlProduct, $swArticle);

                if (is_null($swDetail->getId())) {
                    $kind = $swDetail->getKind();
                    $swArticle->setMainDetail($swDetail);
                    $swDetail->setKind($kind);
                    $swArticle->setDetails(array($swDetail));
                }

                if ($this->isJtlParent($jtlProduct) && $swArticle !== null) {
                    self::$masterProductIds[$jtlProduct->getId()->getHost()] = IdConcatenator::link(array($swArticle->getMainDetail()->getId(), $swArticle->getId()));
                }
            }

            //Set main detail in-/active hack
            if ($this->setMainDetailActive) {
                $swArticle->getMainDetail()->setActive($swArticle->getActive());
                ShopUtil::entityManager()->persist($swArticle->getMainDetail());
            }

            // Save article and detail
            ShopUtil::entityManager()->persist($swArticle);
            ShopUtil::entityManager()->persist($swDetail);
            ShopUtil::entityManager()->flush();

            //Change back to entity manager instead of native queries
            if (!$this->isJtlChild($jtlProduct)) {
                $this->prepareSetVariationRelations($jtlProduct, $swArticle);
                $this->saveVariationTranslationData($jtlProduct, $swArticle);
            }

            $this->saveTranslations($jtlProduct, $swArticle, $swDetail, $attrMappings);

        } catch (\Exception $e) {
            Logger::write(sprintf('Exception from Product (%s, %s)', $jtlProduct->getId()->getEndpoint(), $jtlProduct->getId()->getHost()), Logger::ERROR, 'database');
            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
        }


        // Result
        $result->setId(new Identity('', $jtlProduct->getId()->getHost()))
            ->setChecksums($jtlProduct->getChecksums());
        if ($swDetail !== null && $swArticle !== null && (int)$swDetail->getId() > 0 && (int)$swArticle->getId() > 0) {
            $result->setId(new Identity(IdConcatenator::link(array($swDetail->getId(), $swArticle->getId())), $jtlProduct->getId()->getHost()))
                ->setChecksums($jtlProduct->getChecksums());
        }

        return $result;
    }

    /**
     * @param SwDetail $swDetail
     * @return boolean
     */
    protected function isSuitableForMainDetail(SwDetail $swDetail)
    {
        $lastStock = (bool)(method_exists($swDetail, 'getLastStock') ? $swDetail->getLastStock() : $swDetail->getArticle()->getLastStock());
        return $swDetail->getKind() !== self::KIND_VALUE_PARENT && ($swDetail->getInStock() > 0 || !$lastStock);
    }

    /**
     * @param SwArticle $swArticle
     * @return void
     */
    protected function selectSuitableMainDetail(SwArticle $swArticle)
    {
        $mainDetail = $swArticle->getMainDetail();
        // Set new main detail
        /** @var SwDetail $detail */
        foreach ($swArticle->getDetails() as $detail) {
            if ($detail->getKind() === self::KIND_VALUE_PARENT) {
                continue;
            }

            if (!$this->isSuitableForMainDetail($mainDetail) && $this->isSuitableForMainDetail($detail)) {
                $mainDetail = $detail;
            }

            $detail->setKind(self::KIND_VALUE_DEFAULT);
        }

        if ($mainDetail->getKind() !== self::KIND_VALUE_PARENT) {
            $swArticle->setMainDetail($mainDetail);
        }
    }

    /**
     * @param SwArticle $swArticle
     * @throws ORMException
     */
    protected function cleanupConfiguratorSetOptions(SwArticle $swArticle)
    {
        $setOptions = $swArticle->getConfiguratorSet()->getOptions();
        /** @var \Shopware\Models\Article\Configurator\Group[] $group */
        foreach ($swArticle->getConfiguratorSet()->getGroups() as $group) {
            $options = new ArrayCollection();

            /** @var \Shopware\Models\Article\Configurator\Group[] $groupOptions */
            $groupOptions = $group->getOptions();
            foreach ($groupOptions as $option) {
                if ($options->contains($option)) {
                    continue;
                }

                if ($setOptions->contains($option)) {
                    $options->add($option);
                } else {
                    /** @var SwDetail $detail */
                    foreach ($swArticle->getDetails() as $detail) {
                        if ($detail->getConfiguratorOptions()->contains($option)) {
                            $options->add($option);
                            break;
                        }
                    }
                }

                if (!$options->contains($option)) {
                    ShopUtil::entityManager()->remove($option);
                }
            }
        }
    }

    protected function prepareChildAssociatedData(JtlProduct $jtlProduct, SwArticle &$swArticle = null, SwDetail &$swDetail = null)
    {
        $productId = (strlen($jtlProduct->getId()->getEndpoint()) > 0) ? $jtlProduct->getId()->getEndpoint() : null;
        $masterProductId = (strlen($jtlProduct->getMasterProductId()->getEndpoint()) > 0) ? $jtlProduct->getMasterProductId()->getEndpoint() : null;

        if (is_null($masterProductId)) {
            throw new \Exception('Master product id is empty');
        }

        list($detailId, $id) = IdConcatenator::unlink($masterProductId);
        $swArticle = $this->find($id);
        if (is_null($swArticle)) {
            throw new \Exception(sprintf('Cannot find parent product with id (%s)', $masterProductId));
        }

        if (!is_null($productId)) {
            list($detailId, $id) = IdConcatenator::unlink($productId);

            /** @var SwDetail $detail */
            foreach ($swArticle->getDetails() as $detail) {
                if ($detail->getId() === (int)$detailId) {
                    $swDetail = $detail;
                    break;
                }
            }
        }

        if (is_null($swDetail) && strlen($jtlProduct->getSku()) > 0) {
            $swDetail = Shopware()->Models()->getRepository(SwDetail::class)->findOneBy(array('number' => $jtlProduct->getSku()));
        }
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwArticle|null $swArticle
     * @param SwDetail|null $swDetail
     * @throws LanguageException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    protected function prepareProductAssociatedData(JtlProduct $jtlProduct, SwArticle &$swArticle = null, SwDetail &$swDetail = null)
    {
        $productId = (strlen($jtlProduct->getId()->getEndpoint()) > 0) ? $jtlProduct->getId()->getEndpoint() : null;
        if ($productId !== null) {
            list($detailId, $id) = IdConcatenator::unlink($productId);

            $swArticle = $this->find((int)$id);
            if ($swArticle === null) {
                throw new \Exception(sprintf('Article with id (%s) not found', $productId));
            }

            /** @var SwDetail $detail */
            foreach ($swArticle->getDetails() as $detail) {
                if ($detail->getId() === (int)$detailId) {
                    $swDetail = $detail;
                    break;
                }
            }

            if ($swDetail === null) {
                throw new \Exception(sprintf('Detail (%s) from article (%s) not found', $detailId, $id));
            }
        } elseif (strlen($jtlProduct->getSku()) > 0) {
            $swDetail = Shopware()->Models()->getRepository('Shopware\Models\Article\Detail')->findOneBy(array('number' => $jtlProduct->getSku()));
            if ($swDetail) {
                $swArticle = $swDetail->getArticle();
            }
        }

        $isNew = false;
        if ($swArticle === null) {
            $swArticle = new SwArticle();
            $isNew = true;
        }

        $swArticle->setAdded($jtlProduct->getCreationDate())
            ->setAvailableFrom($jtlProduct->getAvailableFrom())
            ->setHighlight(intval($jtlProduct->getIsTopProduct()))
            ->setActive($jtlProduct->getIsActive());

        // new in stock
        if ($jtlProduct->getisNewProduct() && !is_null($jtlProduct->getNewReleaseDate())) {
            $swArticle->setAdded($jtlProduct->getNewReleaseDate());
        }

        // Last stock
        $inStock = 0;
        if ($jtlProduct->getConsiderStock()) {
            $inStock = $jtlProduct->getPermitNegativeStock() ? 0 : 1;
        }

        if (is_callable([$swArticle, 'setLastStock'])) {
            $swArticle->setLastStock($inStock);
        }

        // I18n
        foreach ($jtlProduct->getI18ns() as $i18n) {
            if (ShopUtil::isShopwareDefaultLanguage($i18n->getLanguageISO())) {
                $swArticle->setDescription($i18n->getMetaDescription())
                    ->setDescriptionLong($i18n->getDescription())
                    ->setKeywords($i18n->getMetaKeywords())
                    ->setMetaTitle($i18n->getTitleTag());
            }
        }

        $helper = ProductNameHelper::build($jtlProduct);
        $swArticle->setName($helper->getProductName());

        if ($isNew) {
            ShopUtil::entityManager()->persist($swArticle);
            ShopUtil::entityManager()->flush();
        }
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwArticle $swArticle
     * @throws LanguageException
     */
    protected function prepareCategoryAssociatedData(JtlProduct $jtlProduct, SwArticle $swArticle)
    {
        $collection = new ArrayCollection();
        $categoryMapper = Mmc::getMapper('Category');
        /** @deprecated Will be removed in a future connector release  $mappingOld */
        $mappingOld = Application()->getConfig()->get('category_mapping', false);
        $useMapping = Application()->getConfig()->get('category.mapping', $mappingOld);
        foreach ($jtlProduct->getCategories() as $category) {
            if (strlen($category->getCategoryId()->getEndpoint()) > 0) {
                $swCategory = $categoryMapper->find(intval($category->getCategoryId()->getEndpoint()));
                if ($swCategory) {
                    $collection->add($swCategory);

                    // Category Mapping
                    if ($useMapping) {
                        foreach ($jtlProduct->getI18ns() as $i18n) {
                            if (ShopUtil::isShopwareDefaultLanguage($i18n->getLanguageISO()) === false && strlen($i18n->getName()) > 0) {
                                $categoryMapping = CategoryMappingUtil::findCategoryMappingByParent($swCategory->getId(), $i18n->getLanguageISO());
                                if ($categoryMapping !== null) {
                                    $collection->add($categoryMapping);
                                }
                            }
                        }
                    }
                }
            }
        }

        $swArticle->setCategories($collection);
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwArticle $swArticle
     */
    protected function prepareInvisibilityAssociatedData(JtlProduct $jtlProduct, SwArticle $swArticle)
    {
        // Invisibility
        $collection = new ArrayCollection();
        foreach ($jtlProduct->getInvisibilities() as $invisibility) {
            $swCustomerGroup = CustomerGroupUtil::get(intval($invisibility->getCustomerGroupId()->getEndpoint()));
            if ($swCustomerGroup === null) {
                $swCustomerGroup = CustomerGroupUtil::get(Shopware()->Shop()->getCustomerGroup()->getId());
            }

            if ($swCustomerGroup) {
                $collection->add($swCustomerGroup);
            }
        }

        $swArticle->setCustomerGroups($collection);
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwArticle $swArticle
     * @throws DatabaseException
     */
    protected function prepareTaxAssociatedData(JtlProduct $jtlProduct, SwArticle $swArticle)
    {
        $swTax = null;
        $taxRepository = Shopware()->Models()->getRepository(Tax::class);
        if (!is_null($jtlProduct->getTaxClassId()) && !empty($taxId = $jtlProduct->getTaxClassId()->getEndpoint())) {
            $swTax = $taxRepository->findOneBy(['id' => $taxId]);
        }

        if(is_null($swTax)) {
            $swTax = $taxRepository->findOneBy(['tax' => $jtlProduct->getVat()]);
            if (count($jtlProduct->getTaxRates()) > 0 && !is_null($jtlProduct->getTaxClassId())) {
                $swTax = $this->findSwTaxByJtlTaxRates($jtlProduct->getVat(), ...$jtlProduct->getTaxRates()) ?? $swTax;
                if ($swTax instanceof Tax) {
                    //$product->getTaxClassId()->setEndpoint((string)$swTax->getId());
                }
            }
        }

        if (!$swTax instanceof Tax) {
            throw new DatabaseException('Could not find any matching Tax entity');
        }

        $swArticle->setTax($swTax);
    }

    /**
     * @param float $vat
     * @param TaxRate ...$jtlTaxRates
     * @return Tax|null
     */
    protected function findSwTaxByJtlTaxRates(float $vat, TaxRate ...$jtlTaxRates): ?Tax
    {
        $swTax = null;
        $taxRepository = Shopware()->Models()->getRepository(Tax::class);
        /** @var Tax[] $swTaxGroups */
        $swTaxGroups = [];
        foreach ($taxRepository->findBy(['tax' => $vat]) as $swTaxGroup) {
            $swTaxGroups[$swTaxGroup->getId()] = $swTaxGroup;
        }

        switch (count($swTaxGroups)) {
            case 0:
                return null;
                break;
            case 1:
                return reset($swTaxGroups);
                break;
            default:
                $commonTaxRates = [];
                foreach ($swTaxGroups as $swTaxGroup) {
                    /** @var Rule[] $swGroupRules */
                    $swGroupRules = array_combine(array_map(function (Rule $swTaxRule) {
                        return $swTaxRule->getCountry()->getIso();
                    }, $swTaxGroup->getRules()->toArray()), $swTaxGroup->getRules()->toArray());

                    $commonTaxRates[$swTaxGroup->getId()] = 0;
                    foreach ($jtlTaxRates as $jtlTaxRate) {
                        if (isset($swGroupRules[$jtlTaxRate->getCountryIso()]) && $swGroupRules[$jtlTaxRate->getCountryIso()]->getTax() === $jtlTaxRate->getRate()) {
                            $commonTaxRates[$swTaxGroup->getId()]++;
                        }
                    }
                }

                $actualMatches = 0;
                foreach ($commonTaxRates as $swTaxGroupId => $matches) {
                    if ($matches > $actualMatches) {
                        $actualMatches = $matches;
                        $swTax = $swTaxGroups[$swTaxGroupId];
                    }
                }

                break;
        }

        return $swTax;
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwArticle $swArticle
     * @throws ORMException
     */
    protected function prepareManufacturerAssociatedData(JtlProduct $jtlProduct, SwArticle $swArticle)
    {
        // Manufacturer
        $manufacturerMapper = Mmc::getMapper('Manufacturer');
        $manufacturerSW = $manufacturerMapper->find((int)$jtlProduct->getManufacturerId()->getEndpoint());
        if ($manufacturerSW) {
            $swArticle->setSupplier($manufacturerSW);
        } else {
            // Work Around - load dummy manufacturer
            $manufacturerSW = $manufacturerMapper->findOneBy(array('name' => '_'));

            if ($manufacturerSW === null) {
                $manufacturerSW = new \Shopware\Models\Article\Supplier();
                $manufacturerSW->setName('_')
                    ->setLink('');

                $manufacturerSW->setDescription('');
                $manufacturerSW->setMetaTitle('');
                $manufacturerSW->setMetaDescription('');
                $manufacturerSW->setMetaKeywords('');

                ShopUtil::entityManager()->persist($manufacturerSW);
            }

            $swArticle->setSupplier($manufacturerSW);
        }
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwArticle $swArticle
     * @throws ORMException
     */
    protected function prepareSpecialPriceAssociatedData(JtlProduct $jtlProduct, SwArticle $swArticle)
    {
        // ProductSpecialPrice
        if (is_array($jtlProduct->getSpecialPrices())) {
            foreach ($jtlProduct->getSpecialPrices() as $i => $productSpecialPrice) {
                if (count($productSpecialPrice->getItems()) == 0) {
                    continue;
                }

                $collection = array();
                $swPriceGroup = Shopware()->Models()->getRepository('Shopware\Models\Price\Group')->find(intval($productSpecialPrice->getId()->getEndpoint()));
                if ($swPriceGroup === null) {
                    $swPriceGroup = new \Shopware\Models\Price\Group();
                    ShopUtil::entityManager()->persist($swPriceGroup);
                }

                // SpecialPrice
                foreach ($productSpecialPrice->getItems() as $specialPrice) {
                    $swCustomerGroup = CustomerGroupUtil::get(intval($specialPrice->getCustomerGroupId()->getEndpoint()));
                    if ($swCustomerGroup === null) {
                        $swCustomerGroup = CustomerGroupUtil::get(Shopware()->Shop()->getCustomerGroup()->getId());
                    }

                    $price = null;
                    $productPrices = $jtlProduct->getPrices();
                    $priceCount = count($productPrices);
                    if ($priceCount == 1) {
                        $price = reset($productPrices);
                    } elseif ($priceCount > 1) {
                        foreach ($productPrices as $productPrice) {
                            if ($swCustomerGroup->getId() == intval($productPrice->getCustomerGroupId()->getEndpoint())) {
                                $price = $productPrice->getNetPrice();

                                break;
                            }
                        }
                    }

                    if ($price === null) {
                        Logger::write(sprintf('Could not find any price for customer group (%s)', $specialPrice->getCustomerGroupId()->getEndpoint()), Logger::WARNING, 'database');

                        continue;
                    }

                    $swPriceDiscount = Shopware()->Models()->getRepository('Shopware\Models\Price\Discount')->findOneBy(array('groupId' => $specialPrice->getProductSpecialPriceId()->getEndpoint()));
                    if ($swPriceDiscount === null) {
                        $swPriceDiscount = new \Shopware\Models\Price\Discount();
                        ShopUtil::entityManager()->persist($swPriceDiscount);
                    }

                    $discountValue = 100 - (($specialPrice->getPriceNet() / $price) * 100);

                    $swPriceDiscount->setCustomerGroup($swCustomerGroup)
                        ->setDiscount($discountValue)
                        ->setStart(1);

                    ShopUtil::entityManager()->persist($swPriceDiscount);

                    $collection[] = $swPriceDiscount;
                }

                ShopUtil::entityManager()->persist($swPriceGroup);

                $swPriceGroup->setName("Standard_{$i}")
                    ->setDiscounts($collection);

                $swArticle->setPriceGroup($swPriceGroup)
                    ->setPriceGroupActive(1);
            }
        }
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwArticle $swArticle
     * @param SwDetail|null $swDetail
     * @param boolean $isChild
     * @throws LanguageException
     */
    protected function prepareDetailAssociatedData(JtlProduct $jtlProduct, SwArticle $swArticle, SwDetail &$swDetail = null, bool $isChild = false)
    {
        // Detail
        if ($swDetail === null) {
            $swDetail = new SwDetail();
        }

        $swDetail->setAdditionalText('');
        $swArticle->setChanged();

        $kind = ($isChild && $swDetail->getId() != self::KIND_VALUE_PARENT && $swArticle->getMainDetail() !== null && $swArticle->getMainDetail()->getId() == $swDetail->getId()) ? self::KIND_VALUE_MAIN : self::KIND_VALUE_DEFAULT;
        $active = $jtlProduct->getIsActive();
        if (!$isChild) {
            $kind = $this->isJtlParent($jtlProduct) ? self::KIND_VALUE_PARENT : self::KIND_VALUE_MAIN;
            $active = $this->isJtlParent($jtlProduct) ? false : $active;
        }

        //$kind = $isChild ? 2 : 1;
        $swDetail->setSupplierNumber($jtlProduct->getManufacturerNumber())
            ->setNumber($jtlProduct->getSku())
            ->setActive($active)
            ->setKind($kind)
            ->setStockMin(0)
            ->setPosition($jtlProduct->getSort())
            ->setWeight($jtlProduct->getProductWeight())
            ->setInStock(floor($jtlProduct->getStockLevel()->getStockLevel()))
            ->setStockMin($jtlProduct->getMinimumQuantity())
            ->setMinPurchase(floor($jtlProduct->getMinimumOrderQuantity()))
            ->setReleaseDate($jtlProduct->getAvailableFrom())
            ->setPurchasePrice($jtlProduct->getPurchasePrice())
            ->setEan($jtlProduct->getEan());

        $swDetail->setWidth($jtlProduct->getWidth());
        $swDetail->setLen($jtlProduct->getLength());
        $swDetail->setHeight($jtlProduct->getHeight());

        // Delivery time
        $exists = false;
        $considerNextAvailableInflowDate = (bool)Application()->getConfig()->get('product.push.consider_supplier_inflow_date_for_shipping', true);
        if ($considerNextAvailableInflowDate && $jtlProduct->getStockLevel()->getStockLevel() <= 0 && !is_null($jtlProduct->getNextAvailableInflowDate())) {
            $inflow = new \DateTime($jtlProduct->getNextAvailableInflowDate()->format('Y-m-d'));
            $today = new \DateTime((new \DateTime())->format('Y-m-d'));
            if ($inflow->getTimestamp() - $today->getTimestamp() > 0) {
                $swDetail->setShippingTime(($jtlProduct->getAdditionalHandlingTime() + (int)$inflow->diff($today)->days));
                $exists = true;
            }
        }

        $useHandlingTimeOnly = (bool)Application()->getConfig()->get('product.push.use_handling_time_for_shipping', false);
        if (!$exists && !$useHandlingTimeOnly) {
            foreach ($jtlProduct->getI18ns() as $i18n) {
                if ($i18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                    $deliveryStatus = trim(str_replace(['Tage', 'Days', 'Tag', 'Day'], '', $i18n->getDeliveryStatus()));
                    if ($deliveryStatus !== '' && $deliveryStatus !== '0') {
                        $swDetail->setShippingTime($deliveryStatus);
                        $exists = true;
                        break;
                    }
                }
            }
        }

        if (!$exists) {
            $swDetail->setShippingTime($jtlProduct->getAdditionalHandlingTime() + $jtlProduct->getSupplierDeliveryTime());
        }

        // Last stock
        $inStock = 0;
        if ($jtlProduct->getConsiderStock()) {
            $inStock = $jtlProduct->getPermitNegativeStock() ? 0 : 1;
        }

        if (is_callable([$swDetail, 'setLastStock'])) {
            $swDetail->setLastStock($inStock);
        }

        // Base Price
        $swDetail->setReferenceUnit(0.0);
        $swDetail->setPurchaseUnit($jtlProduct->getMeasurementQuantity());
        if ($jtlProduct->getBasePriceDivisor() > 0 && $jtlProduct->getMeasurementQuantity() > 0) {
            $swDetail->setReferenceUnit(($jtlProduct->getMeasurementQuantity() / $jtlProduct->getBasePriceDivisor()));
        }

        $swDetail->setWeight($jtlProduct->getProductWeight())
            ->setPurchaseSteps($jtlProduct->getPackagingQuantity())
            ->setArticle($swArticle);
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwDetail $swDetail
     * @throws LanguageException
     */
    protected function prepareDetailVariationAssociatedData(JtlProduct $jtlProduct, SwDetail $swDetail)
    {
        $groupMapper = Mmc::getMapper('ConfiguratorGroup');
        $optionMapper = Mmc::getMapper('ConfiguratorOption');
        $swDetail->getConfiguratorOptions()->clear();
        foreach ($jtlProduct->getVariations() as $variation) {
            $variationName = null;
            foreach ($variation->getI18ns() as $variationI18n) {
                if (ShopUtil::isShopwareDefaultLanguage($variationI18n->getLanguageISO())) {
                    $variationName = $variationI18n->getName();
                }
            }

            $groupSW = $groupMapper->findOneBy(array('name' => $variationName));
            if ($groupSW !== null) {
                foreach ($variation->getValues() as $variationValue) {
                    $name = null;
                    foreach ($variationValue->getI18ns() as $variationValueI18n) {
                        if (ShopUtil::isShopwareDefaultLanguage($variationValueI18n->getLanguageISO())) {
                            $name = $variationValueI18n->getName();
                        }
                    }

                    if ($name === null) {
                        continue;
                    }

                    $optionSW = $optionMapper->findOneBy(array('name' => $name, 'groupId' => $groupSW->getId()));
                    if ($optionSW === null) {
                        continue;
                    }

                    $swDetail->getConfiguratorOptions()->add($optionSW);
                }
            }
        }
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwArticle $swArticle
     * @param SwDetail $swDetail
     * @param array $attrMappings
     * @param false $isChild
     * @throws LanguageException
     * @throws ORMException
     */
    protected function prepareAttributeAssociatedData(JtlProduct $jtlProduct, SwArticle $swArticle, SwDetail $swDetail, array &$attrMappings, $isChild = false)
    {
        // Attribute
        $attributeSW = $swDetail->getAttribute();
        if ($attributeSW === null) {
            $attributeSW = new Article();
            $attributeSW->setArticleDetail($swDetail);
            ShopUtil::entityManager()->persist($attributeSW);
        }

        // Image configuration ignores
        if ($this->isJtlParent($jtlProduct)) {
            $productAttribute = new ProductAttribute($swArticle->getId());
            $productAttribute->delete();
        }

        $attributes = [];
        $mappings = [];
        $attrMappings = [];

        $customPropertySupport = (bool)Application()->getConfig()->get('product.push.enable_custom_properties', false);

        $shopwareLocale = ShopUtil::locale()->getLocale();
        foreach ($jtlProduct->getAttributes() as $attribute) {
            if (!$customPropertySupport && $attribute->getIsCustomProperty()) {
                continue;
            }

            $attributeI18n = I18n::findByLocale($shopwareLocale, ...$attribute->getI18ns());

            $lcAttributeName = strtolower($attributeI18n->getName());
            $attributeValue = $attributeI18n->getValue();

            // active
            if (in_array($lcAttributeName, [ProductAttr::IS_ACTIVE, 'isactive'])) {
                $isActive = (strtolower($attributeValue) === 'false'
                    || strtolower($attributeValue) === '0') ? 0 : 1;
                if ($isChild) {
                    $swDetail->setActive($isActive);
                } else {
                    /** @var SwDetail $detail */
                    $swArticle->setActive($isActive);
                    $this->setMainDetailActive = true;
                }

                continue;
            }

            // Notification
            if (in_array($lcAttributeName, [ProductAttr::SEND_NOTIFICATION, 'sw_send_notification'])) {
                $notification = (strtolower($attributeValue) === 'false'
                    || strtolower($attributeValue) === '0') ? 0 : 1;

                $swArticle->setNotification($notification);

                continue;
            }

            // Shipping free
            if (in_array($lcAttributeName, [ProductAttr::SHIPPING_FREE, 'shippingfree'])) {
                $shippingFree = (strtolower($attributeValue) === 'false'
                    || strtolower($attributeValue) === '0') ? 0 : 1;

                $swDetail->setShippingFree($shippingFree);

                continue;
            }

            // Pseudo sales
            if (in_array($lcAttributeName, [ProductAttr::PSEUDO_SALES, 'sw_pseudo_sales'])) {
                $swArticle->setPseudoSales((int)$attributeValue);

                continue;
            }

            if ($lcAttributeName === ProductAttr::PRICE_GROUP_ID) {
                if (empty($attributeValue)) {
                    $swArticle->setPriceGroupActive(false);
                } else {
                    $swArticle->setPriceGroupActive(true);
                    $priceGroupId = (int)$attributeValue;
                    $priceGroupSW = Shopware()->Models()->getRepository(SwGroup::class)->find($priceGroupId);
                    if ($priceGroupSW instanceof SwGroup) {
                        $swArticle->setPriceGroup($priceGroupSW);
                    }
                }
                continue;
            }

            // Image configuration ignores
            if ($lcAttributeName === strtolower(ProductAttr::IMAGE_CONFIGURATION_IGNORES)
                && $this->isJtlParent($jtlProduct)) {
                try {
                    $oldAttributeValue = $productAttribute->getKey();
                    $productAttribute->setKey(ProductAttr::IMAGE_CONFIGURATION_IGNORES)
                        ->setValue($attributeValue)
                        ->save(false);

                    if ($oldAttributeValue !== $attributeValue) {
                        $this->rebuildArticleImagesMappings($swArticle);
                    }
                } catch (\Exception $e) {
                    Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
                }

                continue;
            }

            if (in_array($lcAttributeName, [ProductAttr::IS_MAIN, 'is_main']) && $isChild && (bool)$attributeValue === true) {
                /** @var SwDetail $detail */
                ShopUtil::entityManager()->refresh($swArticle);
                $details = $swArticle->getDetails();
                foreach ($details as $detail) {
                    if ($detail->getKind() !== self::KIND_VALUE_PARENT) {
                        $detail->setKind(self::KIND_VALUE_DEFAULT);
                    }
                }
                $swArticle->setMainDetail($swDetail);
                $this->setMainDetailActive = true;

                continue;
            }

            if ($isChild && $lcAttributeName === ProductAttr::ADDITIONAL_TEXT) {
                $swDetail->setAdditionalText($attributeValue);
                continue;
            }

            if (!$isChild && $lcAttributeName === ProductAttr::CUSTOM_PRODUCTS_TEMPLATE) {
                $pluginName = "SwagCustomProducts";
                /** @var Plugin $plugin */
                $plugin = ShopUtil::entityManager()->getRepository(Plugin::class)->findOneByName($pluginName);
                if ($plugin instanceof Plugin && $plugin->getActive()) {
                    $result = ShopUtil::entityManager()->getConnection()->createQueryBuilder()
                        ->delete('s_plugin_custom_products_template_product_relation')
                        ->where('article_id = :articleId')
                        ->setParameter('articleId', $swArticle->getId())
                        ->execute();

                    /** @var Template|null $template */
                    $template = ShopUtil::entityManager()->getRepository(Template::class)->findOneByInternalName($attributeValue);
                    if ($template instanceof Template) {
                        $template->getArticles()->add($swArticle);
                        ShopUtil::entityManager()->persist($template);
                    }
                }
            }

            $mappings[$attributeI18n->getName()] = $attribute->getId()->getHost();
            $attributes[$attributeI18n->getName()] = $attributeValue;
        }

        /* Save shopware attributes only from jtl products which are not a varvcombi parent */
        if ($this->isJtlParent($jtlProduct)) {
            return;
        }

        /** @deprecated Will be removed in future connector releases $nullUndefinedAttributesOld */
        $nullUndefinedAttributesOld = (bool)Application()->getConfig()->get('null_undefined_product_attributes_during_push', true);
        $nullUndefinedAttributes = (bool)Application()->getConfig()->get('product.push.null_undefined_attributes', $nullUndefinedAttributesOld);

        $swAttributesList = Shopware()->Container()->get('shopware_attribute.crud_service')->getList('s_articles_attributes');

        foreach ($swAttributesList as $tSwAttribute) {
            if ($tSwAttribute->getColumnName() === self::ATTRIBUTE_ARTICLE_SEARCH_KEYWORDS) {
                $keywordAttribute[self::ATTRIBUTE_ARTICLE_SEARCH_KEYWORDS] = $jtlProduct->getKeywords();
                TranslatableAttributes::setAttribute($tSwAttribute, $attributeSW, $keywordAttribute, false);
            } else {
                $result = TranslatableAttributes::setAttribute($tSwAttribute, $attributeSW, $attributes, $nullUndefinedAttributes);
                if ($result === true) {
                    $attrMappings[$tSwAttribute->getColumnName()] = $mappings[$tSwAttribute->getColumnName()];
                }
            }
        }

        ShopUtil::entityManager()->persist($attributeSW);
        $swDetail->setAttribute($attributeSW);
    }

    /**
     * @param JtlProduct $jtlProduct
     * @return bool
     */
    protected function hasVariationChanges(JtlProduct $jtlProduct): bool
    {
        if (count($jtlProduct->getVariations()) > 0) {
            if (strlen($jtlProduct->getId()->getEndpoint()) > 0 && IdConcatenator::isProductId($jtlProduct->getId()->getEndpoint())) {
                $checksum = ChecksumLinker::find($jtlProduct, ProductChecksum::TYPE_VARIATION);
                if ($checksum === null) {
                    return false;
                }

                return $checksum->hasChanged();
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwArticle $swArticle
     * @throws LanguageException
     * @throws ORMException
     */
    protected function prepareVariationAssociatedData(JtlProduct $jtlProduct, SwArticle $swArticle)
    {
        // Variations
        if ($this->hasVariationChanges($jtlProduct)) {
            $swConfigSet = $swArticle->getConfiguratorSet();

            $groups = array();
            $options = array();

            if (!$swConfigSet) {
                $swConfigSet = new \Shopware\Models\Article\Configurator\Set();
                $swConfigSet->setName('Set-' . $jtlProduct->getSku());
                ShopUtil::entityManager()->persist($swConfigSet);
            }

            $groupMapper = Mmc::getMapper('ConfiguratorGroup');
            $optionMapper = Mmc::getMapper('ConfiguratorOption');
            $types = array();
            foreach ($jtlProduct->getVariations() as $variation) {

                if (strlen(trim($variation->getType())) > 0) {
                    if (!isset($types[$variation->getType()])) {
                        $types[$variation->getType()] = 0;
                    }

                    $types[$variation->getType()]++;
                }

                $variationName = null;
                $variationValueName = null;
                foreach ($variation->getI18ns() as $variationI18n) {
                    if (ShopUtil::isShopwareDefaultLanguage($variationI18n->getLanguageISO())) {
                        $variationName = $variationI18n->getName();
                    }
                }

                $groupSW = $groupMapper->findOneBy(array('name' => $variationName));
                if ($groupSW === null) {
                    $groupSW = (new \Shopware\Models\Article\Configurator\Group());
                    $groupSW->setName($variationName);
                    $groupSW->setDescription('');
                }

                $groupSW->setPosition($variation->getSort());
                ShopUtil::entityManager()->persist($groupSW);

                $groups[] = $groupSW;

                foreach ($variation->getValues() as $i => $variationValue) {
                    foreach ($variationValue->getI18ns() as $variationValueI18n) {
                        if (ShopUtil::isShopwareDefaultLanguage($variationValueI18n->getLanguageISO())) {
                            $variationValueName = $variationValueI18n->getName();
                        }
                    }

                    $optionSW = null;
                    if ($groupSW->getId() > 0) {
                        $optionSW = $optionMapper->findOneBy(array('name' => $variationValueName, 'groupId' => $groupSW->getId()));
                    }

                    if ($optionSW === null) {
                        $optionSW = new \Shopware\Models\Article\Configurator\Option();
                    }

                    $optionSW->setName($variationValueName);
                    //$optionSW->setPosition(($i + 1));
                    $optionSW->setPosition($variationValue->getSort());
                    $optionSW->setGroup($groupSW);

                    ShopUtil::entityManager()->persist($optionSW);

                    //$options->add($optionSW);
                    $options[] = $optionSW;
                }
            }


            $swConfigSet->setOptions($options)
                ->setGroups($groups)
                ->setType($this->calcVariationType($types));

            ShopUtil::entityManager()->persist($swConfigSet);

            $swArticle->setConfiguratorSet($swConfigSet);
        }
    }

    /**
     * @param array $types
     * @return false|int|string|null
     */
    protected function calcVariationType(array $types)
    {
        if (count($types) == 0) {
            return ProductVariation::TYPE_RADIO;
        }

        arsort($types);

        $checkEven = function ($vTypes) {
            if (count($vTypes) > 1) {
                $arr = array_values($vTypes);
                return ($arr[0] == $arr[1]);
            }

            return false;
        };

        reset($types);
        $key = $checkEven($types) ? ProductVariation::TYPE_RADIO : key($types);

        return VariationType::map($key);
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwArticle $swArticle
     * @param SwDetail $swDetail
     */
    protected function preparePriceAssociatedData(JtlProduct $jtlProduct, SwArticle $swArticle, SwDetail $swDetail)
    {
        $collection = ProductPriceMapper::buildCollection(
            $jtlProduct->getPrices(),
            $swArticle,
            $swDetail,
            $jtlProduct->getRecommendedRetailPrice()
        );

        if (count($collection) > 0) {
            $swDetail->setPrices($collection);
        }
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwArticle $swArticle
     * @param SwDetail $swDetail
     */
    protected function prepareSpecificAssociatedData(JtlProduct $jtlProduct, SwArticle $swArticle, SwDetail $swDetail)
    {
        try {
            $group = null;
            $values = [];
            if (count($jtlProduct->getSpecifics()) > 0) {
                $group = $swArticle->getPropertyGroup();
                $optionIds = $this->getFilterOptionIds($jtlProduct);
                if (is_null($group) || !$this->isSuitableFilterGroup($group, $optionIds)) {
                    $group = null;

                    /** @var Group $fetchedGroup */
                    foreach (Shopware()->Models()->getRepository(Group::class)->findAll() as $fetchedGroup) {
                        if ($this->isSuitableFilterGroup($fetchedGroup, $optionIds)) {
                            $group = $fetchedGroup;
                            break;
                        }
                    }

                    if (is_null($group)) {
                        $options = Shopware()->Models()->getRepository(Option::class)->findById($optionIds);
                        $groupName = implode('_', array_map(function (Option $option) {
                            return $option->getName();
                        }, $options));
                        $group = (new \Shopware\Models\Property\Group())
                            ->setName($groupName)
                            ->setPosition(0)
                            ->setComparable(1)
                            ->setSortMode(0)
                            ->setOptions($options);

                        ShopUtil::entityManager()->persist($group);
                    }
                }

                $values = Shopware()->Models()->getRepository(Value::class)->findById($this->getFilterValueIds($jtlProduct));
            }

            $swArticle->setPropertyValues(new ArrayCollection($values));
            $swArticle->setPropertyGroup($group);
        } catch (\Exception $e) {
            Logger::write(sprintf(
                'Property group (s_articles <--> s_filter) not found! %s',
                ExceptionFormatter::format($e)
            ), Logger::ERROR, 'database');
        }
    }

    /**
     * @param JtlProduct $jtlProduct
     * @return integer[]
     */
    protected function getFilterOptionIds(JtlProduct $jtlProduct): array
    {
        $ids = array_map(function (\jtl\Connector\Model\ProductSpecific $specific) {
            return $specific->getId()->getEndpoint();
        }, $jtlProduct->getSpecifics());

        return array_values(array_unique(array_filter($ids, function ($id) {
            return !empty($id);
        })));
    }

    /**
     * @param JtlProduct $jtlProduct
     * @return integer[]
     */
    protected function getFilterValueIds(JtlProduct $jtlProduct): array
    {
        $ids = array_map(function (\jtl\Connector\Model\ProductSpecific $specific) {
            return $specific->getSpecificValueId()->getEndpoint();
        }, $jtlProduct->getSpecifics());

        return array_values(array_filter($ids, function ($id) {
            return !empty($id);
        }));
    }

    /**
     * @param Group $swGroup
     * @param integer[] $swOptionsIds
     * @return boolean
     */
    protected function isSuitableFilterGroup(Group $swGroup, array $swOptionsIds): bool
    {
        $options = $swGroup->getOptions();
        if (count($options) !== count($swOptionsIds)) {
            return false;
        }

        foreach ($options as $option) {
            if (!in_array($option->getId(), $swOptionsIds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwArticle $swArticle
     * @param SwDetail $swDetail
     * @param array $attrMappings
     * @throws \Zend_Db_Adapter_Exception
     * @throws LanguageException
     */
    protected function saveTranslations(JtlProduct $jtlProduct, SwArticle $swArticle, SwDetail $swDetail, array $attrMappings)
    {
        $type = 'article';
        $key = $swArticle->getId();
        $merge = false;
        if ($this->isJtlChild($jtlProduct)) {
            if ($swDetail !== $swArticle->getMainDetail()) {
                $type = 'variant';
                $key = $swDetail->getId();
            } else {
                $merge = true;
            }
            $translations = $this->createArticleDetailTranslations($jtlProduct, $attrMappings);
        } else {
            if ($jtlProduct->getIsMasterProduct()) {
                $merge = true;
            }
            $translations = $this->createArticleTranslations($jtlProduct, $attrMappings);
        }

        $translationService = ShopUtil::translationService();

        foreach ($translations as $langIso2B => $translation) {
            /** @var \Shopware\Models\Shop\Locale $locale */
            $langIso1 = LanguageUtil::convert(null, $langIso2B);
            if ($langIso1 === LocaleUtil::extractLanguageIsoFromLocale(ShopUtil::locale()->getLocale())) {
                continue;
            }

            /** @var \Shopware\Models\Shop\Shop[] $shops */
            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findByLanguageIso($langIso1);

            foreach ($shops as $shop) {
                if ($merge) {
                    $savedTranslation = $translationService->read($shop->getId(), $type, $key);
                    $translation = array_merge($savedTranslation, $translation);
                }
                $translationService->write($shop->getId(), $type, $key, $translation);
            }
        }

    }

    /**
     * @param JtlProduct $jtlProduct
     * @param array $attrMappings
     * @return string[]
     * @throws LanguageException
     */
    protected function createArticleTranslations(JtlProduct $jtlProduct, array $attrMappings)
    {
        $detailTranslations = [];
        if (!$jtlProduct->getIsMasterProduct()) {
            $detailTranslations = $this->createArticleDetailTranslations($jtlProduct, $attrMappings);
        }

        $data = [];
        foreach ($jtlProduct->getI18ns() as $i18n) {
            $langIso = $i18n->getLanguageISO();
            if (ShopUtil::isShopwareDefaultLanguage($langIso)) {
                continue;
            }

            if (!isset($data[$langIso])) {
                $data[$langIso] = $this->initSwArticleTranslation();
            }

            $data[$langIso] = [
                'name' => ProductNameHelper::build($jtlProduct, $langIso)->getProductName(),
                'descriptionLong' => $i18n->getDescription(),
                'metaTitle' => $i18n->getTitleTag(),
                'description' => $i18n->getMetaDescription(),
                'keywords' => $i18n->getMetaKeywords(),
            ];
        }

        foreach ($detailTranslations as $langIso => $translation) {
            if (!isset($data[$langIso])) {
                $data[$langIso] = [];
            }

            $data[$langIso] = array_merge($data[$langIso], $translation);
        }

        return $data;
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param array $attrMappings
     * @return array
     * @throws LanguageException
     */
    protected function createArticleDetailTranslations(JtlProduct $jtlProduct, array $attrMappings)
    {
        $data = [];
        foreach ($jtlProduct->getAttributes() as $attribute) {
            foreach ($attribute->getI18ns() as $attrI18n) {
                $langIso = $attrI18n->getLanguageISO();
                if (ShopUtil::isShopwareDefaultLanguage($langIso)) {
                    continue;
                }

                if (!isset($data[$langIso])) {
                    $data[$langIso] = $this->initSwVariantTranslation();
                }

                if (strtolower($attrI18n->getName()) === ProductAttr::ADDITIONAL_TEXT) {
                    $data[$langIso]['additionalText'] = $attrI18n->getValue();
                } elseif (($index = array_search($attribute->getId()->getHost(), $attrMappings)) !== false) {
                    $i = "__attribute_{$index}";
                    $data[$langIso][$i] = $attrI18n->getValue();
                }
            }
        }

        // Unit
        if ($jtlProduct->getUnitId()->getHost() != 0) {
            $unitMapper = Mmc::getMapper('Unit');
            $unitSW = $unitMapper->findOneBy(array('hostId' => $jtlProduct->getUnitId()->getHost()));
            if (!is_null($unitSW)) {
                foreach ($unitSW->getI18ns() as $unitI18n) {
                    $langIso = $unitI18n->getLanguageIso();
                    if (ShopUtil::isShopwareDefaultLanguage($langIso)) {
                        continue;
                    }

                    if (!isset($data[$langIso])) {
                        $data[$langIso] = $this->initSwVariantTranslation();
                    }

                    $data[$langIso]['packUnit'] = $unitI18n->getName();
                }
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    protected function initSwVariantTranslation()
    {
        return [
            'additionalText' => '',
            'packUnit' => '',
            'shippingTime' => '',
        ];
    }

    /**
     * @return array
     */
    protected function initSwArticleTranslation()
    {
        return [
            'name' => '',
            'description' => '',
            'descriptionLong' => '',
            'shippingTime' => '',
            'additionalText' => '',
            'keywords' => '',
            'packUnit' => '',
        ];
    }

    /**
     * @param JtlProduct $product
     * @param SwArticle $productSW
     * @throws LanguageException
     */
    protected function saveVariationTranslationData(JtlProduct $product, SwArticle &$productSW)
    {
        /** @var ConfiguratorGroup $groupMapper */
        $groupMapper = Mmc::getMapper('ConfiguratorGroup');

        /** @var ConfiguratorOption $optionMapper */
        $optionMapper = Mmc::getMapper('ConfiguratorOption');
        $confiSetSW = $productSW->getConfiguratorSet();
        if ($confiSetSW !== null && count($product->getVariations()) > 0) {

            // Get default translation values
            $variations = array();
            $values = array();

            foreach ($product->getVariations() as $variation) {
                foreach ($variation->getI18ns() as $variationI18n) {
                    if (ShopUtil::isShopwareDefaultLanguage($variationI18n->getLanguageISO())) {
                        $variations[$variationI18n->getName()] = $variation->getId()->getHost();
                        break;
                    }
                }

                foreach ($variation->getValues() as $value) {
                    foreach ($value->getI18ns() as $valueI18n) {
                        if (ShopUtil::isShopwareDefaultLanguage($valueI18n->getLanguageISO())) {
                            $values[$variation->getId()->getHost()][$valueI18n->getName()] = $value->getId()->getHost();
                            break;
                        }
                    }
                }
            }

            // Write non default translation values
            foreach ($product->getVariations() as $variation) {
                foreach ($variation->getI18ns() as $variationI18n) {
                    if (ShopUtil::isShopwareDefaultLanguage($variationI18n->getLanguageISO()) === false) {
                        foreach ($confiSetSW->getGroups() as $groupSW) {
                            if (isset($variations[$groupSW->getName()]) && $variations[$groupSW->getName()] == $variation->getId()->getHost()) {
                                try {
                                    $groupMapper->saveTranslatation($groupSW->getId(), $variationI18n->getLanguageISO(), $variationI18n->getName());
                                } catch (\Exception $e) {
                                    Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
                                }
                            }
                        }
                    }
                }

                foreach ($variation->getValues() as $value) {
                    foreach ($value->getI18ns() as $valueI18n) {
                        if (ShopUtil::isShopwareDefaultLanguage($valueI18n->getLanguageISO()) === false) {
                            foreach ($confiSetSW->getOptions() as $optionSW) {
                                if (isset($values[$variation->getId()->getHost()][$optionSW->getName()])
                                    && $values[$variation->getId()->getHost()][$optionSW->getName()] == $value->getId()->getHost()) {

                                    try {
                                        $optionMapper->saveTranslatation($optionSW->getId(), $valueI18n->getLanguageISO(), $valueI18n->getName());
                                    } catch (\Exception $e) {
                                        Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwArticle $swArticle
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function prepareSetVariationRelations(JtlProduct $jtlProduct, SwArticle $swArticle)
    {
        if (!$this->hasVariationChanges($jtlProduct)) {
            return;
        }

        $swConfigSet = $swArticle->getConfiguratorSet();

        $sql = "DELETE FROM s_article_configurator_set_group_relations WHERE set_id = ?";
        Shopware()->Db()->query($sql, array($swConfigSet->getId()));

        $sql = "DELETE FROM s_article_configurator_set_option_relations WHERE set_id = ?";
        Shopware()->Db()->query($sql, array($swConfigSet->getId()));

        // Groups
        foreach ($swConfigSet->getGroups() as $groupSW) {
            $sql = "INSERT INTO s_article_configurator_set_group_relations (set_id, group_id) VALUES (?,?)";
            Shopware()->Db()->query($sql, array($swConfigSet->getId(), $groupSW->getId()));
        }

        // Options
        foreach ($swConfigSet->getOptions() as $optionSW) {
            $sql = "INSERT INTO s_article_configurator_set_option_relations (set_id, option_id) VALUES (?,?)";
            Shopware()->Db()->query($sql, array($swConfigSet->getId(), $optionSW->getId()));
        }
    }

    /**
     * @param JtlProduct $product
     * @param SwDetail|null $detailSW
     * @throws LanguageException
     */
    protected function prepareUnitAssociatedData(JtlProduct $product, SwDetail &$detailSW = null)
    {
        if ($product->getUnitId()->getHost() > 0) {
            $unitMapper = Mmc::getMapper('Unit');
            $unitSW = $unitMapper->findOneBy(array('hostId' => $product->getUnitId()->getHost()));
            if ($unitSW !== null) {
                foreach ($unitSW->getI18ns() as $unitI18n) {
                    if ($unitI18n->getLanguageIso() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                        $detailSW->setPackUnit($unitI18n->getName());
                    }
                }
            }
        }
    }

    /**
     * @param JtlProduct $jtlProduct
     * @param SwDetail|null $swDetail
     * @throws \Exception
     */
    protected function prepareMeasurementUnitAssociatedData(JtlProduct $jtlProduct, SwDetail $swDetail = null)
    {
        if (strlen($jtlProduct->getMeasurementUnitCode()) > 0) {
            $measurementUnitMapper = Mmc::getMapper('MeasurementUnit');
            $measurementUnitSW = $measurementUnitMapper->findOneBy(array('unit' => $jtlProduct->getMeasurementUnitCode()));
            if ($measurementUnitSW !== null) {
                $swDetail->setUnit($measurementUnitSW);
            }
        }
    }

    /**
     * @param JtlProduct $jtlproduct
     * @param SwArticle $swArticle
     * @throws LanguageException
     * @throws ORMException
     */
    protected function prepareMediaFileAssociatedData(JtlProduct $jtlproduct, SwArticle $swArticle)
    {
        $linkCollection = array();
        $downloadCollection = array();

        foreach ($jtlproduct->getMediaFiles() as $mediaFile) {
            $name = '';
            foreach ($mediaFile->getI18ns() as $i18n) {
                if (ShopUtil::isShopwareDefaultLanguage($i18n->getLanguageIso())) {
                    $name = $i18n->getName();
                }
            }

            if (preg_match('/^http|ftp{1}/i', $mediaFile->getUrl())) {
                $swLink = new SwLink();
                $swLink->setLink($mediaFile->getUrl())
                    ->setName($name);

                ShopUtil::entityManager()->persist($swLink);
                $linkCollection[] = $swLink;
            } else {
                $swDownload = (new SwDownload())
                    ->setFile($mediaFile->getUrl())
                    //->setSize(0)
                    ->setName($name);

                ShopUtil::entityManager()->persist($swDownload);
                $downloadCollection[] = $swDownload;
            }
        }

        $swArticle->setLinks($linkCollection);
        $swArticle->setDownloads($downloadCollection);
    }

    /**
     * @param SwArticle $swArticle
     */
    protected function deleteTranslationData(SwArticle $swArticle)
    {
        ShopUtil::translationService()->delete('article', $swArticle->getId());
    }

    /**
     * @param JtlProduct $jtlProduct
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function deleteProductData(JtlProduct $jtlProduct)
    {
        $productId = (strlen($jtlProduct->getId()->getEndpoint()) > 0) ? $jtlProduct->getId()->getEndpoint() : null;

        if ($productId !== null) {
            list($detailId, $id) = IdConcatenator::unlink($productId);
            $detailSW = $this->findDetail((int)$detailId);
            if ($detailSW === null) {
                //throw new DatabaseException(sprintf('Detail (%s) not found', $detailId));
                Logger::write(sprintf('Detail with id (%s, %s) not found',
                    $jtlProduct->getId()->getEndpoint(),
                    $jtlProduct->getId()->getHost()
                ), Logger::ERROR, 'database');
                return;
            }

            $swProduct = $this->find((int)$id);
            if ($swProduct === null) {
                Logger::write(sprintf('Product with id (%s, %s) not found',
                    $jtlProduct->getId()->getEndpoint(),
                    $jtlProduct->getId()->getHost()
                ), Logger::ERROR, 'database');
                return;
            }

            $mainDetailId = Shopware()->Db()->fetchOne(
                'SELECT main_detail_id FROM s_articles WHERE id = ?',
                array($swProduct->getId())
            );

            $sql = 'DELETE FROM s_article_configurator_option_relations WHERE article_id = ?';
            Shopware()->Db()->query($sql, array($detailSW->getId()));

            if ($this->isSwChild($swProduct, $detailSW)) {
                try {
                    Shopware()->Db()->delete('s_articles_attributes', array('articledetailsID = ?' => $detailSW->getId()));
                    Shopware()->Db()->delete('s_articles_prices', array('articledetailsID = ?' => $detailSW->getId()));
                    Shopware()->Db()->delete('s_articles_details', array('id = ?' => $detailSW->getId()));

                    if ($mainDetailId == $detailSW->getId()) {
                        $count = Shopware()->Db()->fetchOne(
                            'SELECT count(*) FROM s_articles_details WHERE articleID = ?',
                            array($swProduct->getId())
                        );

                        $kindSql = ($count > 1) ? ' AND kind != ' . self::KIND_VALUE_PARENT . ' ' : '';

                        Shopware()->Db()->query(
                            'UPDATE s_articles SET main_detail_id = (SELECT id FROM s_articles_details WHERE articleID = ? ' . $kindSql . ' LIMIT 1) WHERE id = ?',
                            array($swProduct->getId(), $swProduct->getId())
                        );
                    }
                } catch (\Exception $e) {
                    Logger::write('DETAIL ' . ExceptionFormatter::format($e), Logger::ERROR, 'database');
                }
            } elseif ($swProduct !== null) {
                try {
                    $this->deleteTranslationData($swProduct);

                    $set = $swProduct->getConfiguratorSet();
                    if ($set !== null) {
                        ShopUtil::entityManager()->remove($set);
                    }

                    Shopware()->Db()->delete('s_articles_attributes', array('articledetailsID = ?' => $detailSW->getId()));
                    Shopware()->Db()->delete('s_articles_prices', array('articledetailsID = ?' => $detailSW->getId()));
                    Shopware()->Db()->delete('s_articles_details', array('id = ?' => $detailSW->getId()));
                    Shopware()->Db()->query(
                        'DELETE f, r
                            FROM s_filter f
                            LEFT JOIN s_filter_relations r ON r.groupID = f.id
                            WHERE f.name = ?',
                        array($detailSW->getNumber())
                    );
                    Shopware()->Db()->delete('s_filter_articles', array('articleID = ?' => $swProduct->getId()));

                    ShopUtil::entityManager()->remove($swProduct);
                    ShopUtil::entityManager()->flush($swProduct);
                } catch (\Exception $e) {
                    Logger::write('PARENT ' . ExceptionFormatter::format($e), Logger::ERROR, 'database');
                }
            }
        }
    }

    /**
     * @param JtlProduct $jtlProduct
     * @return bool
     */
    public function isJtlChild(JtlProduct $jtlProduct)
    {
        return (!$jtlProduct->getIsMasterProduct() && $jtlProduct->getMasterProductId()->getHost() > 0);
    }

    /**
     * @param JtlProduct $jtlProduct
     * @return bool
     */
    public function isJtlParent(JtlProduct $jtlProduct)
    {
        return ($jtlProduct->getIsMasterProduct() && $jtlProduct->getMasterProductId()->getHost() == 0);
    }

    /**
     * @param SwArticle|null $swArticle
     * @param SwDetail $swDetail
     * @return bool
     */
    public function isSwChild(SwArticle $swArticle = null, SwDetail $swDetail)
    {
        // If the parent is already deleted or a configurator set is present
        if ($swArticle === null || ($swArticle->getConfiguratorSet() !== null && $swArticle->getConfiguratorSet()->getId() > 0)) {
            return ((int)$swDetail->getKind() !== self::KIND_VALUE_PARENT);
        }

        return false;
    }


    /**
     * @param array $data
     * @return boolean
     */
    public function isDetailData(array $data)
    {
        return (
            isset($data['article']) &&
            is_array($data['article']) &&
            isset($data['article']['configuratorSetId']) &&
            (int)$data['article']['configuratorSetId'] > 0 &&
            isset($data['kind']) &&
            $data['kind'] != self::KIND_VALUE_PARENT
        );
    }

    /**
     * @param mixed[] $data
     * @return boolean
     */
    public function isParentData(array $data)
    {
        return (
            isset($data['configuratorSetId']) &&
            (int)$data['configuratorSetId'] > 0 &&
            isset($data['kind']) &&
            (int)$data['kind'] == self::KIND_VALUE_PARENT
        );
    }
}
