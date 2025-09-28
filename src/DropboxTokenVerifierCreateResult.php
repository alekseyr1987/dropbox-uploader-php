<?php

namespace Dbox\UploaderApi;

final class DropboxTokenVerifierCreateResult
{
    private bool $success;
    private ?DropboxTokenVerifier $verifier;
    private array $error;

    private function __construct(bool $success, ?DropboxTokenVerifier $verifier, array $error)
    {
        $this->success = $success;
        $this->verifier = $verifier;
        $this->error = $error;
    }

    public static function success(DropboxTokenVerifier $verifier): self
    {
        return new self(true, $verifier, []);
    }

    public static function failure(array $error): self
    {
        return new self(false, null, $error);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getVerifier(): ?DropboxTokenVerifier
    {
        return $this->verifier;
    }

    public function getError(): array
    {
        return $this->error;
    }
}
