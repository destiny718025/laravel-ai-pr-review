<?php

namespace App\Services\AI;

use App\Contracts\AI\AIReviewProvider;
use App\Data\AI\AIReviewRequest;
use App\Data\AI\CodexAuthCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class HttpOpenAICodexOAuthReviewProvider implements AIReviewProvider
{
    public function __construct(
        private readonly CodexAuthCacheReader $authCacheReader,
    ) {}

    public function review(AIReviewRequest $request): string
    {
        $credentials = $this->authCacheReader->read();

        $response = $this->request($credentials)
            ->post('/responses', [
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

        if (! is_array($response)) {
            throw new UnexpectedValueException('Codex response must be an object.');
        }

        return $this->extractReviewJson($response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractReviewJson(array $response): string
    {
        $output = $response['output'] ?? null;

        if ($output !== null && ! is_array($output)) {
            throw new UnexpectedValueException('Codex response output must be an array.');
        }

        if (is_array($output)) {
            foreach ($output as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $content = $item['content'] ?? null;

                if (! is_array($content)) {
                    continue;
                }

                foreach ($content as $part) {
                    if (! is_array($part)) {
                        continue;
                    }

                    $type = $part['type'] ?? null;

                    if (! is_string($type) || ! in_array($type, ['output_text', 'text'], true)) {
                        continue;
                    }

                    $text = $part['text'] ?? null;

                    if (is_string($text) && trim($text) !== '') {
                        return $text;
                    }
                }
            }
        }

        $fallback = $response['output_text'] ?? null;

        if ($fallback === null) {
            throw new UnexpectedValueException('Codex returned an unsupported response shape.');
        }

        if (! is_string($fallback) || trim($fallback) === '') {
            throw new UnexpectedValueException('Codex response output_text must be a string.');
        }

        return $fallback;
    }

    private function request(CodexAuthCredentials $credentials): PendingRequest
    {
        $request = Http::baseUrl((string) config('services.codex.base_url', 'https://chatgpt.com/backend-api/codex'))
            ->timeout((int) config('services.codex.timeout', 30))
            ->acceptJson()
            ->asJson()
            ->withToken($credentials->accessToken);

        if (is_string($credentials->accountId) && $credentials->accountId !== '') {
            $request = $request->withHeaders([
                'ChatGPT-Account-ID' => $credentials->accountId,
            ]);
        }

        return $request;
    }
}
