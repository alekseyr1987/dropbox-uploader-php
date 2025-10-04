<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\TokenVerifier;

/**
 * Represents the result of creating a `DboxTokenVerifier` instance.
 *
 * Encapsulates both successful and failed creation attempts.
 *
 * On success:
 * - `$success` will be true.
 * - `$verifier` will contain a valid verifier.
 * - `$error` will be an empty array.
 *
 * On failure:
 * - `$success` will be false.
 * - `$verifier` will be null.
 * - `$error` will contain details about what went wrong.
 */
final class DboxTokenVerifierCreateResult
{
    /**
     * Indicates whether the verifier creation was successful.
     *
     * @var bool
     */
    private bool $success;

    /**
     * The created verifier if successful, otherwise null.
     *
     * @var ?DboxTokenVerifier
     */
    private ?DboxTokenVerifier $verifier;

    /**
     * Error details if the verifier creation failed. Empty array on success.
     *
     * @var array<string, int|string>
     */
    private array $error;

    /**
     * Private constructor. Use static `success()` or `failure()` methods.
     *
     * @param bool $success Whether the verifier creation was successful
     * @param ?DboxTokenVerifier $verifier The created verifier if successful, null otherwise
     * @param array<string, int|string> $error Error details if failed
     */
    private function __construct(bool $success, ?DboxTokenVerifier $verifier, array $error)
    {
        $this->success = $success;
        $this->verifier = $verifier;
        $this->error = $error;
    }

    /**
     * Creates a successful result with a verifier.
     *
     * @param DboxTokenVerifier $verifier The successfully created verifier
     * @return self
     */
    public static function success(DboxTokenVerifier $verifier): self
    {
        return new self(true, $verifier, []);
    }

    /**
     * Creates a failure result with error details.
     *
     * @param array<string, int|string> $error Error information describing the failure
     * @return self
     */
    public static function failure(array $error): self
    {
        return new self(false, null, $error);
    }

    /**
     * Returns true if the verifier creation was successful.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Returns the created verifier if successful.
     *
     * @return ?DboxTokenVerifier The created verifier on success, or null on failure
     */
    public function getVerifier(): ?DboxTokenVerifier
    {
        return $this->verifier;
    }

    /**
     * Returns error details if the verifier creation failed.
     *
     * @return array<string, int|string> Array containing error details, empty if success
     */
    public function getError(): array
    {
        return $this->error;
    }
}
