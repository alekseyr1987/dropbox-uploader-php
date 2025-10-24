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
 * $verifierResult = DboxTokenVerifier::create(
 *     [
 *         'store_type' => 'local',
 *         'path' => 'tmp',
 *     ]
 * );
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

            return DboxTokenVerifierCreateResult::failure(
                [
                    'type' => $error->type,
                    'message' => $error->message,
                    'time' => time(),
                ]
            );
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

            return DboxTokenVerifierVerifyResult::failure(
                [
                    'type' => $error->type,
                    'message' => $error->message,
                    'time' => time(),
                ]
            );
        }
    }

    /**
     * Validates the configuration array according to the selected store type.
     */
    private function validateConfig(): void
    {
        $storeType = (string) $this->config['store_type'];

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
                'host' => 'string',
                'port' => 'int',
                'dbname' => 'string',
                'username' => 'string',
                'password' => 'string',
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
        $storeType = (string) $this->config['store_type'];

        switch ($storeType) {
            case 'local':
                $path = (string) $this->config['path'];

                $baseDir = trim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'dbox_uploader';
                $filePath = $baseDir.DIRECTORY_SEPARATOR.'token.json';

                if ('prepare' === $type) {
                    $this->prepareLocalDirectoriesAndFiles($baseDir);
                }

                if ('validate' === $type) {
                    return $this->validateLocalToken($filePath);
                }

                if ('write' === $type) {
                    $this->writeLocalToken($filePath);
                }

                break;

            case 'redis':
                $host = (string) $this->config['host'];
                $port = (int) $this->config['port'];
                $credentials = (string) $this->config['credentials'];
                $db = (int) $this->config['db'];

                if ('validate' === $type) {
                    return $this->validateRedisToken($host, $port, $credentials, $db);
                }

                if ('write' === $type) {
                    $this->writeRedisToken($host, $port, $credentials, $db);
                }

                break;

            case 'mysql':
                $host = (string) $this->config['host'];
                $port = (int) $this->config['port'];
                $dbname = (string) $this->config['dbname'];
                $username = (string) $this->config['username'];
                $password = (string) $this->config['password'];

                $options = [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
                ];

                if ('prepare' === $type) {
                    $this->prepareMysqlSchema($host, $port, $dbname, $username, $password, $options);
                }

                if ('validate' === $type) {
                    return $this->validateMysqlToken($host, $port, $dbname, $username, $password, $options);
                }

                if ('write' === $type) {
                    $this->writeMysqlToken($host, $port, $dbname, $username, $password, $options);
                }

                break;
        }

        return false;
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

        $fileContent = (string) file_get_contents($filePath);

        return DboxJsonDecoder::decode($fileContent, [], $filePath)['expires_in'] > time();
    }

    /**
     * Writes the access token to local storage as a JSON file.
     *
     * @param string $filePath Path to write the token
     */
    private function writeLocalToken(string $filePath): void
    {
        file_put_contents(
            $filePath,
            json_encode(
                [
                    'access_token' => $this->access_token,
                    'expires_in' => time() + 10800,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );
    }

    /**
     * Validates if a Redis-stored Dropbox token exists.
     *
     * Connects to a Redis server, authenticates, selects the database, and checks whether the key `dbox_token` exists.
     *
     * @param string $host        Redis server hostname or IP
     * @param int    $port        Redis server port
     * @param string $credentials Password or authentication string
     * @param int    $db          Redis database index to select
     *
     * @return bool True if the token exists, false otherwise
     */
    private function validateRedisToken(string $host, int $port, string $credentials, int $db): bool
    {
        $redis = new \Redis();

        $redis->connect($host, $port);
        $redis->auth($credentials);
        $redis->select($db);

        return (bool) $redis->exists('dbox_token');
    }

    /**
     * Stores the Dropbox token in Redis with a TTL of 10800 seconds (3 hours).
     *
     * Connects to a Redis server, authenticates, selects the database, and writes the access token with expiration.
     *
     * @param string $host        Redis server hostname or IP
     * @param int    $port        Redis server port
     * @param string $credentials Password or authentication string
     * @param int    $db          Redis database index to select
     */
    private function writeRedisToken(string $host, int $port, string $credentials, int $db): void
    {
        $redis = new \Redis();

        $redis->connect($host, $port);
        $redis->auth($credentials);
        $redis->select($db);

        $redis->setEx('dbox_token', 10800, $this->access_token);
    }

    /**
     * Prepares the MySQL database and schema for storing Dropbox tokens and files.
     *
     * Creates the database if it does not exist, ensures required tables (`dbox_token` and `dbox_files`) exist with proper columns, constraints, and indexes. Also deletes expired rows from `dbox_files`.
     *
     * @param string            $host     MySQL host
     * @param int               $port     MySQL port
     * @param string            $dbname   Database name
     * @param string            $username Database username
     * @param string            $password Database password
     * @param array<int, mixed> $options  PDO options
     */
    private function prepareMysqlSchema(string $host, int $port, string $dbname, string $username, string $password, array $options): void
    {
        $pdo = new \PDO("mysql:host={$host};port={$port}", $username, $password, $options);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $pdo->exec("USE `{$dbname}`");

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS `dbox_token` (
                `id` TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `token` TEXT NOT NULL,
                `expires_in` DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS `dbox_files` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `hash` VARCHAR(64) NOT NULL,
                `chunk` SMALLINT UNSIGNED NOT NULL,
                `body` MEDIUMBLOB NOT NULL,
                `expires_in` DATETIME NOT NULL,
                CONSTRAINT `dbox_hash_chunk_unique` UNIQUE (`hash`, `chunk`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $stmt = $pdo->query("SHOW INDEX FROM `dbox_files` WHERE `Key_name` = 'dbox_hash_index'");

        if (false !== $stmt) {
            if (!$stmt->fetch()) {
                $pdo->exec('CREATE INDEX `dbox_hash_index` ON `dbox_files` (`hash`)');
            }
        }

        $pdo->exec('DELETE FROM `dbox_files` WHERE `expires_in` <= NOW()');
    }

    /**
     * Validates if the Dropbox token in MySQL is still valid.
     *
     * Connects to MySQL using PDO and checks whether there is any record in `dbox_token` whose `expires_in` is in the future.
     *
     * @param string            $host     MySQL host
     * @param int               $port     MySQL port
     * @param string            $dbname   Database name
     * @param string            $username Database username
     * @param string            $password Database password
     * @param array<int, mixed> $options  PDO options
     *
     * @return bool True if a valid token exists, false otherwise
     */
    private function validateMysqlToken(string $host, int $port, string $dbname, string $username, string $password, array $options): bool
    {
        $pdo = new \PDO("mysql:host={$host};port={$port};dbname={$dbname}", $username, $password, $options);

        $stmt = $pdo->query('SELECT 1 FROM `dbox_token` WHERE `expires_in` > NOW()');

        if (false !== $stmt) {
            return (bool) $stmt->fetchColumn();
        }

        return false;
    }

    /**
     * Writes or updates the Dropbox token in MySQL.
     *
     * Inserts a new row in `dbox_token` with `id = 1` or updates the existing token and expiration timestamp if the row already exists.
     *
     * @param string            $host     MySQL host
     * @param int               $port     MySQL port
     * @param string            $dbname   Database name
     * @param string            $username Database username
     * @param string            $password Database password
     * @param array<int, mixed> $options  PDO options
     */
    private function writeMysqlToken(string $host, int $port, string $dbname, string $username, string $password, array $options): void
    {
        $pdo = new \PDO("mysql:host={$host};port={$port};dbname={$dbname}", $username, $password, $options);

        $stmt = $pdo->prepare('
            INSERT INTO `dbox_token` (`id`, `token`, `expires_in`)
            VALUES (1, :token, NOW() + INTERVAL 3 HOUR)
            ON DUPLICATE KEY UPDATE
                `token` = VALUES (`token`),
                `expires_in` = NOW() + INTERVAL 3 HOUR
        ');

        if (false !== $stmt) {
            $stmt->execute(
                [
                    ':token' => $this->access_token,
                ]
            );
        }
    }
}
