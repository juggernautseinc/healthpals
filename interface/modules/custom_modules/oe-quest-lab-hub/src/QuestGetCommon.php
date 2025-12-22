<?php

/**
 * Quest API GET Request Handler and Compendium Retrieval
 *
 * This file contains the QuestGetCommon class which provides standardized
 * functionality for making GET requests to the Quest Diagnostics API.
 * It handles authentication, request execution, and processing responses.
 * It also includes specialized functionality for retrieving and processing
 * compendium data files from Quest.
 *
 * @package   OpenEMR
 * @link      https://open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2024.  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\Quest\Module;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use ZipArchive;
use Juggernaut\Quest\Module\Services\ImportCompendiumData;

/**
 * Class QuestGetCommon
 *
 * Provides standardized methods for making GET requests to the Quest API
 * and specialized functionality for retrieving compendium data (lab test codes).
 * This class handles authentication token management, file downloads, extraction
 * of zip files, and initiating the import process for compendium data.
 *
 * @package Juggernaut\Quest\Module
 */
class QuestGetCommon
{
    /**
     * Temporary directory path for downloaded and extracted files
     * @var string
     */
    private string $tmpDir;

    /**
     * Constructor - Initializes with temporary directory path
     * 
     * Sets up the temporary directory path based on the current site
     * where downloaded files will be stored and processed.
     */
    public function __construct()
    {
        $this->tmpDir = dirname(__DIR__, 5) . '/sites/' . $_SESSION['site_id'] . '/documents/temp/';
    }

    /**
     * Sends a GET request to a Quest API endpoint
     * 
     * Handles the complete request lifecycle including:
     * - Obtaining a fresh authentication token
     * - Determining the correct environment URL (test/production)
     * - Setting up proper headers for authentication
     * - Executing the request and processing the response
     * - Error handling and logging
     *
     * @param string $resourceLocation The API endpoint path to request
     * @return string The API response if successful, or an error message with HTTP status code
     */
    final public function getRequestToQuest(
        $resourceLocation
    ): string
    {
        $token = new QuestToken();
        $postToken = json_decode($token->getFreshToken(), true);
        $postToken = $postToken['access_token'] ?? '';
        $mode = $token->operationMode() ?? '';
        $curl = curl_init();
        if (!empty($mode) && !empty($resourceLocation) && !empty($postToken)) {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $mode . $resourceLocation,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer " . $postToken
                ),
            ));
        } else {
            error_log(" Quest Lab Order:Debug location " . $mode . $resourceLocation);
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

    /**
     * Retrieves and processes the Quest compendium data file
     * 
     * Downloads the compendium file (containing lab test codes) from Quest,
     * extracts its contents, and initiates the import process to add the
     * data to OpenEMR's database. Handles file operations and error conditions.
     *
     * @param string $fileName Name to use for the downloaded file
     * @param string $retrieveURILocation The API endpoint path for the compendium file
     * @return string Status message indicating success or failure with details
     */
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

    /**
     * Extracts contents of a downloaded zip file
     * 
     * Unzips the downloaded compendium file to extract the contained
     * data files that will be imported into the database.
     *
     * @param string $fileName Name of the zip file to extract
     * @return bool True if extraction was successful, false otherwise
     */
    private function unzipCdcFile($fileName): bool
    {
        #unpack file into the temp directory for import to database
        //$tmpDir = dirname(__DIR__, 5) . '/sites/' . $_SESSION['site_id'] . '/documents/temp/';
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
