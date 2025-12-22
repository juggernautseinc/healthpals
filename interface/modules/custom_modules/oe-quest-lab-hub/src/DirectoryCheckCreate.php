<?php

/**
 * Directory Validation and Creation Utility
 *
 * This file contains the DirectoryCheckCreate class which manages the
 * labs directory structure required by the Quest Lab Hub module.
 * It ensures that the necessary directories exist for storing lab
 * requisitions and results, creating them if they don't exist.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\Quest\Module;

/**
 * Class DirectoryCheckCreate
 *
 * Manages the directory structure for lab documents in OpenEMR.
 * This utility class checks for the existence of the labs directory
 * in the site's document folder and creates it if it doesn't exist.
 * It's used to ensure that requisition forms and lab results have
 * a valid location to be stored.
 *
 * @package Juggernaut\Quest\Module
 */
class DirectoryCheckCreate
{
    /**
     * Full path to the labs directory
     * @var string
     */
    private string $location;
    
    /**
     * Status of directory creation operation
     * @var bool|null
     */
    private $status;
    
    /**
     * Constructor - Initializes directory verification
     * 
     * Checks if the labs directory exists and creates it if necessary.
     * Sets the status property to indicate success or failure of directory creation.
     */
    public function __construct()
    {
        $dirExists = $this->doesDirectoryExist();
        if (!$dirExists) {
            $this->status = $this->createDirectory();
        }
    }
    
    /**
     * Checks if the labs directory exists
     * 
     * Sets the location property to the expected path for the labs
     * directory and checks if that directory exists.
     *
     * @return bool True if the directory exists, false otherwise
     */
    public function doesDirectoryExist(): bool
    {
        $this->location = dirname(__FILE__, 6) . "/sites/" . $_SESSION['site_id'] . "/documents/labs";
        return file_exists($this->location);
    }
    
    /**
     * Returns the status of directory creation
     * 
     * Provides access to the status property which indicates whether
     * directory creation was successful.
     *
     * @return bool|null True if creation was successful, null if not attempted
     */
    public function directoryStatus()
    {
        return $this->status;
    }

    /**
     * Creates the labs directory
     * 
     * Attempts to create the labs directory with appropriate permissions.
     * Throws a RuntimeException if directory creation fails.
     *
     * @return bool True if directory was created successfully
     * @throws \RuntimeException If directory creation fails
     */
    private function createDirectory(): bool
    {
        if (!mkdir($this->location, 0777, true) && !is_dir($this->location)) {
            throw new \RuntimeException("Unable to create directory: " . $this->location);
        }
        return true;
    }
}
