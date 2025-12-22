<?php

/**
 * Quest Lab Hub Module - Main Interface
 *
 * This file provides the primary user interface for the Quest Lab Hub module.
 * It allows users to enable/disable automatic lab result retrieval, request
 * compendium data, and access module configuration settings. The interface
 * includes tabs for Home, Services, Compendium, and Settings functionality.
 *
 * The home tab provides general information and setup instructions for the module.
 * The services tab allows enabling/disabling the background service for HL7 results.
 * The compendium tab facilitates downloading and importing Quest test codes.
 * The settings tab provides links to configuration options and usage guidance.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

require_once dirname(__FILE__, 5) . "/globals.php";
require_once dirname(__FILE__, 2) . '/vendor/autoload.php';

use Juggernaut\Quest\Module\BackgroundServices;

$backgroundServices = new BackgroundServices();
$status = $backgroundServices->getStatus();

if (isset($_POST['status'])) {
    if (!CsrfUtils::verifyCsrfToken($_POST["token"])) {
        CsrfUtils::csrfNotVerified();
    }
    $backgroundServices->status = (int)$_POST['status'];
    $backgroundServices->changeStatus();
    $status = $backgroundServices->getStatus();
}

$msg = 'Click the button to toggle automatically downloading HL7 results';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <?php Header::setupHeader(); ?>
    <title><?php echo xlt('Quest Lab Quantum Hub'); ?></title>
</head>
<body>

<div class="container mx-auto mt-5">
    <!-- Bootstrap Tabs Setup -->
    <ul class="nav nav-tabs" id="questTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true"><?php echo xlt('Home'); ?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="status-tab" data-toggle="tab" href="#status" role="tab" aria-controls="status" aria-selected="false"><?php echo xlt('Services'); ?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="compendium-tab" data-toggle="tab" href="#compendium" role="tab" aria-controls="compendium" aria-selected="false"><?php echo xlt('Compendium'); ?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="settings-tab" data-toggle="tab" href="#settings" role="tab" aria-controls="settings" aria-selected="false"><?php echo xlt('Settings'); ?></a>
        </li>
    </ul>

    <div class="tab-content" id="questTabContent">
        <!-- Home Tab -->
        <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
            <div class="row">
                <div class="mx-auto mt-2" style="width: 80%">
                    <p><?php echo xlt('Thank you for enabling this module'); ?>.</p>
                    <p>
                        <?php echo xlt('If you have not contacted Quest, please take this time to contact them to begin the implementation process.'); ?><br>
                        <?php echo xlt("After clicking the button, select for physicians") ?><br>
                        <?php echo xlt("Vendor name: ")?><b><?php echo text("Juggernaut Systems Express") ?></b> <br>
                        <br>
                        <a class="btn btn-primary mt-1" href="https://www.getmyinterface.com/" target="_blank"><?php echo xlt('Request Implementation'); ?></a>
                    </p>
                    <p><?php echo xlt('Your OpenEMR server will need to be connected to a FQDN (Fully Qualified Domain Name) in order to use this module.'); ?></p>
                    <p><strong><?php echo xlt('You will also need a SSL certificate that is issued by a recognized authority. You cannot use a self-signed certificate.'); ?></strong></p>

                    <h3><?php echo xlt('Training Video'); ?></h3>
                    <p><?php echo xlt("Please watch this video on how to configure this module on your OpenEMR server"); ?></p>
                    <p><a class="btn btn-primary" href="https://youtu.be/4vYWFb4f_64" target="_blank"><?php echo xlt('Training Video'); ?></a></p>
                </div>
            </div>
        </div>

        <!-- Status Tab -->
        <div class="tab-pane fade" id="status" role="tabpanel" aria-labelledby="status-tab">
            <div class="row">
                <div class="mx-auto mt-5" style="width: 80%">
                    <form method="post" action="index.php">
                        <?php if ($status['active'] == '1') { ?>
                            <input type="hidden" name="status" value="0">
                            <input type="hidden" name="token" value="<?php echo CsrfUtils::collectCsrfToken(); ?>">
                            <button type="submit" class="btn btn-success"><?php echo xlt('Enabled'); ?></button>
                            <span class="ml-3"><?php echo xlt($msg); ?></span>
                        <?php } else { ?>
                            <input type="hidden" name="status" value="1">
                            <input type="hidden" name="token" value="<?php echo CsrfUtils::collectCsrfToken(); ?>">
                            <button type="submit" class="btn btn-danger"><?php echo xlt('Disable'); ?></button>
                            <span class="ml-3"><?php echo xlt($msg); ?></span>
                        <?php } ?>
                    </form>
                </div>
            </div>
        </div>
        <!-- Compendium Request Tab -->
        <div class="tab-pane fade mt-5" id="compendium" role="tabpanel" aria-labelledby="compendium-tab">
            <div class="row">
                <div class="mx-auto" style="width: 80%">
                    <h3><?php echo xlt('Compendium Request'); ?></h3>
                    <p><?php echo xlt('Click the button below to import Quest standard order codes.'); ?></p>
                    <p><?php echo xlt('This will populate the system with lab order codes to select from when creating a lab order.'); ?></p>
                    <p><a class="btn btn-primary" href="requestCompendium.php"><?php echo xlt('Request Compendium'); ?></a></p>
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div class="tab-pane fade mt-5" id="settings" role="tabpanel" aria-labelledby="settings-tab">
            <div class="row">
                <div class="mx-auto" style="width: 80%">
                    <h3><?php echo xlt('Module Settings'); ?></h3>
                    <p><?php echo xlt('Configure additional settings for the Quest Quantum Lab Hub module in Admin Config.'); ?></p>
                    <!-- Add any specific settings or controls here -->
                    <a class="btn-primary btn" href="<?php echo $GLOBALS['webroot'] ?>/interface/super/edit_globals.php"><?php echo xlt('Config Settings'); ?></a>
                </div>
            </div>
            <div class="row">
                <div class="mx-auto" style="width: 80%">
                    <h4 class="mt-3"><?php echo xlt('About Operating Mode'); ?></h4>
                    <p><?php echo xlt("By default, the system is in testing mode. All orders will be sent the certification hub."); ?></p>
                    <p><?php echo xlt("Once certification is completed, go to Admin, Config, Quest Lab Hub and set system to production"); ?> </p>
                </div>
            </div>
            <div class="row">
                <div class="mx-auto" style="width: 80%">
                    <h4 class="mt-3"><?php echo xlt('About label printing'); ?></h4>
                    <p><?php echo xlt("In the config, go to PDF settings. The default setting is for Avery labels 5160"); ?>.</p>
                    <p><?php echo xlt("After the lab order is created. On the forms encounter screen, there will be a Specimen Label button"); ?></p>
                    <p><?php echo xlt("If you would like to use a dynamo printer. Select to edit the order and there will be a label button in there that will print a bar coded label. Either label is acceptable"); ?></p>
                    <p><?php echo xlt("The system will print three labels at a time.") ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Include necessary scripts for Bootstrap Tabs -->
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>
</html>
