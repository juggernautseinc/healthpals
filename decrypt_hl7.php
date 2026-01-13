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

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
<?php
echo "Show contents: <br>";
$i = 0;
foreach ($iterator as $fileinfo) {
    if ($i === 0) {
        continue;
    }
    if ($fileinfo->isFile()) {
        echo "<a href=''>" . $fileinfo->getFilename() . "</a><br>";
    }
}
?>
<textarea>

</textarea>
</body>
</html>


