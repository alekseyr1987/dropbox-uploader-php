<?php

namespace Dbox\UploaderApi;

final class DropboxCreateClientResult
{
    private bool $success;
    private ?DropboxApiClient $client;
    private array $error;

    private function __construct(bool $success, ?DropboxApiClient $client, array $error)
    {
        $this->success = $success;
        $this->client = $client;
        $this->error = $error;
    }

    public static function success(DropboxApiClient $client): self
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

    public function getClient(): ?DropboxApiClient
    {
        return $this->client;
    }

    public function getError(): array
    {
        return $this->error;
    }
}
