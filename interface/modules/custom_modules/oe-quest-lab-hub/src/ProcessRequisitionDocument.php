<?php

/**
 * Quest Lab Requisition Document Generator
 *
 * This file contains the ProcessRequisitionDocument class which is responsible for
 * generating and retrieving requisition forms from the Quest Diagnostics API.
 * When lab orders are transmitted, this class requests and processes the associated
 * PDF requisition documents that accompany those orders.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\Quest\Module;

use Exception;

/**
 * Class ProcessRequisitionDocument
 *
 * Handles the generation and retrieval of requisition documents from the Quest
 * Diagnostics API. This class processes HL7 order messages, transmits them to Quest,
 * and receives PDF requisition forms which are then saved in the OpenEMR document
 * system for printing or viewing.
 * 
 * @package Juggernaut\Quest\Module
 */
class ProcessRequisitionDocument
{
    /**
     * Base64-encoded HL7 order message to send to Quest API
     * @var mixed
     */
    private mixed $orderHl7;
    
    /**
     * Filename for the saved requisition document
     * @var string
     */
    private string $reqName;
    
    /**
     * Path where requisition documents will be saved
     * @var string
     */
    private string $path;

    /**
     * Constructor - Initialize with HL7 order data
     *
     * Takes an HL7 order message and prepares it for transmission
     * to the Quest API by base64-encoding it.
     * 
     * @param string $orderHl7 Raw HL7 order message
     */
    public function __construct($orderHl7)
    {
        $this->orderHl7 = base64_encode($orderHl7);
    }

    /**
     * Builds the request payload for the API call
     * 
     * Creates a JSON request body containing the encoded HL7 order
     * and specifies which document types to request (ABN, REQ, AOE).
     *
     * @return bool|string JSON-encoded request payload or false on failure
     */
    private function buildRequest(): bool|string
    {
        $request = json_encode( [
            "documentTypes" => [
                                    "ABN", "REQ", "AOE"
                               ],

            "orderHl7" => $this->orderHl7
        ]);
        error_log("Requisition request payload completed");
        return $request;
    }

    /**
     * Sends the request to Quest API and processes the response
     * 
     * Makes the API call to request requisition documents, handles the
     * response, and saves the returned PDF to the appropriate location.
     * Includes error handling for API communication issues.
     *
     * @return string|false Filename of the saved requisition document or false on failure
     */
    public function sendRequest(): string
    {
        $token = new QuestToken();
        $mode = $token->operationMode();
        error_log("Requisition document: " . $mode);
        $postToken = json_decode($token->getFreshToken(), true);
        $postToken = $postToken['access_token'] ?? '';
        $requestPayload = $this->buildRequest();

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $mode . '/hub-resource-server/oauth2/order/document',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $requestPayload,
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . $postToken,
                'Content-Type: application/json',
            ),
        ));

        try {
            $response = curl_exec($curl);
            if (!$response) {
                throw new Exception('cURL error: ' . curl_error($curl));
            }

            $info = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if (!curl_errno($curl)) {
                $code = $info['http_code'] ?? '';
                error_log("Requisition document: returned successfully $code");
                curl_close($curl);
                $responsePdf = json_decode($response, true);
                file_put_contents('/var/www/html/emr/sites/' . $_SESSION['site_id'] . '/documents/labs/requisitionRaw.json', $responsePdf);
                $returnedPdfDocument = $responsePdf['orderSupportDocuments'][0]['documentData'] ?? '';
                $pdfDecoded = base64_decode($returnedPdfDocument); //This is not a base64 encoded string
                $this->path = Bootstrap::requisitionFormPath();
                $this->reqName = 'labRequisition-' . time() . '.pdf';
                $directory = new DirectoryCheckCreate();
                file_put_contents($this->path . $this->reqName, base64_decode($pdfDecoded));
                return $this->reqName;
            } else {
                error_log('Requisition document: retrieval failed ' . $info['http_code']);
                curl_close($curl);
                return false;
            }
        } catch (Exception $e) {
            error_log('Requisition document: ' . $e->getMessage());
            file_put_contents($this->path . $this->reqName, "Requisition form not available. Contact Quest Diagnostics");
            return false;
        }
    }
}
