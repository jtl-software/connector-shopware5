<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @copyright 2010-2013 JTL-Software GmbH
 */

require_once(__DIR__ . '/../vendor/autoload.php');

use \jtl\Connector\Application\Application;
use \jtl\Connector\Core\Rpc\RequestPacket;
use \jtl\Connector\Core\Rpc\ResponsePacket;
use \jtl\Connector\Core\Rpc\Error;
use \jtl\Connector\Core\Http\Response;
use \jtl\Connector\Shopware\Connector;
use \jtl\Connector\Core\Logger\Logger;

error_reporting(E_ALL);
ini_set('display_errors', 1);

function exception_handler(\Exception $exception)
{
    $trace = $exception->getTrace();
    if (isset($trace[0]['args'][0])) {
        $requestpacket = $trace[0]['args'][0];
    }
    
    $error = new Error();
    $error->setCode($exception->getCode())
        ->setData("Exception: " . substr(strrchr(get_class($exception), "\\"), 1) . " - File: {$exception->getFile()} - Line: {$exception->getLine()}")
        ->setMessage($exception->getMessage());

    Logger::write($error->getData(), Logger::ERROR, 'global');

    $responsepacket = new ResponsePacket();
    $responsepacket->setError($error)
        ->setId('unknown')
        ->setJtlrpc('2.0');

    if (isset($requestpacket) && $requestpacket !== null && is_object($requestpacket) && $requestpacket instanceof RequestPacket) {
        $responsepacket->setId($requestpacket->getId());
    }
    
    Response::send($responsepacket);
}

function error_handler($errno, $errstr, $errfile, $errline, $errcontext)
{
    $types = array(
        E_ERROR => array(Logger::ERROR, 'E_ERROR'),
        E_WARNING => array(Logger::WARNING, 'E_WARNING'),
        E_PARSE => array(Logger::WARNING, 'E_PARSE'),
        E_NOTICE => array(Logger::NOTICE, 'E_NOTICE'),
        E_CORE_ERROR => array(Logger::ERROR, 'E_CORE_ERROR'),
        E_CORE_WARNING => array(Logger::WARNING, 'E_CORE_WARNING'),
        E_CORE_ERROR => array(Logger::ERROR, 'E_COMPILE_ERROR'),
        E_CORE_WARNING => array(Logger::WARNING, 'E_COMPILE_WARNING'),
        E_USER_ERROR => array(Logger::ERROR, 'E_USER_ERROR'),
        E_USER_WARNING => array(Logger::WARNING, 'E_USER_WARNING'),
        E_USER_NOTICE => array(Logger::NOTICE, 'E_USER_NOTICE'),
        E_STRICT => array(Logger::NOTICE, 'E_STRICT'),
        E_RECOVERABLE_ERROR => array(Logger::ERROR, 'E_RECOVERABLE_ERROR'),
        E_DEPRECATED => array(Logger::INFO, 'E_DEPRECATED'),
        E_USER_DEPRECATED => array(Logger::INFO, 'E_USER_DEPRECATED')
    );

    if (isset($types[$errno])) {
        $err = "(" . $types[$errno][1] . ") File ({$errfile}, {$errline}): {$errstr}";
        Logger::write($err, $types[$errno][0], 'global');
    } else {
        Logger::write("File ({$errfile}, {$errline}): {$errstr}", Logger::ERROR, 'global');
    }
}

function shutdown_handler()
{
    if (($err = error_get_last())) {
        ob_clean();

        $error = new Error();
        $error->setCode($err['type'])
            ->setData('Shutdown! File: ' . $err['file'] . ' - Line: ' . $err['line'])
            ->setMessage($err['message']);

        $reponsepacket = new ResponsePacket();
        $reponsepacket->setError($error)
            ->setId('unknown')
            ->setJtlrpc('2.0');
    
        Response::send($reponsepacket);
    }
}

set_error_handler('error_handler', E_ALL);
set_exception_handler('exception_handler');
register_shutdown_function('shutdown_handler');

// Connector instance
$connector = Connector::getInstance();
$application = Application::getInstance();
$application->register($connector);
$application->run();
