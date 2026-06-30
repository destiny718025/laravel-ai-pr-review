# Phase 06: OpenAI Codex OAuth AI Provider - Pattern Map

**Mapped:** 2026-06-29
**Files analyzed:** 11 target files
**Analogs found:** 11 / 11

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `config/services.php` | config | request-response | `config/services.php` | exact |
| `app/Providers/AppServiceProvider.php` | provider | request-response | `app/Providers/AppServiceProvider.php` | exact |
| `app/Data/AI/CodexAuthCredentials.php` | data | transform | `app/Data/AI/AIReviewFailure.php` | role-match |
| `app/Services/AI/CodexAuthCacheReader.php` | service | file-I/O | `app/Services/AI/FakeAIReviewProvider.php` | partial |
| `app/Services/AI/HttpOpenAICodexOAuthReviewProvider.php` | service | request-response | `app/Services/AI/HttpOpenAIReviewProvider.php` | exact |
| `app/Services/AI/AIReviewFailureMapper.php` | service | transform | `app/Services/GitHub/GitHubFailureMapper.php` | role-match |
| `tests/Unit/AI/CodexAuthCacheReaderTest.php` | test | file-I/O | `tests/Unit/AI/FakeAIReviewProviderTest.php` | partial |
| `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php` | test | request-response | `tests/Unit/AI/OpenAIReviewProviderTest.php` | exact |
| `tests/Unit/AI/OpenAIReviewProviderTest.php` | test | request-response | `tests/Unit/AI/OpenAIReviewProviderTest.php` | exact |
| `tests/Unit/AI/AIReviewFailureMapperTest.php` | test | transform | `tests/Unit/GitHub/GitHubFailureMapperTest.php` | role-match |
| `tests/Feature/QueuedReviewFailureTest.php` | test | request-response | `tests/Feature/QueuedReviewFailureTest.php` | exact |

## Pattern Assignments

### `config/services.php` (config, request-response)

**Analog:** `config/services.php` lines 25-37

Keep third-party settings centralized in the nested `services.*` array and drive everything from config keys, not `env()` calls in services:

```php
'github' => [
    'base_url' => env('GITHUB_API_BASE_URL', 'https://api.github.com'),
    'api_version' => env('GITHUB_API_VERSION', '2022-11-28'),
    'token' => env('GITHUB_TOKEN'),
],

'openai' => [
    'enabled' => env('OPENAI_ENABLED', false),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com'),
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-5.4-mini'),
    'timeout' => env('OPENAI_TIMEOUT', 30),
],
```

Phase 06 should keep the same shape and add explicit provider-selection and Codex auth-path/base-url keys under `services`, rather than reading `env()` in AI services.

### `app/Providers/AppServiceProvider.php` (provider, request-response)

**Analog:** `app/Providers/AppServiceProvider.php` lines 17-26

Provider resolution is already container-driven. Replace the boolean branch with an explicit selector, but keep the binding in `register()`:

```php
public function register(): void
{
    $this->app->bind(GitHubClient::class, HttpGitHubClient::class);
    $this->app->bind(AIReviewProvider::class, function () {
        if ((bool) config('services.openai.enabled', false)) {
            return app(HttpOpenAIReviewProvider::class);
        }

        return app(FakeAIReviewProvider::class);
    });
}
```

Use this exact `bind(..., function () { return app(...); })` pattern for `fake`, `openai_api_key`, and `openai_codex_oauth`. Throw a safe `InvalidArgumentException` for unsupported selectors instead of falling back silently.

### `app/Data/AI/CodexAuthCredentials.php` (data, transform)

**Analog:** `app/Data/AI/AIReviewFailure.php` lines 5-10, `app/Data/GitHub/PullRequestSnapshot.php` lines 5-12

Keep AI DTOs small, `readonly`, and constructor-promoted:

```php
readonly class AIReviewFailure
{
    public function __construct(
        public string $code,
        public string $message,
    ) {}
}
```

```php
readonly class PullRequestSnapshot
{
    public function __construct(
        public string $title,
        public string $state,
        public string $headSha,
    ) {
    }
}
```

`CodexAuthCredentials` should follow this shape: only expose parsed runtime fields the provider needs, such as `accessToken`, optional `accountId`, optional `authMode`, and optional `lastRefresh`.

### `app/Services/AI/CodexAuthCacheReader.php` (service, file-I/O)

**Analog:** `app/Services/AI/FakeAIReviewProvider.php` lines 10-16, `app/Data/GitHub/PullRequestSnapshot.php` lines 17-44

Use an injectable path/config seam like the fake provider does, but combine it with strict payload validation:

