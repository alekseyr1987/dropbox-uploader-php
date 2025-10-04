<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\ApiClient;

/**
 * Represents the result of creating a `DboxApiClient` instance.
 *
 * Encapsulates both successful and failed creation attempts.
 *
 * On success:
 * - `$success` will be true.
 * - `$client` will contain a valid client.
 * - `$error` will be an empty array.
 *
 * On failure:
 * - `$success` will be false.
 * - `$client` will be null.
 * - `$error` will contain details about what went wrong.
 */
final class DboxApiClientCreateResult
{
    /**
     * Indicates whether the client creation was successful.
     *
     * @var bool
     */
    private bool $success;

    /**
     * The created client if successful, otherwise null.
     *
     * @var ?DboxApiClient
     */
    private ?DboxApiClient $client;

    /**
     * Error details if the client creation failed. Empty array on success.
     *
     * @var array<string, int|string>
     */
    private array $error;

    /**
     * Private constructor. Use static `success()` or `failure()` methods.
     *
     * @param bool $success Whether the client creation was successful
     * @param ?DboxApiClient $client The created client if successful, null otherwise
     * @param array<string, int|string> $error Error details if failed
     */
    private function __construct(bool $success, ?DboxApiClient $client, array $error)
    {
        $this->success = $success;
        $this->client = $client;
        $this->error = $error;
    }

    /**
     * Creates a successful result with a client.
     *
     * @param DboxApiClient $client The successfully created client
     * @return self
     */
    public static function success(DboxApiClient $client): self
    {
        return new self(true, $client, []);
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
     * Returns true if the client creation was successful.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Returns the created client if successful.
     *
     * @return ?DboxApiClient The created client on success, or null on failure
     */
    public function getClient(): ?DboxApiClient
    {
        return $this->client;
    }

    /**
     * Returns error details if the client creation failed.
     *
     * @return array<string, int|string> Array containing error details, empty if success
     */
    public function getError(): array
    {
        return $this->error;
    }
}
