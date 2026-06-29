<?php

namespace Tests\Unit\AI;

use App\Data\AI\CodexAuthCredentials;
use App\Exceptions\AI\CodexAuthException;
use App\Services\AI\CodexAuthCacheReader;
use Tests\TestCase;

class CodexAuthCacheReaderTest extends TestCase
{
    private string $tempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDirectory = sys_get_temp_dir().'/codex-auth-cache-reader-'.bin2hex(random_bytes(8));

        mkdir($this->tempDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDirectory);

        parent::tearDown();
    }

    public function test_reader_prefers_explicit_override_path_before_codex_home_and_home_directory(): void
    {
        $overridePath = $this->tempDirectory.'/override-auth.json';
        $codexHome = $this->tempDirectory.'/codex-home';
        $fallbackHome = $this->tempDirectory.'/home';

        mkdir($codexHome, 0777, true);
        mkdir($fallbackHome.'/.codex', 0777, true);

        $this->writeAuthJson($overridePath, [
            'auth_mode' => 'chatgpt',
            'last_refresh' => '2026-06-29T00:00:00Z',
            'tokens' => [
                'access_token' => 'override-access-token',
                'account_id' => 'account-override',
            ],
        ]);

        $this->writeAuthJson($codexHome.'/auth.json', [
            'tokens' => [
                'access_token' => 'codex-home-token',
                'account_id' => 'account-codex-home',
            ],
        ]);

        $this->writeAuthJson($fallbackHome.'/.codex/auth.json', [
            'tokens' => [
                'access_token' => 'home-token',
                'account_id' => 'account-home',
            ],
        ]);

        config([
            'services.codex.auth_path' => $overridePath,
            'services.codex.home' => $codexHome,
            'services.codex.fallback_home' => $fallbackHome,
        ]);

        $credentials = app(CodexAuthCacheReader::class)->read();

        $this->assertInstanceOf(CodexAuthCredentials::class, $credentials);
        $this->assertSame('override-access-token', $credentials->accessToken);
        $this->assertSame('account-override', $credentials->accountId);
        $this->assertSame('chatgpt', $credentials->authMode);
        $this->assertSame('2026-06-29T00:00:00Z', $credentials->lastRefresh);
    }

    public function test_reader_fails_safely_when_auth_cache_is_missing(): void
    {
        config([
            'services.codex.auth_path' => $this->tempDirectory.'/missing-auth.json',
            'services.codex.home' => null,
            'services.codex.fallback_home' => null,
        ]);

        $exception = $this->captureFailure(fn () => app(CodexAuthCacheReader::class)->read());

        $this->assertSame('auth_cache_missing', $exception->reason());
        $this->assertMessageIsSafe($exception, [
            $this->tempDirectory,
            'missing-auth.json',
        ]);
    }

    public function test_reader_fails_safely_when_auth_cache_is_not_a_readable_file(): void
    {
        $directoryPath = $this->tempDirectory.'/not-a-file';
        mkdir($directoryPath, 0777, true);

        config([
            'services.codex.auth_path' => $directoryPath,
            'services.codex.home' => null,
            'services.codex.fallback_home' => null,
        ]);

        $exception = $this->captureFailure(fn () => app(CodexAuthCacheReader::class)->read());

        $this->assertSame('auth_cache_unreadable', $exception->reason());
        $this->assertMessageIsSafe($exception, [
            $directoryPath,
        ]);
    }

    public function test_reader_fails_safely_when_auth_cache_json_is_malformed(): void
    {
        $path = $this->tempDirectory.'/malformed-auth.json';
        $rawBody = '{"tokens":{"access_token":"secret-access-token"';
        file_put_contents($path, $rawBody);

        config([
            'services.codex.auth_path' => $path,
            'services.codex.home' => null,
            'services.codex.fallback_home' => null,
        ]);

        $exception = $this->captureFailure(fn () => app(CodexAuthCacheReader::class)->read());

        $this->assertSame('auth_cache_malformed', $exception->reason());
        $this->assertMessageIsSafe($exception, [
            'secret-access-token',
            $rawBody,
        ]);
    }

    public function test_reader_fails_safely_when_access_token_is_missing(): void
    {
        $path = $this->tempDirectory.'/missing-token-auth.json';
        $this->writeAuthJson($path, [
            'tokens' => [
                'account_id' => 'account-without-token',
            ],
        ]);

        config([
            'services.codex.auth_path' => $path,
            'services.codex.home' => null,
            'services.codex.fallback_home' => null,
        ]);

        $exception = $this->captureFailure(fn () => app(CodexAuthCacheReader::class)->read());

        $this->assertSame('access_token_missing', $exception->reason());
        $this->assertMessageIsSafe($exception, [
            'account-without-token',
            '"tokens"',
            '"account_id"',
        ]);
    }

    private function captureFailure(callable $callback): CodexAuthException
    {
        try {
            $callback();
        } catch (CodexAuthException $exception) {
            return $exception;
        }

        $this->fail('Expected CodexAuthException to be thrown.');
    }

    private function assertMessageIsSafe(CodexAuthException $exception, array $fragments): void
    {
        foreach ($fragments as $fragment) {
            $this->assertStringNotContainsString($fragment, $exception->getMessage());
        }
    }

    private function writeAuthJson(string $path, array $payload): void
    {
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.'/'.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);

                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
