<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around OpenAI-compatible APIs (Groq, OpenAI).
 *
 * Resolution order for credentials:
 *   1. Tenant settings  (ai.provider / ai.model + ai_api_key column)
 *   2. Global .env      (AI_PROVIDER / GROQ_API_KEY / OPENAI_API_KEY)
 *
 * Returns null silently when no key is configured so callers can fall back
 * to the existing rule-based logic without any code changes.
 */
class AiService
{
    private ?string $apiKey    = null;
    private string  $provider  = 'groq';
    private string  $model     = 'llama-3.1-8b-instant';
    private string  $baseUrl   = 'https://api.groq.com/openai/v1';
    private bool    $available = false;

    public function __construct()
    {
        $tenant = app('tenant');

        if ($tenant && $tenant->isAiEnabled() && $tenant->ai_api_key) {
            // Tenant-level config takes precedence
            $this->apiKey   = $tenant->ai_api_key;
            $this->provider = $tenant->getAiProvider();
            $this->model    = $tenant->getAiModel() ?: $this->defaultModel($this->provider);
            $this->baseUrl  = $this->baseUrl($this->provider);
            $this->available = true;
        } else {
            // Fall back to global .env
            $provider = config('ai.default_provider', 'groq');
            $key      = config("ai.providers.{$provider}.api_key");

            if ($key) {
                $this->apiKey   = $key;
                $this->provider = $provider;
                $this->model    = config("ai.providers.{$provider}.model", $this->defaultModel($provider));
                $this->baseUrl  = config("ai.providers.{$provider}.base_url", $this->baseUrl($provider));
                $this->available = true;
            }
        }
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * Find the best matching menu item name given free-text input.
     * Returns the exact item name from $itemNames or null.
     */
    public function matchMenuItem(string $userInput, array $itemNames): ?string
    {
        if (! $this->available || empty($itemNames)) {
            return null;
        }

        $list   = implode("\n", array_map(fn ($n, $i) => ($i + 1) . ". {$n}", $itemNames, array_keys($itemNames)));
        $prompt = "You are a restaurant ordering assistant. Given this menu:\n{$list}\n\nWhich item does the customer want? Customer said: \"{$userInput}\"\n\nReply with ONLY the exact item name from the menu list, or NONE if nothing matches.";

        $reply = $this->complete($prompt);

        if (! $reply || $reply === 'NONE') {
            return null;
        }

        // Validate the reply is actually in our list (case-insensitive)
        $normalized = mb_strtolower(trim($reply));
        foreach ($itemNames as $name) {
            if (mb_strtolower($name) === $normalized) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Find the best matching menu category name given free-text input.
     * Returns the exact category name or null.
     */
    public function matchMenuCategory(string $userInput, array $categoryNames): ?string
    {
        if (! $this->available || empty($categoryNames)) {
            return null;
        }

        $list   = implode(', ', $categoryNames);
        $prompt = "Restaurant menu categories: {$list}\n\nWhat category is the customer looking for? They said: \"{$userInput}\"\n\nReply with ONLY the exact category name, or NONE.";

        $reply = $this->complete($prompt);

        if (! $reply || $reply === 'NONE') {
            return null;
        }

        $normalized = mb_strtolower(trim($reply));
        foreach ($categoryNames as $name) {
            if (mb_strtolower($name) === $normalized) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Send a low-level chat completion and return the text reply.
     * Results are cached 24 h by prompt hash to minimise API calls.
     */
    public function complete(string $prompt, int $maxTokens = 80): ?string
    {
        if (! $this->available) {
            return null;
        }

        $cacheKey = 'ai_' . md5($this->model . $prompt);

        return Cache::remember($cacheKey, 86400, function () use ($prompt, $maxTokens) {
            try {
                $response = Http::withToken($this->apiKey)
                    ->timeout(8)
                    ->post("{$this->baseUrl}/chat/completions", [
                        'model'       => $this->model,
                        'messages'    => [['role' => 'user', 'content' => $prompt]],
                        'max_tokens'  => $maxTokens,
                        'temperature' => 0.1,
                    ]);

                if (! $response->successful()) {
                    Log::warning('AiService API error', ['status' => $response->status(), 'body' => $response->body()]);

                    return null;
                }

                return trim($response->json('choices.0.message.content', '')) ?: null;
            } catch (\Exception $e) {
                Log::warning('AiService exception', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    // -------------------------------------------------------------------------

    private function defaultModel(string $provider): string
    {
        return match ($provider) {
            'openai' => 'gpt-4o-mini',
            default  => 'llama-3.1-8b-instant',
        };
    }

    private function baseUrl(string $provider): string
    {
        return match ($provider) {
            'openai' => 'https://api.openai.com/v1',
            default  => 'https://api.groq.com/openai/v1',
        };
    }
}
