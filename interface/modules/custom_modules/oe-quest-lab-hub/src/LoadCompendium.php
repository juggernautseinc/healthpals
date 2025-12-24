<?php

/**
 * Quest Lab Compendium Request Handler
 *
 * This file contains the LoadCompendium class which is responsible for
 * requesting the compendium file list from the Quest Diagnostics API.
 * The compendium contains the catalog of available lab tests and their codes,
 * which are essential for submitting properly formatted lab orders.
 *
 * @package   OpenEMR
 * @link      https://open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2024. Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\Quest\Module;

/**
 * Class LoadCompendium
 *
 * Handles the process of requesting the compendium file list from the Quest API.
 * The compendium contains detailed information about available lab tests, their codes,
 * and specifications. This class provides functionality to initiate the request for
 * this data, which will later be processed by other components of the module for
 * importing into the OpenEMR system.
 *
 * @package Juggernaut\Quest\Module
 */
class LoadCompendium
{
    /**
     * Requests the compendium file list from the Quest API
     *
     * Makes a GET request to the Quest API endpoint for requesting compendium data.
     * This method uses the QuestGetCommon class to handle the actual API communication.
     * The response typically contains information about available compendium files
     * that can be retrieved and imported.
     *
     * @return string JSON response from the Quest API containing compendium information
     */
    final public function requestCompendiumFileList(): string
    {
        $resourceLocation = '/hub-resource-server/oauth2/compendium/requestCompendiums/CDC?BU=STL';
        $response = new QuestGetCommon();

        return $response->getRequestToQuest(
            $resourceLocation,
        );
    }
}
