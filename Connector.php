<?php

use Shopware\Components\CSRFWhitelistAware;

class Shopware_Controllers_Frontend_Jtlconnector extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    public function getWhitelistedCSRFActions()
    {
        return [
            'index'
        ];
    }

    public function preDispatch()
    {
        if (in_array($this->Request()->getActionName(), array('index'))) {
            Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();
        }
    }

    public function indexAction()
    {
        session_destroy();
        if (!defined('CONNECTOR_DIR')) {
            define('CONNECTOR_DIR', __DIR__);
        }

        $bootstrapFile = sprintf('%s/src/bootstrap.php', CONNECTOR_DIR);
        if (!file_exists($bootstrapFile)) {
            throw new \Exception('Could not find src/bootstrap.php. Something is very wrong!');
        }

        $application = null;
        try {
            require_once $bootstrapFile;
        } catch (\Exception $e) {
            if (is_object($application)) {
                $handler = $application->getErrorHandler()->getExceptionHandler();
                $handler($e);
            }
        }
    }
}
