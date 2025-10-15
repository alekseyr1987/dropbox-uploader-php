<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\Tests\TokenVerifier;

use Dbox\UploaderApi\TokenVerifier\DboxTokenVerifier;
use Dbox\UploaderApi\TokenVerifier\DboxTokenVerifierCreateResult;
use Dbox\UploaderApi\Utils\ExceptionAnalyzer\DboxExceptionAnalyzer;
use Dbox\UploaderApi\Utils\ExceptionAnalyzer\DboxExceptionAnalyzerInfoResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DboxTokenVerifier::class)]
#[CoversClass(DboxTokenVerifierCreateResult::class)]
#[CoversClass(DboxExceptionAnalyzer::class)]
#[CoversClass(DboxExceptionAnalyzerInfoResult::class)]
final class DboxTokenVerifierTest extends TestCase {
    #[DataProvider('validConfigurationProvider')]
    public function testCreateVerifierSuccessWithMultipleConfiguration(array $config): void
    {
        $verifierResult = DboxTokenVerifier::create($config);

        $this->assertInstanceOf(DboxTokenVerifierCreateResult::class, $verifierResult);
        $this->assertTrue($verifierResult->isSuccess());
        $this->assertInstanceOf(DboxTokenVerifier::class, $verifierResult->getVerifier());
        $this->assertCount(0, $verifierResult->getError());
    }

    public static function validConfigurationProvider(): array
    {
        return [
            'store_type -> local' => [
                ['store_type' => 'local', 'path' => __DIR__ . '/../.cache']
            ],
            'store_type -> redis' => [
                ['store_type' => 'redis', 'host' => 'localhost', 'port' => 6379, 'credentials' => 'admin', 'db' => 0]
            ],
            'store_type -> mysql' => [
                ['store_type' => 'mysql', 'hostname' => 'localhost', 'username' => 'admin', 'password' => 'admin', 'database' => 'mysql', 'port' => 3306]
            ]
        ];
    }

    #[DataProvider('invalidConfigurationProvider')]
    public function testCreateVerifierFailureWithMultipleConfiguration(array $config, string $expectedMessage): void
    {
        $verifierResult = DboxTokenVerifier::create($config);

        $this->assertInstanceOf(DboxTokenVerifierCreateResult::class, $verifierResult);
        $this->assertFalse($verifierResult->isSuccess());
        $this->assertNull($verifierResult->getVerifier());

        $error = $verifierResult->getError();

        $this->assertCount(3, $error);
        $this->assertArrayHasKey('type', $error);
        $this->assertSame('InvalidArgumentException', $error['type']);
        $this->assertArrayHasKey('message', $error);
        $this->assertStringContainsString($expectedMessage, $error['message']);
        $this->assertArrayHasKey('time', $error);
    }

