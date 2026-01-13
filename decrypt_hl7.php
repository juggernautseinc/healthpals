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

if ($argc < 2) {
    echo "Usage:\n";
    echo "  List files:    php decrypt_hl7.php --list <directory>\n";
    echo "  Decrypt file:  php decrypt_hl7.php <input_file> [output_file]\n";
    echo "\nExamples:\n";
    echo "  php decrypt_hl7.php --list /path/to/procedure_results/\n";
    echo "  php decrypt_hl7.php '/path/to/file.hl7'\n";
    exit(1);
}

// Handle --list command
if ($argv[1] === '--list') {
    if ($argc < 3) {
        echo "Error: Please specify a directory to list\n";
        exit(1);
    }

    $directory = rtrim($argv[2], '/');

    if (!is_dir($directory)) {
        echo "Error: Directory does not exist: $directory\n";
        exit(1);
    }

    echo "Listing files in: $directory\n";
    echo str_repeat("=", 100) . "\n\n";

    $files = scandir($directory);
    $count = 0;

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $fullPath = $directory . '/' . $file;

        if (is_file($fullPath)) {
            $count++;
            $size = filesize($fullPath);
            $modified = date('Y-m-d H:i:s', filemtime($fullPath));

            // Check if file appears to be encrypted
            $encrypted = 'Unknown';
            $handle = @fopen($fullPath, 'r');
            if ($handle) {
                $firstBytes = fread($handle, 3);
                fclose($handle);
                if (preg_match('/^00[1-6]/', $firstBytes)) {
                    $encrypted = 'Yes (v' . $firstBytes . ')';
                } else {
                    $encrypted = 'No';
                }
            }

            printf("%4d. %-50s %12s  %s  Enc: %s\n",
                   $count,
                   $file,
                   number_format($size) . ' bytes',
                   $modified,
                   $encrypted);
        }
    }

    echo "\n" . str_repeat("=", 100) . "\n";
    echo "Total files: $count\n";
    echo "\nTo decrypt a file, run:\n";
    echo "  php decrypt_hl7.php '$directory/<filename>'\n";
    exit(0);
}

// Handle decrypt command
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
        if (file_puts($outputFile, $decryptedContent) === false) {
            echo "Error: Could not write to output file: $outputFile\n";
            exit(1);
        }
        echo "Successfully decrypted to: $outputFile\n";
    } else {
        echo "Decrypted HL7 content:\n";
        echo "=" . str_repeat("=", 98) . "\n";
        echo $decryptedContent;
        echo "\n" . str_repeat("=", 99) . "\n";
    }

} catch (Exception $e) {
    echo "Error during decryption: " . $e->getMessage() . "\n";
    exit(1);
}
