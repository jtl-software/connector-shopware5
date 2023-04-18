<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use Doctrine\ORM\ORMException;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\Utilities\Singleton;
use jtl\Connector\Model\DataModel;
use jtl\Connector\Shopware\Model\ProductAttr;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Configurator\Option;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Image as ArticleImage;

abstract class DataMapper extends Singleton
{
    protected $manager;


    protected function __construct()
    {
        $this->manager = \Shopware()->Models();
    }

    protected function Manager() //phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return $this->manager;
    }

    /**
     * @param object $entity
     * @throws \Exception
     */
    protected function flush($entity = null)
    {
        $this->Manager()->getConnection()->beginTransaction();
        try {
            $this->Manager()->flush($entity);
            $this->Manager()->getConnection()->commit();
            $this->Manager()->clear();
        } catch (\Exception $e) {
            $this->Manager()->getConnection()->rollBack();
            throw new \Exception($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param Article $article
     * @throws ORMException
     */
    protected function rebuildArticleImagesMappings(Article $article)
    {
        $this->removeArticleImagesMappings($article);
        $this->createArticleImagesMappings($article);
        Logger::write(
            \sprintf(
                'Image mappings for article (%s) rebuilded',
                $article->getId()
            ),
            Logger::DEBUG,
            'image'
        );
    }

    /**
     * @param Article $article
     * @return array|false|string[]
     */
    protected function getIgnoreGroups(Article $article)
    {
        $qb = ShopUtil::entityManager()->getDBALQueryBuilder()
            ->select('cpa.value')
            ->from('jtl_connector_product_attributes', 'cpa')
            ->andWhere('cpa.product_id = :productId')
            ->andWhere('cpa.key = :key')
            ->setParameters(['productId' => $article->getId(), 'key' => ProductAttr::IMAGE_CONFIGURATION_IGNORES]);

        $stmt = $qb->execute();

        $result = $stmt->fetchColumn();
        if (\is_string($result) && \strlen($result) > 0) {
            return \explode('|||', $result);
        }
        return [];
    }

    /**
     * @param Article $article
     * @throws ORMException
     */
    protected function createArticleImagesMappings(Article $article)
    {
        $ignoreGroups = $this->getIgnoreGroups($article);

        /** @var Detail $detail */
        foreach ($article->getDetails() as $detail) {
            $detailOptions = \array_filter(
                $detail->getConfiguratorOptions()->toArray(),
                function (Option $option) use ($ignoreGroups) {
                    return !\in_array($option->getGroup()->getName(), $ignoreGroups);
                }
            );

            /** @var ArticleImage $image */
            foreach ($detail->getImages() as $image) {
                if ($this->articleImageMappingExists($image->getParent(), $detailOptions)) {
                    continue;
                }

                $mapping = new ArticleImage\Mapping();
                $mapping->setImage($image->getParent());
                /** @var Option $option */
                foreach ($detailOptions as $option) {
                    $rule = new ArticleImage\Rule();
                    $rule->setMapping($mapping);
                    $rule->setOption($option);
                    $mapping->getRules()->add($rule);
                }
                $image->getParent()->getMappings()->add($mapping);
                ShopUtil::entityManager()->persist($image->getParent());
            }
        }
    }

    /**
     * @param Article $article
     * @throws ORMException
     */
    protected function removeArticleImagesMappings(Article $article)
    {
        /** @var ArticleImage $image */
        foreach ($article->getImages() as $image) {
            foreach ($image->getMappings() as $mapping) {
                foreach ($mapping->getRules() as $rule) {
                    ShopUtil::entityManager()->remove($rule);
                }
                ShopUtil::entityManager()->remove($mapping);
                $image->getMappings()->removeElement($mapping);
            }
        }
    }


    /**
     * @param ArticleImage $image
     * @param array $options
     * @return bool
     */
    protected function articleImageMappingExists(ArticleImage $image, array $options): bool
    {
        /** @var ArticleImage\Mapping $mapping */
        foreach ($image->getMappings() as $mapping) {
            $rules = $mapping->getRules()->toArray();
            if (\count($rules) !== \count($options)) {
                continue;
            }

            $mappingOptions = [];
            foreach ($rules as $rule) {
                $mappingOptions[] = $rule->getOption();
            }

            $diff = \array_udiff($options, $mappingOptions, function (Option $a, Option $b) {
                return $a->getId() - $b->getId();
            });

            if (empty($diff)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param DataModel $attribute
     * @param string $local
     * @return void
     */
    protected static function logNullTranslation(DataModel $attribute, string $local)
    {
        Logger::write(\sprintf(
            'No Translation found for Attribute %s in locale %s',
            $attribute->getId()->getHost(),
            $local
        ), Logger::WARNING, 'database');
    }
}
