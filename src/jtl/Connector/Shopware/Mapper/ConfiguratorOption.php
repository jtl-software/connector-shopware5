<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware\Controller
 */

namespace jtl\Connector\Shopware\Mapper;

use \jtl\Connector\Shopware\Utilities\Mmc;
use \jtl\Connector\Core\Logger\Logger;
use \Shopware\Components\Api\Exception as ApiException;
use \Shopware\Models\Article\Configurator\Option as ConfiguratorOptionModel;
use \jtl\Connector\ModelContainer\ProductContainer;
use \jtl\Connector\Shopware\Model\ProductVariation;
use \jtl\Connector\Core\Utilities\DataConverter;
use \jtl\Connector\Shopware\Model\DataModel;
use \jtl\Connector\Core\Utilities\Language as LanguageUtil;

class ConfiguratorOption extends DataMapper
{
    public function find($id)
    {
        return $this->Manager()->getRepository('Shopware\Models\Article\Configurator\Option')->find($id);
    }

    public function findOneBy(array $kv)
    {
        return $this->Manager()->getRepository('Shopware\Models\Article\Configurator\Option')->findOneBy($kv);
    }

    public function findOneByName($name, $forceObject = false)
    {
        $result = Shopware()->Db()->fetchOne(
            'SELECT id FROM s_article_configurator_options WHERE name = ?',
            array($name)
        );

        if ($forceObject) {
            return $this->find($result);
        }

        return ($result !== false) ? $result : null;
    }

    public function save(array $data, $namespace = '\Shopware\Models\Article\Configurator\Option')
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
     * @return \Shopware\Models\Article\Configurator\Option
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     */
    public function update($id, array $params)
    {
        if (empty($id)) {
            throw new ApiException\ParameterMissingException();
        }

        /** @var $configuratorOption \Shopware\Models\Article\Supplier */
        $configuratorOption = $this->find($id);

        if (!$configuratorOption) {
            throw new ApiException\NotFoundException("Configurator Option by id $id not found");
        }

        $configuratorOption->fromArray($params);

        $violations = $this->Manager()->validate($configuratorOption);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->flush();

        return $configuratorOption;
    }

    /**
     * @param array $params
     * @return \Shopware\Models\Article\Configurator\Option
     * @throws \Shopware\Components\Api\Exception\ValidationException
     */
    public function create(array $params)
    {
        $configuratorOption = new ConfiguratorOptionModel();

        $configuratorOption->fromArray($params);

        $violations = $this->Manager()->validate($configuratorOption);
        if ($violations->count() > 0) {
            throw new ApiException\ValidationException($violations);
        }

        $this->Manager()->persist($configuratorOption);
        $this->flush();

        return $configuratorOption;
    }

    /**
     * @param int $id
     * @param string $localId
     * @param string $translation
     * @return \Shopware\Models\Article\Configurator\Option
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     */
    public function createTranslatation($id, $localId, $translation)
    {
        $configuratorOption = $this->find($id);

        if (!$configuratorOption) {
            throw new ApiException\NotFoundException("Configurator Option by id $id not found");
        }

        $resource = \Shopware\Components\Api\Manager::getResource('Translation');
        $resource->create(array(
            'type' => \Shopware\Components\Api\Resource\Translation::TYPE_CONFIGURATOR_OPTION,
            'key' => $configuratorOption->getId(),
            'localeId' => $localId,
            'data' => array('name' => $translation)
        ));

        return $configuratorOption;
    }

