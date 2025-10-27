<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\Tests\TokenVerifier;

use Dbox\UploaderApi\TokenVerifier\DboxTokenVerifier;
use Dbox\UploaderApi\TokenVerifier\DboxTokenVerifierCreateResult;
use Dbox\UploaderApi\Utils\ExceptionAnalyzer\DboxExceptionAnalyzer;
use Dbox\UploaderApi\Utils\ExceptionAnalyzer\DboxExceptionAnalyzerInfoResult;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DboxTokenVerifier::class)]
#[CoversClass(DboxTokenVerifierCreateResult::class)]
#[CoversClass(DboxExceptionAnalyzer::class)]
#[CoversClass(DboxExceptionAnalyzerInfoResult::class)]
final class DboxTokenVerifierTest extends TestCase {
    #[DataProvider('invalidConfigurationProvider')]
    public function testInvalidConfigurationsAreRejected(array $config, string $expectedMessage): void
    {
        $verifierResult = DboxTokenVerifier::create($config);

        $error = $verifierResult->getError();

        $this->assertSame('InvalidArgumentException', $error['type']);
        $this->assertStringContainsString($expectedMessage, $error['message']);
    }

    public static function invalidConfigurationProvider(): array
    {
        return [
            'store_type -> unknown -> unsupported' => [
                [
                    'store_type' => 'unknown'
                ],
                self::failureUnknownMessage()
            ],
            "store_type -> local -> parameter 'path' -> missing" => [
                [
                    'store_type' => 'local'
                ],
                self::failureMissingMessage('path', 'local')
            ],
            "store_type -> local -> parameter 'path' -> wrong type" => [
                [
                    'store_type' => 'local',
                    'path' => null
                ],
                self::failureWrongTypeMessage('path', 'local', 'string')
            ],
            "store_type -> local -> parameter 'path' -> empty" => [
                [
                    'store_type' => 'local',
                    'path' => ''
                ],
                self::failureEmptyMessage('path', 'local')
            ],
            "store_type -> redis -> parameter 'host' -> missing" => [
                [
                    'store_type' => 'redis'
                ],
                self::failureMissingMessage('host', 'redis')
            ],
            "store_type -> redis -> parameter 'host' -> wrong type" => [
                [
                    'store_type' => 'redis',
                    'host' => null
                ],
                self::failureWrongTypeMessage('host', 'redis', 'string')
            ],
            "store_type -> redis -> parameter 'host' -> empty" => [
                [
                    'store_type' => 'redis',
                    'host' => ''
                ],
                self::failureEmptyMessage('host', 'redis')
            ],
            "store_type -> redis -> parameter 'port' -> missing" => [
                [
                    'store_type' => 'redis',
                    'host' => 'localhost'
                ],
                self::failureMissingMessage('port', 'redis')
            ],
            "store_type -> redis -> parameter 'port' -> wrong type" => [
                [
                    'store_type' => 'redis',
                    'host' => 'localhost',
                    'port' => null
                ],
                self::failureWrongTypeMessage('port', 'redis', 'int')
            ],
            "store_type -> redis -> parameter 'credentials' -> missing" => [
                [
                    'store_type' => 'redis',
                    'host' => 'localhost',
                    'port' => 6379
                ],
                self::failureMissingMessage('credentials', 'redis')
            ],
            "store_type -> redis -> parameter 'credentials' -> wrong type" => [
                [
                    'store_type' => 'redis',
                    'host' => 'localhost',
                    'port' => 6379,
                    'credentials' => null
                ],
                self::failureWrongTypeMessage('credentials', 'redis', 'string')
            ],
            "store_type -> redis -> parameter 'credentials' -> empty" => [
                [
                    'store_type' => 'redis',
                    'host' => 'localhost',
                    'port' => 6379,
                    'credentials' => ''
                ],
                self::failureEmptyMessage('credentials', 'redis')
            ],
            "store_type -> redis -> parameter 'db' -> missing" => [
                [
                    'store_type' => 'redis',
                    'host' => 'localhost',
                    'port' => 6379,
                    'credentials' => 'some_password'
                ],
                self::failureMissingMessage('db', 'redis')
            ],
            "store_type -> redis -> parameter 'db' -> wrong type" => [
                [
                    'store_type' => 'redis',
                    'host' => 'localhost',
                    'port' => 6379,
                    'credentials' => 'some_password',
                    'db' => null
                ],
                self::failureWrongTypeMessage('db', 'redis', 'int')
            ],
            "store_type -> mysql -> parameter 'host' -> missing" => [
                [
                    'store_type' => 'mysql'
                ],
                self::failureMissingMessage('host', 'mysql')
            ],
            "store_type -> mysql -> parameter 'host' -> wrong type" => [
                [
                    'store_type' => 'mysql',
                    'host' => null
                ],
                self::failureWrongTypeMessage('host', 'mysql', 'string')
            ],
            "store_type -> mysql -> parameter 'host' -> empty" => [
                [
                    'store_type' => 'mysql',
                    'host' => ''
                ],
                self::failureEmptyMessage('host', 'mysql')
            ],
            "store_type -> mysql -> parameter 'port' -> missing" => [
                [
                    'store_type' => 'mysql',
                    'host' => 'localhost'
                ],
                self::failureMissingMessage('port', 'mysql')
            ],
            "store_type -> mysql -> parameter 'port' -> wrong type" => [
                [
                    'store_type' => 'mysql',
                    'host' => 'localhost',
                    'port' => null
                ],
                self::failureWrongTypeMessage('port', 'mysql', 'int')
            ],
            "store_type -> mysql -> parameter 'dbname' -> missing" => [
                [
                    'store_type' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306
                ],
                self::failureMissingMessage('dbname', 'mysql')
            ],
            "store_type -> mysql -> parameter 'dbname' -> wrong type" => [
                [
                    'store_type' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'dbname' => null
                ],
                self::failureWrongTypeMessage('dbname', 'mysql', 'string')
            ],
            "store_type -> mysql -> parameter 'dbname' -> empty" => [
                [
                    'store_type' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'dbname' => ''
                ],
                self::failureEmptyMessage('dbname', 'mysql')
            ],
            "store_type -> mysql -> parameter 'username' -> missing" => [
                [
                    'store_type' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'dbname' => 'some_db'
                ],
                self::failureMissingMessage('username', 'mysql')
            ],
            "store_type -> mysql -> parameter 'username' -> wrong type" => [
                [
                    'store_type' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'dbname' => 'some_db',
                    'username' => null
                ],
                self::failureWrongTypeMessage('username', 'mysql', 'string')
            ],
            "store_type -> mysql -> parameter 'username' -> empty" => [
                [
                    'store_type' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'dbname' => 'some_db',
                    'username' => ''
                ],
                self::failureEmptyMessage('username', 'mysql')
            ],
            "store_type -> mysql -> parameter 'password' -> missing" => [
                [
                    'store_type' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'dbname' => 'some_db',
                    'username' => 'some_user'
                ],
                self::failureMissingMessage('password', 'mysql')
            ],
            "store_type -> mysql -> parameter 'password' -> wrong type" => [
                [
                    'store_type' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'dbname' => 'some_db',
                    'username' => 'some_user',
                    'password' => null
                ],
                self::failureWrongTypeMessage('password', 'mysql', 'string')
            ],
            "store_type -> mysql -> parameter 'password' -> empty" => [
                [
                    'store_type' => 'mysql',
                    'host' => 'localhost',
                    'port' => 3306,
                    'dbname' => 'some_db',
                    'username' => 'some_user',
                    'password' => ''
                ],
                self::failureEmptyMessage('password', 'mysql')
            ]
        ];
    }

