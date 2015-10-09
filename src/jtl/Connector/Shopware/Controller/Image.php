<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Controller;

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

            $mapper = Mmc::getMapper('Image');

            $modelContainer = array();
            if ($queryFilter->getFilter('relationType') !== null) {
                $modelContainer[$queryFilter->getFilter('relationType')] = $mapper->findAll($limit, false, $queryFilter->getFilter('relationType'));
            } else {
                // Get all images
                $relationTypes = array(
                    ImageRelationType::TYPE_PRODUCT,
                    ImageRelationType::TYPE_CATEGORY,
                    ImageRelationType::TYPE_MANUFACTURER
                );

                foreach ($relationTypes as $relationType) {
                    $modelContainer[$relationType] = $mapper->findAll($limit, false, $relationType);
                }
            }

            $proto = ShopUtil::getProtocol();
            foreach ($modelContainer as $relationType => $models) {
                foreach ($models as $modelSW) {
                    switch ($relationType) {
                        case ImageRelationType::TYPE_PRODUCT:
                            $model = Mmc::getModel('Image');

                            /*
                            $id = ImageModel::generateId(ImageRelationType::TYPE_PRODUCT, (int) $modelSW['id'], (int) $modelSW['media_id']);
                            $path = $modelSW['path'];
                            $foreignKey = IdConcatenator::link(array($modelSW['detailId'], $modelSW['articleID']));

                            $model->setId(new Identity($id));
                            $model->setRelationType($relationType)
                                ->setForeignKey(new Identity($foreignKey))
                                ->setFilename(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $path))
                                ->setRemoteUrl(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $path))
                                ->setSort((int) $modelSW['position']);
                            */

                            // Parent
                            $id = ImageModel::generateId(ImageRelationType::TYPE_PRODUCT, $modelSW['id'], $modelSW['media']['id']);
                            $path = $modelSW['media']['path'];

                            // Child?
                            if (isset($modelSW['parent']) && $modelSW['parent'] !== null) {
                                $id = ImageModel::generateId(ImageRelationType::TYPE_PRODUCT, $modelSW['id'], $modelSW['parent']['media']['id']);
                                $foreignKey = IdConcatenator::link(array($modelSW['articleDetailId'], $modelSW['parent']['articleId']));
                                $path = $modelSW['parent']['media']['path'];
                            } else {
                                $foreignKey = IdConcatenator::link(array($modelSW['article']['mainDetailId'], $modelSW['articleId']));
                            }

                            $model->setId(new Identity($id));
                            $model->setRelationType($relationType)
                                ->setForeignKey(new Identity($foreignKey))
                                ->setFilename(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $path))
                                ->setRemoteUrl(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $path))
                                ->setSort($modelSW['position']);

                            $result[] = $model;
                            break;
                        case ImageRelationType::TYPE_CATEGORY:
                            $model = Mmc::getModel('Image');
                            
                            $model->setId(new Identity(ImageModel::generateId(ImageRelationType::TYPE_CATEGORY, $modelSW['id'], $modelSW['mediaId'])));

                            $model->setRelationType($relationType)
                                ->setForeignKey(new Identity($modelSW['id']))
                                ->setFilename(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $modelSW['path']))
                                ->setRemoteUrl(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $modelSW['path']));

                            $result[] = $model;
                            break;
                        case ImageRelationType::TYPE_MANUFACTURER:
                            $model = Mmc::getModel('Image');

                            $model->setId(new Identity(ImageModel::generateId(ImageRelationType::TYPE_MANUFACTURER, $modelSW['id'], $modelSW['mediaId'])));
                            
                            $model->setRelationType($relationType)
                                ->setForeignKey(new Identity($modelSW['id']))
                                ->setFilename(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $modelSW['path']))
                                ->setRemoteUrl(sprintf('%s://%s%s/%s', $proto, Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), $modelSW['path']));

                            $result[] = $model;
                            break;
                        /*
                        case ImageRelationType::TYPE_PRODUCT_VARIATION_VALUE:
                            $model = Mmc::getModel('Image');

                            // Work Around
                            // id = s_article_img_mapping_rules.id
                            $model->setId(new Identity('option_' . $modelSW['id']));
                            
                            $model->setRelationType($relationType)
                                ->setForeignKey(new Identity($modelSW['articleID'] . '_' . $modelSW['group_id'] . '_' . $modelSW['foreignKey']))
                                ->setFilename(sprintf('http://%s%s/%s%s', Shopware()->Shop()->getHost(), Shopware()->Shop()->getBasePath(), 'media/image/', $modelSW['path'] . '.' . $modelSW['extension']));

                            $result[] = $model->getPublic();
                            break;
                        */
                    }
                }
            }

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
                    ImageRelationType::TYPE_MANUFACTURER
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
