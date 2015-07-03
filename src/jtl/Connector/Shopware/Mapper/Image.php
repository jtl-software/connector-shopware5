<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Drawing\ImageRelationType;
use \jtl\Connector\Core\Logger\Logger;
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
                $query = Shopware()->Models()->createNativeQuery(
                    'SELECT count(*) as count 
                    FROM s_articles_img a
                    LEFT JOIN jtl_connector_link_product_image p ON p.id = a.id
                    WHERE p.host_id IS NULL', $rsm);
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
        $result = new ImageModel;

        $this->deleteImageData($image);

        // Result
        $result->setId(new Identity('', $image->getForeignKey()->getHost()))
            ->setRelationType($image->getRelationType());

        return $result;
    }

    public function save(ImageModel $image)
    {
        $mediaSW = null;
        $imageSW = null;
        $result = new ImageModel;

        $this->prepareImageAssociatedData($image, $mediaSW, $imageSW);

        $violations = $this->Manager()->validate($mediaSW);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

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

        return $result;
    }

    protected function deleteImageData(ImageModel &$image)
    {
        list($type, $imageId, $mediaId) = IdConcatenator::unlink($image->getId()->getEndpoint());

        switch ($image->getRelationType()) {
            case ImageRelationType::TYPE_PRODUCT:
                $foreignId = (strlen($image->getForeignKey()->getEndpoint()) > 0) ? $image->getForeignKey()->getEndpoint() : null;
                if ($foreignId === null) {
                    return;
                }

                list($detailId, $articleId) = IdConcatenator::unlink($foreignId);

                // Special delete all images for a single product call
                if ($image->getSort() == 0 && strlen($image->getId()->getEndpoint()) == 0) {
                    $this->Manager()->createQueryBuilder()
                        ->delete('Shopware\Models\Article\Image', 'image')
                        ->where('image.article = :id')
                        ->setParameter('id', $articleId)
                        ->getQuery()
                        ->execute();

                    return;
                }

                $imageSW = $this->Manager()->getRepository('Shopware\Models\Article\Image')->find((int) $imageId);
                if ($imageSW !== null) {                    
                    $imageChildSW = $this->loadChildImage($detailId);

                    //Check if product is a configurator and check if the image is in use from another child
                    if ($this->isParentImageInUse($imageSW->getId(), $detailId)) {
                        $imageSW = $imageChildSW;
                    } elseif ($imageChildSW !== null) {
                        $this->Manager()->remove($imageChildSW);
                    }

                    $this->Manager()->remove($imageSW);
                    $this->Manager()->flush();
                }
                break;
            case ImageRelationType::TYPE_CATEGORY:
                $categorySW = $this->Manager()->getRepository('Shopware\Models\Category\Category')->find((int) $imageId);
                if ($categorySW !== null) {
                    $categorySW->setMedia(null);
                    $this->Manager()->persist($categorySW);
                    $this->Manager()->flush();
                }
                break;
            case ImageRelationType::TYPE_MANUFACTURER:
                $supplierSW = $this->Manager()->getRepository('Shopware\Models\Article\Supplier')->find((int) $imageId);
                if ($supplierSW !== null) {
                    $supplierSW->setImage('');
                    $this->Manager()->persist($supplierSW);
                    $this->Manager()->flush();
                }
                break;
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

            $childImageSW = $this->getChildImage($image, $mediaSW, $detailSW);
            $childImageSW->setParent($imageSW);
            $this->Manager()->persist($childImageSW);
        } else {
            $this->copyNewMedia($image, $mediaSW, $file);
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

    protected function loadChildImage($detailId)
    {
        $results = $this->Manager()->createQueryBuilder()
            ->select(
                'image'
            )
            ->from('Shopware\Models\Article\Image', 'image')
            ->where('image.articleDetailId = :detailId')
            ->setParameter('detailId', $detailId)
            ->getQuery()->getResult();

        return (is_array($results) && count($results) == 1) ? $results[0] : null;
    }

    protected function getChildImage(ImageModel $image, MediaSW $mediaSW, DetailSW $detailSW)
    {
        $imageSW = $this->loadChildImage($detailSW->getId());

        if ($imageSW === null) {
            $imageSW = new ArticleImageSW;
            $imageSW->setHeight(0);
            $imageSW->setDescription('');
            $imageSW->setWidth(0);
            $imageSW->setArticle($detailSW->getArticle());
            $imageSW->setArticleDetail($detailSW);

            $this->Manager()->persist($imageSW);
        }

        $imageSW->setExtension($mediaSW->getExtension());
        $imageSW->setPosition($image->getSort());
        $main = ($image->getSort() == 1) ? 1 : 2;
        $imageSW->setMain($main);

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
        $imageSW->setPath($mediaSW->getName());
        $imageSW->setMedia($mediaSW);

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
        if ($mediaSW->getId() > 0 && $this->generadeMD5($mediaSW->getPath()) != md5_file($image->getFilename())) {
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

    protected function isChild(ImageModel &$image)
    {
        return (strlen($image->getForeignKey()->getEndpoint()) > 0 && strpos($image->getForeignKey()->getEndpoint(), '_') !== false);
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
}