```php
public function __construct(private readonly ?string $fixturePath = null) {}

public function review(AIReviewRequest $request): string
{
    $path = $this->fixturePath ?: base_path('tests/Fixtures/AI/fake-review-valid.json');

    return (string) file_get_contents($path);
}
```

```php
public static function fromGitHubPayload(array $payload): self
{
    $head = $payload['head'] ?? null;

    if (! is_array($head)) {
        throw new \UnexpectedValueException('GitHub pull request payload is missing head metadata.');
    }

    return new self(
        title: self::requiredString($payload, 'title'),
        state: self::requiredString($payload, 'state'),
        headSha: self::requiredString($head, 'sha'),
    );
}
```

For Phase 06, keep filesystem work isolated here:
- discover override path first, then `CODEX_HOME/auth.json`, then `~/.codex/auth.json`
- parse JSON with explicit required-field checks
- throw typed/safe exceptions or `UnexpectedValueException` variants without embedding raw file contents or token fragments

### `app/Services/AI/HttpOpenAICodexOAuthReviewProvider.php` (service, request-response)

**Analog:** `app/Services/AI/HttpOpenAIReviewProvider.php` lines 12-70

Mirror the current provider structure closely: build the outbound payload in `review()`, keep HTTP client setup in a private `request()` helper, and return raw JSON text only.

```php
public function review(AIReviewRequest $request): string
{
    $response = $this->request()
        ->post('/v1/responses', [
            'model' => config('services.openai.model'),
            'input' => [
                [
                    'role' => 'system',
                    'content' => $request->instructions,
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'repository' => $request->repositoryFullName,
                        'pull_request_number' => $request->pullRequestNumber,
                        'source_url' => $request->sourceUrl,
                        'head_sha' => $request->headSha,
                        'title' => $request->title,
                        'files' => $request->changedFiles,
                    ], JSON_THROW_ON_ERROR),
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_object',
                ],
            ],
        ])
        ->throw()
        ->json();
```

```php
private function request(): PendingRequest
{
    $request = Http::baseUrl((string) config('services.openai.base_url', 'https://api.openai.com'))
        ->timeout((int) config('services.openai.timeout', 30))
        ->acceptJson()
        ->asJson();

    $apiKey = config('services.openai.api_key');

    if (is_string($apiKey) && $apiKey !== '') {
        $request = $request->withToken($apiKey);
    }

    return $request;
}
```

Keep the same separation, but source the bearer token from `CodexAuthCacheReader`, target the Codex backend URL from config, and preserve the current response-text extraction contract:

```php
$text = data_get($response, 'output.0.content.0.text')
    ?? data_get($response, 'choices.0.message.content');

if (! is_string($text) || $text === '') {
    throw new \UnexpectedValueException('OpenAI response did not include review JSON text.');
}
```

### `app/Services/AI/AIReviewFailureMapper.php` (service, transform)

**Analog:** `app/Services/GitHub/GitHubFailureMapper.php` lines 21-84, `app/Services/AI/AIReviewFailureMapper.php` lines 12-39

Keep returning a small failure DTO, but extend AI mapping using the same status-aware branching style the GitHub mapper already uses:

```php
if ($throwable instanceof RequestException) {
    return $this->mapRequestException($throwable, $context);
}

if ($throwable instanceof ConnectionException) {
    return new GitHubFailure(
        'server_unavailable',
        $this->serverUnavailableMessage($context),
    );
}

if ($throwable instanceof \UnexpectedValueException) {
    return new GitHubFailure(
        'malformed_response',
        $this->malformedResponseMessage($context),
    );
}
```

Preserve the existing AI mapper return shape and safe-message style:

```php
if ($throwable instanceof JsonException) {
    return new AIReviewFailure(
        'invalid_json',
        'AI provider returned invalid JSON. Try running the review again.',
    );
}
```

Phase 06 should add Codex-specific categories for missing auth cache, malformed auth cache, missing access token, unauthorized, rate-limited, transport failure, malformed response, and unsupported response shape. Do not include headers, auth file contents, or bearer fragments in any returned message.

### `tests/Unit/AI/CodexAuthCacheReaderTest.php` (test, file-I/O)

**Analog:** `tests/Unit/AI/FakeAIReviewProviderTest.php` lines 20-35, `tests/Unit/GitHub/GitHubFailureMapperTest.php` lines 14-54

Follow the project’s unit-test style: one behavior per method, explicit config setup, and direct assertions on safe outputs.