    public static function invalidConfigurationProvider(): array
    {
        return [
            'store_type -> unknown -> unsupported' => [
                ['store_type' => 'unknown'],
                "Unsupported store_type 'unknown'"
            ],
            "store_type -> local -> parameter 'path' -> missing" => [
                ['store_type' => 'local'],
                self::failureMissingMessage('path', 'local')
            ],
            "store_type -> local -> parameter 'path' -> wrong type" => [
                ['store_type' => 'local', 'path' => null],
                self::failureWrongTypeMessage('path', 'local', 'string')
            ],
            "store_type -> local -> parameter 'path' -> empty" => [
                ['store_type' => 'local', 'path' => ''],
                self::failureEmptyMessage('path', 'local')
            ],
            "store_type -> redis -> parameter 'host' -> missing" => [
                ['store_type' => 'redis'],
                self::failureMissingMessage('host', 'redis')
            ],
            "store_type -> redis -> parameter 'host' -> wrong type" => [
                ['store_type' => 'redis', 'host' => null],
                self::failureWrongTypeMessage('host', 'redis', 'string')
            ],
            "store_type -> redis -> parameter 'host' -> empty" => [
                ['store_type' => 'redis', 'host' => ''],
                self::failureEmptyMessage('host', 'redis')
            ],
            "store_type -> redis -> parameter 'port' -> missing" => [
                ['store_type' => 'redis', 'host' => 'localhost'],
                self::failureMissingMessage('port', 'redis')
            ],
            "store_type -> redis -> parameter 'port' -> wrong type" => [
                ['store_type' => 'redis', 'host' => 'localhost', 'port' => null],
                self::failureWrongTypeMessage('port', 'redis', 'int')
            ],
            "store_type -> redis -> parameter 'credentials' -> missing" => [
                ['store_type' => 'redis', 'host' => 'localhost', 'port' => 6379],
                self::failureMissingMessage('credentials', 'redis')
            ],
            "store_type -> redis -> parameter 'credentials' -> wrong type" => [
                ['store_type' => 'redis', 'host' => 'localhost', 'port' => 6379, 'credentials' => null],
                self::failureWrongTypeMessage('credentials', 'redis', 'string')
            ],
            "store_type -> redis -> parameter 'credentials' -> empty" => [
                ['store_type' => 'redis', 'host' => 'localhost', 'port' => 6379, 'credentials' => ''],
                self::failureEmptyMessage('credentials', 'redis')
            ],
            "store_type -> redis -> parameter 'db' -> missing" => [
                ['store_type' => 'redis', 'host' => 'localhost', 'port' => 6379, 'credentials' => 'admin'],
                self::failureMissingMessage('db', 'redis')
            ],
            "store_type -> redis -> parameter 'db' -> wrong type" => [
                ['store_type' => 'redis', 'host' => 'localhost', 'port' => 6379, 'credentials' => 'admin', 'db' => null],
                self::failureWrongTypeMessage('db', 'redis', 'int')
            ],
            "store_type -> mysql -> parameter 'hostname' -> missing" => [
                ['store_type' => 'mysql'],
                self::failureMissingMessage('hostname', 'mysql')
            ],
            "store_type -> mysql -> parameter 'hostname' -> wrong type" => [
                ['store_type' => 'mysql', 'hostname' => null],
                self::failureWrongTypeMessage('hostname', 'mysql', 'string')
            ],
            "store_type -> mysql -> parameter 'hostname' -> empty" => [
                ['store_type' => 'mysql', 'hostname' => ''],
                self::failureEmptyMessage('hostname', 'mysql')
            ],
            "store_type -> mysql -> parameter 'username' -> missing" => [
                ['store_type' => 'mysql', 'hostname' => 'localhost'],
                self::failureMissingMessage('username', 'mysql')
            ],
            "store_type -> mysql -> parameter 'username' -> wrong type" => [
                ['store_type' => 'mysql', 'hostname' => 'localhost', 'username' => null],
                self::failureWrongTypeMessage('username', 'mysql', 'string')
            ],
            "store_type -> mysql -> parameter 'username' -> empty" => [
                ['store_type' => 'mysql', 'hostname' => 'localhost', 'username' => ''],
                self::failureEmptyMessage('username', 'mysql')
            ],
            "store_type -> mysql -> parameter 'password' -> missing" => [
                ['store_type' => 'mysql', 'hostname' => 'localhost', 'username' => 'admin'],
                self::failureMissingMessage('password', 'mysql')
            ],
            "store_type -> mysql -> parameter 'password' -> wrong type" => [
                ['store_type' => 'mysql', 'hostname' => 'localhost', 'username' => 'admin', 'password' => null],
                self::failureWrongTypeMessage('password', 'mysql', 'string')
            ],
            "store_type -> mysql -> parameter 'password' -> empty" => [
                ['store_type' => 'mysql', 'hostname' => 'localhost', 'username' => 'admin', 'password' => ''],
                self::failureEmptyMessage('password', 'mysql')
            ],
            "store_type -> mysql -> parameter 'database' -> missing" => [
                ['store_type' => 'mysql', 'hostname' => 'localhost', 'username' => 'admin', 'password' => 'admin'],
                self::failureMissingMessage('database', 'mysql')
            ],
            "store_type -> mysql -> parameter 'database' -> wrong type" => [
                ['store_type' => 'mysql', 'hostname' => 'localhost', 'username' => 'admin', 'password' => 'admin', 'database' => null],
                self::failureWrongTypeMessage('database', 'mysql', 'string')
            ],
            "store_type -> mysql -> parameter 'database' -> empty" => [
                ['store_type' => 'mysql', 'hostname' => 'localhost', 'username' => 'admin', 'password' => 'admin', 'database' => ''],
                self::failureEmptyMessage('database', 'mysql')
            ],
            "store_type -> mysql -> parameter 'port' -> missing" => [
                ['store_type' => 'mysql', 'hostname' => 'localhost', 'username' => 'admin', 'password' => 'admin', 'database' => 'mysql'],
                self::failureMissingMessage('port', 'mysql')
            ],
            "store_type -> mysql -> parameter 'port' -> wrong type" => [
                ['store_type' => 'mysql', 'hostname' => 'localhost', 'username' => 'admin', 'password' => 'admin', 'database' => 'mysql', 'port' => null],
                self::failureWrongTypeMessage('port', 'mysql', 'int')
            ]
        ];
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
