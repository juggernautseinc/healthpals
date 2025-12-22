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
            $list = json_decode($compendiumFileName, true);
            foreach ($list as $item) {
                if (empty($item)) {
                    continue;
                }
                foreach ($item[0] as $key => $value) {
                    $pattern = "/TMP_CDC_FULL/";
                    if ($key == 'fileName' && preg_match($pattern, $value)) {
                        $compendiumFileName = $value;
                    }
                    if ($key == 'retrieveURI' && preg_match($pattern, $value)) {
                        $resourceLocation = $value;
                    }
                }
            }

            echo "<p>File Name: $compendiumFileName</p>";
            echo "<p>Retrieve URI: $resourceLocation</p>";

            ?>
        </div>
        <div class="col-md-12" id="stepone">
            <p><?php echo xlt('File download is completed ') ?></p>
            <button class='btn btn-primary' id="getCompendiumFile"><?php echo xlt("Import Data"); ?></button>
            <a href="index.php" class='btn btn-primary ml-3' id="getCompendiumFile"><?php echo xlt("Back"); ?></a>
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
                    let error = response.substring(0, 5);
                    if (error === 'Error') {
                        $('#quest-error').show().append('<p>' + response + '</p>');
                        return;
                    } else {
                        $('#quest-error').show().append('<p>' + response + '</p>');
                    }
                    console.log('Success:', response);
                    $('.loader').hide();
                },
                error: function(xhr, status, error) {
                    $('#quest-error').show().append('<p>' + error + '</p>');
                    $('.loader').hide();
                    console.error('Error:', error);
                }
            });
        });
    });
</script>
</html>

