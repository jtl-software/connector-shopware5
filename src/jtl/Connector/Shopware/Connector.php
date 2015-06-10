<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Shopware
 */
namespace jtl\Connector\Shopware;

use \jtl\Connector\Core\Config\Config;
use \jtl\Connector\Core\Config\Loader\Json as ConfigJson;
use \jtl\Connector\Core\Config\Loader\System as ConfigSystem;
use \jtl\Connector\Core\Rpc\RequestPacket;
use \jtl\Connector\Base\Connector as BaseConnector;
use \jtl\Connector\Core\Utilities\RpcMethod;
use \jtl\Connector\Core\Rpc\Method;
use \jtl\Connector\Core\Controller\Controller as CoreController;
use \jtl\Connector\Result\Action;
use \jtl\Connector\Core\Rpc\Error;
use \jtl\Connector\Shopware\Mapper\PrimaryKeyMapper;
use \jtl\Connector\Shopware\Authentication\TokenLoader;
use \jtl\Connector\Shopware\Checksum\ChecksumLoader;
use \jtl\Connector\Session\SessionHelper;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use \jtl\Connector\Core\Logger\Logger;
use \jtl\Connector\Formatter\ExceptionFormatter;
use \jtl\Connector\Shopware\Utilities\Mmc;

/**
 * Shopware Connector
 *
 * @access public
 * @author Daniel Böhmer <daniel.boehmer@jtl-software.com
 */
class Connector extends BaseConnector
{
    /**
     * Current Controller
     *
     * @var \jtl\Connector\Core\Controller\Controller
     */
    protected $controller;
    
    /**
     * @var string
     */
    protected $action;

    public function __construct()
    {
        $this->useSuperGlobals = false;
    }

    public function initialize()
    {
        $this->setModelNamespace('jtl\Connector\Shopware\Model')
            ->setPrimaryKeyMapper(new PrimaryKeyMapper())
            ->setTokenLoader(new TokenLoader())
            ->setChecksumLoader(new ChecksumLoader());

        // Doctrine register entity
        $config = Shopware()->Models()->getConfiguration();
        $driverChain = $config->getMetadataDriverImpl();

        $annotationDriver = new AnnotationDriver(
            $config->getAnnotationsReader(),
            array(
                $config->getAttributeDir()
            )
        );

        $driverChain->addDriver($annotationDriver, 'jtl\\Connector\\Shopware\\Model\\');
        $driverChain->addDriver($annotationDriver, 'jtl\\Connector\\Shopware\\Model\\Linker\\');
        $config->setMetadataDriverImpl($driverChain);

        $s = new SessionHelper('shopware');

        $config = null;
        if (isset($s->config)) {
            $config = $s->config;
        }
                
        if (empty($config)) {
            if (!is_null($this->config)) {
                $config = $this->getConfig();
            }

            if (empty($config)) {
                // Application object is not initialized. Bypass by manually creating
                // the Config object
                $json = new ConfigJson(CONNECTOR_DIR . '/config/config.json');
                $config = new Config(array(
                    $json,
                    new ConfigSystem()
                ));

                $this->setConfig($config);
            }
        }

        if (!isset($s->config)) {
            $s->config = $config;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \jtl\Connector\Application\IEndpointConnector::canHandle()
     */
    public function canHandle()
    {
        $controller = RpcMethod::buildController($this->getMethod()->getController());
        
        $class = "\\jtl\\Connector\\Shopware\\Controller\\{$controller}";
        if (class_exists($class)) {
            $this->controller = $class::getInstance();
            $this->action = RpcMethod::buildAction($this->getMethod()->getAction());

            return is_callable(array($this->controller, $this->action));
        }

        return false;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \jtl\Connector\Application\IEndpointConnector::handle()
     */
    public function handle(RequestPacket $requestpacket)
    {
        $config = $this->getConfig();
        
        // Set the config to our controller
        $this->controller->setConfig($config);

        // Set the method to our controller
        $this->controller->setMethod($this->getMethod());

        if ($this->action === Method::ACTION_PUSH || $this->action === Method::ACTION_DELETE) {
            if ($this->action === Method::ACTION_PUSH && $this->getMethod()->getController() === 'image') {
                return $this->controller->{$this->action}($requestpacket->getParams());
            }

            // Product Price work around
            if ($this->getMethod()->getController() === 'product_price') {
                $action = new Action();
                $action->setHandled(true);
                
                try {
                    $mapper = Mmc::getMapper('ProductPrice');
                    $res = $mapper->save($requestpacket->getParams());

                    $action->setResult($res);
                } catch (\Exception $exc) {
                    Logger::write(ExceptionFormatter::format($exc), Logger::WARNING, 'controller');

                    $err = new Error();
                    $err->setCode($exc->getCode());
                    $err->setMessage($exc->getMessage());
                    $action->setError($err);
                }

                return $action;
            }

            if (!is_array($requestpacket->getParams())) {
                throw new \Exception('Param must be an array');
            }

            $action = new Action();
            $results = array();
            $errors = array();
            $entities = $requestpacket->getParams();
            foreach ($entities as $entity) {
                $result = $this->controller->{$this->action}($entity);

                if ($result->getResult() !== null) {
                    $results[] = $result->getResult();
                }

                $action->setHandled(true)
                    ->setResult($results)
                    ->setError($result->getError());    // Todo: refactor to array of errors
            }

            return $action;
        } else {
            return $this->controller->{$this->action}($requestpacket->getParams());
        }
    }
    
    /**
     * Getter Controller
     *
     * @return \jtl\Connector\Core\Controller\Controller
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * Setter Controller
     *
     * @param \jtl\Connector\Core\Controller\Controller $controller
     */
    public function setController(CoreController $controller)
    {
        $this->controller = $controller;
        return $this;
    }

    /**
     * Getter Action
     *
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Setter Action
     *
     * @param string $action
     */
    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }
}
