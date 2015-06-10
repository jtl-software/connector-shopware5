<?php
class Shopware_Plugins_Frontend_jtlconnector_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
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
        return 'JTL Shopware Connector';
    }
 
    public function getVersion()
    {
        return '1.0.3';
    }
 
    public function getInfo()
    {
        return array(
            'version' => $this->getVersion(),
            'label' => $this->getLabel(),
            'author' => 'JTL-Software GmbH',
            'description' => '',
            'support' => 'JTL-Software Forum',
            'link' => 'http://forum.jtl-software.de'
        );
    }
 
    public function install()
    {
        if (!$this->assertVersionGreaterThen('4.2.3')) {
            return array(
                'success' => false,
                'message' => 'Das Plugin benötigt mindestens die Shopware Version 4.2.3'
            );
        }

        // PHP
        if (!version_compare(PHP_VERSION, '5.4', '>=')) {
            return array(
                'success' => false,
                'message' => 'Das Plugin benötigt mindestens die PHP Version 5.4, ' . PHP_VERSION . ' ist installiert.'
            );
        }

        // Sqlite 3
        if (!extension_loaded('sqlite3') || !class_exists('Sqlite3')) {
            return array(
                'success' => false,
                'message' => 'Das Plugin benötigt die sqlite3 PHP extension.'
            );
        }

        ini_set('max_execution_time', 0);

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_Jtlconnector',
            'onGetControllerPathFrontend'
        );

        $form = $this->Form();
 
        $form->setElement('text', 'auth_token',
            array(
                'label' => 'Passwort',
                'required' => true,
                'value' => $this->createGuid()
            )
        );

        $res = Shopware()->Db()->query('SELECT * FROM s_core_shops WHERE `default` = 1 AND active = 1');
        $shop = $res->fetch();
        $url = 'Hauptshop nicht gefunden';
        if (is_array($shop) && isset($shop['id'])) {
            $proto = (bool) $shop['always_secure'] ? 'https' : 'http';
            $url = sprintf('%s://%s%s/%s', $proto, $shop['host'], $shop['base_path'], 'jtlconnector/');
        }

        $form->setElement('text', 'connector_url',
            array(
                'label' => 'Connector Url (Info! Bitte nicht bearbeiten)',
                'required' => true,
                'value' => $url
            )
        );

        $this->createProductChecksumTable();
        $this->createCategoryLevelTable();
        $this->createMappingTables();
        $this->fillCategoryLevelTable();

        return array(
            'success' => true,
            'invalidateCache' => array('backend', 'proxy')
        );
    }

    public function update($oldVersion)
    {
        ini_set('max_execution_time', 0);

        switch ($oldVersion) {
            case '1.0.0':
                Shopware()->Db()->query("UPDATE s_articles_details SET ordernumber = REPLACE(ordernumber, '.0', '.jtlcon.0') WHERE ordernumber LIKE '%.0' AND kind = 0");
                $this->createPaymentTable();
                $this->createPaymentMappingTable();
                break;
            case '1.0.1':
                Shopware()->Db()->query("UPDATE s_articles_details SET active = 0 WHERE ordernumber LIKE '%.jtlcon.0'");
                break;
            case '1.0.2':
                break;
            default:
                return false;
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
        $this->createPaymentTable();
        $this->createPaymentMappingTable();
        $this->createUnitTable();
    }

    private function dropMappingTable()
    {
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_product_checksum`');
        Shopware()->Db()->query('DROP TABLE IF EXISTS `jtl_connector_category_level`');
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
        $this->dropMappingTable();
        Shopware()->Db()->query("DELETE FROM s_articles_details WHERE ordernumber LIKE '%.jtlcon.0'");

        return true;
    }

    public static function onGetControllerPathFrontend(Enlight_Event_EventArgs $args)
    {
        return dirname(__FILE__) . '/Connector.php';
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
        // Dirty inject parent and insert in db work around
        $res = Shopware()->Db()->query('SELECT d.*, a.configurator_set_id
                                            FROM s_articles_details d
                                            JOIN s_articles a ON a.id = d.articleID');

        $i = 0;
        while ($product = $res->fetch()) {
            if ((int) $product['kind'] == 1 && (int) $product['configurator_set_id'] > 0) {
                $productSW = Shopware()->Models()->find('Shopware\Models\Article\Article', (int) $product['articleID']);
                $detailSW = Shopware()->Models()->find('Shopware\Models\Article\Detail', (int) $product['id']);

                //$detailSW->setKind(2);

                $parentDetailSW = new \Shopware\Models\Article\Detail();
                $parentDetailSW->setSupplierNumber($product['suppliernumber'])
                    ->setNumber(sprintf('%s.%s', $product['ordernumber'], 'jtlcon.0'))
                    ->setActive(0)
                    ->setKind(0)
                    ->setStockMin($product['stockmin'])
                    ->setInStock($product['instock'])
                    ->setReleaseDate($product['releasedate'])
                    ->setEan($product['ean']);

                $parentDetailSW->setArticle($productSW);

                $priceCollection = array();
                foreach ($detailSW->getPrices() as $priceSW) {
                    $parentPriceSW = new Shopware\Models\Article\Price();
                    $parentPriceSW->setArticle($productSW)
                        ->setCustomerGroup($priceSW->getCustomerGroup())
                        ->setFrom($priceSW->getFrom())
                        ->setTo($priceSW->getTo())
                        ->setDetail($parentDetailSW)
                        ->setPrice($priceSW->getPrice())
                        ->setPseudoPrice($priceSW->getPseudoPrice())
                        ->setBasePrice($priceSW->getBasePrice())
                        ->setPercent($priceSW->getPercent());

                    $priceCollection[] = $parentPriceSW;
                }

                $parentDetailSW->setPrices($priceCollection);

                Shopware()->Models()->persist($parentDetailSW);
                //Shopware()->Models()->persist($detailSW);
                $i++;

                if ($i % 50 == 0) {
                    Shopware()->Models()->flush();
                    $i = 0;
                }
            }
        }

        Shopware()->Models()->flush();
    }

    private function fillCategoryLevelTable(array $parentIds = null, $level = 0)
    {
        $where = 'WHERE parent IS NULL';
        if ($parentIds === null) {
            $parentIds = array();
            Shopware()->Db()->query('TRUNCATE TABLE jtl_connector_category_level');
        } else {
            $where = 'WHERE parent IN (' . implode(',', $parentIds) . ')';
            $parentIds = array();
        }

        $categories = Shopware()->Db()->fetchAssoc('SELECT id FROM s_categories ' . $where);

        if (count($categories) > 0) {
            foreach ($categories as $category) {
                $parentIds[] = (int) $category['id'];

                $sql = '
                    INSERT IGNORE INTO jtl_connector_category_level
                    (
                        category_id, level
                    )
                    VALUES (?,?)
                ';

                Shopware()->Db()->query($sql, array((int) $category['id'], $level));
            }

            $this->fillCategoryLevelTable($parentIds, $level + 1);
        }
    }

    private function createUnitTable()
    {
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

    private function createProductChecksumTable()
    {
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

    private function createCategoryLevelTable()
    {
        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_category_level` (
              `category_id` int(11) unsigned NOT NULL,
              `level` int(10) unsigned NOT NULL,
              PRIMARY KEY (`category_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_category_level`
            ADD CONSTRAINT `jtl_connector_category_level` FOREIGN KEY (`category_id`) REFERENCES `s_categories` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
        ';

        Shopware()->Db()->query($sql);
    }

    //////////////////////
    // Linker DB Tables //
    //////////////////////
    private function createCategoryMappingTable()
    {
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
        ';

        Shopware()->Db()->query($sql);
    }

    private function createProductImageMappingTable()
    {
        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_link_product_image` (
              `id` int(11) NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              `image_id` varchar(255) NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_link_product_image`
            ADD CONSTRAINT `jtl_connector_link_product_image_1` FOREIGN KEY (`id`) REFERENCES `s_articles_img` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_product_image` ADD INDEX(`host_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createManufacturerMappingTable()
    {
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
        $sql = '
            CREATE TABLE IF NOT EXISTS `jtl_connector_link_payment` (
              `payment_id` int(11) unsigned NOT NULL,
              `host_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`payment_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            ALTER TABLE `jtl_connector_link_payment`
            ADD CONSTRAINT `jtl_connector_link_payment_1` FOREIGN KEY (`payment_id`) REFERENCES `jtl_connector_payment` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
            ALTER TABLE `jtl_connector_link_payment` ADD INDEX(`host_id`);
        ';

        Shopware()->Db()->query($sql);
    }

    private function createPaymentTable()
    {
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
}
