<?php

/**
 * Script to decrypt HL7 result files encrypted by OpenEMR
 *
 * Usage:
 *   List files:    php decrypt_hl7.php --list <directory>
 *   Decrypt file:  php decrypt_hl7.php <input_file> [output_file]
 */


require_once(__DIR__ . '/interface/globals.php');

use OpenEMR\Common\Crypto\CryptoGen;

$directory = 'sites/default/documents/temp';

$iterator = new DirectoryIterator($directory);


    echo "Show contents: " . PHP_EOL;
    foreach ($iterator as $fileinfo) {
        if ($fileinfo->isFile()) {
            echo $fileinfo->getFilename() . "<br>";
        }
    }

