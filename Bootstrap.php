<?php

use jtl\Connector\Core\Config\Config;
use jtl\Connector\Core\IO\Path;
use jtl\Connector\Core\Logger\Logger;
use jtl\Connector\Core\System\Check as CheckUtil;
use jtl\Connector\Core\Utilities\Language as LanguageUtil;
use jtl\Connector\Formatter\ExceptionFormatter;
use jtl\Connector\Shopware\Service\Translation;
use jtl\Connector\Shopware\Utilities\CustomerGroup as CustomerGroupUtil;
use jtl\Connector\Shopware\Utilities\Mmc;
use jtl\Connector\Shopware\Utilities\Payment;
use jtl\Connector\Shopware\Utilities\Shop as ShopUtil;
use jtl\Connector\Shopware\Mapper\Product as ProductMapper;
use Shopware\Components\Plugin\CachedConfigReader;
use Shopware\Models\Category\Category;
use Shopware\Models\Shop\Shop;
use Symfony\Component\Yaml\Yaml;

define('CONNECTOR_DIR', __DIR__);

class Shopware_Plugins_Frontend_jtlconnector_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    public const
        DELETE_USER_DATA = 'delete_user_data',
        CONNECTOR_URL = 'connector_url',
        AUTH_TOKEN = 'auth_token',
        DEVELOPER_LOGGING = 'developer_logging',

        DOWNLOAD_LOGS_BUTTON = 'download_logs',
        DELETE_LOGS_BUTTON = 'delete_logs';

    /**
     * @var Config
     */
    protected $config;


    public function __construct($name, Enlight_Config $info = null)
    {
        $this->runAutoload();
        parent::__construct($name, $info);
    }

    public function getCapabilities()
    {
        return array(
            'install' => true,
            'update' => true,
            'enable' => true,
        );
    }

    public function getLabel()
    {
        return 'JTL Shopware 5 Connector';
    }

    public function getVersion()
    {
        return trim(Yaml::parseFile(__DIR__ . '/build-config.yaml')['version']);
    }

    public function getInfo()
    {
        return array(
            'version' => $this->getVersion(),
            'label' => $this->getLabel(),
            'author' => 'JTL-Software GmbH',
            'description' => 'Verbinden Sie Ihren Shop mit JTL-Wawi, der kostenlosen Multichannel-Warenwirtschaft für den Versandhandel.',
            'support' => 'JTL-Software Forum',
            'link' => 'http://www.jtl-software.de'
        );
    }

    public function install()
    {
        Logger::write('Shopware plugin installer started...', Logger::INFO, 'install');

        // Config
        $config_file = Path::combine(__DIR__, 'config', 'config.json');
        if (!file_exists($config_file)) {
            file_put_contents($config_file, json_encode(array(
                'developer_logging' => false,
                'category' => [
                    'mapping' => false,
                    'push' => [
                        'null_undefined_attributes' => true,
                    ]
                ],
                'product' => [
                    'push' => [
                        'enable_custom_properties' => false,
                        'null_undefined_attributes' => true,
                        'article_detail_preselection' => false,
                        'use_handling_time_for_shipping' => false,
                        'consider_supplier_inflow_date_for_shipping' => true,
                    ]
                ],
                'customer_order' => [
                    'pull' => [
                        'start_date' => null,
                        'status_processing' => true,
                    ]
                ],
                'payment' => [
                    'pull' => [
                        'allowed_cleared_states' => []
                    ]
                ]
            ), JSON_PRETTY_PRINT));
        }

        $this->config = new Config($config_file);

        Logger::write('Checking shopware version...', Logger::INFO, 'install');

        if (!$this->assertMinimumVersion('5.2.0')) {
            Logger::write('Shopware version missmatch', Logger::ERROR, 'install');

            return array(
                'success' => false,
                'message' => 'Das Plugin benötigt mindestens die Shopware Version 5.2.0'
            );
        }

        // Check requirements
        try {
            CheckUtil::run();
        } catch (\Exception $e) {
            Logger::write(ExceptionFormatter::format($e), Logger::ERROR, 'install');

            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }

        ini_set('max_execution_time', 0);

        $this->registerController('Frontend', 'Jtlconnector', 'onGetControllerPathFrontend');

        $this->registerController('Backend', 'Jtlconnector', 'onGetControllerPathBackend');

        $this->subscribeEvent('Shopware_Controllers_Backend_Config_After_Save_Config_Element','afterSaveConfigElement');

        $this->subscribeTranslationService();

        $this->setConfigFormElements();
        $this->createProductChecksumTable();
        $this->createMappingTables();
        $this->fillCategoryTable();
        $this->fillCrossSellingGroupTable();
        $this->migratePaymentLinkTable();

        return array(
            'success' => true,
            'invalidateCache' => array('backend', 'proxy')
        );
    }

    /**
     * @return void
     */
    public function setConfigFormElements()
    {
        /** @var Shop $shop */
        $shop = ShopUtil::entityManager()->getRepository(Shop::class)->findOneBy(['default' => 1, 'active' => 1]);

        $url = 'Hauptshop nicht gefunden';
        if (!is_null($shop)) {
            $proto = $shop->getSecure() ? 'https' : 'http';
            $url = sprintf('%s://%s%s/%s', $proto, $shop->getHost(), $shop->getBasePath(), 'jtlconnector/');
        }

        // Connector URL
        $this->Form()->setElement('text', self::CONNECTOR_URL, [
            'label' => 'Connector Url (Info! Bitte nicht bearbeiten)',
            'required' => true,
            'value' => $url,
            'position' => 0
        ]);

        $authToken = $this->createGuid();
        $tokenElement = $this->form->getElement(self::AUTH_TOKEN);
        if (!is_null($tokenElement) && !empty($tokenElement->getValue())) {
            $authToken = $tokenElement->getValue();
        }

        // Connector Auth Token
        $this->Form()->setElement('text', self::AUTH_TOKEN, [
            'label' => 'Passwort',
            'required' => true,
            'value' => $authToken,
            'position' => 1
        ]);

        $this->Form()->setElement('boolean', self::DELETE_USER_DATA, [
            'label' => 'Linking Tabellen nach Deinstallation löschen',
            'required' => true,
            'value' => true,
            'position' => 2
        ]);

        $this->Form()->setElement('boolean', self::DEVELOPER_LOGGING, [
            'label' => 'Connector Entwickler-Logs',
            'required' => true,
            'value' => $this->info->get(self::DEVELOPER_LOGGING, false),
            'position' => 3
        ]);

        $router = Shopware()->Front()->Router();

        $checkLogsUrl = $router->assemble(['module' => 'backend', 'controller' => 'jtlconnector', 'action' => 'check-logs']);
        $downloadLogsUrl = $router->assemble(['module' => 'backend', 'controller' => 'jtlconnector', 'action' => 'download-logs']);

        $this->Form()->setElement('button', self::DOWNLOAD_LOGS_BUTTON, [
            'label' => 'Logs herunterladen',
            'class'=>'foo',
            'handler' => sprintf('function() {
                Ext.Ajax.request({
                    url: "%s",
                    success: function (response) {
                        window.open("%s");
                    },
                    failure: function (response) {
                        let data = Ext.decode(response.responseText);
                        Shopware.Msg.createGrowlMessage("Error", data.message);
                    }
                });
             }', $checkLogsUrl, $downloadLogsUrl),
            'position' => 4
        ]);

        $deleteLogsUrl = $router->assemble(['module' => 'backend', 'controller' => 'jtlconnector', 'action' => 'delete-logs']);

        $this->Form()->setElement('button', self::DELETE_LOGS_BUTTON, [
            'label' => 'Logs löschen',
            'handler' => sprintf('function() {
                if(confirm("Do you want to delete all connector logs?")){
                  Ext.Ajax.request({
                    url: "%s",
                    success: function (response) {
                        let data = Ext.decode(response.responseText);
                        Shopware.Msg.createGrowlMessage("Success", data.message);
                    },
                    failure: function (response) {
                        let data = Ext.decode(response.responseText);
                        Shopware.Msg.createGrowlMessage("Error", data.message);
                    }
                  });
                }                  
             }', $deleteLogsUrl),
            'position' => 5
        ]);
    }

    public function update($oldVersion)
    {
        ini_set('max_execution_time', 0);

        switch ($oldVersion) {
            case '1.0.0':
                Shopware()->Db()->query("UPDATE s_articles_details SET ordernumber = REPLACE(ordernumber, '.0', '.jtlcon.0') WHERE ordernumber LIKE '%.0' AND kind = 0");
                $this->createPaymentTable();
                $this->createPaymentMappingTable();
            case '1.0.1':
                Shopware()->Db()->query("UPDATE s_articles_details SET active = 0 WHERE ordernumber LIKE '%.jtlcon.0'");
            case '1.0.2':
                $this->createCategoryTable();
                $this->fillCategoryTable();
            case '1.0.3':
            case '1.0.4':
                Shopware()->Db()->query("UPDATE s_articles_details SET ordernumber = REPLACE(ordernumber, '.jtlcon.0', ''), kind = 0 WHERE ordernumber LIKE '%.jtlcon.0'");
            case '1.0.5':
            case '1.0.6':
            case '1.0.7':
                Shopware()->Db()->query('ALTER TABLE `jtl_connector_link_image` ADD INDEX(`host_id`, `image_id`)');
            case '1.0.8':
                Shopware()->Db()->query(
                    'UPDATE jtl_connector_payment p
                     JOIN s_order o ON o.id = p.customerOrderId
                     SET p.totalSum = o.invoice_amount'
                );
            case '1.0.9':
            case '1.0.10':
            case '1.0.11':
            case '1.0.12':
            case '1.1.0':
            case '1.1.1':
            case '1.1.2':
            case '1.2.1':
                Shopware()->Db()->query('ALTER TABLE `jtl_connector_link_product_image` ADD `media_id` INT(10) UNSIGNED NOT NULL AFTER `image_id`');
                Shopware()->Db()->query('ALTER TABLE `jtl_connector_link_product_image` ADD INDEX `id_media_id` (`id`, `media_id`)');

                Shopware()->Db()->query(
                    'UPDATE jtl_connector_link_product_image l
                    JOIN s_articles_img i ON i.id = l.id
                    JOIN s_articles_img p ON p.id = i.parent_id
                    SET l.media_id = if (i.media_id > 0, i.media_id, p.media_id)'
                );
            case '1.2.2':
            case '1.2.3':
            case '1.2.4':
            case '1.2.5':
            case '1.3.0':
            case '1.3.1':
            case '1.3.2':
                $this->createCrossSellingGroupTable();
                $this->fillCrossSellingGroupTable();
            case '1.4.0':
            case '1.4.1':
            case '1.4.2':
                $this->createCrossSellingGroupTable();
                $related = jtl\Connector\Shopware\Model\CrossSellingGroup::RELATED;
                $similar = jtl\Connector\Shopware\Model\CrossSellingGroup::SIMILAR;
                $relatedGroupId = Shopware()->Db()->fetchOne('SELECT group_id FROM jtl_connector_crosssellinggroup_i18n WHERE name = ?',
                    [$related]);
                $similarGroupId = Shopware()->Db()->fetchOne('SELECT group_id FROM jtl_connector_crosssellinggroup_i18n WHERE name = ?',
                    [$similar]);

                if ($relatedGroupId === null && $similarGroupId === null) {
                    $this->fillCrossSellingGroupTable();
                }
            case '1.4.3':
            case '1.4.4':
            case '1.4.5':
            case '1.4.6':
            case '1.4.7':
            case '1.4.8':
            case '1.4.9':
            case '2.0.0':
            case '2.0.1':
            case '2.0.2':
            case '2.0.3':
            case '2.0.4':
            case '2.0.5':
            case '2.0.6':
            case '2.0.7':
            case '2.0.8':
            case '2.0.9':
            case '2.0.10':
            case '2.0.11':
            case '2.0.12':
            case '2.0.13':
            case '2.0.14':
            case '2.0.15':
                $this->createSpecialProductAttributeTable();
            case '2.0.16':
            case '2.0.17':
            case '2.0.18':
            case '2.1':
            case '2.1.1':
            case '2.1.2':
            case '2.1.3':
            case '2.1.4':
            case '2.1.5':
            case '2.1.6':
            case '2.1.7':
            case '2.1.8':
            case '2.1.9':
            case '2.1.10':
            case '2.1.11':
            case '2.1.12':
            case '2.1.13':
            case '2.1.14':
                Shopware()->Db()->query("UPDATE s_articles_details sad SET sad.kind = 3 WHERE sad.kind = 0");
            case '2.1.15':
            case '2.1.16':
                Shopware()->Db()->query("UPDATE s_articles_details sad SET sad.kind = 3 WHERE sad.active = 0 AND sad.ordernumber LIKE '%.0'");
            case '2.1.17':
            case '2.1.18':
            case '2.1.19':
            case '2.1.20':
            case '2.1.21':
            case '2.2.0':
            case '2.2.0.1':
            case '2.2.0.2':
            case '2.2.1':
            case '2.2.1.1':
            case '2.2.1.2':
            case '2.2.1.3':
            case '2.2.1.4':
            case '2.2.2':
            case '2.2.3':
            case '2.2.3.1':
                Shopware()->Db()->query("DROP TABLE IF EXISTS `jtl_connector_category_level`");
                $this->setConfigFormElements();
            case '2.2.4':
            case '2.2.4.1':
            case '2.2.4.2':
            case '2.2.4.3':
            case '2.2.4.4':
                $this->subscribeTranslationService();
            case '2.2.5':
            case '2.2.5.1':
            case '2.2.5.2':
            case '2.2.5.3':
            case '2.3.0':
            case '2.3.0.1':
            case '2.3.0.2':
            case '2.4.0':
                $this->migratePaymentLinkTable();
            case '2.4.1':
            case '2.5.0':
            case '2.5.1':
            case '2.5.2':
            case '2.5.3':
            case '2.5.4':
            case '2.6.0':
            case '2.6.1':
            case '2.7.0':
            case '2.7.0.1':
            case '2.7.0.2':
            case '2.7.0.3':
            case '2.8.0':
            case '2.8.1':
            case '2.8.2':
            case '2.8.2.1':
            case '2.8.3':
            case '2.8.3.1':
            case '2.8.4':
            case '2.8.5':
            case '2.8.5.1':
            case '2.8.5.2':
            case '2.8.5.3':
            case '2.8.5.4':
            case '2.8.5.5':
            case '2.8.6':
                $this->registerController('Backend', 'Jtlconnector', 'onGetControllerPathBackend');
                $this->setConfigFormElements();
                break;
        }

        return true;
    }

    private function createMappingTables()
    {
        $this->createParentDummies();
        $this->createCategoryMappingTable();
        $this->createDetailMappingTable();
        $this->createCustomerMappingTable();
        $this->createCustomerOrderMappingTable();
        $this->createDeliveryNoteMappingTable();
        $this->createImageMappingTable();
        $this->createProductImageMappingTable();
        $this->createManufacturerMappingTable();
        $this->createSpecificMappingTable();
        $this->createSpecificValueMappingTable();
        $this->createCrossSellingMappingTable();
        $this->createPaymentMappingTable();
        $this->createUnitTable();
        $this->createCategoryTable();
        $this->createCrossSellingGroupTable();
        $this->createSpecialProductAttributeTable();
    }

    private function dropMappingTable()
    {
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_product_checksum`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_link_category`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_link_detail`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_link_customer`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_link_order`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_link_note`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_link_image`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_link_product_image`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_link_manufacturer`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_link_specific`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_link_specific_value`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_link_payment`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_unit_i18n`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_unit`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_payment`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_crossselling`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_category`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_crosssellinggroup_i18n`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_crosssellinggroup`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_product_attributes`');
    }

    public function enable()
    {
        return true;
    }

    public function disable()
    {
        return true;
    }

    public function uninstall()
    {
        /** @var CachedConfigReader $configReader */
        $configReader = Shopware()->Container()->get('shopware.plugin.cached_config_reader');
        $pluginConfig = $configReader->getByPluginName('jtlconnector');

        if (!isset($pluginConfig[self::DELETE_USER_DATA]) || $pluginConfig[self::DELETE_USER_DATA] === true) {
            $this->dropMappingTable();
            Shopware()->Db()->query("DELETE FROM s_articles_details WHERE kind = ?", [ProductMapper::KIND_VALUE_PARENT]);
        }

        return true;
    }

    public function afterSaveConfigElement(Enlight_Event_EventArgs $data)
    {
        $formElement = $data->get('element');
        if ($formElement->getName() === self::DEVELOPER_LOGGING && $formElement->getForm()->getPlugin()->getName() === $this->name) {

            $value = $formElement->getValue();

            $values = $formElement->getValues();
            if (!empty($values->getValues()) && count($values->getValues()) === 1) {
                $value = $values->getValues()[0]->getValue();
            }

            $config = new Config(Path::combine(sprintf('%s/config/config.json', __DIR__)));
            $config->save(self::DEVELOPER_LOGGING, $value);
        }
    }

    protected function subscribeTranslationService()
    {
        $this->subscribeEvent(
            'Enlight_Bootstrap_InitResource_Translation',
            'overrideTranslationService'
        );
    }

    /**
     * @param Enlight_Event_EventArgs $args
     * @return string
     */
    public static function onGetControllerPathFrontend(Enlight_Event_EventArgs $args)
    {
        return dirname(__FILE__) . '/Connector.php';
    }

    /**
     * @param Enlight_Event_EventArgs $args
     * @return string
     */
    public static function onGetControllerPathBackend(Enlight_Event_EventArgs $args)
    {
        return dirname(__FILE__) . '/ConnectorBackend.php';
    }

    /**
     * @param Enlight_Event_EventArgs $args
     * @return Translation
     */
    public static function overrideTranslationService(Enlight_Event_EventArgs $args)
    {
        $container = Shopware()->Container();
        $connection = $container->get('dbal_connection');
        return new Translation($connection, $container);
    }


    private function runAutoload()
    {
        $loader = null;

        $filePath = sprintf('%s/vendor/autoload.php', __DIR__);
        if (!file_exists($filePath)) {
            throw new \Exception('Could not find vendor/autoload.php. Did you run "composer install"?');
        }

        $loader = require_once($filePath);
        if ($loader instanceof \Composer\Autoload\ClassLoader) {
            $loader->add('', CONNECTOR_DIR . '/plugins');
        }
    }

    private function createGuid()
    {
        if (function_exists('com_create_guid')) {
            return trim(com_create_guid(), '{}');
        }

        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(16384, 20479),
            mt_rand(32768, 49151),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535)
        );
    }

    private function createParentDummies()
    {
        Logger::write('create parent dummies...', Logger::INFO, 'install');

        // Dirty inject parent and insert in db work around
        $res = Shopware()->Db()->query('SELECT d.*, a.configurator_set_id
                                            FROM s_articles_details d
                                            JOIN s_articles a ON a.id = d.articleID
                                            LEFT JOIN s_articles_details dd ON d.articleID = dd.articleID AND dd.kind = ?
                                            WHERE a.configurator_set_id > 0 AND d.kind = ? AND dd.articleID IS NULL',
            [ProductMapper::KIND_VALUE_PARENT, ProductMapper::KIND_VALUE_MAIN]);

        $i = 0;
        while ($product = $res->fetch()) {
            $productSW = Shopware()->Models()->find('Shopware\Models\Article\Article', (int)$product['articleID']);
            $detailSW = Shopware()->Models()->find('Shopware\Models\Article\Detail', (int)$product['id']);

            if ($productSW === null || $detailSW === null) {
                continue;
            }

            //$detailSW->setKind(2);
            $parentDetailSW = new \Shopware\Models\Article\Detail();
            $parentDetailSW->setSupplierNumber($product['suppliernumber'])
                ->setNumber(sprintf('%s.%s', $product['ordernumber'], '0'))
                ->setActive(0)
                ->setKind(ProductMapper::KIND_VALUE_PARENT)
                ->setStockMin($product['stockmin'])
                ->setInStock($product['instock'])
                ->setReleaseDate($product['releasedate'])
                ->setEan($product['ean']);

            if (is_callable([$parentDetailSW, 'setLastStock'])) {
                $parentDetailSW->setLastStock((int)$product['laststock']);
            }

            $parentDetailSW->setArticle($productSW);

            $prices = Shopware()->Db()->fetchAssoc(
                'SELECT * FROM s_articles_prices WHERE articleID = ? AND articledetailsID = ?',
                array($productSW->getId(), $detailSW->getId())
            );

            $priceCollection = array();
            foreach ($prices as $price) {
                $customerGroupSW = CustomerGroupUtil::getByKey($price['pricegroup']);
                if ($customerGroupSW === null) {
                    continue;
                }

                $parentPriceSW = new Shopware\Models\Article\Price();
                $parentPriceSW->setArticle($productSW)
                    ->setCustomerGroup($customerGroupSW)
                    ->setFrom($price['from'])
                    ->setTo($price['to'])
                    ->setDetail($parentDetailSW)
                    ->setPrice($price['price'])
                    ->setPseudoPrice($price['pseudoprice'])
                    //->setBasePrice($price['baseprice'])
                    ->setPercent($price['percent']);

                $priceCollection[] = $parentPriceSW;
            }

            $parentDetailSW->setPrices($priceCollection);

            Shopware()->Models()->persist($parentDetailSW);
            $i++;

            if ($i % 50 == 0) {
                Shopware()->Models()->flush();
                $i = 0;
            }
        }

        Shopware()->Models()->flush();

    }

    private function fillCategoryTable()
    {
        Logger::write('fill category table...', Logger::INFO, 'install');

        // Check Mapping activation
        $shopMapper = Mmc::getMapper('Shop');

        /** @var Category $rootCategory */
        $rootCategory = ShopUtil::entityManager()->getRepository(Category::class)->findOneBy(['parentId' => null]);
        $l2cExists = false;
        if ($rootCategory instanceof Category) {
            /** @var Category $cat */
            foreach ($rootCategory->getChildren()->toArray() as $cat) {
                if (!$cat->isLeaf()) {
                    $l2cExists = true;
                    break;
                }
            }
        }

        if ($l2cExists || $shopMapper->duplicateLocalizationsExist()) {
            $this->config->save('category.mapping', false);
            return;
        } else {
            $this->config->save('category.mapping', true);
        }

        $mainShopId = (int)Shopware()->Db()->fetchOne('SELECT id FROM s_core_shops WHERE `default` = 1');
        $shopCategories = Shopware()->Db()->fetchAssoc(
            'SELECT s.id, s.category_id, l.locale
             FROM s_core_shops s
             JOIN s_categories c ON c.id = s.category_id
             JOIN s_core_locales l ON l.id = s.locale_id
             ORDER BY s.default DESC'
        );

        if (count($shopCategories) > 0) {
            $parentCategoryId = null;
            foreach ($shopCategories as $shopCategory) {
                $categoryId = (int)$shopCategory['category_id'];
                if ((int)$shopCategory['id'] == $mainShopId) {
                    $parentCategoryId = (int)$shopCategory['category_id'];

                    continue;
                }

                if ($parentCategoryId === null) {
                    continue;
                }

                $sql = '
                    INSERT INTO jtl_connector_category
                    (
                        parent_id, lang, category_id
                    )
                    VALUES (?,?,?)
                ';

                Shopware()->Db()->query($sql,
                    array($parentCategoryId, LanguageUtil::map($shopCategory['locale']), $categoryId));
            }
        }
    }

    private function fillCrossSellingGroupTable()
    {
        Logger::write('fill cross selling group table...', Logger::INFO, 'install');

        Shopware()->Db()->insert('jtl_connector_crosssellinggroup', [
            'host_id' => 0
        ]);

        Shopware()->Db()->insert('jtl_connector_crosssellinggroup_i18n', [
            'group_id' => Shopware()->Db()->lastInsertId(),
            'languageISO' => 'ger',
            'name' => jtl\Connector\Shopware\Model\CrossSellingGroup::RELATED,
            'description' => 'Zubehör Artikel'
        ]);

        Shopware()->Db()->insert('jtl_connector_crosssellinggroup', [
            'host_id' => 0
        ]);

        Shopware()->Db()->insert('jtl_connector_crosssellinggroup_i18n', [
            'group_id' => Shopware()->Db()->lastInsertId(),
            'languageISO' => 'ger',
            'name' => jtl\Connector\Shopware\Model\CrossSellingGroup::SIMILAR,
            'description' => 'Ähnliche Artikel'
        ]);
    }

    private function createUnitTable()
    {
        Logger::write('Create unit table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_unit` ( 
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, 
                `host_id` INT(10) UNSIGNED NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;
            ALTER TABLE `jtl_connector_unit` ADD INDEX( `host_id`);
        ';

        Shopware()->Db()->query($sql);

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_unit_i18n` ( 
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, 
                `unit_id` INT(10) UNSIGNED NOT NULL,
                `languageIso` varchar(255) NOT NULL,
                `name` varchar(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;
            ALTER TABLE `jtl_connector_unit_i18n`
            ADD CONSTRAINT `jtl_connector_unit_i18n_1` FOREIGN KEY (`unit_id`) REFERENCES `jtl_connector_unit` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_unit_i18n` ADD INDEX( `unit_id`, `languageIso`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createCategoryTable()
    {
        Logger::write('Create category table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_category` (
              `parent_id` int(11) unsigned NOT NULL,
              `lang` varchar(3) NOT NULL,
              `category_id` int(11) unsigned NOT NULL,
              PRIMARY KEY (`parent_id`, `lang`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_category`
            ADD CONSTRAINT `jtl_connector_category_1` FOREIGN KEY (`parent_id`) REFERENCES `s_categories` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_category`
            ADD CONSTRAINT `jtl_connector_category_2` FOREIGN KEY (`category_id`) REFERENCES `s_categories` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_category` ADD INDEX(`category_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createCrossSellingGroupTable()
    {
        Logger::write('Create cross selling group table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_crosssellinggroup` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `host_id` INT(10) UNSIGNED NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;
            ALTER TABLE `jtl_connector_crosssellinggroup` ADD INDEX( `host_id`);
        ';

        Shopware()->Db()->query($sql);

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_crosssellinggroup_i18n` (
                `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `group_id` INT(10) UNSIGNED NOT NULL,
                `languageISO` varchar(255) NOT NULL,
                `name` varchar(255) NOT NULL,
                `description` varchar(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;
            ALTER TABLE `jtl_connector_crosssellinggroup_i18n`
            ADD CONSTRAINT `jtl_connector_crosssellinggroup_i18n_1` FOREIGN KEY (`group_id`) REFERENCES `jtl_connector_crosssellinggroup` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_crosssellinggroup_i18n` ADD INDEX( `group_id`, `languageISO`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createProductChecksumTable()
    {
        Logger::write('Create product checksum table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_product_checksum` (
              `product_id` int(11) unsigned NOT NULL,
              `detail_id` int(11) unsigned NOT NULL,
              `type` tinyint unsigned NOT NULL,
              `checksum` varchar(255) NOT NULL,
              PRIMARY KEY (`product_id`,`detail_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_product_checksum`
            ADD CONSTRAINT `jtl_connector_product_checksum1` FOREIGN KEY (`product_id`) REFERENCES `s_articles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_product_checksum`
            ADD CONSTRAINT `jtl_connector_product_checksum2` FOREIGN KEY (`detail_id`) REFERENCES `s_articles_details` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
        ';

        Shopware()->Db()->query($sql);
    }

    //////////////////////
    // Linker DB Tables //
    //////////////////////
    private function createCategoryMappingTable()
    {
        Logger::write('Create category mapping table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_link_category` (
              `category_id` int(11) unsigned NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`category_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_link_category`
            ADD CONSTRAINT `jtl_connector_link_category_1` FOREIGN KEY (`category_id`) REFERENCES `s_categories` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_category` ADD INDEX(`host_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createDetailMappingTable()
    {
        Logger::write('Create detail mapping table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_link_detail` (
              `product_id` int(11) unsigned NOT NULL,
              `detail_id` int(11) unsigned NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`product_id`, `detail_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_link_detail`
            ADD CONSTRAINT `jtl_connector_link_detail_1` FOREIGN KEY (`product_id`) REFERENCES `s_articles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_detail`
            ADD CONSTRAINT `jtl_connector_link_detail_2` FOREIGN KEY (`detail_id`) REFERENCES `s_articles_details` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_detail` ADD INDEX(`host_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createCustomerMappingTable()
    {
        Logger::write('Create customer mapping table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_link_customer` (
              `customer_id` int(11) NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`customer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_link_customer`
            ADD CONSTRAINT `jtl_connector_link_customer_1` FOREIGN KEY (`customer_id`) REFERENCES `s_user` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_customer` ADD INDEX(`host_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createCustomerOrderMappingTable()
    {
        Logger::write('Create customer order mapping table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_link_order` (
              `order_id` int(11) NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_link_order`
            ADD CONSTRAINT `jtl_connector_link_order_1` FOREIGN KEY (`order_id`) REFERENCES `s_order` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_order` ADD INDEX(`host_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createDeliveryNoteMappingTable()
    {
        Logger::write('Create delivery note mapping table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_link_note` (
              `note_id` int(11) NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`note_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_link_note`
            ADD CONSTRAINT `jtl_connector_link_note_1` FOREIGN KEY (`note_id`) REFERENCES `s_order_documents` (`ID`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_note` ADD INDEX(`host_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createImageMappingTable()
    {
        Logger::write('Create image mapping table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_link_image` (
              `media_id` int(11) NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              `image_id` varchar(255) NOT NULL,
              PRIMARY KEY (`image_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_link_image`
            ADD CONSTRAINT `jtl_connector_link_image_1` FOREIGN KEY (`media_id`) REFERENCES `s_media` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_image` ADD INDEX(`host_id`);
            ALTER TABLE `jtl_connector_link_image` ADD INDEX(`host_id`, `image_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createProductImageMappingTable()
    {
        Logger::write('Create product image mapping table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_link_product_image` (
              `id` int(11) NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              `image_id` varchar(255) NOT NULL,
              `media_id` INT(10) UNSIGNED NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_link_product_image`
            ADD CONSTRAINT `jtl_connector_link_product_image_1` FOREIGN KEY (`id`) REFERENCES `s_articles_img` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_product_image` ADD INDEX(`host_id`);
            ALTER TABLE `jtl_connector_link_product_image` ADD INDEX `id_media_id` (`id`, `media_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createManufacturerMappingTable()
    {
        Logger::write('Create manufacturer mapping table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_link_manufacturer` (
              `manufacturer_id` int(11) NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`manufacturer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_link_manufacturer`
            ADD CONSTRAINT `jtl_connector_link_manufacturer_1` FOREIGN KEY (`manufacturer_id`) REFERENCES `s_articles_supplier` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_manufacturer` ADD INDEX(`host_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createSpecificMappingTable()
    {
        Logger::write('Create specific mapping table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_link_specific` (
              `specific_id` int(11) NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`specific_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_link_specific`
            ADD CONSTRAINT `jtl_connector_link_specific_1` FOREIGN KEY (`specific_id`) REFERENCES `s_filter_options` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_specific` ADD INDEX(`host_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createSpecificValueMappingTable()
    {
        Logger::write('Create specific value mapping table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_link_specific_value` (
              `specific_value_id` int(11) NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`specific_value_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_link_specific_value`
            ADD CONSTRAINT `jtl_connector_link_specific_value_1` FOREIGN KEY (`specific_value_id`) REFERENCES `s_filter_values` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_specific_value` ADD INDEX(`host_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createPaymentMappingTable()
    {
        Logger::write('Create payment mapping table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_link_payment` (
              `order_id` int(11) NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_link_payment`
            ADD CONSTRAINT `jtl_connector_link_payment_1` FOREIGN KEY (`order_id`) REFERENCES `s_order` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_payment` ADD INDEX(`host_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createPaymentTable()
    {
        Logger::write('Create payment table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_payment` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `customerOrderId` int(11) NOT NULL,
              `billingInfo` varchar(255) NULL,
              `creationDate` datetime NOT NULL,
              `paymentModuleCode` varchar(255) NOT NULL,
              `totalSum` double NOT NULL,
              `transactionId` varchar(255) NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_payment` ADD INDEX(`customerOrderId`);
            ALTER TABLE `jtl_connector_payment`
            ADD CONSTRAINT `jtl_connector_payment_1` FOREIGN KEY (`customerOrderId`) REFERENCES `s_order` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
        ';

        Shopware()->Db()->query($sql);
    }

    private function createCrossSellingMappingTable()
    {
        Logger::write('Create cross selling mapping table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_crossselling` (
              `product_id` int(11) unsigned NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_crossselling`
            ADD CONSTRAINT `jtl_connector_crossselling_1` FOREIGN KEY (`product_id`) REFERENCES `s_articles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_crossselling` ADD INDEX(`host_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createSpecialProductAttributeTable()
    {
        Logger::write('Create special product attribute table...', Logger::INFO, 'install');

        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_product_attributes` (
              `product_id` int(11) unsigned NOT NULL,
              `key` varchar(255) NOT NULL,
              `value` varchar(255) NOT NULL,
              PRIMARY KEY (`product_id`, `key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_product_attributes`
            ADD CONSTRAINT `jtl_connector_product_attributes_1` FOREIGN KEY (`product_id`) REFERENCES `s_articles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
        ';

        Shopware()->Db()->query($sql);
    }

    private function migratePaymentLinkTable()
    {
        $db = Shopware()->Db();

        $existingColumnsInfo = $db->query('SHOW COLUMNS FROM `jtl_connector_link_payment`;')->fetchAll();
        $existingColumns = array_map(function ($data) {
            return $data['Field'];
        }, $existingColumnsInfo);

        if (!in_array('order_id', $existingColumns)) {
            $db->query('ALTER TABLE `jtl_connector_link_payment` ADD COLUMN `order_id` INT(11) DEFAULT NULL;');
        }

        if (in_array('payment_id', $existingColumns)) {
            $db->query('DELETE `jclp` 
                FROM `jtl_connector_link_payment` `jclp`
                LEFT JOIN `jtl_connector_payment` `jcp` ON `jcp`.`id` = `jclp`.`payment_id`
                LEFT JOIN `s_order` `so` ON `so`.`id` = `jcp`.`customerOrderId` 
                WHERE `so`.id IS NULL OR `jcp`.id IS NULL');

            $migratedOrdersCount = $db->fetchOne('SELECT COUNT(order_id) FROM `jtl_connector_link_payment` WHERE order_id IS NOT NULL');

            $limit = 15000;
            $i = ceil($migratedOrdersCount / $limit);
            do {
                $offset = $i * $limit;
                $sql = sprintf(
                    'UPDATE `jtl_connector_link_payment`
                     JOIN 
                     (
                         (SELECT `id`, `customerOrderId` FROM `jtl_connector_payment` LIMIT %s OFFSET %s) `jcp`
                     ) ON `payment_id` = `jcp`.`id`
                     SET `order_id` = `jcp`.`customerOrderId`
                     WHERE `order_id` IS NULL', $limit, $offset);
                $i++;
            } while ($db->exec($sql) > 0);

            $db->query('SET FOREIGN_KEY_CHECKS=0;');
            $db->query('ALTER TABLE `jtl_connector_link_payment` DROP FOREIGN KEY `jtl_connector_link_payment_1`;');
            $db->query('ALTER TABLE `jtl_connector_link_payment` DROP COLUMN `payment_id`;');
            $db->query('ALTER TABLE `jtl_connector_link_payment` ADD PRIMARY KEY (`order_id`)');
            $db->query('ALTER TABLE `jtl_connector_link_payment` ADD CONSTRAINT `jtl_connector_link_payment_1` FOREIGN KEY (`order_id`) REFERENCES `s_order` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION');
            $db->query('DROP TRIGGER IF EXISTS `jtl_connector_payment`;');
            $db->query('DROP TABLE IF EXISTS `jtl_connector_payment`');
            $db->query('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
}
