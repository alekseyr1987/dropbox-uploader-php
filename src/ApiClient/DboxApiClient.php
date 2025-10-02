<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\ApiClient;

use Dbox\UploaderApi\ExceptionAnalyzer\DboxExceptionAnalyzer;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client;
use RuntimeException;
use Throwable;

final class DboxApiClient
{
    private Client $client;

    private function __construct(array $config)
    {
        $this->client = new Client($config);
    }

    public static function create(array $config = []): DboxApiClientCreateResult
    {
        try {
            return DboxApiClientCreateResult::success(new self($config));
        } catch (Throwable $e) {
            $errorInfo = DboxExceptionAnalyzer::info($e);

            return DboxApiClientCreateResult::failure([
                'type' => $errorInfo->type,
                'message' => $errorInfo->message,
                'time' => time()
            ]);
        }
    }

    public function fetchDropboxToken(string $refreshToken, string $appKey, string $appSecret): DboxApiClientFetchTokenResult
    {
        $attempt = 0;

        while ($attempt < 5) {
            $attempt++;

            try {
                $httpResponse = $this->client->post('https://api.dropbox.com/oauth2/token', [
                    'form_params' => [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $refreshToken,
                        'client_id' => $appKey,
                        'client_secret' => $appSecret
                    ]
                ]);

                $fields = $this->extractJsonFields($httpResponse, [
                    'access_token' => null
                ]);

                if (is_null($fields['access_token'])) {
                    throw new RuntimeException('Required fields are missing in the Dropbox API response.');
                }

                return DboxApiClientFetchTokenResult::success($fields['access_token']);
            } catch (Throwable $e) {
                $errorInfo = DboxExceptionAnalyzer::info($e);

                if (!$errorInfo->repeat) {
                    return DboxApiClientFetchTokenResult::failure([
                        'action' => 'Fetching Dropbox oauth2/token',
                        'attempt' => $attempt,
                        'type' => $errorInfo->type,
                        'message' => $errorInfo->message,
                        'time' => time()
                    ]);
                }
            }
        }

        return DboxApiClientFetchTokenResult::failure([
            'action' => 'Fetching Dropbox oauth2/token',
            'attempt' => $attempt,
            'type' => 'MaxAttemptsExceeded',
            'message' => 'Exceeded maximum retry attempts.',
            'time' => time()
        ]);
    }

    private function extractJsonFields(ResponseInterface $response, array $pathsWithDefaults): array
    {
        $result = [];

        if ($response->getStatusCode() !== 200) {
            return $pathsWithDefaults;
        }

        $data = json_decode((string) $response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return $pathsWithDefaults;
        }

        foreach ($pathsWithDefaults as $path => $default) {
            $result[$path] = $this->getValueByPath($data, $path, $default);
        }

        return $result;
    }

    private function getValueByPath(array $data, string $path, $default)
    {
        $segments = explode(':', $path);

        $current = $data;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } elseif (is_array($current) && is_numeric($segment) && array_key_exists((int) $segment, $current)) {
                $current = $current[(int) $segment];
            } else {
                return $default;
            }
        }

        return $current;
    }
}