```php
public function test_fake_provider_returns_deterministic_fixture_json(): void
{
    $json = app(AIReviewProvider::class)->review($this->request());

    $this->assertJson($json);
    $this->assertStringContainsString('Unhandled malformed upstream payload', $json);
}
```

```php
public function test_maps_malformed_payload_to_safe_message(): void
{
    $failure = app(GitHubFailureMapper::class)->map(new \UnexpectedValueException('raw payload missing head.sha'));

    $this->assertSame('malformed_response', $failure->code);
    $this->assertSame('GitHub returned an unexpected response. Try again later.', $failure->message);
}
```

Use temporary test files or an injected path override. Cover missing file, unreadable file, malformed JSON, and missing access token, and assert that exception messages never contain raw token-like substrings.

### `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php` (test, request-response)

**Analog:** `tests/Unit/AI/OpenAIReviewProviderTest.php` lines 34-63

Mirror the current provider HTTP fake pattern:

```php
Http::preventStrayRequests();
Http::fake([
    'https://api.openai.test/v1/responses' => Http::response([
        'output' => [
            [
                'content' => [
                    [
                        'text' => '{"findings":[]}',
                    ],
                ],
            ],
        ],
    ]),
]);

$json = app(HttpOpenAIReviewProvider::class)->review($this->request());

$this->assertSame('{"findings":[]}', $json);
Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v1/responses'));
Http::assertSentCount(1);
```

Extend the same structure for:
- bearer token sourced from fake auth credentials
- 401/403 and 429 backend failures
- transport exceptions
- malformed success bodies
- no fallback to API-key provider

### `tests/Unit/AI/OpenAIReviewProviderTest.php` (test, request-response)

**Analog:** `tests/Unit/AI/OpenAIReviewProviderTest.php` lines 14-32

Keep provider-resolution assertions container-focused and config-driven:

```php
public function test_openai_provider_resolves_only_when_config_enabled(): void
{
    config([
        'services.openai.enabled' => true,
        'services.openai.base_url' => 'https://api.openai.test',
        'services.openai.api_key' => 'sk-test-secret',
        'services.openai.model' => 'gpt-test',
        'services.openai.timeout' => 5,
    ]);

    $this->assertInstanceOf(HttpOpenAIReviewProvider::class, app(AIReviewProvider::class));
}
```

```php
public function test_fake_provider_remains_default_when_openai_disabled(): void
{
    config(['services.openai.enabled' => false]);

    $this->assertInstanceOf(FakeAIReviewProvider::class, app(AIReviewProvider::class));
}
```

Phase 06 should convert these into explicit selector tests for `fake`, `openai_api_key`, and `openai_codex_oauth`, with an assertion that Codex mode resolves its own provider class instead of falling through to API-key behavior.

### `tests/Unit/AI/AIReviewFailureMapperTest.php` (test, transform)

**Analog:** `tests/Unit/GitHub/GitHubFailureMapperTest.php` lines 14-107, `tests/Unit/AI/AIReviewFailureMapperTest.php` lines 13-51

Use the existing AI assertions for safe-message shape, then borrow GitHub’s `RequestException` fixture helper pattern for status-based cases:

```php
public function test_transport_timeout_maps_to_safe_summary(): void
{
    $failure = app(AIReviewFailureMapper::class)->map(
        new ConnectionException('Authorization: Bearer sk-secret'),
    );

    $this->assertSame('provider_unavailable', $failure->code);
    $this->assertSame('AI provider could not be reached. Try running the review again later.', $failure->message);
    $this->assertStringNotContainsString('sk-secret', $failure->message);
}
```

```php
private function requestException(int $status, array $headers = []): RequestException
{
    $response = new Response(new PsrResponse(
        $status,
        $headers,
        json_encode(['message' => 'raw upstream body'], JSON_THROW_ON_ERROR),
    ));

    return new RequestException($response);
}
```

Add focused cases for Codex 401/403, 429, malformed response, and missing-auth situations. Keep every assertion centered on sanitized `code` and `message` values only.

### `tests/Feature/QueuedReviewFailureTest.php` (test, request-response)

**Analog:** `tests/Feature/QueuedReviewFailureTest.php` lines 24-73 and 140-152

Preserve the queued-review failure pattern: inject a fake provider or throwing stub, execute the job, then assert persisted safe failure state and zero secret leakage.

```php
$this->app->instance(AIReviewProvider::class, new class implements AIReviewProvider
{
    public function review(AIReviewRequest $request): string
    {
        throw new ConnectionException('Timeout with Authorization: Bearer sk-secret');
    }
});

$reviewRun = $this->queuedReviewRun();

(new ExecuteReviewRunJob($reviewRun->id))->handle(app(ReviewExecutionService::class));

$this->assertFailedSafely($reviewRun, 'AI provider could not be reached. Try running the review again later.');
```

