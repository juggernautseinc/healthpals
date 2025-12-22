<?php

/**
 * Quest Lab Hub Compendium Data Import Service
 *
 * This file contains the ImportCompendiumData class which is responsible for 
 * importing lab test compendium data from Quest Diagnostics into OpenEMR.
 * It processes both standard test codes and AOE (Ask at Order Entry) data
 * from text files and inserts them into the OpenEMR database.
 *
 * @package   OpenEMR
 * @link      https://open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2024.  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\Quest\Module\Services;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Logging\SystemLogger;

/**
 * Class ImportCompendiumData
 *
 * Handles the import of Quest Diagnostics lab test compendium data into OpenEMR.
 * This includes standard lab test codes (procedure_type table) and AOE (Ask at Order Entry) 
 * questions (procedure_questions table) that are associated with specific lab tests.
 * 
 * The class reads from temporary files downloaded from Quest and processes them 
 * into the appropriate database tables, creating necessary dataset groups and 
 * handling duplicate entries appropriately.
 *
 * @package Juggernaut\Quest\Module\Services
 */
class ImportCompendiumData
{
    /**
     * Raw compendium data from the Quest file
     * @var string|false
     */
    private string|false $compendiumData;
    
    /**
     * Logger instance for error tracking
     * @var SystemLogger
     */
    private $logging;
    
    /**
     * Test name from compendium data
     * @var string
     */
    private $name;
    
    /**
     * Test description from compendium data
     * @var string
     */
    private $description;
    
    /**
     * Test code from compendium data
     * @var string
     */
    private $code;
    
    /**
     * Current table name being operated on
     * @var string
     */
    private string $tableName;
    
    /**
     * Procedure code for AOE data
     * @var string
     */
    private $pcode;
    
    /**
     * Question code for AOE data
     * @var string
     */
    private $qcode;
    
    /**
     * Sequence number for AOE questions
     * @var string|int
     */
    private $seq;
    
    /**
     * Available options for AOE questions
     * @var string
     */
    private $options;
    
    /**
     * Help text/tips for AOE questions
     * @var string
     */
    private $tips;
    
    /**
     * Question text for AOE questions
     * @var string
     */
    private $qtext;
    
    /**
     * Raw AOE data from the Quest file
     * @var string|false
     */
    private $compendiumAoeData;

    /**
     * Constructor - Initializes import process
     * 
     * Sets up logging, locates and processes compendium data files.
     * Handles both test code data and AOE question data if available.
     * Cleans up temporary files after processing.
     */
    public function __construct()
    {
        $this->logging = new SystemLogger();
        $insert = new QueryUtils();
        $this->tableName = $insert::escapeTableName('procedure_type');
        //unzipped data files
        $compendium = dirname(__DIR__, 6) . '/sites/' . $_SESSION['site_id'] . '/documents/temp/ORDCODE_TMP.TXT';
        $aoe = dirname(__DIR__, 6) . '/sites/' . $_SESSION['site_id'] . '/documents/temp/AOE_TMP.TXT';

        if(file_exists($compendium)) {
            $this->compendiumData = file_get_contents($compendium);
            $this->importData();
            unlink($compendium);
        } else {
            $this->logging->error('File not found: Check sites/documents/temp directory permission');
        }
        if(file_exists($aoe)) {
            $this->compendiumAoeData = file_get_contents($aoe);
            $this->importAoeData();
            unlink($aoe);
        } else {
            $this->logging->error('File not found: Check sites/documents/temp directory permission');
        }
    }

    /**
     * Imports AOE (Ask at Order Entry) data from the Quest file
     * 
     * Processes the AOE file data and inserts questions into the 
     * procedure_questions table. Handles database structure adjustments
     * if needed to accommodate the imported data.
     * 
     * @return void
     */
    private function importAoeData(): void
    {
        if ($this->checkPrimarykey() == 0) {
            //if the primary key is not set then drop it because there will be duplicates
            sqlStatement("ALTER TABLE procedure_questions DROP PRIMARY KEY");
        } else {
            //if there are records we need to clear out the questions and then reload them
            $this->reloadQuestionBank();
        }

        $i = 0; //first array can't be used, need to skip it

        $lines = explode("\n", $this->compendiumAoeData);
        //loop through the data and insert it into the database skipping first array
        foreach ($lines as $line) {
            if ($i == 0) {
                $i++;
                continue;
            }
            $fields = explode("^", $line);
            $this->pcode = $fields[3] ?? '';
            $this->qcode = $fields[2] ?? '';
            $this->seq = $fields[4] ?? '';
            $this->tips = $fields[11] ?? '';
            $this->options = $fields[9] ?? '';
            $this->qtext = $fields[5] ?? '';
            $this->insertAoeData();

            $i++;
        }
    }
    
