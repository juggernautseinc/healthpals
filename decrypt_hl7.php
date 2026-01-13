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

$directory = '/var/www/vhosts/healthpals.co/emr.healthpals.co/openemr-7.0.3/sites/default/documents/procedure_results/1-98765432';

echo $directory . PHP_EOL;

if (is_dir($directory)) {
    echo "Show contents: " . PHP_EOL;
    foreach (scandir($directory) as $file) {
        $fullPath = $directory . DIRECTORY_SEPARATOR . $file;

        if (is_file($fullPath)) {
            echo $file . PHP_EOL;
        }
    }
}
