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
        $receiverId = $this->pullReceiverId();
        $resourceLocation = '/hub-resource-server/oauth2/compendium/requestCompendiums/CDC?BU=' . $receiverId;
        $response = new QuestGetCommon();

        return $response->getRequestToQuest(
            $resourceLocation,
        );
    }

    private function pullReceiverId(): string
    {
        $receiverId = sqlQuery("SELECT recv_fac_id FROM procedure_providers WHERE name = 'Quest'");
        return $receiverId['recv_fac_id'];
    }
}