```php
foreach (['Authorization', 'Bearer', 'sk-secret', 'raw provider payload', 'secret fragment'] as $fragment) {
    $this->assertStringNotContainsString($fragment, (string) $reviewRun->safe_error_message);
}
```

Add Codex-auth and Codex-backend failure scenarios here rather than branching `ReviewExecutionService`. The execution workflow should stay provider-agnostic.

## Shared Patterns

### AI Provider Contract

**Source:** `app/Contracts/AI/AIReviewProvider.php` lines 7-9
**Apply to:** `HttpOpenAICodexOAuthReviewProvider`, provider-resolution tests

```php
interface AIReviewProvider
{
    public function review(AIReviewRequest $request): string;
}
```

Do not change the contract for Phase 06. The new provider must still return raw JSON text.

### Request DTO Boundary

**Source:** `app/Data/AI/AIReviewRequest.php` lines 5-18, `app/Services/ReviewExecutionService.php` lines 62-85
**Apply to:** `HttpOpenAICodexOAuthReviewProvider`

```php
readonly class AIReviewRequest
{
    public function __construct(
        public string $repositoryFullName,
        public int $pullRequestNumber,
        public string $sourceUrl,
        public string $headSha,
        public string $title,
        public array $changedFiles,
        public string $instructions,
    ) {}
}
```

```php
return new AIReviewRequest(
    repositoryFullName: $repository->full_name,
    pullRequestNumber: $pullRequest->number,
    sourceUrl: $pullRequest->source_url,
    headSha: (string) $reviewRun->github_head_sha,
    title: (string) $reviewRun->github_title,
    changedFiles: $reviewRun->files
        ->map(fn ($file): array => [
            'filename' => $file->filename,
            'patch' => $file->patch,
            'sha' => $file->sha,
        ])
        ->values()
        ->all(),
    instructions: $this->instructionBuilder->buildWithCustomInstructions(
        $this->instructionSettings->findGlobal()?->custom_instructions,
    ),
);
```

Codex OAuth should consume this DTO as-is. Do not add provider-specific branches to `ReviewExecutionService`.

### Validation Boundary

**Source:** `app/Services/AI/AIReviewPayloadValidator.php` lines 18-40
**Apply to:** `HttpOpenAICodexOAuthReviewProvider`, `QueuedReviewFailureTest`

```php
$validator = Validator::make($payload, [
    'findings' => ['required', 'array'],
    'findings.*' => ['required', 'array'],
    'findings.*.severity' => ['required', 'string', Rule::in(ValidatedFindingPayload::SEVERITIES)],
    'findings.*.category' => ['required', 'string', Rule::in(ValidatedFindingPayload::CATEGORIES)],
    'findings.*.file_path' => ['required', 'string'],
    'findings.*.line_reference' => ['nullable', 'string'],
    'findings.*.title' => ['required', 'string'],
    'findings.*.rationale' => ['required', 'string'],
    'findings.*.suggested_comment_text' => ['required', 'string'],
]);
```

The new provider should adapt upstream payloads until it can return JSON matching this validator. Unsupported shapes should fail before persistence.

### Safe Failure Persistence

**Source:** `app/Services/ReviewExecutionService.php` lines 35-59
**Apply to:** `AIReviewFailureMapper`, `QueuedReviewFailureTest`

```php
try {
    $payload = json_decode(
        $this->provider->review($this->makeRequest($reviewRun)),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    if (! is_array($payload)) {
        throw new \UnexpectedValueException('AI review payload must be an object.');
    }

    $validatedFindings = $this->validator->validate($payload);
    // ...
} catch (\Throwable $throwable) {
    $failure = $this->failureMapper->map($throwable);

    $this->reviewRuns->markExecutionFailed($reviewRun, $failure->message);
}
```

Phase 06 should keep failure handling here. New provider/auth errors must reduce to sanitized mapped messages before the repository persists them.

## No Analog Found

None. The repo already has usable analogs for config binding, external HTTP providers, safe failure mapping, DTOs, and queued-failure tests. The weakest match is `CodexAuthCacheReader`, which should combine the file-read seam from `FakeAIReviewProvider` with strict payload validation from `PullRequestSnapshot`.

## Metadata

**Analog search scope:** `app/`, `config/`, `tests/`, `.planning/phases/06-openai-codex-oauth-ai-provider/`
**Files scanned:** 17
**Pattern extraction date:** 2026-06-29
