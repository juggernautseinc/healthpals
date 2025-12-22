<?php
/**
 * Quest Lab Hub Encounter Form Button Provider
 *
 * This file contains the AddButtonEncounterForm class which is responsible for
 * adding lab-related buttons to the OpenEMR encounter form interface. These buttons
 * provide quick access to laboratory functions such as printing specimen labels.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\Quest\Module;

/**
 * Class AddButtonEncounterForm
 *
 * Provides buttons for the OpenEMR encounter form interface related to
 * Quest lab functionality. This class is used to dynamically insert UI elements
 * into the encounter form that give users quick access to lab-related features.
 * It works with the Bootstrap class's encounterButtonRender event handler to
 * add buttons at the appropriate locations in the interface.
 *
 * @package Juggernaut\Quest\Module
 */
class AddButtonEncounterForm
{
    /**
     * Creates a specimen label button for the encounter form
     *
     * Generates HTML for a button that allows users to print specimen labels
     * for lab orders. The button links to the lab_labels.php page and is
     * configured to print three labels by default.
     *
     * @return string HTML button element for the specimen labels function
     */
    public function specimenLabelButton(): string
    {
        return "<a class='btn btn-secondary btn-sm' href='" . $GLOBALS['web_root'] .
            "/interface/modules/custom_modules/oe-quest-lab-hub/public/lab_labels.php?count=3' title='" .
            xla('Print Specimen Label') .
            "' onclick='top.restoreSession()'>" .
            xlt('Specimen Labels') . "</a>";
    }
}
