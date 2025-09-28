<?php

namespace Dbox\UploaderApi;

use RuntimeException;
use InvalidArgumentException;
use UnexpectedValueException;
use Throwable;

final class DropboxTokenVerifier
{
    private array $config;
    private string $access_token;

    public function __construct(array $config)
    {
        $config['store_type'] = $config['store_type'] ?? 'local';

        $this->config = $config;

        $this->validateConfig();

        $this->handleStoreTypeAction('remove');
    }

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

    public function verify(string $dropboxRefreshToken, string $dropboxAppKey, string $dropboxAppSecret): DropboxVerifyTokenResult
    {
        try {
            if ($this->handleStoreTypeAction('validate')) {
                return DropboxVerifyTokenResult::success();
            }

            $clientResult = DropboxApiClient::create();

            if (!$clientResult->isSuccess()) {
                return DropboxVerifyTokenResult::failure($clientResult->getError());
            }

            $client = $clientResult->getClient();

            $tokenResult = $client->fetchDropboxToken($dropboxRefreshToken, $dropboxAppKey, $dropboxAppSecret);

            if (!$tokenResult->isSuccess()) {
                return DropboxVerifyTokenResult::failure($tokenResult->getError());
            }

            $this->access_token = $tokenResult->getAccessToken();

            $this->handleStoreTypeAction('write');

            return DropboxVerifyTokenResult::success();
        } catch (Throwable $e) {
            ['type' => $type, 'message' => $message] = ExceptionAnalyzer::analyze($e);

            return DropboxVerifyTokenResult::failure([
                'type' => $type,
                'message' => $message,
                'time' => time()
            ]);
        }
    }

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
