<?php

/*
 * package   OpenEMR
 * link           https://open-emr.org
 * author      Sherwin Gaddis <sherwingaddis@gmail.com>
 * Copyright (c) 2024.  Sherwin Gaddis <sherwingaddis@gmail.com>
 */

use Juggernaut\Quest\Module\QuestGetCommon;

require_once dirname(__DIR__, 4) . '/globals.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fileName = $_POST['fileName'];
    $retrieveURI = $_POST['retrieveURI'];

    if (empty($fileName) || empty($retrieveURI)) {
        echo "File Name or Retrieve URI is empty";
        exit;
    } else {
        $retrieveCompendium = new QuestGetCommon();
        $results = $retrieveCompendium->retrieveCompendium($fileName, $retrieveURI);
        echo $results;
    }
}

