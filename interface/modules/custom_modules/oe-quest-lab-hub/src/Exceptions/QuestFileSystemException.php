<?php

/**
 * QuestFileSystemException
 *
 * Custom exception for filesystem-related errors in the Quest Lab Hub module.
 * Thrown when file operations fail (create, read, write, delete).
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2024 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\Quest\Module\Exceptions;

class QuestFileSystemException extends \Exception
{
    /**
     * Path that caused the error
     * @var string|null
     */
    private ?string $path;

    /**
     * Operation that failed (create, read, write, delete)
     * @var string|null
     */
    private ?string $operation;

    /**
     * Constructor
     *
     * @param string $message Exception message
     * @param int $code Exception code
     * @param string|null $path The file path that caused the error
     * @param string|null $operation The operation that failed
     * @param \Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?string $path = null,
        ?string $operation = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->path = $path;
        $this->operation = $operation;
    }

    /**
     * Get the path
     *
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Get the operation
     *
     * @return string|null
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }
}
