<?php

namespace App\Services\AI;

use App\Contracts\AI\AIReviewProvider;
use App\Data\AI\AIReviewRequest;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class HttpOpenAIReviewProvider implements AIReviewProvider
{
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

        if (! is_array($response)) {
            throw new \UnexpectedValueException('OpenAI response must be an object.');
        }

        $text = data_get($response, 'output.0.content.0.text')
            ?? data_get($response, 'choices.0.message.content');

        if (! is_string($text) || $text === '') {
            throw new \UnexpectedValueException('OpenAI response did not include review JSON text.');
        }

        return $text;
    }

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
}
