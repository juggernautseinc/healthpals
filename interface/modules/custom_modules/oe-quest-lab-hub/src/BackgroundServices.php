<?php

/**
 * Quest Lab Hub Background Services Manager
 *
 * This file contains the BackgroundServices class which is responsible for
 * managing the background service that automatically retrieves lab results
 * from the Quest Diagnostics API. It provides methods to enable/disable
 * the service and check its current status.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\Quest\Module;

/**
 * Class BackgroundServices
 *
 * Manages the OpenEMR background service for Quest lab results retrieval.
 * This class provides methods to enable or disable automatic lab results
 * retrieval, which runs at scheduled intervals in the background. It works
 * with OpenEMR's background_services table to control the Quest_Lab_Hub service.
 *
 * @package Juggernaut\Quest\Module
 */
class BackgroundServices
{
    /**
     * Current status of the background service
     * When true, the service should be enabled; when false, disabled
     * 
     * @var array|bool|null
     */
    public array|bool|null $status;
    
    /**
     * Updates the background service status in the database
     * 
     * Changes the active status of the Quest_Lab_Hub background service
     * based on the current value of the status property. This enables
     * or disables automatic lab results retrieval.
     *
     * @return void
     */
    public function changeStatus(): void
    {
        if ($this->status) {
            $status = 1;
        } else {
            $status = 0;
        }
        sqlQuery("UPDATE `background_services` SET `active` = ? WHERE `name` = 'Quest_Lab_Hub'", [$status]);
    }

    /**
     * Retrieves the current status of the background service
     * 
     * Queries the database to determine whether the Quest_Lab_Hub
     * background service is currently active or inactive.
     *
     * @return bool|array|null Array containing active status if found, false or null otherwise
     */
    public function getStatus(): bool|array|null
    {
        return sqlQuery("SELECT `active` FROM `background_services` WHERE `name` = 'Quest_Lab_Hub'");
    }
}
