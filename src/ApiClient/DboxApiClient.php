<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\ApiClient;

use Dbox\UploaderApi\Utils\ExceptionAnalyzer\DboxExceptionAnalyzer;
use Dbox\UploaderApi\Utils\JsonDecoder\DboxJsonDecoder;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * Client for interacting with the Dropbox API.
 *
 * Provides methods for performing API requests and handling responses, encapsulating success and error states in result objects.
 *
 * Example usage:
 * ```
 * $clientResult = DboxApiClient::create();
 *
 * if ($clientResult->isSuccess()) {
 *     $client = $clientResult->getClient();
 *
 *     $tokenResult = $client->fetchDropboxToken('APrChJVJXAYTTAAAAAANAa5fBLvJJ4L9yS1f0A7FX6CumHfh52L4PhsFu6hamyG_', '1z968ffbd0gnbbo', 'csuqk6t2gmcjdak');
 *
 *     if ($tokenResult->isSuccess()) {
 *         $accessToken = $tokenResult->getAccessToken();
 *     } else {
 *         $error = $tokenResult->getError();
 *     }
 * } else {
 *     $error = $clientResult->getError();
 * }
 * ```
 */
final class DboxApiClient
{
    /**
     * Guzzle HTTP client used for making API requests.
     *
     * @var Client HTTP client instance
     */
    private Client $client;

    /**
     * Private constructor. Use static `create()` method to instantiate.
     *
     * @param array<string, mixed> $config Guzzle client configuration
     */
    private function __construct(array $config)
    {
        $this->client = new Client($config);
    }

    /**
     * Creates a new `DboxApiClient` wrapped in a result object.
     *
     * Handles exceptions during instantiation and returns a `DboxApiClientCreateResult`.
     *
     * @param array<string, mixed> $config Optional Guzzle client configuration
     *
     * @return DboxApiClientCreateResult Result object containing either a `DboxApiClient` instance or error details
     */
    public static function create(array $config = []): DboxApiClientCreateResult
    {
        try {
            return DboxApiClientCreateResult::success(new self($config));
        } catch (\Throwable $e) {
            $error = DboxExceptionAnalyzer::info($e);

            return DboxApiClientCreateResult::failure(
                [
                    'type' => $error->type,
                    'message' => $error->message,
                    'time' => time(),
                ]
            );
        }
    }

    /**
     * Fetches a Dropbox OAuth2 access token using a refresh token.
     *
     * Retries up to 5 times for transient errors.
     *
     * @param string $refreshToken Dropbox refresh token
     * @param string $appKey       Dropbox app key
     * @param string $appSecret    Dropbox app secret
     *
     * @return DboxApiClientFetchTokenResult The result of the token fetch operation
     */
    public function fetchDropboxToken(string $refreshToken, string $appKey, string $appSecret): DboxApiClientFetchTokenResult
    {
        $attempt = 0;

        while ($attempt < 5) {
            ++$attempt;

            try {
                $httpResponse = $this->client->post(
                    'https://api.dropbox.com/oauth2/token',
                    [
                        'form_params' => [
                            'grant_type' => 'refresh_token',
                            'refresh_token' => $refreshToken,
                            'client_id' => $appKey,
                            'client_secret' => $appSecret,
                        ],
                    ]
                );

                $fields = $this->extractJsonFields(
                    $httpResponse,
                    [
                        'access_token' => null,
                    ]
                );

                if (!is_string($fields['access_token'])) {
                    throw new \RuntimeException("Required field 'access_token' are missing in the Dropbox API response.");
                }

                if ('' === $fields['access_token']) {
                    throw new \RuntimeException("Required field 'access_token' are invalid in the Dropbox API response.");
                }

                return DboxApiClientFetchTokenResult::success($fields['access_token']);
            } catch (\Throwable $e) {
                $error = DboxExceptionAnalyzer::info($e);

                if (!$error->repeat) {
                    return DboxApiClientFetchTokenResult::failure(
                        [
                            'action' => 'Fetching Dropbox oauth2/token',
                            'attempt' => $attempt,
                            'type' => $error->type,
                            'message' => $error->message,
                            'time' => time(),
                        ]
                    );
                }
            }
        }

        return DboxApiClientFetchTokenResult::failure(
            [
                'action' => 'Fetching Dropbox oauth2/token',
                'attempt' => $attempt,
                'type' => 'MaxAttemptsExceeded',
                'message' => 'Exceeded maximum retry attempts.',
                'time' => time(),
            ]
        );
    }

    /**
     * Extracts specific fields from a JSON HTTP response.
     *
     * If the response code is not 200 or the response body is invalid JSON, default values are returned for each requested path.
     *
     * @param ResponseInterface    $response          HTTP response object
     * @param array<string, mixed> $pathsWithDefaults Keys are field paths, values are default values
     *
     * @return array<string, mixed> Extracted fields with defaults applied
     */
    private function extractJsonFields(ResponseInterface $response, array $pathsWithDefaults): array
    {
        $responseContent = (string) $response->getBody();

        $data = DboxJsonDecoder::decode($responseContent, $pathsWithDefaults);

        $result = [];

        foreach ($pathsWithDefaults as $path => $default) {
            $result[$path] = $this->getValueByPath($data, $path, $default);
        }

        return $result;
    }

    /**
     * Retrieves a value from a nested array using a colon-delimited path.
     *
     * @param array<string, mixed> $data    The array to search
     * @param string               $path    Colon-delimited path (e.g., "parent:child:0")
     * @param mixed                $default Value to return if the path does not exist
     *
     * @return mixed The value at the path, or default if missing
     */
    private function getValueByPath(array $data, string $path, mixed $default): mixed
    {
        $current = $data;

        $segments = explode(':', $path);

        foreach ($segments as $segment) {
            if (!is_array($current)) {
                return $default;
            }

            $key = is_numeric($segment) ? (int) $segment : $segment;

            if (!array_key_exists($key, $current)) {
                return $default;
            }

            $current = $current[$key];
        }

        return $current;
    }
}
