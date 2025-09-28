<?php

namespace Dbox\UploaderApi;

use GuzzleHttp\Exception\RequestException;
use ReflectionClass;
use Throwable;

final class ExceptionAnalyzer
{
    private function __construct() {}

    public static function analyze(Throwable $e): array
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

        return [
            'type' => $type,
            'message' => $message,
            'repeat' => $repeat
        ];
    }
}
