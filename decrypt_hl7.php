<?php
/**
 * Script to decrypt HL7 result files encrypted by OpenEMR
 * Usage: php decrypt_hl7.php <input_file> [output_file]
 */

require_once(__DIR__ . '/interface/globals.php');

use OpenEMR\Common\Crypto\CryptoGen;

if ($argc < 2) {
    echo "Usage: php decrypt_hl7.php <input_file> [output_file]\n";
    echo "Example: php decrypt_hl7.php '/home/sherwin/Documents/HealthPal/Results/quest_results_2026-01-08 18:20:35_1.hl7'\n";
    exit(1);
}

$inputFile = $argv[1];
$outputFile = $argv[2] ?? null;

if (!file_exists($inputFile)) {
    echo "Error: Input file does not exist: $inputFile\n";
    exit(1);
}

// Read the encrypted content
$encryptedContent = file_get_contents($inputFile);
if ($encryptedContent === false) {
    echo "Error: Could not read input file\n";
    exit(1);
}

// Check if drive encryption is enabled
if (!$GLOBALS['drive_encryption']) {
    echo "Warning: drive_encryption is disabled in globals. File may not be encrypted.\n";
    echo "Displaying content as-is:\n\n";
    echo $encryptedContent;
    exit(0);
}

try {
    // Initialize CryptoGen
    $crypto = new CryptoGen();

    // Decrypt using 'database' key source (as per hl7Crypt function)
    $decryptedContent = $crypto->decryptStandard($encryptedContent, null, 'database');

    if ($decryptedContent === false) {
        echo "Error: Decryption failed\n";
        exit(1);
    }

    if (empty($decryptedContent)) {
        echo "Warning: Decrypted content is empty\n";
        exit(1);
    }

    // Output or save
    if ($outputFile) {
        if (file_put_contents($outputFile, $decryptedContent) === false) {
            echo "Error: Could not write to output file: $outputFile\n";
            exit(1);
        }
        echo "Successfully decrypted to: $outputFile\n";
    } else {
        echo "Decrypted HL7 content:\n";
        echo "=" . str_repeat("=", 70) . "\n";
        echo $decryptedContent;
        echo "\n" . str_repeat("=", 71) . "\n";
    }

} catch (Exception $e) {
    echo "Error during decryption: " . $e->getMessage() . "\n";
    exit(1);
}
