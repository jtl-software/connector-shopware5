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
        define('CONNECTOR_DIR', __DIR__);
    
        // Tmp directory fallback
        $dir = sys_get_temp_dir();
        if (!is_writeable($dir)) {
            $dir = CONNECTOR_DIR . DIRECTORY_SEPARATOR . 'tmp';
        }
        
        $application = null;

        try {
            if (file_exists(CONNECTOR_DIR . '/connector.phar')) {
                if (is_writable($dir)) {
                    include_once('phar://' . CONNECTOR_DIR . '/connector.phar/src/bootstrap.php');
                } else {
                    echo sprintf('Directory %s is not writeable. Please contact your administrator or hoster.', $dir);
                }
            } else {
                include_once(CONNECTOR_DIR . '/src/bootstrap.php');
            }
        } catch (\Exception $e) {
            if (is_object($application)) {
                $handler = $application->getErrorHandler()->getExceptionHandler();
                $handler($e);
            }
        }
    }
}
