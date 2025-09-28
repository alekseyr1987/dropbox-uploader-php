<?php

namespace Dbox\UploaderApi\ApiClient;

final class DropboxApiClientFetchTokenResult
{
    private bool $success;
    private ?string $accessToken;
    private array $error;

    private function __construct(bool $success, ?string $accessToken, array $error)
    {
        $this->success = $success;
        $this->accessToken = $accessToken;
        $this->error = $error;
    }

    public static function success(string $accessToken): self
    {
        return new self(true, $accessToken, []);
    }

    public static function failure(array $error): self
    {
        return new self(false, null, $error);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function getError(): array
    {
        return $this->error;
    }
}
