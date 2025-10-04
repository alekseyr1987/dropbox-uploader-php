<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\TokenVerifier;

use Dbox\UploaderApi\ApiClient\DboxApiClient;
use Dbox\UploaderApi\ExceptionAnalyzer\DboxExceptionAnalyzer;

use RuntimeException;
use InvalidArgumentException;
use UnexpectedValueException;
use Throwable;

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
 *     'path' => __DIR__ . '/tmp'
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
     * @var array<string, int|string>
     */
    private array $config;

    /**
     * Cached Dropbox access token after successful verification.
     *
     * @var string
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

        $this->handleStoreTypeAction('remove');
    }

    /**
     * Creates a new `DboxTokenVerifier` wrapped in a result object.
     *
     * Handles exceptions during instantiation and returns a `DboxTokenVerifierCreateResult`.
     *
     * @param array<string, int|string> $config Verifier configuration
     * @return DboxTokenVerifierCreateResult
     */
    public static function create(array $config): DboxTokenVerifierCreateResult
    {
        try {
            return DboxTokenVerifierCreateResult::success(new self($config));
        } catch (Throwable $e) {
            $errorInfo = DboxExceptionAnalyzer::info($e);

            return DboxTokenVerifierCreateResult::failure([
                'type' => $errorInfo->type,
                'message' => $errorInfo->message,
                'time' => time()
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
                'path' => 'string'
            ]
            // TODO: Uncomment the necessary types as they are implemented.
            /*,
            'redis' => [
                'host' => 'string',
                'port' => 'int',
                'credentials' => 'string',
                'db' => 'int'
            ],
            'mysql' => [
                'hostname' => 'string',
                'username' => 'string',
                'password' => 'string',
                'database' => 'string',
                'port' => 'int'
            ]*/
        ];

        if (!array_key_exists($storeType, $rules)) {
            throw new InvalidArgumentException("Unsupported store_type '$storeType'.");
        }

        foreach ($rules[$storeType] as $param => $type) {
            if (!array_key_exists($param, $this->config)) {
                throw new InvalidArgumentException("Missing configuration parameter '$param' for store_type '$storeType'.");
            }

            $matchType = false;

            $paramValue = $this->config[$param];

            switch ($type) {
                case 'string':
                    $matchType = is_string($paramValue);

                    break;
                case 'int':
                    $matchType = is_int($paramValue);

                    break;
            }

            if (!$matchType) {
                throw new InvalidArgumentException("Parameter '$param' for store_type '$storeType' must be of type '$type'.");
            }

            if ($type === 'string' && empty($paramValue)) {
                throw new InvalidArgumentException("Configuration parameter '$param' for store_type '$storeType' cannot be empty.");
            }
        }
    }

    /**
     * Verifies a Dropbox API access token and persists it if necessary.
     *
     * Performs validation or fetches a new token from Dropbox via `DboxApiClient` if required. Handles exceptions and returns a result object encapsulating success or failure.
     *
     * @param string $refreshToken Dropbox refresh token
     * @param string $appKey Dropbox app key
     * @param string $appSecret Dropbox app secret
     * @return DboxTokenVerifierVerifyResult The result of token verification
     */
    public function verify(string $refreshToken, string $appKey, string $appSecret): DboxTokenVerifierVerifyResult
    {
        try {
            if ($this->handleStoreTypeAction('validate')) {
                return DboxTokenVerifierVerifyResult::success();
            }

            $clientResult = DboxApiClient::create();

            if (!$clientResult->isSuccess()) {
                return DboxTokenVerifierVerifyResult::failure($clientResult->getError());
            }

            $client = $clientResult->getClient();

            $tokenResult = $client->fetchDropboxToken($refreshToken, $appKey, $appSecret);

            if (!$tokenResult->isSuccess()) {
                return DboxTokenVerifierVerifyResult::failure($tokenResult->getError());
            }

            $this->access_token = $tokenResult->getAccessToken();

            $this->handleStoreTypeAction('write');

            return DboxTokenVerifierVerifyResult::success();
        } catch (Throwable $e) {
            $errorInfo = DboxExceptionAnalyzer::info($e);

            return DboxTokenVerifierVerifyResult::failure([
                'type' => $errorInfo->type,
                'message' => $errorInfo->message,
                'time' => time()
            ]);
        }
    }

    /**
     * Handles store-type-specific actions such as writing, validating, or removing tokens.
     *
     * @param string $type Action type: 'remove', 'validate', or 'write'
     * @return bool True if action succeeded (for validate), false otherwise
     */
    private function handleStoreTypeAction(string $type): bool
    {
        $result = true;

        switch ($this->config['store_type']) {
            case 'local':
                $baseDir = DIRECTORY_SEPARATOR . trim($this->config['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dbox_uploader';
                $filePath = $baseDir . DIRECTORY_SEPARATOR . 'token.json';

                if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true)) {
                    throw new RuntimeException("Unable to create directory: '$baseDir'.");
                }

                if ($type === 'remove') {
                    $this->removeLocalDirectoriesWithFiles($baseDir);
                }

                if ($type === 'validate') {
                    $result = $this->validateLocalToken($filePath);
                }

                if ($type === 'write') {
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
     * Recursively removes directories and files for local storage.
     *
     * @param string $baseDir Base directory to clean
     * @param bool $recursion Whether the call is recursive
     */
    private function removeLocalDirectoriesWithFiles(string $baseDir, bool $recursion = false): void
    {
        if (!$recursion) {
            $currentTime = time();
        }

        foreach (scandir($baseDir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $newPath = $baseDir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($newPath)) {
                if ($recursion) {
                    $this->removeLocalDirectoriesWithFiles($newPath, $recursion);
                } else {
                    $modificationTime = filemtime($newPath);

                    if ($modificationTime === false) {
                        continue;
                    }

                    if ($currentTime - $modificationTime >= 3600) {
                        $this->removeLocalDirectoriesWithFiles($newPath, true);
                    }
                }
            } else {
                if ($recursion) {
                    unlink($newPath);
                }
            }
        }

        if ($recursion) {
            rmdir($baseDir);
        }
    }

    /**
     * Validates the local access token stored in a file.
     *
     * @param string $filePath Path to the local token file
     * @return bool True if token is valid and not expired
     */
    private function validateLocalToken(string $filePath): bool
    {
        if (!is_file($filePath)) {
            return false;
        }

        $fileContent = file_get_contents($filePath);

        if ($fileContent === false) {
            throw new RuntimeException("Unable to read data from file: '$filePath'.");
        }

        $data = json_decode($fileContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new UnexpectedValueException("Unable to decode JSON data from file: '$filePath'.");
        }

        if (time() >= $data['expires_in']) {
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
        $json = json_encode([
            'access_token' => $this->access_token,
            'expires_in' => time() + 10800
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException("Unable to encode data to JSON: '$filePath'.");
        }

        if (file_put_contents($filePath, $json) === false) {
            throw new RuntimeException("Unable to write data to file: '$filePath'.");
        }
    }
}
