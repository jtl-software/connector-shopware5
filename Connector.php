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
                include_once('phar://' . CONNECTOR_DIR . '/connector.phar/src/bootstrap.php');
            } else {
                include_once(CONNECTOR_DIR . '/src/bootstrap.php');
            }
        } catch (\Exception $exc) {
            exception_handler($exc);
        }
    }
}
