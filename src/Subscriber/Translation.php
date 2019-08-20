<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2019 JTL-Software GmbH
 *
 * Created at 13.08.2019 10:18
 */
namespace jtl\Connector\Shopware\Subscriber;

use Enlight\Event\SubscriberInterface;


class Translation implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return ['Enlight_Bootstrap_InitResource_Translation' => 'overwriteTranslation'];
    }

    public function overwriteTranslation()
    {
        $container = Shopware()->Container();
        $connection = $container->get('dbal_connection');

        return new \jtl\Connector\Shopware\Service\Translation($connection, $container);
    }
}
