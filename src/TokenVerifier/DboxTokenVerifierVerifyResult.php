<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\TokenVerifier;

/**
 * Represents the result of verifying a Dropbox API access token.
 *
 * Encapsulates both successful and failed creation attempts.
 *
 * On success:
 * - `$success` will be true.
 * - `$error` will be an empty array.
 *
 * On failure:
 * - `$success` will be false.
 * - `$error` will contain details about what went wrong.
 */
final class DboxTokenVerifierVerifyResult
{
    /**
     * Indicates whether the token verification was successful.
     *
     * @var bool
     */
    private bool $success;

    /**
     * Error details if verification failed. Empty array on success.
     *
     * @var array<string, int|string>
     */
    private array $error;

    /**
     * Private constructor. Use static `success()` or `failure()` methods.
     *
     * @param bool $success Whether verification succeeded
     * @param array<string, int|string> $error Error details if failed
     */
    private function __construct(bool $success, array $error = [])
    {
        $this->success = $success;
        $this->error = $error;
    }

    /**
     * Creates a successful verification result.
     *
     * @return self
     */
    public static function success(): self
    {
        return new self(true);
    }

    /**
     * Creates a failure result with error details.
     *
     * @param array<string, int|string> $error Error information describing the failure
     * @return self
     */
    public static function failure(array $error): self
    {
        return new self(false, $error);
    }

    /**
     * Returns true if the verification was successful.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Returns error details if the verification failed.
     *
     * @return array<string, int|string> Array containing error details, empty if success
     */
    public function getError(): array
    {
        return $this->error;
    }
}
