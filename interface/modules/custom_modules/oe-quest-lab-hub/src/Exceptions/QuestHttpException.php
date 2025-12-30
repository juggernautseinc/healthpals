<?php

/**
 * QuestHttpException
 *
 * Custom exception for HTTP and API-related errors in the Quest Lab Hub module.
 * This helps scope exceptions to identify the source and nature of HTTP errors clearly.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2024 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\Quest\Module\Exceptions;

class QuestHttpException extends \Exception
{
    /**
     * HTTP status code
     * @var int|null
     */
    private ?int $statusCode;

    /**
     * Response body
     * @var string|null
     */
    private ?string $responseBody;

    /**
     * Constructor
     *
     * @param string $message Exception message
     * @param int $code Exception code
     * @param int|null $statusCode HTTP status code
     * @param string|null $responseBody Response body from API
     * @param \Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?int $statusCode = null,
        ?string $responseBody = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    /**
     * Get the HTTP status code
     *
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Get the response body
     *
     * @return string|null
     */
    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
