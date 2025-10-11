<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\Tests\ApiClient;

use Dbox\UploaderApi\ApiClient\DboxApiClient;
use Dbox\UploaderApi\ApiClient\DboxApiClientCreateResult;
use Dbox\UploaderApi\ApiClient\DboxApiClientFetchTokenResult;
use Dbox\UploaderApi\Utils\ExceptionAnalyzer\DboxExceptionAnalyzer;
use Dbox\UploaderApi\Utils\ExceptionAnalyzer\DboxExceptionAnalyzerInfoResult;
use Dbox\UploaderApi\Utils\JsonDecoder\DboxJsonDecoder;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DboxApiClient::class)]
#[CoversClass(DboxApiClientCreateResult::class)]
#[CoversClass(DboxApiClientFetchTokenResult::class)]
#[CoversClass(DboxExceptionAnalyzer::class)]
#[CoversClass(DboxExceptionAnalyzerInfoResult::class)]
#[CoversClass(DboxJsonDecoder::class)]
final class DboxApiClientTest extends TestCase {
    public function testCreateClientSuccessWithValidConfiguration(): void
    {
        $clientResult = DboxApiClient::create();

        $this->assertInstanceOf(DboxApiClientCreateResult::class, $clientResult);
        $this->assertTrue($clientResult->isSuccess());
        $this->assertInstanceOf(DboxApiClient::class, $clientResult->getClient());
        $this->assertCount(0, $clientResult->getError());
    }

    public function testCreateClientFailureWithInvalidConfiguration(): void
    {
        $clientResult = DboxApiClient::create(['handler' => true]);

        $this->assertInstanceOf(DboxApiClientCreateResult::class, $clientResult);
        $this->assertFalse($clientResult->isSuccess());
        $this->assertNull($clientResult->getClient());

        $error = $clientResult->getError();

        $this->assertCount(3, $error);
        $this->assertArrayHasKey('type', $error);
        $this->assertSame('InvalidArgumentException', $error['type']);
        $this->assertArrayHasKey('message', $error);
        $this->assertStringContainsString('handler must be a callable', $error['message']);
        $this->assertArrayHasKey('time', $error);
    }

    public function testFetchTokenSuccessWithValidAccessToken(): void
    {
        $client = $this->createClientWithMock([$this->createHttp200Response(['access_token' => 'fake-access-token'])]);

        $tokenResult = $client->fetchDropboxToken('fake-refresh', 'fake-key', 'fake-secret');

        $this->assertInstanceOf(DboxApiClientFetchTokenResult::class, $tokenResult);
        $this->assertTrue($tokenResult->isSuccess());
        $this->assertSame('fake-access-token', $tokenResult->getAccessToken());
        $this->assertCount(0, $tokenResult->getError());
    }

    public function testFetchTokenFailureWhenAccessTokenIsMissing(): void
    {
        $client = $this->createClientWithMock([$this->createHttp200Response(['client_id' => 514])]);

        $tokenResult = $client->fetchDropboxToken('fake-refresh', 'fake-key', 'fake-secret');

        $this->assertInstanceOf(DboxApiClientFetchTokenResult::class, $tokenResult);
        $this->assertFalse($tokenResult->isSuccess());
        $this->assertNull($tokenResult->getAccessToken());

        $error = $tokenResult->getError();

        $this->assertCount(5, $error);
        $this->assertArrayHasKey('action', $error);
        $this->assertSame('Fetching Dropbox oauth2/token', $error['action']);
        $this->assertArrayHasKey('attempt', $error);
        $this->assertSame(1, $error['attempt']);
        $this->assertArrayHasKey('type', $error);
        $this->assertSame('RuntimeException', $error['type']);
        $this->assertArrayHasKey('message', $error);
        $this->assertStringContainsString('Required fields are missing or invalid in the Dropbox API response', $error['message']);
        $this->assertArrayHasKey('time', $error);
    }

    public function testFetchTokenFailureAfterMaxRetryAttempts(): void
    {
        $client = $this->createClientWithMock(array_fill(0, 5, $this->createHttp429Response()));

        $tokenResult = $client->fetchDropboxToken('fake-refresh', 'fake-key', 'fake-secret');

        $this->assertInstanceOf(DboxApiClientFetchTokenResult::class, $tokenResult);
        $this->assertFalse($tokenResult->isSuccess());
        $this->assertNull($tokenResult->getAccessToken());

        $error = $tokenResult->getError();

        $this->assertCount(5, $error);
        $this->assertArrayHasKey('action', $error);
        $this->assertSame('Fetching Dropbox oauth2/token', $error['action']);
        $this->assertArrayHasKey('attempt', $error);
        $this->assertSame(5, $error['attempt']);
        $this->assertArrayHasKey('type', $error);
        $this->assertSame('MaxAttemptsExceeded', $error['type']);
        $this->assertArrayHasKey('message', $error);
        $this->assertStringContainsString('Exceeded maximum retry attempts', $error['message']);
        $this->assertArrayHasKey('time', $error);
    }

    private function createClientWithMock(array $responses): DboxApiClient
    {
        $mock = new MockHandler($responses);

        $handler = HandlerStack::create($mock);

        $clientResult = DboxApiClient::create(['handler' => $handler]);

        return $clientResult->getClient();
    }

    private function createHttp200Response(array $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
    }

    private function createHttp429Response(): Response
    {
        return new Response(429);
    }
}
