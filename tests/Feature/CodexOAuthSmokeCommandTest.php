<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CodexOAuthSmokeCommandTest extends TestCase
{
    private string $tempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDirectory = sys_get_temp_dir().'/codex-oauth-smoke-command-'.bin2hex(random_bytes(8));
        mkdir($this->tempDirectory, 0777, true);

        config([
            'services.ai.provider' => 'openai_codex_oauth',
            'services.codex.auth_path' => $this->tempDirectory.'/auth.json',
            'services.codex.home' => null,
            'services.codex.fallback_home' => null,
            'services.codex.base_url' => 'https://chatgpt.test/backend-api/codex',
            'services.codex.timeout' => 7,
            'services.openai.model' => 'gpt-test',
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDirectory)) {
            array_map('unlink', glob($this->tempDirectory.'/*') ?: []);
            rmdir($this->tempDirectory);
        }

        parent::tearDown();
    }

    public function test_dry_run_checks_auth_cache_without_printing_token(): void
    {
        $this->writeAuthJson('codex-secret-token', 'acct-123456');

        $exitCode = Artisan::call('ai:codex-oauth-test');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Provider: openai_codex_oauth', $output);
        $this->assertStringContainsString($this->tempDirectory.'/auth.json (readable)', $output);
        $this->assertStringContainsString('Auth cache: readable', $output);
        $this->assertStringContainsString('Access token: present', $output);
        $this->assertStringContainsString('Account ID: *******3456', $output);
        $this->assertStringContainsString('Dry run complete. Add --live', $output);
        $this->assertStringNotContainsString('codex-secret-token', $output);
        $this->assertStringNotContainsString('acct-123456', $output);
    }

    public function test_missing_auth_cache_fails_safely(): void
    {
        $exitCode = Artisan::call('ai:codex-oauth-test');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString($this->tempDirectory.'/auth.json (missing)', $output);
        $this->assertStringContainsString('Auth cache: failed', $output);
        $this->assertStringContainsString('Reason: auth_cache_missing', $output);
    }

    public function test_live_mode_sends_codex_request_and_validates_response(): void
    {
        $this->writeAuthJson('codex-secret-token', 'acct-123456');

        Http::preventStrayRequests();
        Http::fake([
            'https://chatgpt.test/backend-api/codex/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => <<<'JSON'
{"findings":[{"severity":"low","category":"maintainability","file_path":"app/Example.php","line_reference":"2","title":"Smoke test finding","rationale":"This validates that Codex OAuth response text can be decoded and schema-validated.","suggested_comment_text":"The Codex OAuth provider smoke test returned a valid finding payload."}]}
JSON,
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $exitCode = Artisan::call('ai:codex-oauth-test', ['--live' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Live request succeeded. Valid findings: 1', $output);
        $this->assertStringNotContainsString('codex-secret-token', $output);
        $this->assertStringNotContainsString('acct-123456', $output);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $reviewInput = json_decode((string) $payload['input'][1]['content'], true, 512, JSON_THROW_ON_ERROR);

            return $request->url() === 'https://chatgpt.test/backend-api/codex/responses'
                && $request->hasHeader('Authorization', 'Bearer codex-secret-token')
                && $request->hasHeader('ChatGPT-Account-ID', 'acct-123456')
                && $payload['store'] === false
                && $payload['stream'] === true
                && $reviewInput['repository'] === 'example/codex-oauth-smoke-test'
                && $reviewInput['pull_request_number'] === 1;
        });
    }

    private function writeAuthJson(string $accessToken, ?string $accountId): void
    {
        file_put_contents($this->tempDirectory.'/auth.json', json_encode([
            'auth_mode' => 'chatgpt',
            'last_refresh' => '2026-06-30T00:00:00Z',
            'tokens' => [
                'access_token' => $accessToken,
                'account_id' => $accountId,
            ],
        ], JSON_THROW_ON_ERROR));
    }
}
