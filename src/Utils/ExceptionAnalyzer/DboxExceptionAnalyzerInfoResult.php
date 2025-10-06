<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\Utils\ExceptionAnalyzer;

/**
 * Represents analyzed exception information.
 *
 * Contains:
 * - `type`: The short class name of the exception.
 * - `message`: Formatted exception message.
 * - `repeat`: Whether the operation should be retried (e.g., HTTP 429).
 */
final class DboxExceptionAnalyzerInfoResult
{
    /**
     * Short class name of the exception.
     *
     * @var string
     */
    public string $type;

    /**
     * Formatted exception message.
     *
     * @var string
     */
    public string $message;

    /**
     * Indicates whether the operation should be repeated.
     *
     * @var bool
     */
    public bool $repeat;

    /**
     * Initializes the analyzed exception information result.
     *
     * @param string $type Short class name of the exception
     * @param string $message Formatted exception message
     * @param bool $repeat Whether the operation should be retried
     */
    public function __construct(string $type, string $message, bool $repeat)
    {
        $this->type = $type;
        $this->message = $message;
        $this->repeat = $repeat;
    }
}