    /*
    public function prepareData(ProductContainer $container, ProductVariation $productVariation, $productId, $groupId, array &$data)
    {
        if ($data === null) {
            $data = array();
        }

        if (!isset($data['configuratorSet'])) {
            $data['configuratorSet'] = array();
        }

        $shopMapper = Mmc::getMapper('Shop');
        $shops = $shopMapper->findAll();

        foreach ($container->getProductVariationValues() as $productVariationValue) {
            if (empty($productVariation->getId()->getEndpoint())) {

                // creating new configuratorOption
                $optionId = null;
                $isAvailable = false;
                foreach ($container->getProductVariationValueI18ns() as $productVariationValueI18n) {

                    // find default shop language to create a base variation
                    if ($productVariationValue->getId()->getHost() == $productVariationValueI18n->getProductVariationValueId()->getHost()
                        && $productVariationValueI18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                        $params = DataConverter::toArray(DataModel::map(false, null, $productVariationValue));
                        $params['name'] = $productVariationValueI18n->getName();
                        $params['position'] = 1;
                        $params['groupId'] = $groupId;

                        // Create new option
                        $configuratorOption = $this->create($params);

                        $optionId = $configuratorOption->getId();

                        $data['configuratorSet']['options'][$optionId]['name'] = $params['name'];
                        $data['configuratorSet']['options'][$optionId]['id'] = $optionId;
                        $data['configuratorSet']['options'][$optionId]['groupId'] = $groupId;

                        $isAvailable = $optionId > 0;
                    }
                }

                if (!$isAvailable) {
                    Logger::write('Product variation value (Host: ' . $productVariationValue->getId()->getHost() . ') could not be created', Logger::WARNING, 'database');

                    continue;
                }

                $data['configuratorSet']['options'][$optionId] = array_merge($data['configuratorSet']['options'][$optionId],
                    DataConverter::toArray(DataModel::map(false, null, $productVariationValue)));

                $data['configuratorSet']['options'][$optionId]['id'] = $optionId;
                $data['configuratorSet']['options'][$optionId]['articleId'] = $productId;

                // find all non defaut languages to create a translation model
                foreach ($container->getProductVariationValueI18ns() as $productVariationValueI18n) {
                    if ($productVariationValue->getId()->getHost() == $productVariationValueI18n->getProductVariationValueId()->getHost()
                        && $productVariationValueI18n->getLanguageISO() !== LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                        $localeId = null;
                        foreach ($shops as $shop) {
                            if (LanguageUtil::map($shop['locale']['locale']) === $productVariationValueI18n->getLanguageISO()) {
                                $localeId = $shop['locale']['id'];
                            }
                        }

                        if ($localeId === null) {
                            Logger::write('Cannot find any shop localeId with locale (' . $productVariationI18n->getLanguageISO() . ')', Logger::WARNING, 'database');

                            continue;
                        }

                        $this->createTranslatation($optionId, $localeId, $productVariationValueI18n->getName());

                        $data['configuratorSet']['options'][$optionId]['translations'][$productVariationValueI18n->getLanguageISO()] = array();
                        $data['configuratorSet']['options'][$optionId]['translations'][$productVariationValueI18n->getLanguageISO()]['name'] = $productVariationValueI18n->getName();
                        $data['configuratorSet']['options'][$optionId]['translations'][$productVariationValueI18n->getLanguageISO()]['optionId'] = $optionId;
                    }
                }
            } else { // Only update existing variation values

                list($productId, $groupId, $optionId) = explode('_', $productVariationValue->getId()->getEndpoint());

                $data['configuratorSet']['options'][$optionId] = DataConverter::toArray(DataModel::map(false, null, $productVariationValue));
                $data['configuratorSet']['options'][$optionId]['id'] = $optionId;
                $data['configuratorSet']['options'][$optionId]['articleId'] = $productId;
                $data['configuratorSet']['options'][$optionId]['groupId'] = $groupId;

                foreach ($container->getProductVariationValueI18ns() as $productVariationValueI18n) {
                    if ($productVariationValue->getId()->getEndpoint() == $productVariationValueI18n->getProductVariationValueId()->getEndpoint()) {

                        // Update default language name on the current base variation
                        if ($productVariationValueI18n->getLanguageISO() === LanguageUtil::map(Shopware()->Shop()->getLocale()->getLocale())) {
                            $data['configuratorSet']['options'][$optionId]['name'] = $productVariationValueI18n->getName();
                        } else {

                            // New language translation value
                            if (empty($productVariationValueI18n->getProductVariationValueId()->getEndpoint())) {
                                $localeId = null;
                                foreach ($shops as $shop) {
                                    if (LanguageUtil::map($shop['locale']['locale']) === $productVariationValueI18n->getLanguageISO()) {
                                        $localeId = $shop['locale']['id'];
                                    }
                                }

                                $this->createTranslatation($optionId, $localeId, $productVariationValueI18n->getName());
                            }

                            $data['configuratorSet']['options'][$optionId]['translations'][$productVariationValueI18n->getLanguageISO()] = array();
                            $data['configuratorSet']['options'][$optionId]['translations'][$productVariationValueI18n->getLanguageISO()]['name'] = $productVariationValueI18n->getName();
                            $data['configuratorSet']['options'][$optionId]['translations'][$productVariationValueI18n->getLanguageISO()]['optionId'] = $optionId;
                        }
                    }
                }
            }
        }
    }
    */
}
