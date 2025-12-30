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
use Juggernaut\Quest\Module\Exceptions\QuestHttpException;
use OpenEMR\Common\Logging\SystemLogger;

/**
 * QuestPostCommon
 *
 * Handles POST requests to Quest Hub using oeHttpRequest.
 * Replaces legacy curl implementation with modern HTTP client.
 *
 * @package Juggernaut\Quest\Module
 */
class QuestPostCommon
{
    /**
     * HTTP Client
     * @var oeHttpRequest
     */
    private oeHttpRequest $httpClient;

    /**
     * System logger
     * @var SystemLogger
     */
    private SystemLogger $logger;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->httpClient = new oeHttpRequest(new Client());
        $this->logger = new SystemLogger();
    }

    /**
     * Send a POST request to Quest Hub
     *
     * @param string $resourceLocation API endpoint path
     * @param string $payload JSON payload as string
     * @return string Response body from Quest Hub
     * @throws QuestHttpException If the request fails
     */
    public function postRequestToQuest(
        string $resourceLocation,
        string $payload
    ): string {
        try {
            // Validate inputs
            if (empty($resourceLocation)) {
                throw new QuestHttpException(
                    'Resource location cannot be empty',
                    400
                );
            }

            if (empty($payload)) {
                throw new QuestHttpException(
                    'Payload cannot be empty',
                    400
                );
            }

            // Get fresh token
            $token = new QuestToken();
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
            $baseUrl = $token->operationMode();

            // Make the request
            $response = $this->httpClient
                ->usingBaseUri($baseUrl)
                ->usingHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->asJson()
                ->send('POST', $resourceLocation, [
                    'json' => json_decode($payload, true),
                ]);

            // Validate response
            if ($response->getStatusCode() !== 200) {
                $this->logger->error(
                    'Quest Hub POST request failed',
                    [
                        'endpoint' => $resourceLocation,
                        'status_code' => $response->getStatusCode(),
                    ]
                );
                throw new QuestHttpException(
                    'Quest Hub POST request failed',
                    0,
                    $response->getStatusCode(),
                    $response->getBody()
                );
            }

            return $response->getBody();
        } catch (QuestHttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error(
                'Quest POST request exception',
                ['error' => $e->getMessage()]
            );
            throw new QuestHttpException(
                'Exception during Quest POST request: ' . $e->getMessage(),
                0,
                null,
                null,
                $e
            );
        }
    }
}
