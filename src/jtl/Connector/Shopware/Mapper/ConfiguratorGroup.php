<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Core\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \Shopware\Models\Article\Configurator\Group as ConfiguratorGroupModel;
use \jtl\Connector\ModelContainer\ProductContainer;
use \jtl\Connector\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Model\DataModel;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;
use \jtl\Connector\Shopware\Utilities\Locale as LocaleUtil;
use \jtl\Connector\Shopware\Utilities\Translation as TranslationUtil;

class ConfiguratorGroup extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Article\Configurator\Group')->find($id);
    }

    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Article\Configurator\Group')->findOneBy($kv);
    }

    public function findOneByName($name, $forceObject = false)
    {
        $result = Shopware()->Db()->fetchOne(
            'SELECT id FROM s_article_configurator_groups WHERE name = ?',
            array($name)
        );

        if ($forceObject) {
            return $this->find($result);
        }

        return ($result !== false) ? $result : null;
    }

    public function delete($id)
    {
        $group = $this->find($id);
        if ($group) {
            $this->Manager()->remove($group);
            $this->flush();
        }

        return $group;
    }

    public function save(array $data, $namespace = '\Shopware\Models\Article\Configurator\Group')
    {
        Logger::write(print_r($data, 1), Logger::DEBUG, 'database');
        
        try {
            if (!$data['id']) {
                return $this->create($data);
            } else {
                return $this->update($data['id'], $data);
            }
        } catch (ApiException\NotFoundException $exc) {
            return $this->create($data);
        }
    }

    /**
     * @param int $id
     * @param array $params
     * @return \Shopware\Models\Article\Configurator\Group
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function update($id, array $params)
    {
        if (empty($id)) {
            throw new ApiException\ParameterMissingException();
        }

        /** @var $configuratorGroup \Shopware\Models\Article\Supplier */
        $configuratorGroup = $this->find($id);

        if (!$configuratorGroup) {
            throw new ApiException\NotFoundException("Configurator Group by id $id not found");
        }

        $configuratorGroup->fromArray($params);

        $violations = $this->Manager()->validate($configuratorGroup);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->flush();

        return $configuratorGroup;
    }

    /**
     * @param array $params
     * @return \Shopware\Models\Article\Configurator\Group
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    public function create(array $params)
    {
        $configuratorGroup = new ConfiguratorGroupModel();

        $configuratorGroup->fromArray($params);

        $violations = $this->Manager()->validate($configuratorGroup);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($configuratorGroup);
        $this->flush();

        return $configuratorGroup;
    }

    /**
     * @param int $id
     * @param string $iso
     * @param string $translation
     * @return \Shopware\Models\Article\Configurator\Group
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     */
    public function saveTranslatation($id, $iso, $translation)
    {
        $configuratorGroup = $this->find($id);

        if (!$configuratorGroup) {
            throw new ApiException\NotFoundException(sprintf('Configurator Group by id (%s) not found', $id));
        }

        $locale = LocaleUtil::getByKey(LanguageUtil::map(null, null, $iso));

        if ($locale === null) {
            throw new ApiException\NotFoundException(sprintf('Could not find any locale for iso (%s)', $iso));
        }

        $shopMapper = Mmc::getMapper('Shop');
        $shop = $shopMapper->findByLocale($locale->getLocale());

        if ($shop === null) {
            throw new ApiException\NotFoundException(sprintf('Could not find any shop with locale (%s) and iso (%s)', $locale->getLocale(), $iso));
        }

        $translationUtil = new TranslationUtil();
        $translationUtil->delete('configuratorgroup', $id, $shop->getId());
        $translationUtil->write(
            $shop->getId(),
            'configuratorgroup',
            $configuratorGroup->getId(),
            array('name' => $translation)
        );

        return $configuratorGroup;
    }

    /*
    public function prepareData(ProductContainer $container, $productId, array &$data)
    {
        if (count($container->getProductVariations()) > 0) {
            if ($data === null) {
                $data = array();
            }

            if (!isset($data['configuratorSet'])) {
                $data['configuratorSet'] = array();
            }

            $configuratorOptionMapper = Mmc::getMapper('ConfiguratorOption');
            $shopMapper = Mmc::getMapper('Shop');
            $shops = $shopMapper->findAll();

            foreach ($container->getProductVariations() as $productVariation) {

                // New
                $groupId = null;
                if (empty($productVariation->getId()->getEndpoint())) {
                    if (empty($productVariation->getId()->getHost())) {
                        Logger::write('Product variation host and endpoint ids cannot be empty', Logger::WARNING, 'database');

                        continue;
                    }

                    // creating new configuratorGroup
                    $isAvailable = false;
                    foreach ($container->getProductVariationI18ns() as $productVariationI18n) {

                        // find default shop language to create a base variation
                        if ($productVariation->getId()->getHost() == $productVariationI18n->getProductVariationId()->getHost()
                            && $productVariationI18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                            $params = DataConverter::toArray(DataModel::map(false, null, $productVariation));
                            $params['name'] = $productVariationI18n->getName();

                            $configuratorGroup = $this->findOneBy(array('name' => $params['name']));

                            if (!$configuratorGroup) {
                                $configuratorGroup = $this->create($params);

                                // todo: relations????
                            }

                            $groupId = $configuratorGroup->getId();

                            $data['configuratorSet']['groups'][$groupId]['name'] = $params['name'];
                            $isAvailable = $groupId > 0;
                        }
                    }

                    if (!$isAvailable) {
                        Logger::write('Product variation (Host: ' . $productVariation->getId()->getHost() . ') could not be created', Logger::WARNING, 'database');

                        continue;
                    }

                    $data['configuratorSet']['groups'][$groupId] = array_merge($data['configuratorSet']['groups'][$groupId],
                        DataConverter::toArray(DataModel::map(false, null, $productVariation)));

                    $data['configuratorSet']['groups'][$groupId]['id'] = $groupId;
                    $data['configuratorSet']['groups'][$groupId]['articleId'] = $productId;

                    // find all non defaut languages to create a translation model
                    foreach ($container->getProductVariationI18ns() as $productVariationI18n) {
                        if ($productVariation->getId()->getHost() == $productVariationI18n->getProductVariationId()->getHost()
                            && $productVariationI18n->getLanguageISO() !== LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                            $localeId = null;
                            foreach ($shops as $shop) {
                                if (LanguageUtil::map($shop['locale']['locale']) === $productVariationI18n->getLanguageISO()) {
                                    $localeId = $shop['locale']['id'];
                                }
                            }

                            if ($localeId === null) {
                                Logger::write('Cannot find any shop localeId with languageIso (' . $productVariationI18n->getLanguageISO() . ')', Logger::WARNING, 'database');

                                continue;
                            }

                            $this->createTranslatation($groupId, $localeId, $productVariationI18n->getName());

                            $data['configuratorSet']['groups'][$groupId]['translations'][$productVariationI18n->getLanguageISO()] = array();
                            $data['configuratorSet']['groups'][$groupId]['translations'][$productVariationI18n->getLanguageISO()]['name'] = $productVariationI18n->getName();
                            $data['configuratorSet']['groups'][$groupId]['translations'][$productVariationI18n->getLanguageISO()]['groupId'] = $groupId;
                        }
                    }

                    // Creating new variation value
                    $configuratorOptionMapper->prepareData($container, $productVariation, $productId, $groupId, $data);
                } else { // Only update existing variations

                    list($productId, $groupId) = explode('_', $productVariation->getId()->getEndpoint());

                    $data['configuratorSet']['groups'][$groupId] = DataConverter::toArray(DataModel::map(false, null, $productVariation));
                    $data['configuratorSet']['groups'][$groupId]['id'] = $groupId;
                    $data['configuratorSet']['groups'][$groupId]['articleId'] = $productId;

                    foreach ($container->getProductVariationI18ns() as $productVariationI18n) {
                        if ($productVariation->getId()->getEndpoint() == $productVariationI18n->getProductVariationId()->getEndpoint()) {

                            // Update default language name on the current base variation
                            if ($productVariationI18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                                $data['configuratorSet']['groups'][$groupId]['name'] = $productVariationI18n->getName();
                            } else {

                                // New language translation value
                                if (empty($productVariationI18n->getProductVariationId()->getEndpoint())) {
                                    $localeId = null;
                                    foreach ($shops as $shop) {
                                        if (LanguageUtil::map($shop['locale']['locale']) === $productVariationI18n->getLanguageISO()) {
                                            $localeId = $shop['locale']['id'];
                                        }
                                    }

                                    $this->createTranslatation($groupId, $localeId, $productVariationI18n->getName());
                                }

                                $data['configuratorSet']['groups'][$groupId]['translations'][$productVariationI18n->getLanguageISO()] = array();
                                $data['configuratorSet']['groups'][$groupId]['translations'][$productVariationI18n->getLanguageISO()]['name'] = $productVariationI18n->getName();
                                $data['configuratorSet']['groups'][$groupId]['translations'][$productVariationI18n->getLanguageISO()]['groupId'] = $groupId;
                            }
                        }
                    }

                    // Prepare variation value
                    $configuratorOptionMapper->prepareData($container, $productVariation, $productId, $groupId, $data);
                }
            }
        }
    }
    */
}
