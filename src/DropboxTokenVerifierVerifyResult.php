<?php

namespace Dbox\UploaderApi;

final class DropboxTokenVerifierVerifyResult
{
    private bool $success;
    private array $error;

    private function __construct(bool $success, array $error = [])
    {
        $this->success = $success;
        $this->error = $error;
    }

    public static function success(): self
    {
        return new self(true);
    }

    public static function failure(array $error): self
    {
        return new self(false, $error);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getError(): array
    {
        return $this->error;
    }
}
