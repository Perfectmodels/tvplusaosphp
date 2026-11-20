<?php

namespace App\Services\Ai;

use App\Contracts\AiProviderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * GroqProvider — endpoint OpenAI-compatible /openai/v1/chat/completions
 * (mêmes paramètres que le SDK JS officiel groq-sdk)
 *
 * Modèles fréquents Groq (tous supportent stream:true):
 *  - llama-3.1-70b-versatile          (default, polyvalent FR)
 *  - qwen/qwen3.8-27b                   (rapide, bon rapport qualité/vitesse)
 *  - llama-3.3-70b-specdec              (encore plus rapide, spec decode)
 *  - mixtral-8x7b-32768                 (mixtral MoE, long contexte)
 *  - deepseek-r1-distill-llama-70b      (raisonnement, utiliser reasoning_effort)
 */
class GroqProvider implements AiProviderInterface
{
    protected Client $client;

    protected string $apiKey;

    protected string $baseUri;

    protected string $defaultModel;

    public function __construct()
    {
        $this->apiKey = config('services.groq.key') ?: env('GROQ_API_KEY', '');
        $this->baseUri = config('services.groq.base_uri') ?: env('AI_BASE_URI', 'https://api.groq.com/openai/v1');
        $this->defaultModel = config('services.groq.model') ?: env('AI_MODEL', 'llama-3.1-70b-versatile');
        $this->client = new Client([
            'base_uri' => rtrim($this->baseUri, '/').'/',
            'timeout' => 120,
            'verify' => true,
        ]);
    }

    /**
     * Construit le corps JSON commun aux appels chat() et streamChat().
     * Suit exactement les paramètres du SDK groq-sdk (max_completion_tokens, top_p, reasoning_effort).
     */
    private function buildPayload(string $systemPrompt, string $userPrompt, array $options, bool $stream): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $temperature = $options['temperature'] ?? 0.7;
        $maxCompletionTok = $options['max_completion_tokens'] ?? $options['max_tokens'] ?? 4096;
        $topP = $options['top_p'] ?? 0.95;
        $reasoningEffort = $options['reasoning_effort'] ?? null;
        $stop = $options['stop'] ?? null;

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'temperature' => $temperature,
            'max_completion_tokens' => $maxCompletionTok,
            'top_p' => $topP,
            'stream' => $stream,
        ];

        if ($reasoningEffort !== null) {
            $payload['reasoning_effort'] = $reasoningEffort;
        }
        if ($stop !== null) {
            $payload['stop'] = $stop;
        }
        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        return $payload;
    }

    public function chat(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $attempts = 0;
        $lastException = null;
        $maxAttempts = max(1, min(2, (int) ($options['attempts'] ?? 2)));
        while ($attempts < $maxAttempts) {
            try {
                $response = $this->client->post('chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer '.$this->apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $this->buildPayload($systemPrompt, $userPrompt, $options, false),
                    'timeout' => max(1, min(120, (int) ($options['timeout'] ?? 120))),
                    'connect_timeout' => 5,
                ]);
                $body = json_decode($response->getBody()->getContents(), true);

                return $body['choices'][0]['message']['content'] ?? '';
            } catch (RequestException $e) {
                $lastException = $e;
                $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
                if ($status === 429) {
                    $attempts++;
                    if ($attempts < $maxAttempts) {
                        sleep(2 + $attempts);
                    }

                    continue;
                }
                Log::error('[Groq] chat RequestException: status='.$status.' msg='.$e->getMessage());
                throw $e;
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::error('[Groq] chat Throwable: '.$e->getMessage());
                $attempts++;
                if ($attempts < $maxAttempts) {
                    sleep(1);
                }
            }
        }
        throw $lastException ?? new \RuntimeException('Groq chat failed');
    }

    public function streamChat(string $systemPrompt, string $userPrompt, callable $onChunk, array $options = []): void
    {
        $attempts = 0;
        $lastException = null;
        while ($attempts < 2) {
            try {
                $response = $this->client->post('chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer '.$this->apiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'text/event-stream',
                    ],
                    'json' => $this->buildPayload($systemPrompt, $userPrompt, $options, true),
                    'stream' => true,
                ]);

                $body = $response->getBody();
                $buffer = '';
                while (! $body->eof()) {
                    $chunk = $body->read(4096);
                    if ($chunk === false || $chunk === '') {
                        continue;
                    }
                    $buffer .= $chunk;

                    $lines = explode("\n", $buffer);
                    $last = array_pop($lines);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '' || str_starts_with($line, ':')) {
                            continue;
                        }
                        if (! str_starts_with($line, 'data:')) {
                            continue;
                        }
                        $data = trim(substr($line, 5));
                        if ($data === '[DONE]') {
                            $onChunk('', true);
                            break 2;
                        }
                        $json = json_decode($data, true);
                        if (! is_array($json)) {
                            continue;
                        }
                        $delta = $json['choices'][0]['delta']['content'] ?? null;
                        if (is_string($delta) && $delta !== '') {
                            $onChunk($delta, false);
                        }
                    }
                    $buffer = $last ?? '';
                }

                return;
            } catch (RequestException $e) {
                $lastException = $e;
                $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
                if ($status === 429) {
                    $attempts++;
                    sleep(2 + $attempts);

                    continue;
                }
                Log::error('[Groq] streamChat RequestException: status='.$status.' msg='.$e->getMessage());
                throw $e;
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::error('[Groq] streamChat Throwable: '.$e->getMessage());
                $attempts++;
                if ($attempts < 2) {
                    sleep(1);
                }
            }
        }
        throw $lastException ?? new \RuntimeException('Groq streamChat failed');
    }
}
