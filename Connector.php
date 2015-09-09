<?php
class Shopware_Controllers_Frontend_Jtlconnector extends Enlight_Controller_Action
{
    public function preDispatch()
    {
        if (in_array($this->Request()->getActionName(), array('index'))) {
            Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();
        }
    }

    public function indexAction()
    {
        session_destroy();
        define('CONNECTOR_DIR', __DIR__);

        try {
            if (file_exists(CONNECTOR_DIR . '/connector.phar')) {
                if (is_writable(sys_get_temp_dir())) {
                    include_once('phar://' . CONNECTOR_DIR . '/connector.phar/src/bootstrap.php');
                } else {
                    echo sprintf('Directory %s is not writeable. Please contact your administrator or hoster.', sys_get_temp_dir());
                }
            } else {
                include_once(CONNECTOR_DIR . '/src/bootstrap.php');
            }
        } catch (\Exception $e) {

        }
    }
}
