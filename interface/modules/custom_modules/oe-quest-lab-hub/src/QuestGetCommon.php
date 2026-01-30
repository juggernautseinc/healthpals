<?php

/*
 * package   OpenEMR
 * link           https://open-emr.org
 * author      Sherwin Gaddis <sherwingaddis@gmail.com>
 * Copyright (c) 2024.  Sherwin Gaddis <sherwingaddis@gmail.com>
 */

namespace Juggernaut\Quest\Module;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use ZipArchive;
use Juggernaut\Quest\Module\Services\ImportCompendiumData;

class QuestGetCommon
{
    private string $tmpDir;

    public function __construct()
    {
        $this->tmpDir = dirname(__DIR__, 5) . '/sites/' . $_SESSION['site_id'] . '/documents/temp/';
    }

    final public function getRequestToQuest(
        $resourceLocation
    ): string
    {
        try {
            $token = new QuestToken();
            $tokenResponse = $token->getFreshToken();

            // Check if token response is an error code (returned as string)
            if (is_numeric($tokenResponse)) {
                $errorMessage = "Failed to retrieve authentication token. HTTP Status Code: " . $tokenResponse;
                error_log("Quest Lab Order - Token Error: " . $errorMessage);
                return "Error: " . $errorMessage;
            }

            $postToken = json_decode($tokenResponse, true);

            // Validate token response structure
            if (!isset($postToken['access_token'])) {
                $errorMessage = "Invalid token response. Missing access_token.";
                error_log("Quest Lab Order - Token Parse Error: " . $errorMessage . " Response: " . $tokenResponse);
                return "Error: " . $errorMessage;
            }

            $accessToken = $postToken['access_token'];
            $mode = $token->operationMode();

            if (empty($mode) || empty($resourceLocation) || empty($accessToken)) {
                $errorMessage = "Missing required parameters. Mode: " . (!empty($mode) ? 'OK' : 'EMPTY') .
                    ", Location: " . (!empty($resourceLocation) ? 'OK' : 'EMPTY') .
                    ", Token: " . (!empty($accessToken) ? 'OK' : 'EMPTY');
                error_log("Quest Lab Order - Config Error: " . $errorMessage);
                return "Error: " . $errorMessage;
            }

            // Use Guzzle client for the request
            $client = new Client();

            $headers = [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ];

            $response = $client->get($mode . $resourceLocation, ['headers' => $headers]);

            if ($response->getStatusCode() === 200) {
                return $response->getBody()->getContents();
            } else {
                $errorMessage = "Unexpected response status: " . $response->getStatusCode();
                error_log("Quest Lab Order - Request Error: " . $errorMessage);
                return "Error: " . $errorMessage;
            }
        } catch (GuzzleException $e) {
            $errorMessage = "HTTP Request Error: " . $e->getMessage();
            error_log("Quest Lab Order - Guzzle Exception: " . $errorMessage);
            return "Error: " . $errorMessage;
        } catch (\Exception $e) {
            $errorMessage = "Unexpected error: " . $e->getMessage();
            error_log("Quest Lab Order - General Exception: " . $errorMessage);
            return "Error: " . $errorMessage;
        }
    }

    final public function retrieveCompendium(
        $fileName,
        $retrieveURILocation
    ): string
    {
        // Create a Guzzle client
        $client = new Client();

        $token = new QuestToken();
        $postToken = json_decode($token->getFreshToken(), true);
        $postToken = $postToken['access_token'] ?? '';
        $mode = $token->operationMode() ?? '';

        // Path where you want to save the downloaded file
        $tmpDir = dirname(__DIR__, 5) . '/sites/' . $_SESSION['site_id'] . '/documents/temp/';
        $saveTo = $tmpDir . $fileName;

        try {
            // Set headers with the token
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36',
                'Authorization' => 'Bearer ' . $postToken,
                "Accept: */*",
                "Accept-Encoding: gzip, deflate, br"
            ];

            // Send a GET request with sink option for download
            $response = $client->get($mode.$retrieveURILocation, ['headers' => $headers, 'sink' => $saveTo]);

            // Check for successful response
            if ($response->getStatusCode() === 200) {
                $unzipResults = $this->unzipCdcFile($fileName);
                $successMessage = xlt("File downloaded successfully imported into database: ");
                if ($unzipResults) {
                    new ImportCompendiumData();
                    unlink($this->tmpDir . $fileName);
                    return "<span class='text-success'><strong>" . $successMessage . "</strong></span>" . $fileName;
                } else {
                    $failureMessage = xlt("Error unzipping file");
                    return "<span class='text-danger'><strong>" . $failureMessage . "</strong></span>";
                }
            } else {

                return "Error downloading file. Status code: " . $response->getStatusCode();
            }
        } catch (GuzzleException $e) {
           return "An error occurred: " . $e->getMessage();
        }
    }
    private function unzipCdcFile($fileName): bool
    {
        #unpack file into the temp directory for import to database
        $zip = new ZipArchive;
        $res = $zip->open($this->tmpDir . $fileName);
        if ($res === TRUE) {
            $zip->extractTo($this->tmpDir);
            $zip->close();
            return true;
        } else {
            return false;
        }
    }
}
