<?php

/**
 * Quest API POST Request Handler
 *
 * This file contains the QuestPostCommon class which provides standardized
 * functionality for making POST requests to the Quest Diagnostics API.
 * It handles authentication, request formatting, and response processing
 * for all POST operations to Quest endpoints.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\Quest\Module;

/**
 * Class QuestPostCommon
 * 
 * Provides a standardized method for making POST requests to the Quest API.
 * This class handles authentication token management, request formatting,
 * and response processing. It's designed to be reused by various components
 * that need to send data to different Quest API endpoints.
 *
 * @package Juggernaut\Quest\Module
 */
class QuestPostCommon
{
    /**
     * Sends a POST request to a Quest API endpoint
     * 
     * Handles the complete request lifecycle including:
     * - Obtaining a fresh authentication token
     * - Determining the correct environment URL (test/production)
     * - Setting up proper headers and request format
     * - Executing the request and processing the response
     * - Error handling and logging
     *
     * @param string $resourceLocation The API endpoint path (e.g., '/hub-resource-server/oauth2/result/getResults')
     * @param string $payload The JSON-encoded request body
     * @return string The API response if successful, or an error message with HTTP status code
     */
    public function postRequestToQuest(
        $resourceLocation,
        $payload
    ): string
    {
        $token = new QuestToken();
        $postToken = json_decode($token->getFreshToken(), true);
        $postToken = $postToken['access_token'] ?? '';
        $mode = $token->operationMode() ?? '';

        $curl = curl_init();
        if (!empty($mode) && !empty($resourceLocation) && !empty($payload)) {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $mode . $resourceLocation,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer " . $postToken,
                    'Content-Type: application/json',
                ),
            ));
        } else {
            error_log(" Quest Lab Order:Debug location " . $mode . $resourceLocation);
            error_log(" Quest Lab Order:Debug payload " . $payload);
        }
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);
        if ($status == 200) {
            return $response;
        } else {
            return "HTTP Status Code: " . $status;
        }
    }
}
