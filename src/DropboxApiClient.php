<?php

namespace Dbox\UploaderApi;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;
use Throwable;

final class DropboxApiClient
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function fetchDropboxToken(string $dropboxRefreshToken, string $dropboxAppKey, string $dropboxAppSecret): array
    {
        $result = [
            'success' => false,
            'access_token' => null,
            'error' => []
        ];

        $repeat = true;

        $attempt = 0;

        while ($repeat && $attempt < 5) {
            $attempt++;

            try {
                $httpResponse = $this->client->post('https://api.dropbox.com/oauth2/token', [
                    'form_params' => [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $dropboxRefreshToken,
                        'client_id' => $dropboxAppKey,
                        'client_secret' => $dropboxAppSecret
                    ]
                ]);

                $fields = $this->extractJsonFields($httpResponse, [
                    'access_token' => null
                ]);

                if (is_null($fields['access_token'])) {
                    throw new RuntimeException('Required fields are missing in the Dropbox API response.');
                }

                $result['success'] = true;
                $result['access_token'] = $fields['access_token'];

                break;
            } catch (Throwable $e) {
                ['type' => $type, 'message' => $message, 'repeat' => $repeat] = self::analyzeException($e);

                $result['error'] = [
                    'action' => 'Fetching Dropbox oauth2/token',
                    'attempt' => $attempt,
                    'type' => $type,
                    'message' => $message,
                    'time' => time()
                ];
            }
        }

        return $result;
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

    private static function analyzeException(Throwable $e): array
    {
        $status = -1;

        if ($e instanceof RequestException) {
            $type = 'RequestException';

            if ($e->hasResponse()) {
                $response = $e->getResponse();

                $status = $response->getStatusCode();

                $message = sprintf('HTTP %d - %s', $status, trim((string) $response->getBody()));
            } else {
                $message = 'No response - ' . $e->getMessage();
            }
        } else {
            if ($e instanceof GuzzleException) {
                $type = 'GuzzleException';
            } elseif ($e instanceof RuntimeException) {
                $type = 'RuntimeException';
            } else {
                $type = 'Exception';
            }

            $message = $e->getMessage();
        }

        $repeat = false;

        if ($status === 429) {
            $repeat = true;

            sleep(10);
        }

        return [
            'type' => $type,
            'message' => $message,
            'repeat' => $repeat
        ];
    }
}
