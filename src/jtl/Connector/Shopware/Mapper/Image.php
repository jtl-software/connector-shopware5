<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Core\IO\Path;
use jtl\Connector\Core\IO\Temp;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Utilities\Seo;
use \jtl\Connector\Drawing\ImageRelationType;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Linker\IdentityLinker;
use \jtl\Connector\Model\Image as JtlImage;
use \jtl\Connector\Shopware\Model\Image as ImageConModel;
use \jtl\Connector\Model\Identity;
use jtl\Connector\Shopware\Model\ProductAttr;
use jtl\Connector\Shopware\Utilities\Sort;
use Shopware\Components\Api\Manager;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Configurator\Option;
use Shopware\Models\Article\Detail;
use Shopware\Models\Media\Album;
use \Shopware\Models\Media\Media as MediaSW;
use \Shopware\Models\Article\Image as ArticleImage;
use \Shopware\Models\Article\Image\Mapping as MappingSW;
use \Shopware\Models\Article\Image\Rule as RuleSW;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Utilities\IdConcatenator;
use \Shopware\Models\Article\Detail as DetailSW;
use \Shopware\Models\Article\Article as ArticleSW;
use Shopware\Models\Media\Media;
use \Symfony\Component\HttpFoundation\File\File;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Shopware\Utilities\Translation as TranslationUtil;
use jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use jtl\Connector\Shopware\Utilities\CategoryMapping as CategoryMappingUtil;
use \jtl\Connector\Shopware\Utilities\Shop;

class Image extends DataMapper
{
    public function find($id)
    {
        return (intval($id) == 0) ? null : $this->Manager()->getRepository('Shopware\Models\Media\Media')->find((int)$id);
    }

