<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Shopware\Utilities\Translation;

class ConfiguratorSet extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->find('Shopware\Models\Article\Configurator\Set', $id);
    }

    public function findByProductId($productId, $count = false)
    {
        $builder = $this->Manager()->createQueryBuilder()->select(array(
            'article',
            'configuratorSet',
            'groups',
            'options'
        ))
        ->from('Shopware\Models\Article\Article', 'article')
        ->innerJoin('article.configuratorSet', 'configuratorSet')
        ->innerJoin('configuratorSet.groups', 'groups', null, null, 'groups.id')
        ->innerJoin('configuratorSet.options', 'options')
        ->where('article.id = ?1')
        ->setParameter(1, $productId)
        ->addOrderBy('groups.position', 'ASC')
        ->addOrderBy('options.position', 'ASC');

        $query = $builder->getQuery();

        if ($count) {
            $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);

            return $paginator->count();
        } else {
            $sets = $query->getResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll(null);

            $translationReader = new Translation;
            for ($i = 0; $i < count($sets); $i++) {
                foreach ($shops as $shop) {
                    
                    // Groups
                    $ks = array_keys($sets[$i]['configuratorSet']['groups']);
                    foreach ($ks as $k) {
                        $translation = $translationReader->read($shop['locale']['id'], 'configuratorgroup', $sets[$i]['configuratorSet']['groups'][$k]['id']);
                        if (!empty($translation)) {
                            $translation['groupId'] = $sets[$i]['configuratorSet']['groups'][$k]['id'];
                            $sets[$i]['configuratorSet']['groups'][$k]['translations'][$shop['locale']['locale']] = $translation;
                        }
                    }

                    // Options
                    $ks = array_keys($sets[$i]['configuratorSet']['options']);
                    foreach ($ks as $k) {
                        $translation = $translationReader->read($shop['locale']['id'], 'configuratoroption', $sets[$i]['configuratorSet']['options'][$k]['id']);
                        if (!empty($translation)) {
                            $translation['optionId'] = $sets[$i]['configuratorSet']['options'][$k]['id'];
                            $sets[$i]['configuratorSet']['options'][$k]['translations'][$shop['locale']['locale']] = $translation;
                        }
                    }
                }
            }

            return $sets;
        }
    }
}
