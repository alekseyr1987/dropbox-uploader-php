<?php

declare(strict_types=1);

namespace Dbox\UploaderApi\ApiClient;

/**
 * Represents the result of fetching a Dropbox API access token.
 *
 * Encapsulates both successful and failed token fetch attempts.
 *
 * On success:
 * - `$success` will be true.
 * - `$accessToken` will contain a non-empty string.
 * - `$error` will be an empty array.
 *
 * On failure:
 * - `$success` will be false.
 * - `$accessToken` will be null.
 * - `$error` will contain details about what went wrong.
 */
final class DboxApiClientFetchTokenResult
{
    /**
     * Indicates whether the token fetch was successful.
     *
     * @var bool True if the token was successfully fetched
     */
    private bool $success;

    /**
     * The fetched access token if successful, otherwise null.
     *
     * @var ?string Non-empty string with the token on success, or null on failure
     */
    private ?string $accessToken;

    /**
     * Error details if the token fetch failed. Empty array on success.
     *
     * @var array<string, int|string> Error details describing the cause of failure
     */
    private array $error;

    /**
     * Private constructor. Use static `success()` or `failure()` methods.
     *
     * @param bool $success Whether the token fetch was successful
     * @param ?string $accessToken The fetched access token if successful, null otherwise
     * @param array<string, int|string> $error Error details if failed
     */
    private function __construct(bool $success, ?string $accessToken, array $error)
    {
        $this->success = $success;
        $this->accessToken = $accessToken;
        $this->error = $error;
    }

    /**
     * Creates a successful result with a non-empty access token.
     *
     * @param string $accessToken The successfully fetched access token
     *
     * @return self Instance representing a successful result
     */
    public static function success(string $accessToken): self
    {
        return new self(true, $accessToken, []);
    }

    /**
     * Creates a failure result with error details.
     *
     * @param array<string, int|string> $error Error information describing the failure
     *
     * @return self Instance representing a failed result
     */
    public static function failure(array $error): self
    {
        return new self(false, null, $error);
    }

    /**
     * Returns true if the token fetch was successful.
     *
     * @return bool True if the token was successfully fetched
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Returns the fetched access token if successful.
     *
     * @return ?string The fetched access token on success, or null on failure
     */
    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    /**
     * Returns error details if the token fetch failed.
     *
     * @return array<string, int|string> Array containing error details, empty if success
     */
    public function getError(): array
    {
        return $this->error;
    }
}
