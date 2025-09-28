<?php

namespace Dbox\UploaderApi;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client;
use RuntimeException;
use Throwable;

final class DropboxApiClient
{
    private Client $client;

    private function __construct(Client $client)
    {
        $this->client = $client;
    }

    public static function create(array $config = []): DropboxCreateClientResult
    {
        try {
            $client = new Client($config);

            return DropboxCreateClientResult::success(new self($client));
        } catch (Throwable $e) {
            ['type' => $type, 'message' => $message] = ExceptionAnalyzer::analyze($e);

            return DropboxCreateClientResult::failure([
                'type' => $type,
                'message' => $message,
                'time' => time()
            ]);
        }
    }

    public function fetchDropboxToken(string $dropboxRefreshToken, string $dropboxAppKey, string $dropboxAppSecret): DropboxFetchTokenResult
    {
        $attempt = 0;

        while ($attempt < 5) {
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

                return DropboxFetchTokenResult::success($fields['access_token']);
            } catch (Throwable $e) {
                ['type' => $type, 'message' => $message, 'repeat' => $repeat] = ExceptionAnalyzer::analyze($e);

                if (!$repeat) {
                    return DropboxFetchTokenResult::failure([
                        'action' => 'Fetching Dropbox oauth2/token',
                        'attempt' => $attempt,
                        'type' => $type,
                        'message' => $message,
                        'time' => time()
                    ]);
                }
            }
        }

        return DropboxFetchTokenResult::failure([
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
