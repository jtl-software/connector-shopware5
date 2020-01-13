<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Model\ImageI18n;
use \jtl\Connector\Result\Action;
use \jtl\Connector\Core\Rpc\Error;
use \jtl\Connector\Drawing\ImageRelationType;
use \jtl\Connector\Shopware\Model\Image as ImageModel;
use \jtl\Connector\Core\Model\QueryFilter;
use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Model\Statistic;
use \jtl\Connector\Model\Identity;
use \jtl\Connector\Shopware\Utilities\IdConcatenator;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use Shopware\Bundle\MediaBundle\MediaService;

/**
 * Image Controller
 * @access public
 */
class Image extends DataController
{
    /**
     * Pull
     *
     * @param \jtl\Connector\Core\Model\QueryFilter $queryFilter
     * @return \jtl\Connector\Result\Action
     */
    public function pull(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $result = array();
            $limit = $queryFilter->isLimit() ? $queryFilter->getLimit() : 100;

            /** @var \jtl\Connector\Shopware\Mapper\Image $mapper */
            $mapper = Mmc::getMapper('Image');

            $modelContainer = array();
            if ($queryFilter->getFilter('relationType') !== null) {
                $modelContainer[$queryFilter->getFilter('relationType')] = $mapper->findAll($limit, false, $queryFilter->getFilter('relationType'));
            } else {
                // Get all images
                $relationTypes = array(
                    ImageRelationType::TYPE_PRODUCT,
                    ImageRelationType::TYPE_CATEGORY,
                    ImageRelationType::TYPE_MANUFACTURER,
                    ImageRelationType::TYPE_SPECIFIC_VALUE
                );

                foreach ($relationTypes as $relationType) {
                    $modelContainer[$relationType] = $mapper->findAll($limit, false, $relationType);
                }
            }

            /** @var MediaService $mediaServie */
            $mediaServie = ShopUtil::mediaService();
            
            //$proto = ShopUtil::getProtocol();
            foreach ($modelContainer as $relationType => $models) {
                foreach ($models as $modelSW) {
                    switch ($relationType) {
                        case ImageRelationType::TYPE_PRODUCT:
                            $model = Mmc::getModel('Image');

                            //Clean unused files
                            if(is_null($modelSW['articleID']) || is_null($modelSW['detailId'])) {
                                $imageSW = Shopware()->Models()->find(\Shopware\Models\Article\Image::class, $modelSW['id']);
                                Shopware()->Models()->remove($imageSW);
                                break;
                            }

                            $id = ImageModel::generateId(ImageRelationType::TYPE_PRODUCT, (int) $modelSW['cId'], (int) $modelSW['media_id']);
                            $foreignKey = IdConcatenator::link(array($modelSW['detailId'], $modelSW['articleID']));

                            $model->setId(new Identity($id));
                            $model->setRelationType($relationType)
                                ->setForeignKey(new Identity($foreignKey))
                                ->setFilename($mediaServie->getUrl($modelSW['path']))
                                ->setRemoteUrl($mediaServie->getUrl($modelSW['path']))
                                ->setSort((int) $modelSW['position']);

                            $this->addPos($model, 'addI18n', 'ImageI18n', $modelSW);
                            if (isset($modelSW['translations'])) {
                                foreach ($modelSW['translations'] as $localeName => $translation) {
                                    $imageI18n = Mmc::getModel('ImageI18n');
                                    $imageI18n->setLanguageISO(LanguageUtil::map($localeName));
                                    $imageI18n->setImageId($model->getId());
                                    $imageI18n->setAltText(isset($translation['description']) ? $translation['description'] : '');
                                    $model->addI18n($imageI18n);
                                }
                            }

                            $result[] = $model;
                            break;
                        case ImageRelationType::TYPE_CATEGORY:
                            $model = Mmc::getModel('Image');
                            
                            $model->setId(new Identity(ImageModel::generateId(ImageRelationType::TYPE_CATEGORY, $modelSW['id'], $modelSW['mediaId'])));

                            $model->setRelationType($relationType)
                                ->setForeignKey(new Identity($modelSW['id']))
                                ->setFilename($mediaServie->getUrl($modelSW['path']))
                                ->setRemoteUrl($mediaServie->getUrl($modelSW['path']));
                                //->setFilename(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $modelSW['path']))
                                //->setRemoteUrl(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $modelSW['path']));

                            $result[] = $model;
                            break;
                        case ImageRelationType::TYPE_MANUFACTURER:
                            $model = Mmc::getModel('Image');

                            $model->setId(new Identity(ImageModel::generateId(ImageRelationType::TYPE_MANUFACTURER, $modelSW['id'], $modelSW['mediaId'])));
                            
                            $model->setRelationType($relationType)
                                ->setForeignKey(new Identity($modelSW['id']))
                                ->setFilename($mediaServie->getUrl($modelSW['path']))
                                ->setRemoteUrl($mediaServie->getUrl($modelSW['path']));
                                //->setFilename(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $modelSW['path']))
                                //->setRemoteUrl(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $modelSW['path']));

                            $result[] = $model;
                            break;
                        case ImageRelationType::TYPE_SPECIFIC_VALUE:
                            $model = Mmc::getModel('Image');

                            $model->setId(new Identity(ImageModel::generateId(ImageRelationType::TYPE_SPECIFIC_VALUE, $modelSW['id'], $modelSW['mediaId'])));

                            $model->setRelationType($relationType)
                                ->setForeignKey(new Identity($modelSW['id']))
                                ->setFilename($mediaServie->getUrl($modelSW['path']))
                                ->setRemoteUrl($mediaServie->getUrl($modelSW['path']));
                                //->setFilename(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $modelSW['path']))
                                //->setRemoteUrl(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $modelSW['path']));

                            $result[] = $model;
                            break;
                    }
                }
            }

            Shopware()->Models()->flush();

            $action->setResult($result);
        } catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }

        return $action;
    }

    /**
     * Statistic
     *
     * @param \jtl\Connector\Core\Model\QueryFilter $queryFilter
     * @return \jtl\Connector\Result\Action
     */
    public function statistic(QueryFilter $queryFilter)
    {
        $action = new Action();
        $action->setHandled(true);

        try {
            $mapper = Mmc::getMapper('Image');

            $statModel = new Statistic();
            $statModel->setControllerName('image');

            if ($queryFilter !== null && $queryFilter->isFilter(QueryFilter::FILTER_RELATION_TYPE)) {
                $statModel->setAvailable($mapper->fetchCount(null, $queryFilter->getFilter(QueryFilter::FILTER_RELATION_TYPE)));
            } else {
                // Get all images
                $relationTypes = array(
                    ImageRelationType::TYPE_PRODUCT,
                    ImageRelationType::TYPE_CATEGORY,
                    ImageRelationType::TYPE_MANUFACTURER,
                    ImageRelationType::TYPE_SPECIFIC_VALUE
                );

                foreach ($relationTypes as $relationType) {
                    $statModel->setAvailable($statModel->getAvailable() + $mapper->fetchCount(null, $relationType));
                }
            }

            $action->setResult($statModel->getPublic());
        } catch (\Exception $exc) {
            $err = new Error();
            $err->setCode($exc->getCode());
            $err->setMessage($exc->getMessage());
            $action->setError($err);
        }
        
        return $action;
    }
}
