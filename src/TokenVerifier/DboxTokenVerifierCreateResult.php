<?php

namespace Dbox\UploaderApi\TokenVerifier;

final class DboxTokenVerifierCreateResult
{
    private bool $success;
    private ?DboxTokenVerifier $verifier;
    private array $error;

    private function __construct(bool $success, ?DboxTokenVerifier $verifier, array $error)
    {
        $this->success = $success;
        $this->verifier = $verifier;
        $this->error = $error;
    }

    public static function success(DboxTokenVerifier $verifier): self
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

    public function getVerifier(): ?DboxTokenVerifier
    {
        return $this->verifier;
    }

    public function getError(): array
    {
        return $this->error;
    }
}
