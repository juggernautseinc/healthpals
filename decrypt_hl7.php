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
$encrypted_contents = __DIR__ . 'sites/default/documents/procedure_results/1-98765432/quest_results_2026-01-08 18:20:35_1.hl7';

$whatsinthere = file_get_contents($encrypted_contents);

var_dump($whatsinthere);
echo $directory . PHP_EOL;

$iterator = new DirectoryIterator($directory);


    echo "Show contents: " . PHP_EOL;
    foreach ($iterator as $fileinfo) {
        if ($fileinfo->isFile()) {
            echo $fileinfo->getFilename() . PHP_EOL;
        }
    }

