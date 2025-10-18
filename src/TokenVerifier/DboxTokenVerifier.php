<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\TokenVerifier;

use Dbox\UploaderApi\ApiClient\DboxApiClient;
use Dbox\UploaderApi\Utils\ExceptionAnalyzer\DboxExceptionAnalyzer;
use Dbox\UploaderApi\Utils\JsonDecoder\DboxJsonDecoder;

/**
 * Verifier for Dropbox API access tokens.
 *
 * Provides methods for creating a verifier, validating configuration, and verifying Dropbox access tokens. Supports multiple storage types.
 *
 * The verifier ensures that the access token is valid, and optionally persists it according to the configured storage type.
 *
 * Example usage:
 * ```
 * $verifierResult = DboxTokenVerifier::create([
 *     'store_type' => 'local',
 *     'path' => 'tmp'
 * ]);
 *
 * if ($verifierResult->isSuccess()) {
 *     $verifier = $verifierResult->getVerifier();
 *
 *     $verifyResult = $verifier->verify('APrChJVJXAYTTAAAAAANAa5fBLvJJ4L9yS1f0A7FX6CumHfh52L4PhsFu6hamyG_', '1z968ffbd0gnbbo', 'csuqk6t2gmcjdak');
 *
 *     if ($verifyResult->isSuccess()) {
 *         // Token is valid and stored
 *     } else {
 *         $error = $verifyResult->getError();
 *     }
 * } else {
 *     $error = $verifierResult->getError();
 * }
 * ```
 */
final class DboxTokenVerifier
{
    /**
     * Configuration for the verifier, including store type and related parameters.
     *
     * @var array<string, int|string> Verifier configuration parameters
     */
    private array $config;

    /**
     * Cached Dropbox access token after successful verification.
     *
     * @var string Access token string
     */
    private string $access_token;

    /**
     * Private constructor. Use static `create()` method to instantiate.
     *
     * @param array<string, int|string> $config Verifier configuration
     */
    private function __construct(array $config)
    {
        $config['store_type'] = $config['store_type'] ?? 'local';

        $this->config = $config;

        $this->validateConfig();

        $this->handleStoreTypeAction('prepare');
    }

    /**
     * Creates a new `DboxTokenVerifier` wrapped in a result object.
     *
     * Handles exceptions during instantiation and returns a `DboxTokenVerifierCreateResult`.
     *
     * @param array<string, int|string> $config Verifier configuration
     *
     * @return DboxTokenVerifierCreateResult Result object containing either a `DboxTokenVerifier` instance or error details
     */
    public static function create(array $config): DboxTokenVerifierCreateResult
    {
        try {
            return DboxTokenVerifierCreateResult::success(new self($config));
        } catch (\Throwable $e) {
            $error = DboxExceptionAnalyzer::info($e);

            return DboxTokenVerifierCreateResult::failure([
                'type' => $error->type,
                'message' => $error->message,
                'time' => time(),
            ]);
        }
    }

    /**
     * Verifies a Dropbox API access token and persists it if necessary.
     *
     * Performs validation or fetches a new token from Dropbox via `DboxApiClient` if required. Handles exceptions and returns a result object encapsulating success or failure.
     *
     * @param string               $refreshToken Dropbox refresh token
     * @param string               $appKey       Dropbox app key
     * @param string               $appSecret    Dropbox app secret
     * @param array<string, mixed> $clientConfig Optional Guzzle client configuration
     *
     * @return DboxTokenVerifierVerifyResult The result of token verification
     */
    public function verify(string $refreshToken, string $appKey, string $appSecret, array $clientConfig = []): DboxTokenVerifierVerifyResult
    {
        try {
            if ($this->handleStoreTypeAction('validate')) {
                return DboxTokenVerifierVerifyResult::success();
            }

            $clientResult = DboxApiClient::create($clientConfig);

            if (!$clientResult->isSuccess()) {
                return DboxTokenVerifierVerifyResult::failure($clientResult->getError());
            }

            $client = $clientResult->getClient();

            $tokenResult = $client->fetchDropboxToken($refreshToken, $appKey, $appSecret); // @phpstan-ignore method.nonObject

            if (!$tokenResult->isSuccess()) {
                return DboxTokenVerifierVerifyResult::failure($tokenResult->getError());
            }

            $this->access_token = $tokenResult->getAccessToken(); // @phpstan-ignore assign.propertyType

            $this->handleStoreTypeAction('write');

            return DboxTokenVerifierVerifyResult::success();
        } catch (\Throwable $e) {
            $error = DboxExceptionAnalyzer::info($e);

            return DboxTokenVerifierVerifyResult::failure([
                'type' => $error->type,
                'message' => $error->message,
                'time' => time(),
            ]);
        }
    }

