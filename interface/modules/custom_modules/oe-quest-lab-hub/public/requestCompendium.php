<?php

/*
 * package   OpenEMR
 * link           https://open-emr.org
 * author      Sherwin Gaddis <sherwingaddis@gmail.com>
 * Copyright (c) 2024.  Sherwin Gaddis <sherwingaddis@gmail.com>
 */

require_once dirname(__DIR__, 4) . '/globals.php';

use OpenEMR\Core\Header;
use Juggernaut\Quest\Module\LoadCompendium;

$requestCompendium = new LoadCompendium();
$compendiumFileName = $requestCompendium->requestCompendiumFileList();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo xlt('Compendium') ?></title>
    <?php Header::setupHeader(['common']); ?>
    <!-- Hide the quest-error div -->
    <style>
        #quest-error {
            display: none;
        }

        .loader {
            border: 16px solid #f3f3f3;
            border-radius: 50%;
            border-top: 16px solid #3498db;
            width: 120px;
            height: 120px;
            -webkit-animation: spin 2s linear infinite; /* Safari */
            animation: spin 2s linear infinite;
        }

        /* Safari */
        @-webkit-keyframes spin {
            0% { -webkit-transform: rotate(0deg); }
            100% { -webkit-transform: rotate(360deg); }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row mt-5">
        <div class="col-md-12">
            <h1><?php echo xlt('Compendium') ?></h1>
            <p><?php echo xlt('This is a list of all the lab orders that can be requested.') ?></p>

            <?php
            // Check if the response is an error
            if (strpos($compendiumFileName, 'Error:') === 0) {
                echo "<div class='alert alert-danger' role='alert'>";
                echo "<strong>" . xlt('Error loading compendium:') . "</strong> " . htmlspecialchars($compendiumFileName);
                echo "</div>";
            } else {
                $list = json_decode($compendiumFileName, true);
                if ($list === null) {
                    echo "<div class='alert alert-danger' role='alert'>";
                    echo "<strong>" . xlt('Error:') . "</strong> " . xlt('Failed to parse compendium response.');
                    echo "</div>";
                } else {
                    $compendiumFileName = $list['fullFileLinks'][0]['fileName'];
                    $resourceLocation = $list['fullFileLinks'][0]['retrieveURI'];
                    $loc = dirname(__DIR__, 5);
                    error_log('Compendium Location: ' . $loc);
                    file_put_contents($loc.'/sites/default/documents/temp/compendium.json', print_r($list, true));
//                    foreach ($list as $item) {
//                        if (empty($item)) {
//                            continue;
//                        }
//                        foreach ($item[0] as $key => $value) {
//                            $pattern = "/";
//                            if ($key == 'fileName' && preg_match($pattern, $value)) {
//                                $compendiumFileName = $value;
//                            }
//                            if ($key == 'retrieveURI' && preg_match($pattern, $value)) {
//                                $resourceLocation = $value;
//                            }
//                        }
//                    }

                    if (!empty($compendiumFileName) && !empty($resourceLocation)) {
                        echo "<p><strong>" . xlt('File Name:') . "</strong> " . htmlspecialchars($compendiumFileName) . "</p>";
                        echo "<p><strong>" . xlt('Retrieve URI:') . "</strong> " . htmlspecialchars($resourceLocation) . "</p>";
                    } else {
                        echo "<div class='alert alert-warning' role='alert'>";
                        echo xlt('No compendium files found in response.');
                        echo "</div>";
                    }
                }
            }

            ?>
        </div>
        <div class="col-md-12" id="stepone">
            <?php if (!empty($compendiumFileName) && !empty($resourceLocation)) { ?>
                <p><?php echo xlt('File download is completed ') ?></p>
                <button class='btn btn-primary' id="getCompendiumFile"><?php echo xlt("Import Data"); ?></button>
            <?php } ?>
            <a href="index.php" class='btn btn-primary ml-3'><?php echo xlt("Back"); ?></a>
        </div>
        <div class="loader"></div>
        <div class="col-md-12 mt-4" id="quest-error">
            <p><?php echo xlt('If you are having trouble downloading the file, please contact support@ehrcommunityhelpdesk.com') ?></p>
        </div>
    </div>
</div> <!-- /container -->
</body>
<script>
    $(document).ready(function() {
        $('.loader').hide();

        $('#getCompendiumFile').click(function() {
            $('.loader').show();
            const data = {
                fileName: '<?php echo $compendiumFileName; ?>',
                retrieveURI: '<?php echo '/hub-resource-server' . $resourceLocation; ?>'
            };

            $.ajax({
                url: 'retrieveCompendium.php',
                type: 'POST',
                data: data,
                success: function(response) {
                    $('.loader').hide();
                    let error = response.substring(0, 5);
                    if (error === 'Error') {
                        $('#quest-error').show().append('<p>' + response + '</p>');
                        return;
                    } else {
                        $('#quest-error').show().append('<p>' + response + '</p>');
                    }
                    console.log('Success:', response);
                },
                error: function(xhr, status, error) {
                    $('.loader').hide();
                    $('#quest-error').show().append('<p>' + error + '</p>');
                    console.error('Error:', error);
                }
            });
        });
    });
</script>
</html>

