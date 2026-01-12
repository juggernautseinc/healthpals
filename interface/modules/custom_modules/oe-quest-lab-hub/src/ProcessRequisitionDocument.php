<?php

/*
 *   package   OpenEMR
 *   link      http://www.open-emr.org
 *  author    Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c)
 *  All rights reserved
 *
 */

namespace Juggernaut\Quest\Module;

use GuzzleHttp\Client;
use OpenEMR\Common\Http\oeHttpRequest;
use OpenEMR\Common\Logging\SystemLogger;
use Juggernaut\Quest\Module\Exceptions\QuestHttpException;
use Juggernaut\Quest\Module\Exceptions\QuestFileSystemException;

class ProcessRequisitionDocument
{
    private mixed $orderHl7;
    private string $reqName;
    private string $path;
    private oeHttpRequest $httpClient;
    private SystemLogger $logger;
    private string $pdfBinary = '';

    public function __construct($orderHl7)
    {
        $this->orderHl7 = base64_encode($orderHl7);
        $this->httpClient = new oeHttpRequest(new Client());
        $this->logger = new SystemLogger();
    }

    private function buildRequest(): bool|string
    {
        $request = json_encode([
            "documentTypes" => [
                "ABN", "REQ", "AOE"
            ],
            "orderHl7" => $this->orderHl7
        ]);
        $this->logger->debug("Requisition request payload completed");
        return $request;
    }

    public function sendRequest(): string
    {
        try {
            $token = new QuestToken();
            $baseUrl = $token->operationMode();
            $this->logger->debug("Requisition document request initiated", ['mode' => $baseUrl]);

            $tokenResponse = json_decode($token->getFreshToken(), true);
            if (!isset($tokenResponse['access_token'])) {
                throw new QuestHttpException(
                    'Failed to extract access token from OAuth2 response',
                    0,
                    400,
                    json_encode($tokenResponse)
                );
            }

            $accessToken = $tokenResponse['access_token'];
            $requestPayload = $this->buildRequest();

            // Make HTTP request using oeHttpRequest
            $response = $this->httpClient
                ->usingBaseUri($baseUrl)
                ->usingHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->asJson()
                ->send('POST', '/hub-resource-server/oauth2/order/document', [
                    'body' => $requestPayload,
                ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->logger->error(
                    'Requisition document request failed',
                    ['status_code' => $statusCode]
                );
                throw new QuestHttpException(
                    'Requisition document request failed',
                    0,
                    $statusCode,
                    $response->getBody()
                );
            }

            $this->logger->debug('Requisition document returned successfully', ['status_code' => $statusCode]);

            // Ensure directories exist
            $this->ensureDirectories();

            // Parse response
            $responsePdf = json_decode($response->getBody(), true);
            if (!is_array($responsePdf)) {
                throw new QuestHttpException(
                    'Invalid JSON response from Quest',
                    0,
                    400,
                    $response->getBody()
                );
            }

            // Save raw response for debugging
            $rawJsonPath = $GLOBALS['OE_SITE_DIR'] . '/documents/labs/requisitionRaw.json';
            file_put_contents($rawJsonPath, json_encode($responsePdf, JSON_PRETTY_PRINT));

            // Extract PDF data
            $returnedPdfDocument = $responsePdf['orderSupportDocuments'][0]['documentData'] ?? '';
            if (empty($returnedPdfDocument)) {
                throw new QuestHttpException(
                    'No PDF document data in response',
                    0,
                    400,
                    json_encode($responsePdf)
                );
            }

            // Decode PDF - Quest returns double base64 encoded data
            // First decode: converts from JSON base64 to intermediate string
            $pdfDecoded = base64_decode($returnedPdfDocument, true);
            if ($pdfDecoded === false) {
                throw new QuestHttpException(
                    'Failed to decode base64 PDF data',
                    0,
                    400
                );
            }

            // Second decode: converts from intermediate base64 to actual PDF binary
            $pdfBinary = base64_decode($pdfDecoded, true);
            if ($pdfBinary === false) {
                throw new QuestHttpException(
                    'Failed to decode second-level base64 PDF data',
                    0,
                    400
                );
            }

            $this->path = Bootstrap::requisitionFormPath();
            $this->reqName = 'labRequisition-' . time() . '.pdf';
            $pdfPath = $this->path . $this->reqName;

            if (file_put_contents($pdfPath, $pdfBinary) === false) {
                throw new QuestFileSystemException(
                    'Failed to write PDF file to: ' . $pdfPath
                );
            }

            // Store binary data for database storage
            $this->pdfBinary = $pdfBinary;

            $this->logger->info('Requisition PDF saved successfully', ['file' => $this->reqName]);
            return [
                'filename' => $this->reqName,
                'binary' => $pdfBinary
            ];

        } catch (QuestHttpException $e) {
            $this->logger->error('Quest HTTP error during requisition fetch', [
                'error' => $e->getMessage(),
                'status_code' => $e->getCode()
            ]);
            throw $e;
        } catch (QuestFileSystemException $e) {
            $this->logger->error('Filesystem error during requisition save', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during requisition fetch', [
                'error' => $e->getMessage()
            ]);
            throw new QuestHttpException(
                'Unexpected error fetching requisition: ' . $e->getMessage(),
                0,
                null,
                null,
                $e
            );
        }
    }

    /**
     * Get the PDF binary data
     *
     * @return string
     */
    public function getPdfBinary(): string
    {
        return $this->pdfBinary;
    }

    /**
     * Ensure all required directories exist
     *
     * @return void
     * @throws QuestFileSystemException
     */
    private function ensureDirectories(): void
    {
        $baseDir = $GLOBALS['OE_SITE_DIR'] . '/documents/labs/';
        $subdirs = ['quest', 'quest/logs'];

        foreach ($subdirs as $subdir) {
            $fullPath = $baseDir . $subdir;
            if (!is_dir($fullPath)) {
                if (!mkdir($fullPath, 0755, true)) {
                    throw new QuestFileSystemException(
                        'Failed to create directory: ' . $fullPath
                    );
                }
                $this->logger->debug('Created directory', ['path' => $fullPath]);
            }
        }
    }
}