    /**
     * Validates the configuration array according to the selected store type.
     */
    private function validateConfig(): void
    {
        $storeType = $this->config['store_type'];

        $rules = [
            'local' => [
                'path' => 'string',
            ],
            'redis' => [
                'host' => 'string',
                'port' => 'int',
                'credentials' => 'string',
                'db' => 'int',
            ],
            'mysql' => [
                'hostname' => 'string',
                'username' => 'string',
                'password' => 'string',
                'database' => 'string',
                'port' => 'int',
            ],
        ];

        if (!array_key_exists($storeType, $rules)) {
            throw new \InvalidArgumentException("Unsupported store_type '{$storeType}'.");
        }

        foreach ($rules[$storeType] as $param => $type) {
            if (!array_key_exists($param, $this->config)) {
                throw new \InvalidArgumentException("Missing configuration parameter '{$param}' for store_type '{$storeType}'.");
            }

            $paramValue = $this->config[$param];

            switch ($type) {
                case 'string':
                    if (!is_string($paramValue)) {
                        throw new \InvalidArgumentException("Parameter '{$param}' for store_type '{$storeType}' must be of type 'string'.");
                    }

                    if ('' === $paramValue) {
                        throw new \InvalidArgumentException("Configuration parameter '{$param}' for store_type '{$storeType}' cannot be empty.");
                    }

                    break;

                case 'int':
                    if (!is_int($paramValue)) {
                        throw new \InvalidArgumentException("Parameter '{$param}' for store_type '{$storeType}' must be of type 'int'.");
                    }

                    break;
            }
        }
    }

    /**
     * Handles store-type-specific actions such as preparing, validating, or writing tokens.
     *
     * @param string $type Action type: 'prepare', 'validate', or 'write'
     *
     * @return bool True if action succeeded (for validate), false otherwise
     */
    private function handleStoreTypeAction(string $type): bool
    {
        $result = true;

        switch ($this->config['store_type']) {
            case 'local':
                $baseDir = trim((string) $this->config['path'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'dbox_uploader';
                $filePath = $baseDir.DIRECTORY_SEPARATOR.'token.json';

                if ('prepare' === $type) {
                    $this->prepareLocalDirectoriesAndFiles($baseDir);
                }

                if ('validate' === $type) {
                    $result = $this->validateLocalToken($filePath);
                }

                if ('write' === $type) {
                    $this->writeLocalToken($filePath);
                }

                break;

            case 'redis':
                // TODO: Implement the deletion of temporary parts of files from redis that are more than 1 hour old.
                // TODO: Implement the receipt of the token from redis and its validation for a period of validity of no more than 3 hours.
                // TODO: Implement writing the token to a redis.

                break;

            case 'mysql':
                // TODO: Implement the deletion of temporary parts of files from mysql that are more than 1 hour old.
                // TODO: Implement the receipt of the token from mysql and its validation for a period of validity of no more than 3 hours.
                // TODO: Implement writing the token to a mysql.

                break;
        }

        return $result;
    }

    /**
     * Ensures the base directory exists and deletes first-level subdirectories and their files if older than 1 hour.
     *
     * @param string $baseDir Base directory to prepare and clean
     */
    private function prepareLocalDirectoriesAndFiles(string $baseDir): void
    {
        if (!is_dir($baseDir)) {
            if (!mkdir($baseDir, 0755, true)) {
                throw new \RuntimeException("Failed to create required local storage directory: '{$baseDir}'.");
            }
        }

        foreach (array_diff(scandir($baseDir), ['.', '..']) as $baseItem) {
            $baseItemPath = $baseDir.DIRECTORY_SEPARATOR.$baseItem;

            if (is_file($baseItemPath)) {
                continue;
            }

            if (time() - filemtime($baseItemPath) < 3600) {
                continue;
            }

            foreach (array_diff(scandir($baseItemPath), ['.', '..']) as $subItem) {
                unlink($baseItemPath.DIRECTORY_SEPARATOR.$subItem);
            }

            rmdir($baseItemPath);
        }
    }

    /**
     * Validates the local access token stored in a file.
     *
     * @param string $filePath Path to the local token file
     *
     * @return bool True if token is valid and not expired
     */
    private function validateLocalToken(string $filePath): bool
    {
        if (!is_file($filePath)) {
            return false;
        }

        if (time() >= DboxJsonDecoder::decode(file_get_contents($filePath), [], $filePath)['expires_in']) {
            return false;
        }

        return true;
    }

    /**
     * Writes the access token to local storage as a JSON file.
     *
     * @param string $filePath Path to write the token
     */
    private function writeLocalToken(string $filePath): void
    {
        file_put_contents($filePath, json_encode(
            [
                'access_token' => $this->access_token,
                'expires_in' => time() + 10800,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ));
    }
}
