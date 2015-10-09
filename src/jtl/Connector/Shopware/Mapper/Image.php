<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use jtl\Connector\Core\IO\Path;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Utilities\Seo;
use \jtl\Connector\Drawing\ImageRelationType;
use jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Model\Image as ImageModel;
use \jtl\Connector\Shopware\Model\Image as ImageConModel;
use \jtl\Connector\Model\Identity;
use \Shopware\Models\Media\Media as MediaSW;
use \Shopware\Models\Article\Image as ArticleImageSW;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Shopware\Utilities\IdConcatenator;
use \Shopware\Models\Article\Detail as DetailSW;
use \Shopware\Models\Article\Article as ArticleSW;
use \Symfony\Component\HttpFoundation\File\File;

class Image extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Media\Media')->find((int) $id);
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
                        'SELECT i.id as cId, a.main_detail_id as detailId, i.*, m.path
                      FROM s_articles_img i
                      LEFT JOIN s_articles_img c ON c.parent_id = i.id
                      LEFT JOIN s_articles a ON a.id = i.articleID
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
                );

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
                    $count += (int) $c['count'];
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
        }

        if ($query !== null) {
            $result = $query->getResult();
            if (isset($result[0]['count'])) {
                $count = (int)$result[0]['count'];
            }
        }

        return $count;
    }

    public function delete(ImageModel $image)
    {
        $result = $image;

        try {
            $this->deleteImageData($image);
        } catch (\Exception $e) {
            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
        }

        return $result;
    }

    public function save(ImageModel $image)
    {
        $mediaSW = null;
        $imageSW = null;
        $result = new ImageModel;

        try {
            $this->prepareImageAssociatedData($image, $mediaSW, $imageSW);

            $this->Manager()->persist($mediaSW);

            if ($imageSW !== null) {
                $this->Manager()->persist($imageSW);
            }

            $this->flush();

            $manager = Shopware()->Container()->get('thumbnail_manager');
            $manager->createMediaThumbnail($mediaSW, array(), true);
            
            // Result
            $result->setId(new Identity(ImageConModel::generateId($image->getRelationType(), $imageSW->getId(), $mediaSW->getId()), $image->getId()->getHost()))
                ->setForeignKey(new Identity($image->getForeignKey()->getEndpoint(), $image->getForeignKey()->getHost()))
                ->setRelationType($image->getRelationType())
                ->setFilename(sprintf('http://%s%s/%s', Shopware()->Shop()->getHost(), Shopware()->Shop()->getBaseUrl(), $mediaSW->getPath()));
        } catch (\Exception $e) {
            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
        }

        @unlink($image->getFilename());

        return $result;
    }

    protected function deleteImageData(ImageModel &$image)
    {
        list($type, $imageId, $mediaId) = IdConcatenator::unlink($image->getId()->getEndpoint());

        $deleteMedia = true;
        switch ($image->getRelationType()) {
            case ImageRelationType::TYPE_PRODUCT:
                $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? $image->getForeignKey()->getEndpoint() : null;
                if ($foreignId === null) {
                    return;
                }

                list($detailId, $articleId) = IdConcatenator::unlink($foreignId);

                // Special delete (masterkill) all images for a single product call
                if ($image->getSort() == 0 && strlen($image->getId()->getEndpoint()) == 0) {
                    Shopware()->Db()->query(
                        'DELETE i, m, r
                          FROM s_articles_img i
                          LEFT JOIN s_article_img_mappings m ON m.image_id = i.id
                          LEFT JOIN s_article_img_mapping_rules r ON r.mapping_id = m.id
                          WHERE i.articleID = ?',
                        array($articleId)
                    );

                    $this->Manager()->createQueryBuilder()
                        ->delete('Shopware\Models\Article\Image', 'image')
                        ->where('image.articleDetailId = :detailId')
                        ->setParameter('detailId', $detailId)
                        ->getQuery()
                        ->execute();

                    return;
                }

                $imageSW = $this->Manager()->getRepository('Shopware\Models\Article\Image')->find((int) $imageId);

                if ($imageSW !== null) {

                    if ($imageSW->getParent() !== null) {
                        if (!$this->isParentImageInUse($imageSW->getParent()->getId(), $detailId)) {
                            $this->Manager()->remove($imageSW->getParent());
                        } else {
                            $deleteMedia = false;
                        }
                    }

                    /*
                    $imageChildSW = $this->loadChildImage($imageSW->getId(), $detailId);

                    //Check if product is a configurator and check if the image is in use from another child
                    if ($this->isParentImageInUse($imageSW->getId(), $detailId)) {
                        $deleteMedia = false;
                        $imageSW = $imageChildSW;
                    } elseif ($imageChildSW !== null) {
                        $this->Manager()->remove($imageChildSW);
                    }
                    */

                    try {
                        $this->Manager()->remove($imageSW);
                        $this->Manager()->flush();
                    } catch (\Exception $e) {
                        Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
                    }
                }
                break;
            case ImageRelationType::TYPE_CATEGORY:
                $categorySW = $this->Manager()->getRepository('Shopware\Models\Category\Category')->find((int) $imageId);
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
                $supplierSW = $this->Manager()->getRepository('Shopware\Models\Article\Supplier')->find((int) $imageId);
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

        $mediaSW = $this->Manager()->getRepository('Shopware\Models\Media\Media')->find((int) $mediaId);
        if ($mediaSW !== null && $deleteMedia) {
            @unlink(sprintf('%s%s', Shopware()->OldPath(), $mediaSW->getPath()));

            try {
                $this->Manager()->remove($mediaSW);
                $this->Manager()->flush();
            } catch (\Exception $e) {
                Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'database');
            }
        }
    }

    protected function prepareImageAssociatedData(ImageModel &$image, MediaSW &$mediaSW = null, \Shopware\Components\Model\ModelEntity &$imageSW = null)
    {
        if (!file_exists($image->getFilename())) {
            throw new \Exception(sprintf('File (%s) does not exists', $image->getFilename()));
        }

        $file = new File($image->getFilename());

        if ($image->getRelationType() === ImageRelationType::TYPE_PRODUCT) {
            $this->prepareProductImageAssociateData($image, $mediaSW, $imageSW, $file);
        } else {
            $mediaSW = $this->getMedia($image, $file);
            $this->copyNewMedia($image, $mediaSW, $file);
        }

        $this->prepareTypeSwitchAssociateData($image, $mediaSW, $imageSW);
    }

    protected function prepareTypeSwitchAssociateData(ImageModel &$image, MediaSW &$mediaSW, \Shopware\Components\Model\ModelEntity &$imageSW = null)
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
        }
    }

    protected function prepareProductImageAssociateData(ImageModel &$image, MediaSW &$mediaSW = null, ArticleImageSW &$imageSW = null, File $file)
    {
        $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? $image->getForeignKey()->getEndpoint() : null;

        if ($foreignId === null) {
            throw new \Exception('ForeignKey cannot be null');
        }

        list($detailId, $articleId) = IdConcatenator::unlink($foreignId);

        $productMapper = Mmc::getMapper('Product');

        $detailSW = $productMapper->findDetail((int) $detailId);
        if ($detailSW === null) {
            throw new \Exception(sprintf('Cannot find product detail with id (%s)', $detailId));
        }

        $productSW = $productMapper->find((int) $articleId);
        if ($productSW === null) {
            throw new \Exception(sprintf('Cannot find product with id (%s)', $articleId));
        }

        list ($uuid, $ext) = explode('.', $image->getFilename());

        // Seo
        $productSeo = $this->getProductSeoName($productSW, $detailSW, $image);
        $filename = sprintf('%s.%s', $productSeo, $ext);

        if (strlen($filename) > 100) {
            $filename = substr($filename, strlen($filename) - 100, 100);
        }

        $path = Path::combine(sys_get_temp_dir(), $filename);
        if (copy($image->getFilename(), $path)) {
            $file = new File($path);
            $image->setFilename($path);
        }

        // Varkombi parent image feature, maybe later
        /*
        $parentExists = false;
        if ($productSW->getConfiguratorSet() !== null) {
            $parentImageSW = $this->findParentImage($articleId, $image->getFilename());
            if ($parentImageSW && $parentImageSW !== null) {
                $imageSW = $parentImageSW;
                $mediaSW = $parentImageSW->getMedia();
                $parentExists = true;
            }
        }

        if (!$parentExists) {
            $mediaSW = $this->getMedia($image, $file);

            // parent image
            $imageSW = $this->getParentImage($image, $mediaSW, $productSW);
        }
        */

        $mediaSW = $this->getMedia($image, $file);

        // parent image
        $imageSW = $this->getParentImage($image, $mediaSW, $productSW);

        // Varkombi?
        if ($productSW->getConfiguratorSet() !== null) {
            if ($imageSW->getId() > 0 && $this->isParentImageInUse($imageSW->getId(), $detailSW->getId())) {
                // Wenn es noch Kinder gibt die das Vaterbild nutzen, lege ein neues Vaterbild an
                $mediaSW = $this->getNewMedia($image, $file);

                $imageSW = $this->newParentImage($image, $mediaSW, $productSW);
                $collection = new \Doctrine\Common\Collections\ArrayCollection;
                $collection->add($imageSW);
                $mediaSW->setArticles($collection);
            } else {
                $this->copyNewMedia($image, $mediaSW, $file);
            }

            $productMapper = Mmc::getMapper('Product');

            // if detail is a child
            if ($imageSW->getParent() === null && $productMapper->isChildSW($productSW, $detailSW)) {
                $childImageSW = $this->getChildImage($image, $mediaSW, $detailSW, $imageSW);
                $this->Manager()->persist($childImageSW);

                // Save mapping and rule
                $this->saveImageMapping($imageSW, $detailSW);
            }
        } else {
            $this->copyNewMedia($image, $mediaSW, $file);
        }

        $this->copyNewMedia($image, $mediaSW, $file);
    }

    protected function saveImageMapping(ArticleImageSW $imageSW, DetailSW $detailSW)
    {
        $mappingSW = null;
        if ($imageSW->getId() > 0) {
            $mappingSW = $this->Manager()->getRepository('Shopware\Models\Article\Image\Mapping')->findOneBy(array('image' => $imageSW->getId()));
        }

        if ($mappingSW === null) {
            $mappingSW = new \Shopware\Models\Article\Image\Mapping();
            $this->Manager()->persist($mappingSW);
        } else {
            Shopware()->Db()->delete('s_article_img_mapping_rules', array('mapping_id = ?' => $mappingSW->getId()));
            //Varkombi parent image feature, maybe later
            /*
            foreach ($detailSW->getConfiguratorOptions() as $optionSW) {
                Shopware()->Db()->delete('s_article_img_mapping_rules', array('option_id = ?' => $optionSW->getId()));
            }
            */
        }

        $mappingSW->setImage($imageSW);

        $relations = Shopware()->Db()->fetchAssoc(
            'SELECT * FROM s_article_configurator_option_relations WHERE article_id = ?',
            array($detailSW->getId())
        );

        $optionMapper = Mmc::getMapper('ConfiguratorOption');
        foreach ($relations as $relation) {
            $optionSW = $optionMapper->find((int) $relation['option_id']);

            if ($optionSW !== null) {
                $ruleSW = new \Shopware\Models\Article\Image\Rule();
                $ruleSW->setMapping($mappingSW);
                $ruleSW->setOption($optionSW);

                $this->Manager()->persist($ruleSW);
            }
        }
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

    protected function loadChildImage($parentId, $detailId)
    {
        try {
            return $this->Manager()->createQueryBuilder()
                ->select(
                    'image'
                )
                ->from('Shopware\Models\Article\Image', 'image')
                ->where('image.parent = :parentId')
                ->andWhere('image.articleDetailId = :detailId')
                ->setParameter('parentId', $parentId)
                ->setParameter('detailId', $detailId)
                ->getQuery()->getOneOrNullResult();
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getChildImage(ImageModel $image, MediaSW $mediaSW, DetailSW $detailSW, ArticleImageSW $parentImageSW)
    {
        $imageSW = $this->loadChildImage($parentImageSW->getId(), $detailSW->getId());

        if ($imageSW === null) {
            $imageSW = new ArticleImageSW;
            $imageSW->setHeight(0);
            $imageSW->setDescription('');
            $imageSW->setWidth(0);
            $imageSW->setArticleDetail($detailSW);

            $this->Manager()->persist($imageSW);
        }

        $imageSW->setExtension($mediaSW->getExtension());
        $imageSW->setPosition($image->getSort());
        $main = ($image->getSort() == 1) ? 1 : 2;
        $imageSW->setMain($main);
        $imageSW->setParent($parentImageSW);

        return $imageSW;
    }

    protected function getParentImage(ImageModel $image, MediaSW $mediaSW, ArticleSW $productSW)
    {
        $imageId = (strlen($image->getId()->getEndpoint()) > 0) ? $image->getId()->getEndpoint() : null;

        // Try to load Image
        if ($imageId !== null) {
            list($type, $id, $mediaId) = IdConcatenator::unlink($imageId);
            $imageSW = $this->Manager()->getRepository('Shopware\Models\Article\Image')->find((int) $id);
        }

        // New Image
        if ($imageSW === null) {
            $imageSW = new ArticleImageSW;
            $imageSW->setHeight(0);
            $imageSW->setDescription('');
            $imageSW->setWidth(0);
            $imageSW->setExtension($mediaSW->getExtension());
            $imageSW->setArticle($productSW);

            $this->Manager()->persist($imageSW);
        }

        $imageSW->setPosition($image->getSort());
        $main = ($image->getSort() == 1) ? 1 : 2;
        $imageSW->setMain($main);

        if ($imageSW->getParent() === null) {
            $imageSW->setPath($mediaSW->getName());
            $imageSW->setMedia($mediaSW);
        }

        return $imageSW;
    }

    protected function newParentImage(ImageModel $image, MediaSW $mediaSW, ArticleSW $productSW)
    {
        $imageSW = new ArticleImageSW;
        $imageSW->setHeight(0);
        $imageSW->setDescription('');
        $imageSW->setWidth(0);
        $imageSW->setExtension($mediaSW->getExtension());
        $imageSW->setArticle($productSW);

        $imageSW->setPosition($image->getSort());
        $main = ($image->getSort() == 1) ? 1 : 2;
        $imageSW->setMain($main);
        $imageSW->setPath($mediaSW->getName());
        $imageSW->setMedia($mediaSW);

        $this->Manager()->persist($imageSW);

        return $imageSW;
    }

    protected function prepareChildImageAssociateData(ImageModel &$image, MediaSW &$mediaSW, ArticleImageSW &$imageSW = null)
    {
        $imageId = (strlen($image->getId()->getEndpoint()) > 0) ? $image->getId()->getEndpoint() : null;
        $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? $image->getForeignKey()->getEndpoint() : null;

        // Try to load Image
        if ($imageId !== null) {
            list($type, $id, $mediaId) = IdConcatenator::unlink($image->getId()->getEndpoint());
            $imageSW = $this->Manager()->getRepository('Shopware\Models\Article\Image')->find((int) $id);
        }

        // New Image?
        if ($imageSW === null) {
            $imageSW = new ArticleImageSW;
            $imageSW->setHeight(0);
            $imageSW->setDescription('');
            $imageSW->setWidth(0);

            if ($foreignId === null) {
                throw new \Exception('ForeignKey cannot be null');
            }

            $productMapper = Mmc::getMapper('Product');
            list($detailId, $articleId) = IdConcatenator::unlink($foreignId);
            $detailSW = $productMapper->findDetail((int) $detailId);
            if ($detailSW === null) {
                throw new \Exception(sprintf('Cannot find child with id (%s)', $detailId));
            }

            $imageSW->setArticleDetail($detailSW);

            // Create new Parent
            $this->createParentImageData($image, $mediaSW, $imageSW);
        } else {
            // Update Image
            $parentImageSW = $imageSW->getParent();

            if ($this->generadeMD5($parentImageSW->getMedia()->getPath()) != $this->generadeMD5($mediaSW->getPath())) {
                $this->createParentImageData($image, $mediaSW, $imageSW);
            }
        }

        $imageSW->setExtension($mediaSW->getExtension());
        $imageSW->setPosition($image->getSort());
        $main = ($image->getSort() == 1) ? 1 : 2;
        $imageSW->setMain($main);
    }

    protected function createParentImageData(ImageModel &$image, MediaSW &$mediaSW, ArticleImageSW &$imageSW = null)
    {
        $productMapper = Mmc::getMapper('Product');

        // Create new Parent
        $parentImageSW = new ArticleImageSW;
        $parentImageSW->setHeight(0);
        $parentImageSW->setDescription('');
        $parentImageSW->setWidth(0);
        $parentImageSW->setExtension($mediaSW->getExtension());
        $parentImageSW->setMedia($mediaSW);
        $parentImageSW->setPosition($image->getSort());
        $parentImageSW->setPath($mediaSW->getName());
        $main = ($image->getSort() == 1) ? 1 : 2;
        $parentImageSW->setMain($main);

        $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? $image->getForeignKey()->getEndpoint() : null;

        if ($foreignId === null) {
            throw new \Exception('ForeignKey cannot be null');
        }

        list($detailId, $articleId) = IdConcatenator::unlink($foreignId);
        $productSW = $productMapper->find((int) $articleId);
        if ($productSW === null) {
            throw new \Exception(sprintf('Cannot find product with id (%s)', $articleId));
        }

        $parentImageSW->setArticle($productSW);

        $this->Manager()->persist($parentImageSW);
        $imageSW->setParent($parentImageSW);
    }

    protected function prepareManufacturerImageAssociateData(ImageModel &$image, MediaSW &$mediaSW, ArticleImageSW &$imageSW = null)
    {
        $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? (int) $image->getForeignKey()->getEndpoint() : null;

        if ($foreignId !== null) {
            $imageSW = $this->Manager()->getRepository('Shopware\Models\Article\Supplier')->find((int) $foreignId);
        } else {
            throw new \Exception('Manufacturer foreign key cannot be null');
        }

        if ($imageSW === null) {
            throw new \Exception(sprintf('Cannot find manufacturer with id (%s)', $foreignId));
        }

        $imageSW->setImage($mediaSW->getPath());
    }

    protected function prepareCategoryImageAssociateData(ImageModel &$image, MediaSW &$mediaSW, ArticleImageSW &$imageSW = null)
    {
        $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? (int) $image->getForeignKey()->getEndpoint() : null;

        if ($foreignId !== null) {
            $imageSW = $this->Manager()->getRepository('Shopware\Models\Category\Category')->find((int) $foreignId);
        } else {
            throw new \Exception('Category foreign key cannot be null');
        }

        if ($imageSW === null) {
            throw new \Exception(sprintf('Cannot find category with id (%s)', $foreignId));
        }

        $imageSW->setMedia($mediaSW);
    }

    protected function getMedia(ImageModel $image, File $file)
    {
        $mediaSW = null;
        $imageId = (strlen($image->getId()->getEndpoint()) > 0) ? $image->getId()->getEndpoint() : null;

        if ($imageId !== null) {
            list($type, $imageId, $mediaId) = IdConcatenator::unlink($image->getId()->getEndpoint());
            $mediaSW = $this->find((int) $mediaId);
        }

        if ($mediaSW === null) {
            $mediaSW = $this->getNewMedia($image, $file);
        }

        return $mediaSW;
    }

    protected function getNewMedia(ImageModel $image, File $file)
    {
        $stats = stat($image->getFilename());
        $infos = pathinfo($image->getFilename());

        $albumId = null;
        switch ($image->getRelationType()) {
            case ImageRelationType::TYPE_PRODUCT:
                $albumId = -1;
                break;
            case ImageRelationType::TYPE_CATEGORY:
                $albumId = -9;
                break;
            case ImageRelationType::TYPE_MANUFACTURER:
                $albumId = -12;
                break;
            default:
                $albumId = -10;
                break;
        }

        $albumSW = $this->Manager()->getRepository('Shopware\Models\Media\Album')->find($albumId);
        if ($albumSW === null) {
            throw new \Exception(sprintf('Album with id (%s) not found', $albumId));
        }

        $mediaSW = new MediaSW;
        $mediaSW->setExtension(strtolower($infos['extension']))
            ->setAlbumId($albumId)
            ->setDescription('')
            ->setName($infos['filename'])
            ->setCreated(new \DateTime())
            ->setFileSize($stats['size'])
            ->setFile($file)
            ->setType(MediaSW::TYPE_IMAGE)
            ->setUserId(0)
            ->setAlbum($albumSW);

        $this->Manager()->persist($mediaSW);

        return $mediaSW;
    }

    protected function copyNewMedia(ImageModel $image, MediaSW &$mediaSW, File $file)
    {
        if ($mediaSW->getId() > 0 && file_exists($image->getFilename()) && $this->generadeMD5($mediaSW->getPath()) !== md5_file($image->getFilename())) {
            $stats = stat($image->getFilename());
            $infos = pathinfo($image->getFilename());

            $manager = Shopware()->Container()->get('thumbnail_manager');
            $manager->removeMediaThumbnails($mediaSW);

            @unlink(sprintf('%s%s', Shopware()->DocPath(), $mediaSW->getPath()));
            $file = $file->move($this->getUploadDir(), $image->getFilename());
            $mediaSW->setFileSize($stats['size'])
                ->setExtension(strtolower($infos['extension']))
                ->setCreated(new \DateTime())
                ->setFile($file);
        }
    }

    protected function getUploadDir()
    {
        // the absolute directory path where uploaded documents should be saved
        return Shopware()->DocPath('media_' . strtolower(MediaSW::TYPE_IMAGE));
    }

    protected function generadeMD5($path)
    {
        return md5_file(sprintf('%s%s', Shopware()->DocPath(), $path));
    }

    protected function getProductSeoName(ArticleSW $productSW, DetailSW $detailSW, ImageModel $image)
    {
        $seo = new Seo();

        $sort = ' ' . $image->getSort();
        if ($productSW->getConfiguratorSet() !== null) {  // Varkombi
            $sort = '';
        }

        $productSeo = sprintf('%s %s%s',
            $productSW->getName(),
            $detailSW->getAdditionalText(),
            $sort
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
                $detailSW->getNumber()
            )
        );
    }

    protected function findParentImage($articleId, $imageFile)
    {
        $results = Shopware()->Db()->fetchAssoc(
            'SELECT m.path, i.id
             FROM s_articles_img i
             JOIN s_media m ON m.id = i.media_id
             WHERE i.articleID = ' . intval($articleId)
        );

        if (is_array($results) && count($results) > 0) {
            foreach ($results as $result) {
                $file = Path::combine(Shopware()->DocPath(), $result['path']);
                if (file_exists($file)) {
                    if (md5_file($imageFile) === md5_file($file)) {
                        return $this->Manager()->find('Shopware\Models\Article\Image', (int) $result['id']);
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
}
