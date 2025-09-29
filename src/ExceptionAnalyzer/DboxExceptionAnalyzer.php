<?php

namespace Dbox\UploaderApi\ExceptionAnalyzer;

use GuzzleHttp\Exception\RequestException;
use ReflectionClass;
use Throwable;

final class DboxExceptionAnalyzer
{
    private function __construct() {}

    public static function info(Throwable $e): DboxExceptionAnalyzerInfoResult
    {
        $status = -1;

        $type = (new ReflectionClass($e))->getShortName();

        $message = $e->getMessage();

        if ($e instanceof RequestException && $e->hasResponse()) {
            $response = $e->getResponse();

            $status = $response->getStatusCode();

            $message = sprintf('HTTP %d - %s', $status, trim((string) $response->getBody()));
        } elseif ($e instanceof RequestException) {
            $message = 'No response - ' . $message;
        }

        $repeat = $status === 429;

        if ($repeat) {
            sleep(10);
        }

        return new DboxExceptionAnalyzerInfoResult($type, $message, $repeat);
    }
}
