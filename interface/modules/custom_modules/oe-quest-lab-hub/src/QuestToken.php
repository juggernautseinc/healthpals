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
use Juggernaut\Quest\Module\Exceptions\QuestConfigException;
use OpenEMR\Common\Logging\SystemLogger;

/**
 * QuestToken
 *
 * Handles OAuth2 token requests to Quest Hub using oeHttpRequest.
 * Replaces legacy curl implementation with modern HTTP client.
 *
 * @package Juggernaut\Quest\Module
 */
class QuestToken
{
    /**
     * Client ID for OAuth2
     * @var string|null
     */
    private ?string $clientId;

    /**
     * Client Secret for OAuth2
     * @var string|null
     */
    private ?string $clientSecret;

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
     *
     * @throws QuestConfigException If configuration is missing
     */
    public function __construct()
    {
        $this->logger = new SystemLogger();

        $b = new Bootstrap($GLOBALS['kernel']->getEventDispatcher());
        $credentials = $b->getGlobalConfig();
        $this->clientId = $credentials->getTextOption();
        $this->clientSecret = $credentials->getEncryptedOption();

        // Validate configuration
        if (empty($this->clientId)) {
            throw new QuestConfigException(
                'Quest client ID is not configured',
                0,
                'oe_quest_config_option_text'
            );
        }

        if (empty($this->clientSecret)) {
            throw new QuestConfigException(
                'Quest client secret is not configured',
                0,
                'oe_quest_config_option_encrypted'
            );
        }

        // Initialize HTTP client
        $this->httpClient = new oeHttpRequest(new Client());
    }

    /**
     * Get a fresh OAuth2 token
     *
     * @return string JSON-encoded token response
     * @throws QuestHttpException If token request fails
     */
    final public function getFreshToken(): string
    {
        return $this->requestNewToken();
    }

    /**
     * Request a new OAuth2 token from Quest Hub
     *
     * @return string JSON-encoded token response
     * @throws QuestHttpException If the request fails
     */
    private function requestNewToken(): string
    {
        try {
            $endPoint = $this->operationMode();
            $tokenUrl = $endPoint . '/hub-authorization-server/oauth2/token';

            // Make the request using oeHttpRequest
            $response = $this->httpClient
                ->usingBaseUri($endPoint)
                ->asFormParams()
                ->post('/hub-authorization-server/oauth2/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);

            // Check response status
            if ($response->getStatusCode() !== 200) {
                $this->logger->error(
                    'Quest Hub token request failed',
                    ['status_code' => $response->getStatusCode()]
                );
                throw new QuestHttpException(
                    'Failed to obtain OAuth2 token from Quest Hub',
                    0,
                    $response->getStatusCode(),
                    $response->getBody()
                );
            }

            return $response->getBody();
        } catch (\Exception $e) {
            if ($e instanceof QuestHttpException) {
                throw $e;
            }
            $this->logger->error('Quest token request exception', ['error' => $e->getMessage()]);
            throw new QuestHttpException(
                'Exception during Quest token request: ' . $e->getMessage(),
                0,
                null,
                null,
                $e
            );
        }
    }

    /**
     * Get the appropriate operation mode URL (testing or production)
     *
     * @return string Base URL for Quest Hub
     */
    public function operationMode(): string
    {
        if (!empty($GLOBALS['oe_quest_production']) && $GLOBALS['oe_quest_production']) {
            return Bootstrap::HUB_RESOURCE_PRODUCTION_URL;
        } else {
            return Bootstrap::HUB_RESOURCE_TESTING_URL;
        }
    }
}
