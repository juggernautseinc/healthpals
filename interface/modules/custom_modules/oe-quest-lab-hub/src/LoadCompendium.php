<?php

/*
 * package   OpenEMR
 * link           https://open-emr.org
 * author      Sherwin Gaddis <sherwingaddis@gmail.com>
 * Copyright (c) 2024.  Sherwin Gaddis <sherwingaddis@gmail.com>
 */

namespace Juggernaut\Quest\Module;

class LoadCompendium
{
    final public function requestCompendiumFileList(): string
    {
        $resourceLocation = '/hub-resource-server/oauth2/compendium/requestCompendiums/CDC?BU=STL';
        $response = new QuestGetCommon();

        return $response->getRequestToQuest(
            $resourceLocation,
        );
    }
}
