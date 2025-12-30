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

use Juggernaut\Quest\Module\Exceptions\QuestHttpException;
use OpenEMR\Common\Logging\SystemLogger;

/**
 * GetHL7Results
 *
 * Handles retrieving HL7 results from Quest Hub.
 *
 * @package Juggernaut\Quest\Module
 */
class GetHL7Results
{
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
        $this->logger = new SystemLogger();
    }

    /**
     * Build the JSON request message for retrieving results
     *
     * @return string JSON-encoded request message
     */
    private function buildResultsRequestMessage(): string
    {
        return json_encode(
            [
                'resultServiceType' => 'HL7',
            ]
        );
    }

    /**
     * Request HL7 results from Quest Hub
     *
     * @return string JSON-encoded results response
     * @throws QuestHttpException If the request fails
     */
    final public function sendForResults(): string
    {
        $this->logger->debug('Requesting HL7 results from Quest Hub');
        $resourceLocation = '/hub-resource-server/oauth2/result/getResults';
        $orderPayload = $this->buildResultsRequestMessage();
        $response = new QuestPostCommon();

        return $response->postRequestToQuest(
            $resourceLocation,
            $orderPayload
        );
    }
}
