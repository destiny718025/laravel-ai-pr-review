<?php

namespace App\Services\AI;

use App\Data\AI\CodexAuthCredentials;
use App\Exceptions\AI\CodexAuthException;
use JsonException;

class CodexAuthCacheReader
{
    public function read(): CodexAuthCredentials
    {
        $path = $this->resolveAuthPath();

        if ($path === null) {
            throw new CodexAuthException(
                'auth_cache_missing',
                'Codex auth cache is unavailable. Sign in with Codex CLI and try again.',
            );
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new CodexAuthException(
                'auth_cache_unreadable',
                'Codex auth cache could not be read. Check your Codex CLI login state and try again.',
            );
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new CodexAuthException(
                'auth_cache_malformed',
                'Codex auth cache is invalid. Re-authenticate with Codex CLI and try again.',
            );
        }

        if (! is_array($payload)) {
            throw new CodexAuthException(
                'auth_cache_malformed',
                'Codex auth cache is invalid. Re-authenticate with Codex CLI and try again.',
            );
        }

        $tokens = $payload['tokens'] ?? null;
        $accessToken = is_array($tokens) ? ($tokens['access_token'] ?? null) : null;

        if (! is_string($accessToken) || trim($accessToken) === '') {
            throw new CodexAuthException(
                'access_token_missing',
                'Codex auth cache does not include a usable access token. Sign in with Codex CLI and try again.',
            );
        }

        $accountId = is_array($tokens) && is_string($tokens['account_id'] ?? null)
            ? $tokens['account_id']
            : null;

        return new CodexAuthCredentials(
            accessToken: $accessToken,
            accountId: $accountId,
            authMode: is_string($payload['auth_mode'] ?? null) ? $payload['auth_mode'] : null,
            lastRefresh: is_string($payload['last_refresh'] ?? null) ? $payload['last_refresh'] : null,
        );
    }

    private function resolveAuthPath(): ?string
    {
        foreach ($this->candidatePaths() as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidatePaths(): array
    {
        $paths = [];

        $overridePath = $this->normalizePath(config('services.codex.auth_path'));

        if ($overridePath !== null) {
            $paths[] = $overridePath;
        }

        $codexHome = $this->normalizePath(config('services.codex.home'));

        if ($codexHome !== null) {
            $paths[] = rtrim($codexHome, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'auth.json';
        }

        $fallbackHome = $this->normalizePath(config('services.codex.fallback_home'));

        if ($fallbackHome !== null) {
            $paths[] = rtrim($fallbackHome, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.codex'.DIRECTORY_SEPARATOR.'auth.json';
        }

        return $paths;
    }

    private function normalizePath(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $path = trim($value);

        return $path === '' ? null : $path;
    }
}
