<?php

use Doctrine\ORM\Mapping as ORM;
use Shopware\Components\CSRFWhitelistAware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * @ORM\Embedded
 */
class Shopware_Controllers_Backend_Jtlconnector extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    public const
        STATUS_OK = 'OK';

    /**
     * @return string[]
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'checkLogs',
            'downloadLogs',
            'deleteLogs',
        ];
    }

    /**
     *
     */
    public function preDispatch()
    {
        if (!defined('CONNECTOR_DIR')) {
            define('CONNECTOR_DIR', __DIR__);
        }

        if (in_array($this->Request()->getActionName(), ['deleteLogs', 'checkLogs'])) {
            Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();
        }
    }

    /**
     *
     */
    public function deleteLogsAction(): void
    {
        foreach ($this->getLogFiles() as $logFile) {
            unlink($logFile);
        }
        $this->sendJsonResponse(self::STATUS_OK, ['message' => 'Log files have been deleted.']);
    }

    /**
     * @throws Exception
     */
    public function checkLogsAction(): void
    {
        $logFilesAvailable = count($this->getLogFiles());
        if ($logFilesAvailable === 0) {
            $this->throwNoLogFilesException();
        }
        $this->sendJsonResponse(self::STATUS_OK);
    }

    /**
     * @throws Exception
     */
    public function downloadLogsAction(): void
    {
        $zipFilepath = $this->getZipFilepath();

        $this->createZipFile($zipFilepath);

        $response = $this->createDownloadResponse($zipFilepath);
        $response->headers->set('Content-Type', 'application/zip');
        $response->send();
    }

    /**
     * @return array
     */
    protected function getLogFiles(): array
    {
        return glob(sprintf('%s*.log', $this->getLogDir()));
    }

    /**
     * @return string
     */
    protected function getZipFilepath(): string
    {
        return sys_get_temp_dir() . '/shopware5-connector-logs.zip';
    }

    /**
     * @param string $zipFilepath
     * @throws Exception
     */
    protected function createZipFile(string $zipFilepath): void
    {
        $zip = new ZipArchive();
        $logDirectory = $this->getLogDir();

        if ($result = $zip->open($zipFilepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception(printf('Failed with code %d', $result));
        } else {
            $zip->addGlob(sprintf('%s*.{log}', $logDirectory), GLOB_BRACE, ['remove_all_path' => true]);
            if ($zip->count() === 0) {
                $this->throwNoLogFilesException();
            }
            $zip->close();
        }
    }

    /**
     * @return string
     */
    protected function getLogDir(): string
    {
        return sprintf('%s/logs/', CONNECTOR_DIR);
    }

    /**
     * @throws Exception
     */
    protected function throwNoLogFilesException(): void
    {
        throw new Exception(sprintf('There are no log files in %s directory. Cannot create zip archive.', $this->getLogDir()));
    }

    /**
     * @param string $status
     * @param array $data
     */
    protected function sendJsonResponse(string $status, array $data = []): void
    {
        (new JsonResponse(['status' => $status, 'data' => $data]))->send();
    }

    /**
     * @param string $tmpFile
     * @return Response
     */
    protected function createDownloadResponse(string $tmpFile): Response
    {
        $file = new File($tmpFile);

        $response = new BinaryFileResponse($file);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        return $response;
    }
}