    /**
     * Imports standard test code data from the Quest file
     * 
     * Processes the compendium file data and inserts test codes into the 
     * procedure_type table. Creates a dataset group for Quest if needed.
     * Skips duplicate entries based on procedure code.
     * 
     * @return void
     */
    private function importData(): void
    {
        $this->createQuestDatasetGroup();
        $i = 0; //first array can't be used, need to skip it
        $lines = explode("\n", $this->compendiumData);
        //loop through the data and insert it into the database skipping first array
        foreach ($lines as $line) {
            if ($i == 0) {
                $i++;
                continue;
            }
            $fields = explode("^", $line);
            $this->name = $fields[6] ?? '';
            $this->description = $fields[6] ?? '';
            $this->code = $fields[1] ?? '';

            if ($this->checkIfDataExists()) {
                $i++;
                continue;
            }
            $this->insertData();
            $i++;
        }
    }
    
    /**
     * Inserts test code data into the procedure_type table
     * 
     * Creates a new procedure entry in the OpenEMR database
     * for a Quest test code. Links to the appropriate parent group
     * and includes necessary metadata.
     * 
     * @return void
     */
    private function insertData(): void
    {
        $providerId = $this->getQuestProviderId();  //this is a function that returns the provider id from order providers
        $parent = $this->dataSetGroup(); //this is a function that returns the group number from procedure_type

        if (!isset($providerId['ppid']) || !isset($parent['procedure_type_id'])) {
            $msg = xlt('Quest provider or type not found ');
            $this->logging->error($msg);
            return;
        }
        if (!isset($this->name) || !isset($this->code) || !isset($this->description)) {
            $msg = xlt('Quest name, code or description not found ');
            $this->logging->error($msg);
            return;
        }
        $sql = "INSERT INTO `procedure_type` (`procedure_type_id`, `parent`, `name`, `lab_id`,
`procedure_code`, `procedure_type`, `body_site`, `specimen`, `route_admin`, `laterality`,
 `description`, `standard_code`, `related_code`, `units`, `range`, `seq`, `activity`, `notes`, `transport`, `procedure_type_name`) VALUES
(NULL, ?, ?, 1, ?, 'ord', '', '', '', '', ?, '', '', '', '', 0, 1, '', NULL, 'laboratory_test')";
        sqlStatement($sql, [$parent['procedure_type_id'], $this->name, $this->code, $this->description]);
    }

    /**
     * Inserts AOE question data into the procedure_questions table
     * 
     * Creates a new question entry in the OpenEMR database for a
     * Quest AOE question. Links to the appropriate lab and procedure code.
     * 
     * @return void
     */
    private function insertAoeData(): void
    {
        //TODO: There need to be a better way to handle inserting and updating data to avoid duplicate records. or is there a need to duplicate
        $this->tableName = 'procedure_questions';
        $labId = $this->getQuestProviderId();
        $labId = $labId['ppid'];
        if (!isset($labId)) {
            $msg = xlt('Quest provider not found ');
            $this->logging->error($msg);
            return;
        }
        //if the data is new then insert it and ignore primary key

        $sql = "INSERT INTO `procedure_questions` (`lab_id`, `procedure_code`, `question_code`, `seq`, `question_text`,
                               `required`, `maxsize`, `fldtype`, `options`, `tips`, `activity`)
        VALUES (?, ?, ?, ?, ?, '1', '0', 'T', ?, ?, '1')";
        sqlStatement(
            $sql,
            array($labId, $this->pcode, $this->qcode, $this->seq, $this->qtext, $this->options, $this->tips)
        );
    }

    /**
     * Creates the Quest dataset group in procedure_type table
     * 
     * Checks if the Quest dataset group exists and creates it if needed.
     * This group serves as the parent for all Quest test codes.
     * 
     * @return void
     */
    private function createQuestDatasetGroup(): void
    {
        //does the dataset group exist because this will be done multiple times.
        $dataGroup = $this->dataSetGroup();
        if (!empty($dataGroup['procedure_type_id'])) {
            //if it does exist then do nothing
            return;
        }
        //if not create the group
        $createGroup = "INSERT INTO `procedure_type`
    (`procedure_type_id`,
     `parent`,
     `name`,
     `lab_id`,
     `procedure_code`,
     `procedure_type`,
     `body_site`,
     `specimen`,
     `route_admin`,
     `laterality`,
     `description`,
     `standard_code`,
     `related_code`,
     `units`,
     `range`,
     `seq`,
     `activity`,
     `notes`,
     `transport`,
     `procedure_type_name`
     ) VALUES
    (NULL, 0, 'Quest Clinical Dataset', 1, '', 'grp', '', '', '', '', 'Quest Clinical Dataset', '', '', '', '', 0, 1, '', NULL, 'procedure')";
        sqlStatement($createGroup);
    }

    /**
     * Retrieves the Quest dataset group ID from procedure_type table
     * 
     * Looks up the procedure_type_id for the Quest Clinical Dataset group
     * which serves as the parent for all Quest test codes.
     * 
     * @return array|bool Array with procedure_type_id if found, false otherwise
     */
    public function dataSetGroup(): array|bool
    {
        return sqlQuery("SELECT `procedure_type_id` FROM `procedure_type` WHERE `name` = 'Quest Clinical Dataset'");
    }

    /**
     * Retrieves the Quest provider ID from procedure_providers table
     * 
     * Looks up the ppid for the Quest provider record which is needed
     * for linking lab tests and questions to the correct lab.
     * 
     * @return array Array containing the ppid value
     */
    public function getQuestProviderId(): array
    {
        $this->tableName = 'procedure_providers';
        return sqlQuery("SELECT `ppid` FROM $this->tableName WHERE `name` = ?", ['Quest']);
    }

    /**
     * Checks if a test code already exists in procedure_type table
     * 
     * Prevents duplicate entries by checking if the current test code
     * is already in the database.
     * 
     * @return bool True if the test code exists, false otherwise
     */
    private function checkIfDataExists(): bool
    {
        $sql = "SELECT `procedure_type_id` FROM `procedure_type` WHERE `procedure_code` = ?";
        $data = sqlQuery($sql, [$this->code]);
        return !empty($data['procedure_type_id']);
    }

    /**
     * Recreates the procedure_questions table
     * 
     * Drops and recreates the procedure_questions table to ensure
     * a clean import of AOE questions without constraint issues.
     * 
     * @return bool Always returns true
     */
    private function reloadQuestionBank(): bool
    {
        sqlStatement("DROP TABLE IF EXISTS `procedure_questions`");
        $sql = "CREATE TABLE `procedure_questions` (
  `lab_id`              bigint(20)   NOT NULL DEFAULT 0   COMMENT 'references procedure_providers.ppid to identify the lab',
  `procedure_code`      varchar(31)  NOT NULL DEFAULT ''  COMMENT 'references procedure_type.procedure_code to identify this order type',
  `question_code`       varchar(31)  NOT NULL DEFAULT ''  COMMENT 'code identifying this question',
  `seq`                 int(11)      NOT NULL default 0   COMMENT 'sequence number for ordering',
  `question_text`       varchar(255) NOT NULL DEFAULT ''  COMMENT 'descriptive text for question_code',
  `required`            tinyint(1)   NOT NULL DEFAULT 0   COMMENT '1 = required, 0 = not',
  `maxsize`             int          NOT NULL DEFAULT 0   COMMENT 'maximum length if text input field',
  `fldtype`             char(1)      NOT NULL DEFAULT 'T' COMMENT 'Text, Number, Select, Multiselect, Date, Gestational-age',
  `options`             text                              COMMENT 'choices for fldtype S and T',
  `tips`                varchar(255) NOT NULL DEFAULT ''  COMMENT 'Additional instructions for answering the question',
  `activity`            tinyint(1)   NOT NULL DEFAULT 1   COMMENT '1 = active, 0 = inactive'
) ENGINE=InnoDB";
        sqlStatement($sql);
        return true;
    }

    /**
     * Checks if the procedure_questions table has data
     * 
     * Determines if there are existing questions in the procedure_questions table
     * to decide whether to drop primary keys or recreate the table.
     * 
     * @return bool True if table has data, false if empty
     */
    private function checkPrimarykey(): bool
    {
        $sql = "SELECT COUNT(*) AS row_count FROM procedure_questions";
        $data = sqlQuery($sql);

        return $data['row_count'];
    }
}
