<?php

    /**
     * Bootstrap file for the Quest Lab Hub Module
     *
     * This file serves as the main entry point for the Quest Lab Hub module,
     * responsible for initializing the module, setting up event listeners,
     * managing global configurations, and handling lab order transmission.
     *
     * @package   OpenEMR
     * @link      http://www.open-emr.org
     *
     * @author    Stephen Nielson <stephen@nielson.org>
     * @copyright Copyright (c) 2021 Stephen Nielson <stephen@nielson.org>
     * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
     * @copyright Copyright (c) 2023 Sherwin Gaddis <sherwingaddis@gmail.com>
     * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
     */

    namespace Juggernaut\Quest\Module;

    require_once(__DIR__ . '/../../../../../library/classes/Document.class.php');

    /**
     * Note the below use statements are importing classes from the OpenEMR core codebase
     */
    use OpenEMR\Common\Logging\SystemLogger;
    use OpenEMR\Core\Kernel;
    use OpenEMR\Events\Encounter\EncounterButtonEvent;
    use OpenEMR\Events\Globals\GlobalsInitializedEvent;
    use OpenEMR\Events\Services\QuestLabTransmitEvent;
    use OpenEMR\Services\Globals\GlobalSetting;
    use OpenEMR\Menu\MenuEvent;
    use Symfony\Component\EventDispatcher\EventDispatcherInterface;
    use Document;

    /**
     * Class Bootstrap
     *
     * The Bootstrap class serves as the main initialization point for the Quest Lab Hub module.
     * It manages the integration between OpenEMR and Quest Diagnostics' Quantum Hub system,
     * handling lab order transmission, requisition form generation, and results retrieval.
     * This class configures all necessary event listeners, global settings, and menu items.
     *
     * @package Juggernaut\Quest\Module
     */
    class Bootstrap
    {
        /**
         * Path to the OpenEMR custom modules directory
         * @var string
         */
        const MODULE_INSTALLATION_PATH = "/interface/modules/custom_modules/";

        /**
         * Module name identifier
         * @var string
         */
        const MODULE_NAME = "oe-module-quest-lab-hub";

        /**
         * URL for Quest Hub testing environment
         * @var string
         */
        const HUB_RESOURCE_TESTING_URL = "https://certhubservices.quanum.com";

        /**
         * URL for Quest Hub production environment
         * @var string
         */
        const HUB_RESOURCE_PRODUCTION_URL = "https://hubservices.quanum.com";

        /**
         * The object responsible for sending and subscribing to events through the OpenEMR system
         * @var EventDispatcherInterface
         */
        private EventDispatcherInterface $eventDispatcher;

        /**
         * Holds our module global configuration values that can be used throughout the module
         * @var QuestGlobalConfig
         */
        private QuestGlobalConfig $globalsConfig;

        /**
         * The folder name of the module, set dynamically from searching the filesystem
         * @var string
         */
        private $moduleDirectoryName;

        /**
         * The name of the requisition form file, used to track downloaded documents
         * @var string
         */
        public string $requisitionFormName;

        /**
         * Logger instance for recording system events
         * @var SystemLogger
         */
        private $logger;

        /**
         * Constructor for the Bootstrap class
         *
         * Initializes the module, sets up the directory structure, global configuration,
         * and prepares the logger.
         *
         * @param EventDispatcherInterface $eventDispatcher The OpenEMR event dispatcher
         */
        public function __construct(EventDispatcherInterface $eventDispatcher)
        {

            if (empty($kernel)) {
                $kernel = new Kernel();
            }

            // we inject our globals value.
            $this->moduleDirectoryName = basename(dirname(__DIR__));
            $this->eventDispatcher = $eventDispatcher;

            $this->globalsConfig = new QuestGlobalConfig($GLOBALS);
            $this->logger = new SystemLogger();
        }

        /**
         * Returns the path where requisition forms are stored
         *
         * @return string Absolute path to the requisition forms directory
         */
        public static function requisitionFormPath(): string
        {
            return dirname(__DIR__, 5) . "/sites/" . $_SESSION['site_id'] . "/documents/labs/";
        }

        /**
         * Subscribes to all necessary events for module functionality
         *
         * Sets up global settings and, if the module is fully configured,
         * registers menu items and subscribes to lab transmission and
         * encounter form events.
         *
         * @return void
         */
        public function subscribeToEvents(): void
        {
            $this->addGlobalSettings();
            // we only add the rest of our event listeners and configuration if we have been fully setup and configured
            if ($this->globalsConfig->isQuestConfigured()) {
                $this->registerMenuItems();
                $this->subscribeToLabTransmissionEvents();
                $this->subscribeToEncounterFormEvents();
            }
        }

        /**
         * Subscribes to lab transmission events for order processing
         *
         * Sets up event listeners for lab order transmission and post-order processing
         *
         * @return void
         */
        public function subscribeToLabTransmissionEvents(): void
        {
            $this->eventDispatcher->addListener(QuestLabTransmitEvent::EVENT_LAB_TRANSMIT, [$this, 'sendOrderToQuestLab']);
            $this->eventDispatcher->addListener(QuestLabTransmitEvent::EVENT_LAB_POST_ORDER_LOAD, [$this, 'downloadPdfToDesktop']);
        }

        /**
         * Subscribes to encounter form events for UI integration
         *
         * Sets up event listeners for the encounter form to add specimen label buttons
         *
         * @return void
         */
        public function subscribeToEncounterFormEvents(): void
        {
            $this->eventDispatcher->addListener(EncounterButtonEvent::BUTTON_RENDER, [$this, 'encounterButtonRender']);
        }

        /**
         * Adds specimen label button to encounter forms
         *
         * Event handler that adds a specimen label button to the encounter form
         *
         * @param EncounterButtonEvent $event The encounter button event
         * @return void
         */
        public function encounterButtonRender(EncounterButtonEvent $event): void
        {
            $addButtonEncounterForm = new AddButtonEncounterForm();
            $event->setButton($addButtonEncounterForm->specimenLabelButton());
        }

        /**
         * Processes and sends a lab order to Quest Diagnostics
         *
         * Handles the lab order transmission event, processes the order data,
         * and optionally generates a requisition form if configured.
         *
         * @param QuestLabTransmitEvent $event The lab transmit event containing order data
         * @return void
         */
        public function sendOrderToQuestLab(QuestLabTransmitEvent $event): void
        {
            $order = $event->getOrder(); // get the order from the event
            $result = new ProcessLabOrder($order); // create a new process lab order We might want to return errors here
            //This logging is for debugging purposes

            $location = dirname(__DIR__, 5) . "/sites/" . $_SESSION['site_id'] . "/documents/labs/";
            file_put_contents($location . 'labOrderResultDebug.txt', print_r($result, true) .PHP_EOL, FILE_APPEND);

            $this->logger->info('Order transmitted to Quest successfully', [
                'order_id' => $event->getOrderId()
            ]);
        }

        /**
         * Stores the requisition document in the OpenEMR documents database table
         *
         * @param array $requisitionData Array containing 'filename' and 'binary' keys
         * @param int $orderId The procedure_order_id
         * @param int $patientId The patient ID
         * @return void
         */
        private function storeRequisitionDocument(array $requisitionData, int $orderId, int $patientId): void
        {
            try {
                if (empty($requisitionData['binary']) || empty($requisitionData['filename'])) {
                    $this->logger->warning('Invalid requisition data for database storage');
                    return;
                }

                // Get or create the "Lab Report" category for documents
                $categoryResult = sqlQuery(
                    "SELECT id FROM categories WHERE name LIKE ? LIMIT 1",
                    array('Lab%Report%')
                );
                $categoryId = $categoryResult['id'] ?? 0;

                if (empty($categoryId)) {
                    // If no Lab Report category exists, try a default category
                    $categoryResult = sqlQuery(
                        "SELECT id FROM categories WHERE name LIKE ? LIMIT 1",
                        array('Documents%')
                    );
                    $categoryId = $categoryResult['id'] ?? 0;
                }

                if (!empty($categoryId)) {
                    $document = new Document();
                    $pdfData = $requisitionData['binary'];
                    $filename = $requisitionData['filename'];

                    $error = $document->createDocument(
                        $patientId,
                        $categoryId,
                        $filename,
                        'application/pdf',
                        $pdfData,
                        'quest',
                        1,
                        $_SESSION['authUserID'] ?? 0,
                        null,
                        null,
                        $orderId,
                        'procedure_order'
                    );

                    if (empty($error)) {
                        // Update documentationOf field to identify as requisition
                        sqlStatement(
                            "UPDATE documents SET documentationOf = ? WHERE id = ?",
                            array('REQ', $document->get_id())
                        );
                        $this->logger->info('Requisition document stored successfully', [
                            'document_id' => $document->get_id(),
                            'order_id' => $orderId
                        ]);
                    } else {
                        $this->logger->error('Failed to create requisition document', ['error' => $error]);
                    }
                } else {
                    $this->logger->warning('No document category found for storing requisition');
                }
            } catch (\Exception $e) {
                $this->logger->error('Exception while storing requisition document', [
                    'error' => $e->getMessage(),
                    'order_id' => $orderId
                ]);
            }
        }

        /**
         * Fetches requisition document and downloads to desktop
         *
         * This is called AFTER the order has been transmitted to Quest.
         * It fetches the requisition document from Quest and stores it.
         * Uses retry logic with exponential backoff as Quest needs time to process the order.
         *
         * @param QuestLabTransmitEvent $event The lab transmit event containing order data
         * @return void
         */
        public function downloadPdfToDesktop(QuestLabTransmitEvent $event): void
        {
            // Only fetch requisition if enabled in globals
            if (!$GLOBALS['oe_quest_download_requisition']) {
                return;
            }

            $order = $event->getOrder();
            $maxRetries = 3;
            $retryDelay = 2; // Initial delay in seconds
            $lastException = null;
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    // Give Quest time to process the order before requesting requisition
                    // Quest needs time to receive and process the HL7 order before it can generate the requisition
                    if ($attempt > 1) {
                        $this->logger->info('Retrying requisition fetch', [
                            'attempt' => $attempt,
                            'delay' => $retryDelay,
                            'order_id' => $event->getOrderId()
                        ]);
                    } else {
                        $this->logger->info('Requesting requisition document from Quest', [
                            'order_id' => $event->getOrderId()
                        ]);
                    }
                    
                    sleep($retryDelay);
                    
                    $pdf = new ProcessRequisitionDocument($order);
                    $result = $pdf->sendRequest();

                    // Store the filename for desktop download and database storage
                    if (is_array($result)) {
                        $this->requisitionFormName = $result['filename'];
                        
                        // Store document in database if order ID is available
                        if ($event->getOrderId()) {
                            $this->storeRequisitionDocument(
                                $result,
                                $event->getOrderId(),
                                $event->getPatientId()
                            );
                        }
                        
                        // Download to user's desktop
                        $sendDownload = new DownloadRequisition();
                        if (!empty($this->requisitionFormName)) {
                            $this->logger->info('Downloading requisition to desktop', [
                                'filename' => $this->requisitionFormName,
                                'attempt' => $attempt
                            ]);
                            $sendDownload->downloadLabPdfRequisition($this->requisitionFormName);
                        }
                        
                        // Success - break out of retry loop
                        return;
                    } else {
                        $this->logger->warning('Requisition request returned non-array result', [
                            'attempt' => $attempt
                        ]);
                        throw new \Exception('Invalid requisition response format');
                    }
                } catch (\Exception $e) {
                    $lastException = $e;
                    $this->logger->warning('Requisition fetch attempt failed', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'error' => $e->getMessage(),
                        'order_id' => $event->getOrderId()
                    ]);
                    
                    // If this is not the last attempt, increase delay for next retry (exponential backoff)
                    if ($attempt < $maxRetries) {
                        $retryDelay = $retryDelay * 2; // Double the delay for next attempt
                    }
                }
            }
            
            // All retries failed
            $this->logger->error('Failed to fetch requisition after all retries', [
                'attempts' => $maxRetries,
                'last_error' => $lastException ? $lastException->getMessage() : 'Unknown error',
                'order_id' => $event->getOrderId()
            ]);
        }

        /**
         * Returns the module's global configuration
         *
         * @return QuestGlobalConfig The module's global configuration
         */
        public function getGlobalConfig(): QuestGlobalConfig
        {
            return $this->globalsConfig;
        }

        /**
         * Sets up global settings for the module
         *
         * Registers an event listener to initialize global settings
         * when OpenEMR loads globals.
         *
         * @return void
         */
        public function addGlobalSettings(): void
        {
            $this->eventDispatcher->addListener(GlobalsInitializedEvent::EVENT_HANDLE, [$this, 'addGlobalQuestSettingsSection']);
        }

        /**
         * Creates and configures the Quest Lab global settings section
         *
         * Event handler that adds Quest Lab-specific settings to the
         * OpenEMR global configuration.
         *
         * @param GlobalsInitializedEvent $event The globals initialized event
         * @return void
         */
        public function addGlobalQuestSettingsSection(GlobalsInitializedEvent $event): void
        {

            $service = $event->getGlobalsService();
            $section = xlt("Quest Lab");
            $service->createSection($section, 'Portal');

            $settings = $this->globalsConfig->getGlobalSettingSectionConfiguration();

            foreach ($settings as $key => $config) {
                $value = $GLOBALS[$key] ?? $config['default'];
                $service->appendToSection(
                    $section,
                    $key,
                    new GlobalSetting(
                        xlt($config['title']),
                        $config['type'],
                        $value,
                        xlt($config['description']),
                        true
                    )
                );
            }
        }

        /**
         * Registers menu items for the module
         *
         * If the module is enabled in the global configuration,
         * adds the menu item event listener.
         *
         * @return void
         */
        public function registerMenuItems()
        {
            if ($this->getGlobalConfig()->getGlobalSetting(QuestGlobalConfig::CONFIG_ENABLE_QUEST)) {
                /**
                 * @var EventDispatcherInterface $eventDispatcher
                 * @var array $module
                 * @global                       $eventDispatcher @see ModulesApplication::loadCustomModule
                 * @global                       $module @see ModulesApplication::loadCustomModule
                 */
                $this->eventDispatcher->addListener(MenuEvent::MENU_UPDATE, [$this, 'addCustomModuleMenuItem']);
            }
        }

        /**
         * Adds the module's menu item to the OpenEMR menu system
         *
         * Creates and configures the Quest Lab Hub menu item
         * with appropriate permissions and settings.
         *
         * @param MenuEvent $event The menu event
         * @return MenuEvent The modified menu event
         */
        public function addCustomModuleMenuItem(MenuEvent $event): MenuEvent
        {
            $menu = $event->getMenu();

            $menuItem = new \stdClass();
            $menuItem->requirement = 0;
            $menuItem->target = 'mod';
            $menuItem->menu_id = 'mod0';
            $menuItem->label = xlt("Quest Lab Hub");
            // TODO: pull the install location into a constant into the codebase so if OpenEMR changes this location it
            // doesn't break any modules.
            $menuItem->url = "/interface/modules/custom_modules/oe-quest-lab-hub/public/index.php";
            $menuItem->children = [];

            /**
             * This defines the Access Control List properties that are required to use this module.
             * Several examples are provided
             */
            $menuItem->acl_req = [];

            /**
             * If you would like to restrict this menu to only logged in users who have access to see all user data
             */
            $menuItem->acl_req = ["admin", "users"];

            /**
             * If you would like to restrict this menu to logged in users who can access patient demographic information
             */
            //$menuItem->acl_req = ["users", "demo"];


            /**
             * This menu flag takes a boolean property defined in the $GLOBALS array that OpenEMR populates.
             * It allows a menu item to display if the property is true, and be hidden if the property is false
             */
            $menuItem->global_req = ["custom_quest_module_enable"];

            /**
             * If you want your menu item to allows be shown then leave this property blank.
             */
            $menuItem->global_req = [];

            foreach ($menu as $item) {
                if ($item->menu_id == 'modimg') {
                    $item->children[] = $menuItem;
                    break;
                }
            }

            $event->setMenu($menu);

            return $event;
        }


        /**
         * Gets the public path for the module
         *
         * @return string Path to the module's public directory
         */
        private function getPublicPath()
        {
            return self::MODULE_INSTALLATION_PATH . ($this->moduleDirectoryName ?? '') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR;
        }

        /**
         * Gets the asset path for the module
         *
         * @return string Path to the module's asset directory
         */
        private function getAssetPath()
        {
            return $this->getPublicPath() . 'assets' . DIRECTORY_SEPARATOR;
        }
    }
