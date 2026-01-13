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


    echo "Show contents: <br>";
    $i = 0;
    foreach ($iterator as $fileinfo) {
        if ($i === 0) continue;
        if ($fileinfo->isFile()) {
            echo "<a href=''" . $fileinfo->getFilename() . "</a><br>";
        }
    }