    public function findBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Media\Media')->findOneBy($kv);
    }

    public function findArticleImage($id)
    {
        try {
            return $this->Manager()->createQueryBuilder()
                ->select(
                    'image',
                    'media'
                )
                ->from('Shopware\Models\Article\Image', 'image')
                ->leftJoin('image.media', 'media')
                ->where('image.id = :id')
                ->setParameter('id', $id)
                ->getQuery()->getOneOrNullResult();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function findAll($limit = null, $count = false, $relationType = null)
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('id', 'id');
        $rsm->addScalarResult('mediaId', 'mediaId');
        $rsm->addScalarResult('path', 'path');

        switch ($relationType) {
            case ImageRelationType::TYPE_PRODUCT:
                return Shopware()->Db()->fetchAssoc(
                    'SELECT i.id as cId, if (d.id > 0, d.id, a.main_detail_id) as detailId, i.*, m.path
                      FROM s_articles_img i
                      LEFT JOIN s_articles_img c ON c.parent_id = i.id
                      LEFT JOIN s_articles a ON a.id = i.articleID
                      LEFT JOIN s_articles_details d ON d.articleID = a.id
                          AND d.kind = ?
                      LEFT JOIN jtl_connector_link_product_image l ON l.id = i.id
                      JOIN s_media m ON m.id = i.media_id
                      WHERE i.articleID IS NOT NULL
                          AND c.id IS NULL
                          AND l.host_id IS NULL
                      UNION
                      SELECT i.id as cId, i.article_detail_id as detailId, p.*, m.path
                      FROM s_articles_img i
                      JOIN s_articles_img p ON i.parent_id = p.id
                      LEFT JOIN jtl_connector_link_product_image l ON l.id = i.id
                      JOIN s_media m ON m.id = p.media_id
                      WHERE i.articleID IS NULL
                          AND l.host_id IS NULL
                      LIMIT ' . intval($limit)
                    , [Product::KIND_VALUE_PARENT]);

                /*
                return $this->Manager()->createQueryBuilder()
                    ->select(
                        'image',
                        'article',
                        'media',
                        'parent',
                        'pmedia'
                    )
                    ->from('jtl\Connector\Shopware\Model\Linker\ProductImage', 'image')
                    ->leftJoin('image.article', 'article')
                    ->leftJoin('image.media', 'media')
                    ->leftJoin('image.parent', 'parent')
                    ->leftJoin('parent.media', 'pmedia')
                    ->leftJoin('image.linker', 'linker')
                    ->setFirstResult(0)
                    ->setMaxResults($limit)
                    ->where('linker.hostId IS NULL')
                    ->getQuery()->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
                */
                break;
            case ImageRelationType::TYPE_CATEGORY:
                $query = Shopware()->Models()->createNativeQuery(
                    'SELECT c.id, m.id AS mediaId, m.path
                    FROM s_categories c
                    JOIN s_media m ON m.id = c.mediaID
                    LEFT JOIN jtl_connector_link_image i ON i.media_id = m.id
                    WHERE i.host_id IS NULL
                    LIMIT ' . $limit, $rsm);
                break;
            case ImageRelationType::TYPE_MANUFACTURER:
                $query = Shopware()->Models()->createNativeQuery(
                    'SELECT s.id, m.id AS mediaId, m.path
                    FROM s_articles_supplier s
                    JOIN s_media m ON m.path = s.img
                    LEFT JOIN jtl_connector_link_image i ON i.media_id = m.id
                    WHERE i.host_id IS NULL
                    LIMIT ' . $limit, $rsm);
                break;
            case ImageRelationType::TYPE_SPECIFIC_VALUE:
                $query = Shopware()->Models()->createNativeQuery(
                    'SELECT v.id, m.id AS mediaId, m.path
                    FROM s_filter_values v
                    JOIN s_media m ON m.id = v.media_id
                    LEFT JOIN jtl_connector_link_image i ON i.media_id = m.id
                    WHERE i.host_id IS NULL
                    LIMIT ' . $limit, $rsm);
                break;
        }

        if ($query !== null) {
            return $query->getResult();
        }

        return array();
    }

    public function fetchCount($limit = 100, $relationType = null)
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();
        $rsm->addScalarResult('count', 'count');

        $query = null;
        $count = 0;
        switch ($relationType) {
            case ImageRelationType::TYPE_PRODUCT:
                $counts = Shopware()->Db()->fetchAssoc(
                    'SELECT count(*) as count
                      FROM s_articles_img i
                      LEFT JOIN s_articles_img c ON c.parent_id = i.id
                      LEFT JOIN s_articles a ON a.id = i.articleID
                      LEFT JOIN jtl_connector_link_product_image l ON l.id = i.id
                      JOIN s_media m ON m.id = i.media_id
                      WHERE i.articleID IS NOT NULL
                          AND c.id IS NULL
                          AND l.host_id IS NULL
                      UNION
                      SELECT count(*) as count
                      FROM s_articles_img i
                      JOIN s_articles_img p ON i.parent_id = p.id
                      LEFT JOIN jtl_connector_link_product_image l ON l.id = i.id
                      JOIN s_media m ON m.id = p.media_id
                      WHERE i.articleID IS NULL
                          AND l.host_id IS NULL'
                );

                foreach ($counts as $c) {
                    $count += (int)$c['count'];
                }

                /*
                $query = Shopware()->Models()->createNativeQuery(
                    'SELECT count(*) as count 
                    FROM s_articles_img a
                    LEFT JOIN jtl_connector_link_product_image p ON p.id = a.id
                    WHERE p.host_id IS NULL', $rsm);
                */
                break;
            case ImageRelationType::TYPE_CATEGORY:
                $query = Shopware()->Models()->createNativeQuery(
                    'SELECT count(*) as count 
                    FROM s_categories c 
                    LEFT JOIN jtl_connector_link_image i ON i.media_id = c.mediaID
                    WHERE c.mediaID > 0
                        AND i.host_id IS NULL', $rsm);
                break;
            case ImageRelationType::TYPE_MANUFACTURER:
                $query = Shopware()->Models()->createNativeQuery(
                    'SELECT count(*) as count
                    FROM s_articles_supplier s
                    JOIN s_media m ON m.path = s.img
                    LEFT JOIN jtl_connector_link_image i ON i.media_id = m.id
                    WHERE LENGTH(s.img) > 0 AND i.host_id IS NULL', $rsm);
                break;
            case ImageRelationType::TYPE_SPECIFIC_VALUE:
                $query = Shopware()->Models()->createNativeQuery(
                    'SELECT count(*) as count
                    FROM s_filter_values v
                    JOIN s_media m ON m.id = v.media_id
                    LEFT JOIN jtl_connector_link_image i ON i.media_id = m.id
                    WHERE v.media_id IS NOT NULL AND i.host_id IS NULL', $rsm);
                break;
        }

        if ($query !== null) {
            $result = $query->getResult();
            if (isset($result[0]['count'])) {
                $count = (int)$result[0]['count'];
            }
        }

        return $count;
    }

    public function delete(JtlImage $image)
    {
        $result = $image;

        try {
            $this->deleteImageData($image);
        } catch (\Exception $e) {
            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
        }

        return $result;
    }

    public function save(JtlImage $image)
    {
        $mediaSW = null;
        $imageSW = null;
        $parentImageSW = null;
        $result = new JtlImage;

        Shop::entityManager()->enableDebugMode();

        try {
            $this->prepareImageAssociatedData($image, $mediaSW, $imageSW, $parentImageSW);

            $this->Manager()->persist($mediaSW);
            $this->Manager()->persist($imageSW);

            $this->flush();

            // Save image title translations
            if (!is_null($parentImageSW)) {
                $this->saveAltText($image, $parentImageSW);
            }

            $manager = Shopware()->Container()->get('thumbnail_manager');
            $manager->createMediaThumbnail($mediaSW, array(), true);

            $endpoint = ImageConModel::generateId($image->getRelationType(), $imageSW->getId(), $mediaSW->getId());
            if (strlen($image->getId()->getEndpoint()) > 0 && $image->getId()->getHost() > 0
                && $endpoint !== $image->getId()->getEndpoint()) {

                Application()->getConnector()->getPrimaryKeyMapper()->delete(
                    $image->getId()->getEndpoint(),
                    $image->getId()->getHost(),
                    IdentityLinker::TYPE_IMAGE
                );

                Application()->getConnector()->getPrimaryKeyMapper()->save(
                    $endpoint,
                    $image->getId()->getHost(),
                    IdentityLinker::TYPE_IMAGE
                );
            }

            // Result
            $result->setId(new Identity($endpoint, $image->getId()->getHost()))
                ->setForeignKey(new Identity($image->getForeignKey()->getEndpoint(), $image->getForeignKey()->getHost()))
                ->setRelationType($image->getRelationType())//->setFilename(sprintf('http://%s%s/%s', Shopware()->Shop()->getHost(), Shopware()->Shop()->getBaseUrl(), $mediaSW->getPath()));
            ;
        } catch (\Exception $e) {
            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
            throw $e;
        } finally {
            @unlink($image->getFilename());
        }

        return $result;
    }

    protected function deleteImageData(JtlImage &$image)
    {
        if (strlen($image->getId()->getEndpoint()) === 0) {
            return $image;
        }

        list($type, $imageId, $mediaId) = IdConcatenator::unlink($image->getId()->getEndpoint());
        $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? $image->getForeignKey()->getEndpoint() : null;
        if (is_null($foreignId)) {
            throw new \RuntimeException(sprintf('Foreign key from image (%s/%s) is empty!', $image->getId()->getEndpoint(), $image->getId()->getHost()));
        }

        $deleteMedia = true;
        switch ($image->getRelationType()) {
            case ImageRelationType::TYPE_PRODUCT:
                $deleteMedia = false;
                list($detailId, $articleId) = IdConcatenator::unlink($foreignId);
                $this->deleteProductImage($articleId, $detailId, $imageId);
                Shop::entityManager()->flush();
                break;
            case ImageRelationType::TYPE_CATEGORY:
                $categorySW = $this->Manager()->getRepository('Shopware\Models\Category\Category')->find((int)$imageId);
                if ($categorySW !== null) {
                    $categorySW->setMedia(null);

                    try {
                        $this->Manager()->persist($categorySW);
                        $this->Manager()->flush();
                    } catch (\Exception $e) {
                        Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
                    }
                }
                break;
            case ImageRelationType::TYPE_MANUFACTURER:
                $supplierSW = $this->Manager()->getRepository('Shopware\Models\Article\Supplier')->find((int)$imageId);
                if ($supplierSW !== null) {
                    $supplierSW->setImage('');

                    try {
                        $this->Manager()->persist($supplierSW);
                        $this->Manager()->flush();
                    } catch (\Exception $e) {
                        Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
                    }
                }
                break;
        }

        if ($deleteMedia) {
            $this->deleteMedia($mediaId);
        }
    }

    protected function deleteProductImage($articleId, $detailId, $imageId)
    {
        /** @var Article $article */
        $article = Shop::entityManager()->find(Article::class, $articleId);
        if (is_null($article)) {
            throw new \RuntimeException('Article with id ' . $articleId . ' not found!');
        }

        $swImage = null;
        /** @var ArticleImage $image */
        foreach ($article->getImages() as $image) {
            if ($image->getId() == $imageId) {
                $swImage = $image;
                break;
            }
        }

        if (is_null($swImage)) {
            throw new \RuntimeException('Image with id ' . $imageId . ' not found!');
        }

        if (!is_null($swImage->getParent())) {
            $swDetail = null;
            foreach ($article->getDetails() as $detail) {
                if ($detail->getId() == $detailId) {
                    $swDetail = $detail;
                    break;
                }
            }

            if (is_null($swDetail)) {
                throw new \RuntimeException('Article detail with id ' . $detailId . ' not found!');
            }

            $mapping = $this->findImageMapping($swDetail, $swImage);
            if (!is_null($mapping)) {
                foreach ($mapping->getRules() as $rule) {
                    Shop::entityManager()->remove($rule);
                }
                shop::entityManager()->remove($mapping);
            }
            Shop::entityManager()->remove($swImage);
            $swImage = $swImage->getParent();

            Logger::write(
                sprintf(
                    'Pseudo image (%s) and depending mappings from article (%s) detail (%s) deleted',
                    $imageId,
                    $articleId,
                    $detailId
                ),
                Logger::DEBUG,
                'image'
            );
        }

        if ($swImage->getChildren()->count() === 0) {
            $this->deleteMediaNew($swImage->getMedia());
            Shop::entityManager()->remove($swImage);

            Logger::write(
                sprintf(
                    'Image (%s) from article (%s) deleted',
                    $imageId,
                    $articleId
                ),
                Logger::DEBUG,
                'image'
            );
        }
    }

    /**
     * @param DetailSW $detail
     * @param ArticleImage $image
     * @return null|MappingSW
     */
    protected function findImageMapping(Detail $detail, ArticleImage $image)
    {
        $detailOptions = array_map(function (Option $option) {
            return $option->getId();
        }, $detail->getConfiguratorOptions()->toArray());

        /** @var ArticleImage\Mapping $mapping */
        foreach ($image->getMappings() as $mapping) {
            $mappingOptions = [];
            /** @var ArticleImage\Rule $rule */
            foreach ($mapping->getRules() as $rule) {
                $mappingOptions[] = $rule->getOption()->getId();
            }

            if ($detailOptions == $mappingOptions) {
                return $mapping;
            }
        }
        return null;
    }

    protected function deleteMedia($mediaId)
    {
        /** @var Media $mediaSW */
        $mediaSW = $this->Manager()->getRepository('Shopware\Models\Media\Media')->find((int)$mediaId);
        $service = Shop::mediaService();
        if ($mediaSW !== null) {
            try {
                $service->delete($mediaSW->getPath());
                $this->Manager()->remove($mediaSW);
                $this->Manager()->flush();
            } catch (\Exception $e) {
                Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
            }
        }
    }

    protected function deleteMediaNew(Media $media)
    {
        $mediaService = Shop::mediaService();
        $thumbnailManager = Shop::thumbnailManager();
        $thumbnailManager->removeMediaThumbnails($media);
        $mediaService->delete($media->getPath());
        $this->Manager()->remove($media);
    }

    protected function prepareImageAssociatedData(JtlImage &$image,
                                                  MediaSW &$mediaSW = null,
                                                  \Shopware\Components\Model\ModelEntity &$imageSW = null,
                                                  \Shopware\Components\Model\ModelEntity &$parentImageSW = null)
    {
        if (!file_exists($image->getFilename())) {
            throw new \RuntimeException(sprintf('File (%s) does not exists', $image->getFilename()));
        }

        if ($image->getRelationType() === ImageRelationType::TYPE_PRODUCT) {
            $imageSW = $this->prepareProductImageAssociateData($image);
            $mediaSW = $imageSW->getMedia();
            if(!is_null($imageSW->getParent())) {
                $parentImageSW = $imageSW->getParent();
                $mediaSW = $parentImageSW->getMedia();
            }
        } else {
            $mediaSW = $this->getMedia($image);
            $this->copyNewMedia($image, $mediaSW);
            $this->prepareTypeSwitchAssociateData($image, $mediaSW, $imageSW);
        }
    }

    protected function prepareTypeSwitchAssociateData(JtlImage &$image, MediaSW &$mediaSW, \Shopware\Components\Model\ModelEntity &$imageSW = null)
    {
        switch ($image->getRelationType()) {
            /*
            case ImageRelationType::TYPE_PRODUCT:
                $this->prepareProductImageAssociateData($image, $mediaSW, $imageSW);
                break;
            */
            case ImageRelationType::TYPE_CATEGORY:
                $this->prepareCategoryImageAssociateData($image, $mediaSW, $imageSW);
                break;
            case ImageRelationType::TYPE_MANUFACTURER:
                $this->prepareManufacturerImageAssociateData($image, $mediaSW, $imageSW);
                break;
            case ImageRelationType::TYPE_SPECIFIC_VALUE:
                $this->prepareSpecificValueImageAssociateDate($image, $mediaSW, $imageSW);
                break;
        }
    }

    /**
     * @param JtlImage $jtlImage
     * @return ArticleImage
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function prepareProductImageAssociateData(JtlImage &$jtlImage)
    {
        $foreignId = (strlen($jtlImage->getForeignKey()->getEndpoint()) > 0) ? $jtlImage->getForeignKey()->getEndpoint() : null;
        if ($foreignId === null) {
            throw new \RuntimeException('ForeignKey cannot be null');
        }

        list($detailId, $articleId) = IdConcatenator::unlink($foreignId);

        /** @var Article $article */
        $article = Shop::entityManager()->find(Article::class, $articleId);
        if (is_null($article)) {
            throw new \RuntimeException('Article with id ' . $articleId . ' not found!');
        }

        $detail = null;
        /** @var Detail $articleDetail */
        foreach ($article->getDetails() as $articleDetail) {
            if ($articleDetail->getId() == $detailId) {
                $detail = $articleDetail;
                break;
            }
        }

        if (is_null($article)) {
            throw new \RuntimeException('Detail with id ' . $detailId . ' not found!');
        }

        /** @var Product $productMapper */
        $productMapper = Mmc::getMapper('Product');

        /** @var \Shopware\Components\Api\Resource\Article $articleResource */
        $articleResource = Manager::getResource('article');

        $existingImage = $this->findExistingImage($article, $jtlImage);
        $imageExists = !is_null($existingImage);

        $swImage = new ArticleImage();
        $media = new Media();

        $isNewImage = empty($jtlImage->getId()->getEndpoint());
        if (!$isNewImage) {
            list($type, $imageId, $mediaId) = IdConcatenator::unlink($jtlImage->getId()->getEndpoint());

            /** @var ArticleImage $swImage */
            $swImage = Shop::entityManager()->find(ArticleImage::class, $imageId);
            $media = $swImage->getMedia();
        }

        if (!$imageExists) {
            list ($uuid, $ext) = explode('.', $jtlImage->getFilename());

            // Seo
            $productSeo = $this->getProductSeoName($article, $detail, $jtlImage);
            $filename = sprintf('%s.%s', $productSeo, $ext);
            if (strlen($filename) > 100) {
                $filename = substr($filename, strlen($filename) - 100, 100);
            }

            $path = Path::combine(Temp::getDirectory(), $filename);
            if (!copy($jtlImage->getFilename(), $path)) {
                throw new \RuntimeException('File with host id ' . $jtlImage->getId()->getHost() . 'could not get copied!');
            }
            $jtlImage->setFilename($path);

            $media = $this->initMedia($jtlImage, $media);

            if ($isNewImage) {
                $swImage = $articleResource->createNewArticleImage($article, $media);
                if ($productMapper->isChildSW($article, $detail)) {
                    $swImage->setArticleDetail($detail);
                }
            } else {
                $swImage = $articleResource->updateArticleImageWithMedia($article, $swImage, $media);
            }

            Logger::write(
                sprintf(
                    'Real image for Product (%s/%s) - Image (%s/%s) saved',
                    $jtlImage->getForeignKey()->getEndpoint(),
                    $jtlImage->getForeignKey()->getHost(),
                    $jtlImage->getId()->getEndpoint(),
                    $jtlImage->getId()->getHost()
                ),
                Logger::DEBUG,
                'image'
            );

        } else {
            $swImage = $existingImage;
        }

        if ($article->getImages()->count() === 1) {
            $swMain = 1;
            $swImage->setPosition(1);
        } elseif (!$productMapper->isChildSW($article, $detail)) {
            $swMain = $jtlImage->getSort() === 1 ? 1 : 2;
            if ($swMain === 1) {
                /** @var ArticleImage $aImage */
                foreach ($article->getImages() as $aImage) {
                    $aImage->setMain(2);
                }
            }
            $swImage->setPosition($jtlImage->getSort());
            Sort::reSort($article->getImages()->toArray(), 'position');
        } elseif (!$imageExists) {
            $swImage->setPosition((count($article->getImages()) + 1));
        }
        $swImage->setMain($swMain);

        $variantImage = null;
        if ($productMapper->isChildSW($article, $detail)) {
            if ($isNewImage) {
                $variantImage = new ArticleImage();
                $variantImage->setArticleDetail($detail);
                $variantImage->setExtension($swImage->getExtension());
                $variantImage->setParent($swImage);
                Shop::entityManager()->persist($variantImage);

                $mapping = new ArticleImage\Mapping();
                $mapping->setImage($swImage);
                foreach ($detail->getConfiguratorOptions() as $option) {
                    $rule = new ArticleImage\Rule();
                    $rule->setMapping($mapping);
                    $rule->setOption($option);
                    Shop::entityManager()->persist($rule);
                    $mapping->getRules()->add($rule);
                }
                Shop::entityManager()->persist($mapping);
            }

            /** @var ArticleImage $child */
            foreach ($swImage->getChildren() as $child) {
                if ($child->getArticleDetail()->getId() === $detail->getId()) {
                    $variantImage = $child;
                    break;
                }
            }

            if (is_null($variantImage)) {
                throw new \RuntimeException('Variant image for article detail with id ' . $detail->getId() . ' not found!');
            }

            $variantMain = $jtlImage->getSort() === 1 ? 1 : 2;
            if ($variantMain === 1) {
                /** @var ArticleImage $image */
                foreach ($detail->getImages() as $image) {
                    $image->setMain(2);
                }
            }
            $variantImage->setMain($variantMain);
            $variantImage->setPosition($jtlImage->getSort());
            Shop::entityManager()->persist($variantImage);

            Logger::write(
                sprintf(
                    'Pseudo variant image for Product (%s/%s) - Sort (%s)- Main (%s) saved',
                    $jtlImage->getForeignKey()->getEndpoint(),
                    $jtlImage->getForeignKey()->getHost(),
                    $jtlImage->getSort(),
                    $variantMain
                ),
                Logger::DEBUG,
                'image'
            );
        }

        Shop::entityManager()->persist($swImage);
        Shop::entityManager()->persist($article);
        Shop::entityManager()->persist($detail);

        return !is_null($variantImage) ? $variantImage : $swImage;
    }

    protected function isParentImageInUse($parentId, $detailId)
    {
        $results = $this->Manager()->createQueryBuilder()
            ->select(
                'image'
            )
            ->from('Shopware\Models\Article\Image', 'image')
            ->where('image.parent = :parentId')
            ->andWhere('image.articleDetailId != :detailId')
            ->setParameter('parentId', $parentId)
            ->setParameter('detailId', $detailId)
            ->getQuery()->getResult();

        return (is_array($results) && count($results) > 0);
    }

    protected function isParentImageInUseByMain($childImageId, $mediaId)
    {
        $count = Shopware()->Db()->fetchOne(
            'SELECT count(*) as count
              FROM jtl_connector_link_product_image l
              LEFT JOIN s_articles_img i ON i.id = l.id
              WHERE l.id != ?
                  AND l.media_id = ?
                  AND i.articleID IS NOT NULL'
            , [intval($childImageId), intval($mediaId)]);

        return ($count !== null && intval($count) > 0);
    }


    protected function prepareManufacturerImageAssociateData(JtlImage &$image, MediaSW &$mediaSW, ArticleImage &$imageSW = null)
    {
        $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? (int)$image->getForeignKey()->getEndpoint() : null;

        if ($foreignId !== null) {
            $imageSW = $this->Manager()->getRepository('Shopware\Models\Article\Supplier')->find((int)$foreignId);
        } else {
            throw new \Exception('Manufacturer foreign key cannot be null');
        }

        if ($imageSW === null) {
            throw new \Exception(sprintf('Cannot find manufacturer with id (%s)', $foreignId));
        }

        $imageSW->setImage($mediaSW->getPath());
    }

    protected function prepareCategoryImageAssociateData(JtlImage &$image, MediaSW &$mediaSW, ArticleImage &$imageSW = null)
    {
        $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ?
            (int)$image->getForeignKey()->getEndpoint() : null;

        if (is_null($foreignId)) {
            throw new \Exception('Category foreign key cannot be null');
        }

        $imageSW = $this->Manager()->getRepository('Shopware\Models\Category\Category')->find((int)$foreignId);

        if (is_null($imageSW)) {
            throw new \Exception(sprintf('Cannot find category with id (%s)', $foreignId));
        }

        // Special category mapping
        /** @deprecated Will be removed in a future connector release  $mappingOld */
        $mappingOld = Application()->getConfig()->get('category_mapping', false);
        if (Application()->getConfig()->get('category.mapping', $mappingOld)) {
            $categorySWs = CategoryMappingUtil::findAllCategoriesByMappingParent((int)$foreignId);
            foreach ($categorySWs as $categorySW) {
                $categorySW->setMedia($mediaSW);

                $this->Manager()->persist($categorySW);
            }
        }

        $imageSW->setMedia($mediaSW);
    }

    protected function prepareSpecificValueImageAssociateDate(JtlImage &$image, MediaSW &$mediaSW, ArticleImage &$imageSW = null)
    {
        $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? (int)$image->getForeignKey()->getEndpoint() : null;

        if ($foreignId !== null) {
            $imageSW = $this->Manager()->getRepository('Shopware\Models\Property\Value')->find((int)$foreignId);
        } else {
            throw new \Exception('Category foreign key cannot be null');
        }

        if ($imageSW === null) {
            throw new \Exception(sprintf('Cannot find specific value with id (%s)', $foreignId));
        }

        $imageSW->setMedia($mediaSW);
    }

    protected function getMedia(JtlImage $image)
    {
        $mediaSW = null;
        $imageId = (strlen($image->getId()->getEndpoint()) > 0) ? $image->getId()->getEndpoint() : null;

        if ($imageId !== null) {
            list($type, $imageId, $mediaId) = IdConcatenator::unlink($image->getId()->getEndpoint());
            $mediaSW = $this->find((int)$mediaId);
        }

        return $this->initMedia($image, $mediaSW);
    }

    /**
     * @param JtlImage $jtlImage
     * @param Media $media
     * @param string|null $fileName
     * @return Media
     * @throws \RuntimeException
     */
    protected function initMedia(JtlImage $jtlImage, Media $media)
    {
        $albumId = null;
        switch ($jtlImage->getRelationType()) {
            case ImageRelationType::TYPE_PRODUCT:
                $albumId = -1;
                break;
            case ImageRelationType::TYPE_CATEGORY:
            case ImageRelationType::TYPE_SPECIFIC_VALUE:
                $albumId = -9;
                break;
            case ImageRelationType::TYPE_MANUFACTURER:
                $albumId = -12;
                break;
            default:
                $albumId = -10;
                break;
        }

        $album = Shop::entityManager()->getRepository(Album::class)->find($albumId);
        if ($album === null) {
            throw new \RuntimeException(sprintf('Album with id (%s) not found', $albumId));
        }

        if (is_null($media)) {
            $media = new Media();
        }

        $media
            ->setFile(new File($jtlImage->getFilename()))
            ->setDescription('')
            ->setCreated(new \DateTime())
            ->setUserId(0)
            ->setAlbum($album);

        Shop::entityManager()->persist($media);

        return $media;
    }

    /**
     * @param JtlImage $jtlImage
     * @param null $fileName
     * @return void
     */
    protected function saveImage(JtlImage $jtlImage, $fileName = null)
    {
        if (is_null($fileName)) {
            $fileName = pathinfo($jtlImage->getFilename(), \PATHINFO_FILENAME);
        }

        Shop::mediaService()->write($this->buildImagePath($fileName), file_get_contents($jtlImage->getFilename()));
    }

    /**
     * @param string $fileName
     * @return string
     */
    protected function buildImagePath($fileName)
    {
        return 'media/image/' . strtolower($fileName);
    }

    protected function getUploadDir($relativeley = false, $stripLastSlash = false)
    {
        // the absolute directory path where uploaded documents should be saved
        $path = Shopware()->DocPath('media_' . strtolower(MediaSW::TYPE_IMAGE));

        if ($relativeley) {
            $path = str_replace(Shopware()->DocPath(), '', $path);
        }

        return $stripLastSlash ? substr($path, 0, strrpos($path, DIRECTORY_SEPARATOR)) : $path;
    }

    protected function generadeMD5($path)
    {
        return md5_file(sprintf('%s%s', Shopware()->DocPath(), $path));
    }

    protected function getProductSeoName(Article $article, Detail $detail, JtlImage $image)
    {
        $seo = new Seo();

        $pk = ' ' . $image->getId()->getHost();
        if ($article->getConfiguratorSet() !== null && $article->getConfiguratorSet()->getId() > 0) {  // Varkombi
            $pk = '';
        }

        $productSeo = sprintf('%s %s%s',
            $article->getName(),
            $detail->getAdditionalText(),
            $pk
        );

        if (strlen($productSeo) > 60) {
            $pos = strpos($productSeo, ' ', 60);
            if ($pos === false) {
                $pos = 60;
            }

            $productSeo = substr($productSeo, 0, $pos);
        }

        return $seo->create(
            sprintf('%s %s',
                $productSeo,
                $detail->getNumber()
            )
        );
    }

    /**
     * @param ArticleSW $article
     * @param JtlImage $jtlImage
     * @return null|ArticleImage
     */
    protected function findExistingImage(Article $article, JtlImage $jtlImage)
    {
        if (count($article->getImages()) > 0) {
            clearstatcache();
            /** @var ArticleImage $image */
            foreach ($article->getImages() as $image) {
                $swImageContent = Shop::mediaService()->read($image->getMedia()->getPath());
                if (md5_file($jtlImage->getFilename()) == md5($swImageContent)) {
                    return $image;
                }
            }
        }

        return null;
    }

    protected function findParentImageOld($articleId, $imageFile)
    {
        $results = Shopware()->Db()->fetchAssoc(
            'SELECT m.path, i.id
             FROM s_articles_img i
             JOIN s_media m ON m.id = i.media_id
             WHERE i.articleID = ' . intval($articleId)
        );

        if (is_array($results) && count($results) > 0) {
            clearstatcache();
            $service = Shop::mediaService();
            foreach ($results as $result) {
                $path = $service->encode($result['path']);
                $file = Path::combine(Shopware()->DocPath(), $path);

                if (file_exists($file)) {
                    if (md5_file($imageFile) === md5_file($file)) {
                        return (intval($result['id']) == 0) ? null : $this->Manager()->find('Shopware\Models\Article\Image', (int)$result['id']);
                        /*
                        return $this->Manager()->createQueryBuilder()->select(
                                'image',
                                'media'
                            )
                            ->from('Shopware\Models\Article\Image', 'image')
                            ->join('image.media', 'media')
                            ->where('image.id = :imageId')
                            ->setParameter('imageId', (int) $result['id'])
                            ->getQuery()->getResult();
                        */
                    }
                }
            }
        }

        return null;
    }

    protected function buildProductImageMappings(ArticleImage $parentSW, $detailId = null)
    {
        try {
            $builder = $this->Manager()->createQueryBuilder()->select(
                'detail',
                'options'
            )
                ->from('Shopware\Models\Article\Detail', 'detail')
                ->join('detail.configuratorOptions', 'options')
                ->join('detail.images', 'images')
                ->where('images.parent = :parentId')
                ->setParameter('parentId', $parentSW->getId());

            if ($detailId !== null) {
                $builder->andWhere('detail.id != :detailId')
                    ->setParameter('detailId', $detailId);
            }

            $parentSW = $this->findArticleImage($parentSW->getId());
            $detailsSW = $builder->getQuery()->getResult();

            foreach ($detailsSW as $detailSW) {

                // Special image configuration ignores
                $ignores_groups = [];
                $group = Shopware()->Db()->fetchOne(
                    'SELECT `value` FROM jtl_connector_product_attributes WHERE product_id = ? AND `key` = ?',
                    array($detailSW->getArticleId(), ProductAttr::IMAGE_CONFIGURATION_IGNORES)
                );

                if ($group !== false) {
                    $ignores_groups = explode('|||', $group);
                }

                $mappingSW = new MappingSW();
                $mappingSW->setImage($parentSW);

                $rules = [];
                foreach ($detailSW->getConfiguratorOptions() as $optionSW) {
                    if (is_array($ignores_groups) && count($ignores_groups) > 0 && in_array($optionSW->getGroup()->getName(), $ignores_groups)) {
                        continue;
                    }

                    $ruleSW = new RuleSW();
                    $ruleSW->setOption($optionSW);
                    $ruleSW->setMapping($mappingSW);
                    $this->Manager()->persist($ruleSW);
                }

                $mappingSW->setRules($rules);
                $this->Manager()->persist($mappingSW);
            }
        } catch (\Exception $e) {
            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
        }
    }

    private function deleteProductImageMappings($imageParentId)
    {
        try {
            Shopware()->Db()->query(
                'DELETE m, r
                    FROM s_article_img_mappings m
                    LEFT JOIN s_article_img_mapping_rules r ON r.mapping_id = m.id
                    WHERE m.image_id = ?',
                [$imageParentId]
            );
        } catch (\Exception $e) {
            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
        }
    }

    /**
     * @param JtlImage $image
     * @param ArticleImage $imageSW
     */
    private function saveAltText(JtlImage $image, ArticleImage &$imageSW)
    {
        $translationUtil = new TranslationUtil();
        $translationUtil->delete('articleimage', $imageSW->getId());

        $shopMapper = Mmc::getMapper('Shop');
        foreach ($image->getI18ns() as $i18n) {
            if (empty($i18n->getAltText())) {
                continue;
            }

            if ($i18n->getLanguageISO() !== LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                $locale = LocaleUtil::getByKey(LanguageUtil::map(null, null, $i18n->getLanguageISO()));
                $shops = $shopMapper->findByLocale($locale->getLocale());

                if ($shops !== null && is_array($shops) && count($shops) > 0) {
                    foreach ($shops as $shop) {
                        $translationUtil->write(
                            $shop->getId(),
                            'articleimage',
                            $imageSW->getId(),
                            array(
                                'description' => $i18n->getAltText()
                            )
                        );
                    }
                }
            }
        }
    }
}