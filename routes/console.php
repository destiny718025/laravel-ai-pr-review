<?php

use App\Contracts\AI\AIReviewProvider;
use App\Data\AI\AIReviewRequest;
use App\Exceptions\AI\CodexAuthException;
use App\Services\AI\AIReviewPayloadValidator;
use App\Services\AI\CodexAuthCacheReader;
use App\Services\AI\ReviewInstructionBuilder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ai:codex-oauth-test {--live : Send a real smoke request through the configured Codex OAuth provider}', function (): int {
    /** @var CodexAuthCacheReader $authCacheReader */
    $authCacheReader = app(CodexAuthCacheReader::class);
    $providerName = (string) config('services.ai.provider', 'fake');
    $mask = static function (?string $value): string {
        if ($value === null || $value === '') {
            return 'not present';
        }

        if (strlen($value) <= 4) {
            return 'present';
        }

        return str_repeat('*', max(strlen($value) - 4, 0)).substr($value, -4);
    };

    $this->info('Codex OAuth AI provider check');
    $this->line('Provider: '.$providerName);
    $this->line('Model: '.(string) config('services.openai.model'));
    $this->line('Codex base URL: '.(string) config('services.codex.base_url'));

    $this->line('');
    $this->line('Auth cache candidates:');

    $candidatePaths = $authCacheReader->candidatePaths();

    if ($candidatePaths === []) {
        $this->warn('  - none configured');
    }

    foreach ($candidatePaths as $path) {
        $status = match (true) {
            is_file($path) && is_readable($path) => 'readable',
            file_exists($path) => 'exists but is not readable',
            default => 'missing',
        };

        $this->line("  - {$path} ({$status})");
    }

    try {
        $credentials = $authCacheReader->read();
    } catch (CodexAuthException $exception) {
        $this->error('Auth cache: failed');
        $this->line('Reason: '.$exception->reason());
        $this->line('Message: '.$exception->getMessage());

        return Command::FAILURE;
    }

    $this->info('Auth cache: readable');
    $this->line('Access token: present');
    $this->line('Account ID: '.$mask($credentials->accountId));
    $this->line('Auth mode: '.($credentials->authMode ?: 'unknown'));
    $this->line('Last refresh: '.($credentials->lastRefresh ?: 'unknown'));

    if (! $this->option('live')) {
        $this->line('');
        $this->info('Dry run complete. Add --live to send a real Codex smoke request.');

        return Command::SUCCESS;
    }

    if ($providerName !== 'openai_codex_oauth') {
        $this->error('Live test requires AI_PROVIDER=openai_codex_oauth.');

        return Command::FAILURE;
    }

    try {
        /** @var AIReviewProvider $provider */
        $provider = app(AIReviewProvider::class);
        /** @var ReviewInstructionBuilder $instructionBuilder */
        $instructionBuilder = app(ReviewInstructionBuilder::class);
        /** @var AIReviewPayloadValidator $validator */
        $validator = app(AIReviewPayloadValidator::class);

        $rawPayload = $provider->review(new AIReviewRequest(
            repositoryFullName: 'example/codex-oauth-smoke-test',
            pullRequestNumber: 1,
            sourceUrl: 'https://github.com/example/codex-oauth-smoke-test/pull/1',
            headSha: '0000000000000000000000000000000000000000',
            title: 'Codex OAuth smoke test',
            changedFiles: [
                [
                    'filename' => 'app/Example.php',
                    'patch' => "@@ -1,3 +1,4 @@\n <?php\n+\n echo 'hello';\n",
                    'sha' => '0000000000000000000000000000000000000000',
                ],
            ],
            instructions: $instructionBuilder->buildDefault(),
        ));

        $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            $this->error('Live request returned a non-object JSON payload.');

            return Command::FAILURE;
        }

        try {
            $findings = $validator->validate($payload);
        } catch (ValidationException $exception) {
            $this->error('Live request returned JSON that failed review schema validation.');
            $this->line('Top-level keys: '.(array_keys($payload) === [] ? 'none' : implode(', ', array_keys($payload))));
            $this->line('Error: '.$exception->getMessage());

            return Command::FAILURE;
        }
    } catch (Throwable $exception) {
        $this->error('Live request failed.');
        $this->line('Error: '.$exception->getMessage());

        return Command::FAILURE;
    }

    $this->info('Live request succeeded. Valid findings: '.count($findings));

    return Command::SUCCESS;
})->purpose('Check Codex OAuth auth cache and optionally send a live AI provider smoke request');
