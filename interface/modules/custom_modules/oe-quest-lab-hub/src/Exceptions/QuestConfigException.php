<?php

/**
 * QuestConfigException
 *
 * Custom exception for configuration-related errors in the Quest Lab Hub module.
 * Thrown when required configuration settings are missing or invalid.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2024 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\Quest\Module\Exceptions;

class QuestConfigException extends \Exception
{
    /**
     * Configuration key that is missing or invalid
     * @var string|null
     */
    private ?string $configKey;

    /**
     * Constructor
     *
     * @param string $message Exception message
     * @param int $code Exception code
     * @param string|null $configKey The configuration key that caused the error
     * @param \Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?string $configKey = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->configKey = $configKey;
    }

    /**
     * Get the configuration key
     *
     * @return string|null
     */
    public function getConfigKey(): ?string
    {
        return $this->configKey;
    }
}
