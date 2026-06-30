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
                'store' => false,
                'stream' => true,
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
            ->throw();

        $body = $response->body();

        if ($this->isEventStream($body, $response->header('Content-Type'))) {
            return $this->extractReviewJsonFromStream($body, $credentials);
        }

        $response = $response->json();

        if (! is_array($response)) {
            throw new UnexpectedValueException('Codex response must be an object.');
        }

        return $this->extractReviewJson($response, $credentials);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function awaitCompletedResponse(array $response, CodexAuthCredentials $credentials): array
    {
        if (! $this->isPendingResponse($response)) {
            return $response;
        }

        $id = $this->responseId($response);

        if ($id === null) {
            throw new UnexpectedValueException(
                'Codex returned an in-progress response without an id. '.$this->describeResponseShape($response),
            );
        }

        $attempts = max(1, (int) config('services.codex.poll_attempts', 20));
        $sleepMs = max(0, (int) config('services.codex.poll_sleep_ms', 250));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }

            $polled = $this->retrieveResponse($credentials, $id);

            if ($this->isTerminalFailureResponse($polled)) {
                throw new UnexpectedValueException(
                    'Codex response did not complete successfully. '.$this->describeResponseShape($polled),
                );
            }

            if (! $this->isPendingResponse($polled)) {
                return $polled;
            }

            $response = $polled;
        }

        throw new UnexpectedValueException(
            'Codex response did not complete before polling limit. '.$this->describeResponseShape($response),
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function isPendingResponse(array $response): bool
    {
        $status = $this->responseStatus($response);

        if (in_array($status, ['in_progress', 'queued'], true)) {
            return true;
        }

        if (in_array($status, ['completed', 'failed', 'cancelled', 'incomplete', 'expired'], true)) {
            return false;
        }

        return $this->responseId($response) !== null
            && $this->hasNoOutputText($response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function responseId(array $response): ?string
    {
        $id = $response['id'] ?? null;

        if (! is_scalar($id) && ! (is_object($id) && method_exists($id, '__toString'))) {
            return null;
        }

        $id = trim((string) $id);

        return $id === '' ? null : $id;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function isTerminalFailureResponse(array $response): bool
    {
        $status = $this->responseStatus($response);

        return in_array($status, ['failed', 'cancelled', 'incomplete', 'expired'], true);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function hasNoOutputText(array $response): bool
    {
        $outputText = $response['output_text'] ?? null;

        if (is_string($outputText) && trim($outputText) !== '') {
            return false;
        }

        $output = $response['output'] ?? null;

        return $output === null || $output === [];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function responseStatus(array $response): ?string
    {
        $status = $response['status'] ?? null;

        if (! is_scalar($status) && ! (is_object($status) && method_exists($status, '__toString'))) {
            return null;
        }

        return strtolower(trim((string) $status));
    }

    private function isEventStream(string $body, ?string $contentType): bool
    {
        if (is_string($contentType) && str_contains(strtolower($contentType), 'text/event-stream')) {
            return true;
        }

        $trimmed = ltrim($body);

        return str_starts_with($trimmed, 'event:') || str_starts_with($trimmed, 'data:');
    }

    private function extractReviewJsonFromStream(string $body, CodexAuthCredentials $credentials): string
    {
        $buffer = '';
        $completedText = null;
        $dataLines = [];
        $eventTypes = [];
        $eventName = null;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = rtrim($line, "\r\n");

            if ($line === '') {
                $completedText = $this->consumeStreamData(
                    $dataLines,
                    $buffer,
                    $completedText,
                    $eventTypes,
                    $credentials,
                    $eventName,
                );
                $dataLines = [];
                $eventName = null;

                continue;
            }

            if (str_starts_with($line, 'event:')) {
                $eventName = ltrim(substr($line, 6));
            }

            if (str_starts_with($line, 'data:')) {
                $dataLines[] = ltrim(substr($line, 5));
            }
        }

        if ($dataLines !== []) {
            $completedText = $this->consumeStreamData(
                $dataLines,
                $buffer,
                $completedText,
                $eventTypes,
                $credentials,
                $eventName,
            );
        }

        if (is_string($completedText) && $this->looksLikeJsonObject($completedText)) {
            return $completedText;
        }

        if ($this->looksLikeJsonObject($buffer)) {
            return $buffer;
        }

        $seen = array_values(array_unique(array_filter($eventTypes, 'is_string')));
        $suffix = $seen === [] ? '' : ' Event types seen: '.implode(', ', $seen).'.';

        throw new UnexpectedValueException('Codex returned an unsupported streaming response shape.'.$suffix);
    }

    /**
     * @param  list<string>  $dataLines
     * @param  list<string>  $eventTypes
     */
    private function consumeStreamData(
        array $dataLines,
        string &$buffer,
        ?string $completedText,
        array &$eventTypes,
        CodexAuthCredentials $credentials,
        ?string $eventName,
    ): ?string
    {
        if ($dataLines === []) {
            return $completedText;
        }

        $data = trim(implode("\n", $dataLines));

        if ($data === '' || $data === '[DONE]') {
            return $completedText;
        }

        $event = json_decode($data, true);

        if (! is_array($event)) {
            throw new UnexpectedValueException('Codex streaming response contains malformed JSON.');
        }

        $type = $event['type'] ?? $eventName;

        if (is_string($type)) {
            $eventTypes[] = $type;
        }

        if (is_string($type) && (str_contains($type, 'failed') || str_contains($type, 'error'))) {
            throw new UnexpectedValueException('Codex streaming response reported a failure.');
        }

        if ($type === 'response.output_text.delta' && is_string($event['delta'] ?? null)) {
            $buffer .= $event['delta'];

            return $completedText;
        }

        if ($type === 'response.output_text.done' && is_string($event['text'] ?? null)) {
            return $event['text'];
        }

        foreach ($this->extractTextFragmentsFromStreamEvent($event, is_string($type) ? $type : null) as $fragment) {
            $buffer .= $fragment;
        }

        $response = $event['response'] ?? null;

        if (is_array($response) && $this->isPendingResponse($response)) {
            return $completedText;
        }

        if (is_array($response) && $this->isCompletedResponseWithoutOutput($response) && $this->looksLikeJsonObject($buffer)) {
            return $completedText;
        }

        if ($type === 'response.completed' && is_array($response)) {
            return $this->extractReviewJson($response, $credentials);
        }

        if (is_array($response) && (array_key_exists('output', $response) || array_key_exists('output_text', $response))) {
            return $this->extractReviewJson($response, $credentials);
        }

        if ($this->isPendingResponse($event)) {
            return $completedText;
        }

        if ($this->isCompletedResponseWithoutOutput($event) && $this->looksLikeJsonObject($buffer)) {
            return $completedText;
        }

        if (array_key_exists('output', $event) || array_key_exists('output_text', $event)) {
            return $this->extractReviewJson($event, $credentials);
        }

        return $completedText;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return list<string>
     */
    private function extractTextFragmentsFromStreamEvent(array $event, ?string $type): array
    {
        $fragments = [];

        if ($this->isTextishEventType($type)) {
            foreach (['delta', 'text', 'output_text'] as $key) {
                if (is_string($event[$key] ?? null)) {
                    $fragments[] = $event[$key];
                }
            }
        }

        foreach (['item', 'part', 'message', 'content'] as $key) {
            if (is_array($event[$key] ?? null)) {
                array_push($fragments, ...$this->extractNestedTextFragments($event[$key]));
            }
        }

        return array_values(array_filter(
            $fragments,
            fn (string $fragment): bool => $fragment !== '',
        ));
    }

    private function isTextishEventType(?string $type): bool
    {
        if ($type === null) {
            return false;
        }

        return str_contains($type, 'output_text')
            || str_contains($type, 'text_delta')
            || str_contains($type, 'content_part')
            || str_contains($type, 'message_delta')
            || str_contains($type, '.text.');
    }

    /**
     * @param  array<mixed>  $value
     * @return list<string>
     */
    private function extractNestedTextFragments(array $value): array
    {
        $fragments = [];
        $type = $value['type'] ?? null;

        if (! is_string($type) || in_array($type, ['output_text', 'text'], true) || $this->isTextishEventType($type)) {
            foreach (['text', 'output_text', 'delta'] as $key) {
                if (is_string($value[$key] ?? null)) {
                    $fragments[] = $value[$key];
                }
            }
        }

        foreach ($value as $nested) {
            if (is_array($nested)) {
                array_push($fragments, ...$this->extractNestedTextFragments($nested));
            }
        }

        return $fragments;
    }

    private function looksLikeJsonObject(string $value): bool
    {
        return str_starts_with(ltrim($value), '{') && str_ends_with(rtrim($value), '}');
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractReviewJson(
        array $response,
        ?CodexAuthCredentials $credentials = null,
        bool $allowRefreshEmptyCompleted = true,
    ): string
    {
        if ($this->isPendingResponse($response)) {
            if ($credentials === null) {
                throw new UnexpectedValueException(
                    'Codex returned a pending response where polling is unavailable. '.$this->describeResponseShape($response),
                );
            }

            return $this->extractReviewJson($this->awaitCompletedResponse($response, $credentials), $credentials);
        }

        if ($allowRefreshEmptyCompleted && $credentials !== null && $this->isCompletedResponseWithoutOutput($response)) {
            $id = $this->responseId($response);

            if ($id !== null) {
                return $this->extractReviewJson($this->retrieveResponse($credentials, $id), $credentials, false);
            }
        }

        $wrappedResponse = $response['response'] ?? null;

        if (is_array($wrappedResponse)) {
            return $this->extractReviewJson($wrappedResponse, $credentials, $allowRefreshEmptyCompleted);
        }

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

            foreach ($this->extractNestedTextFragments($output) as $fragment) {
                if ($this->looksLikeJsonObject($fragment)) {
                    return $fragment;
                }
            }
        }

        $fallback = $response['output_text'] ?? null;

        if ($fallback === null) {
            throw new UnexpectedValueException(
                'Codex returned an unsupported response shape. '.$this->describeResponseShape($response),
            );
        }

        if (! is_string($fallback) || trim($fallback) === '') {
            throw new UnexpectedValueException('Codex response output_text must be a string.');
        }

        return $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function retrieveResponse(CodexAuthCredentials $credentials, string $id): array
    {
        $response = $this->request($credentials, 'application/json')
            ->get('/responses/'.rawurlencode($id))
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new UnexpectedValueException('Codex retrieved response must be an object.');
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function isCompletedResponseWithoutOutput(array $response): bool
    {
        return $this->responseStatus($response) === 'completed'
            && $this->hasNoOutputText($response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function describeResponseShape(array $response): string
    {
        $parts = [];

        $topLevelKeys = array_slice(array_keys($response), 0, 12);
        $parts[] = 'Top-level keys: '.($topLevelKeys === [] ? 'none' : implode(', ', $topLevelKeys)).'.';

        foreach (['type', 'status'] as $key) {
            if (is_string($response[$key] ?? null)) {
                $parts[] = ucfirst($key).': '.$response[$key].'.';
            }
        }

        $parts[] = 'Pending check: status='.($this->responseStatus($response) ?? 'null')
            .', id_type='.get_debug_type($response['id'] ?? null)
            .', has_id='.($this->responseId($response) === null ? 'no' : 'yes')
            .', has_no_output='.($this->hasNoOutputText($response) ? 'yes' : 'no').'.';

        $output = $response['output'] ?? null;

        if (is_array($output)) {
            $outputTypes = [];
            $contentTypes = [];

            foreach ($output as $item) {
                if (! is_array($item)) {
                    $outputTypes[] = get_debug_type($item);

                    continue;
                }

                $type = $item['type'] ?? null;
                $outputTypes[] = is_string($type) ? $type : 'array';

                $content = $item['content'] ?? null;

                if (! is_array($content)) {
                    continue;
                }

                foreach ($content as $part) {
                    if (! is_array($part)) {
                        $contentTypes[] = get_debug_type($part);

                        continue;
                    }

                    $partType = $part['type'] ?? null;
                    $contentTypes[] = is_string($partType) ? $partType : 'array';
                }
            }

            $parts[] = 'Output item types: '.($outputTypes === [] ? 'none' : implode(', ', array_unique($outputTypes))).'.';
            $parts[] = 'Content part types: '.($contentTypes === [] ? 'none' : implode(', ', array_unique($contentTypes))).'.';
        }

        return implode(' ', $parts);
    }

    private function request(CodexAuthCredentials $credentials, string $accept = 'text/event-stream'): PendingRequest
    {
        $request = Http::baseUrl((string) config('services.codex.base_url', 'https://chatgpt.com/backend-api/codex'))
            ->timeout((int) config('services.codex.timeout', 30))
            ->accept($accept)
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
