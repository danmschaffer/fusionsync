<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A small wrapper service for calling OpenAI to analyze code diffs and propose fixes.
 *
 * This implementation uses Laravel's HTTP client to call the Chat Completions endpoint.
 * It is intentionally small and defensive: it will try to parse JSON returned by the model
 * and otherwise return raw content.
 */
class OpenAIService
{
    protected string $apiKey;
    protected string $model;
    protected int $timeout;
    protected string $baseUri;

    public function __construct()
    {
        $this->apiKey = config('openai.api_key') ?? env('OPENAI_API_KEY');
        $this->model = config('openai.model', 'gpt-4o-mini');
        $this->timeout = (int) config('openai.timeout', 60);
        $this->baseUri = rtrim(config('openai.base_uri', 'https://api.openai.com/v1'), '/');
    }

    protected function request(array $payload): array
    {
        $url = $this->baseUri . '/chat/completions';

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout)
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error('OpenAI request failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('OpenAI request failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Analyze a git unified diff for inconsistencies and return structured findings and suggestions.
     *
     * @param string $diff A git unified diff or code snippet
     * @return array Parsed JSON from the LLM or ['raw' => string] if parsing failed
     */
    public function analyzeDiff(string $diff): array
    {
        $system = "You are an expert senior software engineer and code reviewer.\n" .
            "When given a git unified diff, return a JSON object ONLY (no extra commentary).\n" .
            "The JSON must contain at least two keys: \"issues\" (array) and \"suggestions\" (array).\n" .
            "Each issue should be an object with {path, line, severity, message}.\n" .
            "Each suggestion should be an object with {title, description, patch} where patch is a unified diff or code snippet that can be applied.\n" .
            "If you cannot determine a value, use null. Keep the output JSON-serializable.";

        $user = "Analyze the following diff and provide the requested JSON.\n\nDIFF:\n" . $diff;

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => 0,
        ];

        $json = $this->request($payload);

        $content = $json['choices'][0]['message']['content'] ?? ($json['choices'][0]['text'] ?? '');

        // try to decode JSON that the model returned
        $parsed = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            return $parsed;
        }

        // fallback: return raw content to the caller
        return ['raw' => $content];
    }

    /**
     * Convenience wrapper: detect inconsistencies (alias to analyzeDiff)
     */
    public function detectInconsistencies(string $diff): array
    {
        return $this->analyzeDiff($diff);
    }

    /**
     * Ask the model to propose automated fixes (returns suggestions.patch where available).
     */
    public function proposeAutomatedFixes(string $diff): array
    {
        $result = $this->analyzeDiff($diff);
        if (isset($result['suggestions']) && is_array($result['suggestions'])) {
            return $result['suggestions'];
        }

        // If model returned raw text, return that as a single suggestion
        if (isset($result['raw'])) {
            return [['title' => 'LLM raw output', 'description' => 'Raw model output; parsing failed', 'patch' => $result['raw']]];
        }

        return [];
    }
}
