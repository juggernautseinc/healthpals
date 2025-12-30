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

use Exception;

class ProcessRequisitionDocument
{
    private mixed $orderHl7;
    private string $reqName;
    private string $path;

    public function __construct($orderHl7)
    {
        $this->orderHl7 = base64_encode($orderHl7);
    }

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
