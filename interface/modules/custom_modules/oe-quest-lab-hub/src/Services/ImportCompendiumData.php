<?php

/*
 * package   OpenEMR
 * link           https://open-emr.org
 * author      Sherwin Gaddis <sherwingaddis@gmail.com>
 * Copyright (c) 2024.  Sherwin Gaddis <sherwingaddis@gmail.com>
 */

namespace Juggernaut\Quest\Module\Services;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Logging\SystemLogger;
use Juggernaut\Quest\Module\Exceptions\QuestFileSystemException;

/**
 * ImportCompendiumData
 *
 * Imports Quest compendium data from STL files (ORDCODE_STL.TXT and AOE_STL.TXT)
 * into procedure_type and procedure_questions tables.
 *
 * @package Juggernaut\Quest\Module\Services
 */
class ImportCompendiumData
{
    private ?string $compendiumData = null;
    private ?string $compendiumAoeData = null;
    private SystemLogger $logger;
    private string $tempDir;
    private const DELIMITER = '^';


    /**
     * Constructor
     * Initializes import process for new STL compendium format
     *
     * @throws QuestFileSystemException If required files are not found
     */
    public function __construct()
    {
        $this->logger = new SystemLogger();
        $this->tempDir = $GLOBALS['OE_SITE_DIR'] . '/documents/temp/';
        $this->questLabProviderId = $this->getQuestProviderId();

        try {
            // Import order codes from ORDCODE_.TXT
            $ordcodePath = $this->tempDir . self::buildOrdcodeFileName();
            if (file_exists($ordcodePath)) {
                $this->compendiumData = file_get_contents($ordcodePath);
                $this->importOrderCodes();
                unlink($ordcodePath);
            } else {
                $this->logger->error('ORDCODE_.TXT not found', ['path' => $ordcodePath]);
                throw new QuestFileSystemException('ORDCODE_.TXT not found');
            }

            // Import questions from AOE_.TXT
            $aoePath = $this->tempDir . self::buildAoeFileName();
            if (file_exists($aoePath)) {
                $this->compendiumAoeData = file_get_contents($aoePath);
                $this->importAoeData();
                unlink($aoePath);
            } else {
                $this->logger->error('AOE_.TXT not found', ['path' => $aoePath]);
                throw new QuestFileSystemException('AOE_.TXT not found');
            }

            $this->logger->info('Compendium import completed successfully');
        } catch (QuestFileSystemException $e) {
            $this->logger->error('File system error during compendium import', ['error' => $e->getMessage()]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during compendium import', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function getQuestProviderId(): int
    {
        $questLabProviderId = sqlQuery("SELECT id FROM procedure_providers WHERE name = 'Quest'");
        return $questLabProviderId['id'];
    }

    private function buildOrdcodeFileName(): string
    {
        $receiverId = $this->pullReceiverId();
        return "ORDCODE_" . $receiverId . ".TXT";
    }

    private function buildAoeFileName(): string
    {
        $receiverId = $this->pullReceiverId();
        return "AOE_" . $receiverId . ".TXT";
    }

    private function pullReceiverId(): string
    {
        $receiverId = sqlQuery("SELECT recv_fac_id FROM procedure_providers WHERE name = 'Quest'");
        return $receiverId['recv_fac_id'];
    }

    /**
     * Import order codes from ORDCODE_STL.TXT
     * Maps to procedure_type table
     *
     * @return void
     */
    private function importOrderCodes(): void
    {
        $this->createQuestDatasetGroup();

        $lines = explode("\n", $this->compendiumData);

        foreach ($lines as $lineNumber => $line) {
            // Skip header (MSH record) and empty lines
            if ($lineNumber === 0 || empty(trim($line))) {
                continue;
            }

            try {
                $fields = explode(self::DELIMITER, $line);

                // Validate required fields
                if (empty($fields[1]) || !isset($fields[4])) {
                    continue; // Skip invalid lines
                }

                // Only import active records
                if (trim($fields[4]) !== 'A') {
                    continue;
                }

                $procedureCode = trim($fields[1]);
                $testName = trim($fields[6] ?? '');
                $specimenType = trim($fields[7] ?? '');
                $notes = trim($fields[8] ?? '');

                // Skip if procedure code already exists
                if ($this->procedureCodeExists($procedureCode)) {
                    continue;
                }

                $this->insertOrderCode($procedureCode, $testName, $specimenType, $notes);
            } catch (\Exception $e) {
                $this->logger->warning('Error importing order code', [
                    'line' => $lineNumber,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
    }

    /**
     * Import questions from AOE_STL.TXT
     * Maps to procedure_questions table
     *
     * @return void
     */
    private function importAoeData(): void
    {
        $lines = explode("\n", $this->compendiumAoeData);
        $questionsImported = 0;

        foreach ($lines as $lineNumber => $line) {
            // Skip header (MSH record) and empty lines
            if ($lineNumber === 0 || empty(trim($line))) {
                continue;
            }

            try {
                $fields = explode(self::DELIMITER, $line);

                // Validate required fields
                if (empty($fields[3]) || empty($fields[4]) || !isset($fields[6])) {
                    continue; // Skip invalid lines
                }

                // Only import active records
                if (trim($fields[6]) !== 'A') {
                    continue;
                }

                $procedureCode = trim($fields[3]);
                $questionCode = trim($fields[4]);
                $questionText = trim($fields[9] ?? '');
                $tips = trim($fields[11] ?? '');
                $fieldType = $this->mapFieldType(trim($fields[13] ?? 'Q'));

                // Only import if procedure code exists
                if (!$this->procedureCodeExists($procedureCode)) {
                    continue;
                }

                $this->insertQuestion($procedureCode, $questionCode, $questionText, $tips, $fieldType);
                $questionsImported++;
            } catch (\Exception $e) {
                $this->logger->warning('Error importing question', [
                    'line' => $lineNumber,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        if ($questionsImported > 0) {
            $this->logger->info('Questions imported', ['count' => $questionsImported]);
        }
    }
    /**
     * Insert order code into procedure_type table
     *
     * @param string $procedureCode
     * @param string $testName
     * @param string $specimenType
     * @param string $notes
     * @return void
     */
    private function insertOrderCode(string $procedureCode, string $testName, string $specimenType, string $notes): void
    {
        $parentId = $this->getDatasetGroupId();

        if (empty($parentId) || empty($procedureCode)) {
            $this->logger->error('Missing required data for order code insert', [
                'procedure_code' => $procedureCode,
                'parent_id' => $parentId
            ]);
            return;
        }

        $sql = "INSERT INTO `procedure_type` (
            `parent`, `name`, `lab_id`, `procedure_code`, `procedure_type`,
            `body_site`, `specimen`, `route_admin`, `laterality`,
            `description`, `standard_code`, `related_code`, `units`, `range`,
            `seq`, `activity`, `notes`, `transport`, `procedure_type_name`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        try {
            QueryUtils::sqlInsert(
                $sql,
                [
                    $parentId,           // parent
                    $testName,           // name
                    $this->questLabProviderId, // lab_id
                    $procedureCode,      // procedure_code
                    'ord',               // procedure_type (order)
                    '',                  // body_site
                    $specimenType,       // specimen
                    '',                  // route_admin
                    '',                  // laterality
                    $testName,           // description
                    '',                  // standard_code
                    '',                  // related_code
                    '',                  // units
                    '',                  // range
                    0,                   // seq
                    1,                   // activity
                    $notes,              // notes
                    null,                // transport
                    'laboratory_test'    // procedure_type_name
                ]
            );
            $this->logger->debug('Order code inserted', ['code' => $procedureCode]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to insert order code', [
                'procedure_code' => $procedureCode,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Insert question into procedure_questions table
     *
     * @param string $procedureCode
     * @param string $questionCode
     * @param string $questionText
     * @param string $tips
     * @param string $fieldType
     * @return void
     */
    private function insertQuestion(string $procedureCode, string $questionCode, string $questionText, string $tips, string $fieldType): void
    {
        $sql = "INSERT INTO `procedure_questions` (
            `lab_id`, `procedure_code`, `question_code`, `seq`, `question_text`,
            `required`, `maxsize`, `fldtype`, `options`, `tips`, `activity`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        try {
            QueryUtils::sqlInsert(
                $sql,
                [
                    $this->questLabProviderId, // lab_id
                    $procedureCode,      // procedure_code
                    $questionCode,       // question_code
                    0,                   // seq (can be enhanced)
                    $questionText,       // question_text
                    1,                   // required (default)
                    0,                   // maxsize
                    $fieldType,          // fldtype
                    '',                  // options
                    $tips,               // tips
                    1                    // activity
                ]
            );
            $this->logger->debug('Question inserted', ['code' => $questionCode]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to insert question', [
                'question_code' => $questionCode,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create Quest Clinical Dataset group if it doesn't exist
     * This is the parent category for all Quest orders
     *
     * @return void
     */
    private function createQuestDatasetGroup(): void
    {
        $groupId = $this->getDatasetGroupId();

        // Group already exists
        if (!empty($groupId)) {
            return;
        }

        $sql = "INSERT INTO `procedure_type` (
            `parent`, `name`, `lab_id`, `procedure_code`, `procedure_type`,
            `body_site`, `specimen`, `route_admin`, `laterality`,
            `description`, `standard_code`, `related_code`, `units`, `range`,
            `seq`, `activity`, `notes`, `transport`, `procedure_type_name`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        try {
            QueryUtils::sqlInsert(
                $sql,
                [
                    0,                   // parent
                    'Quest Clinical Dataset', // name
                    $this->questLabProviderId,  // lab_id
                    '',                  // procedure_code
                    'grp',               // procedure_type (group)
                    '',                  // body_site
                    '',                  // specimen
                    '',                  // route_admin
                    '',                  // laterality
                    'Quest Clinical Dataset', // description
                    '',                  // standard_code
                    '',                  // related_code
                    '',                  // units
                    '',                  // range
                    0,                   // seq
                    1,                   // activity
                    '',                  // notes
                    null,                // transport
                    'procedure'          // procedure_type_name
                ]
            );
            $this->logger->info('Quest Clinical Dataset group created');
        } catch (\Exception $e) {
            $this->logger->error('Failed to create Quest Clinical Dataset group', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get the procedure_type_id for Quest Clinical Dataset group
     *
     * @return int|null
     */
    private function getDatasetGroupId(): ?int
    {
        $sql = "SELECT `procedure_type_id` FROM `procedure_type` WHERE `name` = ? LIMIT 1";

        try {
            $result = QueryUtils::querySingleRow($sql, ['Quest Clinical Dataset']);
            return $result['procedure_type_id'] ?? null;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching dataset group', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Check if procedure code already exists
     *
     * @param string $procedureCode
     * @return bool
     */
    private function procedureCodeExists(string $procedureCode): bool
    {
        $sql = "SELECT COUNT(*) as count FROM `procedure_type` WHERE `procedure_code` = ? LIMIT 1";

        try {
            $result = QueryUtils::querySingleRow($sql, [$procedureCode]);
            return (int) ($result['count'] ?? 0) > 0;
        } catch (\Exception $e) {
            $this->logger->error('Error checking procedure code', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Map field type code to database field type
     *
     * @param string $fieldTypeCode Code from AOE file (Q, S, N, T, D, etc.)
     * @return string Database field type (T, S, N, D)
     */
    private function mapFieldType(string $fieldTypeCode): string
    {
        return match (strtoupper(trim($fieldTypeCode))) {
            'S' => 'S',      // Select/Dropdown
            'N' => 'N',      // Number
            'D' => 'D',      // Date
            'T' => 'T',      // Text
            'Q' => 'T',      // Question type defaults to Text
            default => 'T'   // Default to Text
        };
    }
}
