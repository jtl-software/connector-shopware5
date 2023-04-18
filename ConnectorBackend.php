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

        if (in_array($this->Request()->getActionName(), ['deleteLogs', 'checkLogs', 'downloadLogs'])) {
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
        $this->sendJsonResponse(['message' => 'Log files have been deleted.'], 200);
    }

    /**
     * @throws Exception
     */
    public function checkLogsAction(): void
    {
        $message = [];
        $status  = 200;

        if ($this->hasLogFiles() === false) {
            $status             = 404;
            $message['message'] = 'No log files found.';
        }

        $this->sendJsonResponse($message, $status);
    }

    /**
     * @throws Exception
     */
    public function downloadLogsAction(): void
    {
        $zipFilepath       = tempnam(sys_get_temp_dir(), 'jtl-connector-logs');
        $noLogFilesMessage = sprintf(
            'There are no log files in %s. Cannot create zip archive.',
            $this->getLogDir()
        );
        $response          = new Response($noLogFilesMessage, 404);

        if ($this->hasLogFiles()) {
            $this->compressLogFiles($zipFilepath);
            $response = $this->createDownloadResponse($zipFilepath)->setStatusCode(200);
            $response->headers->set('Content-Type', 'application/zip');
        }

        $response->send();

        if (is_file($zipFilepath)) {
            unlink($zipFilepath);
        }
    }

    /**
     * @param string $zipFilepath
     * @throws Exception
     */
    protected function compressLogFiles(string $zipFilepath): void
    {
        $logDirectory = $this->getLogDir();

        $zip = new ZipArchive();
        $zip->open($zipFilepath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addGlob(sprintf('%s*.log', $logDirectory), 0, ['remove_all_path' => true]);
        $zip->close();
    }

    /**
     * @return array
     */
    protected function getLogFiles(): array
    {
        return glob(sprintf('%s*.log', $this->getLogDir()));
    }

    /**
     * @return bool
     */
    protected function hasLogFiles(): bool
    {
        return count($this->getLogFiles()) > 0;
    }

    /**
     * @return string
     */
    protected function getLogDir(): string
    {
        return sprintf('%s/logs/', CONNECTOR_DIR);
    }

    /**
     * @param array $data
     * @param int $status
     */
    protected function sendJsonResponse(array $data = [], int $status = 200): void
    {
        (new JsonResponse($data, $status))->send();
    }

    /**
     * @param string $tmpFile
     * @return Response
     */
    protected function createDownloadResponse(string $tmpFile): Response
    {
        return (new BinaryFileResponse(new File($tmpFile)))
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'shopware5-connector-logs.zip');
    }
}
