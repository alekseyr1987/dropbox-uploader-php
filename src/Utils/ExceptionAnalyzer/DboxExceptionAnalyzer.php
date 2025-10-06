<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\Utils\ExceptionAnalyzer;

use GuzzleHttp\Exception\RequestException;
use ReflectionClass;
use Throwable;

/**
 * Utility class for analyzing exceptions from Dropbox API operations.
 *
 * Provides structured information about the exception type, message, and whether the operation should be retried.
 */
final class DboxExceptionAnalyzer
{
    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct() {}

    /**
     * Returns structured information about an exception.
     *
     * Detects HTTP request exceptions and formats the message accordingly. Determines whether the operation should be repeated (e.g., HTTP 429 Too Many Requests).
     *
     * @param Throwable $e The exception to analyze
     *
     * @return DboxExceptionAnalyzerInfoResult Structured exception information
     */
    public static function info(Throwable $e): DboxExceptionAnalyzerInfoResult
    {
        $status = -1;

        $type = (new ReflectionClass($e))->getShortName();

        if ($e instanceof RequestException) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();

                $status = $response->getStatusCode(); // @phpstan-ignore method.nonObject

                $body = trim((string) $response->getBody()); // @phpstan-ignore method.nonObject

                $message = "HTTP $status - $body";
            } else {
                $message = "No response - {$e->getMessage()}";
            }
        } else {
            $message = $e->getMessage();
        }

        $repeat = $status === 429;

        if ($repeat) {
            sleep(10);
        }

        return new DboxExceptionAnalyzerInfoResult($type, $message, $repeat);
    }
}
