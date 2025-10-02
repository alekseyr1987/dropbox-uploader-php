<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\ApiClient;

final class DboxApiClientCreateResult
{
    private bool $success;
    private ?DboxApiClient $client;
    private array $error;

    private function __construct(bool $success, ?DboxApiClient $client, array $error)
    {
        $this->success = $success;
        $this->client = $client;
        $this->error = $error;
    }

    public static function success(DboxApiClient $client): self
    {
        return new self(true, $client, []);
    }

    public static function failure(array $error): self
    {
        return new self(false, null, $error);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getClient(): ?DboxApiClient
    {
        return $this->client;
    }

    public function getError(): array
    {
        return $this->error;
    }
}