    public function testLocalStorageDirectoryCannotBeCreatedDueToPermissions(): void
    {
        vfsStream::setup('tmp', 0000);

        $verifierResult = DboxTokenVerifier::create(
            [
                'store_type' => 'local',
                'path' => 'vfs://tmp'
            ]
        );

        $error = $verifierResult->getError();

        $this->assertSame('RuntimeException', $error['type']);
        $this->assertStringContainsString("Failed to create required local storage directory: 'vfs://tmp/dbox_uploader'", $error['message']);
    }

    public function testLocalStorageDirectoryIsCreated(): void
    {
        $rootDir = vfsStream::setup('tmp', 1777);

        DboxTokenVerifier::create(
            [
                'store_type' => 'local',
                'path' => 'vfs://tmp'
            ]
        );

        $this->assertTrue($rootDir->hasChild('dbox_uploader'));
    }

    public function testExpiredFoldersAreRemovedKeepingTokenFile(): void
    {
        $rootDir = vfsStream::setup('tmp', 1777);

        $baseDir = vfsStream::newDirectory('dbox_uploader', 0755)->at($rootDir);

        vfsStream::newFile('token.json', 0666)->at($baseDir);

        $subDir1 = vfsStream::newDirectory('folder_1', 0755)->at($baseDir);
        $subDir2 = vfsStream::newDirectory('folder_2', 0755)->at($baseDir);

        vfsStream::newFile('file_1', 0666)->at($subDir1);
        vfsStream::newFile('file_1', 0666)->at($subDir2);

        $subDir2->lastModified(time() - 3600);

        DboxTokenVerifier::create(
            [
                'store_type' => 'local',
                'path' => 'vfs://tmp'
            ]
        );

        $this->assertTrue($baseDir->hasChild('token.json'));
        $this->assertTrue($baseDir->hasChild('folder_1'));
        $this->assertFalse($baseDir->hasChild('folder_2'));
    }

    private static function failureUnknownMessage(): string
    {
        return "Unsupported store_type 'unknown'";
    }

    private static function failureMissingMessage(string $parameter, string $storeType): string
    {
        return "Missing configuration parameter '{$parameter}' for store_type '{$storeType}'";
    }

    private static function failureWrongTypeMessage(string $parameter, string $storeType, string $type): string
    {
        return "Parameter '{$parameter}' for store_type '{$storeType}' must be of type '{$type}'";
    }

    private static function failureEmptyMessage(string $parameter, string $storeType): string
    {
        return "Configuration parameter '{$parameter}' for store_type '{$storeType}' cannot be empty";
    }
}
