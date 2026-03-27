<?php

namespace App\Services\WhatsApp;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppClient
{
    private string $baseUrl;
    private string $apiVersion;

    public function __construct()
    {
        $this->baseUrl = config('whatsapp.api_base_url');
        $this->apiVersion = config('whatsapp.api_version');
    }

    public function sendTextMessage(Tenant $tenant, string $to, string $text): ?array
    {
        return $this->sendMessage($tenant, $to, [
            'type' => 'text',
            'text' => ['body' => $text],
        ]);
    }

    public function sendInteractiveButtons(Tenant $tenant, string $to, string $body, array $buttons): ?array
    {
        $buttonObjects = [];
        foreach ($buttons as $i => $button) {
            $buttonObjects[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $button['id'] ?? "btn_$i",
                    'title' => substr($button['title'], 0, 20),
                ],
            ];
        }

        return $this->sendMessage($tenant, $to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => ['buttons' => $buttonObjects],
            ],
        ]);
    }

    public function sendImageMessage(Tenant $tenant, string $to, string $imageUrl, ?string $caption = null): ?array
    {
        $image = ['link' => $imageUrl];
        if ($caption) {
            $image['caption'] = $caption;
        }

        return $this->sendMessage($tenant, $to, [
            'type' => 'image',
            'image' => $image,
        ]);
    }

    public function sendCtaUrlButton(Tenant $tenant, string $to, string $body, string $buttonText, string $url): ?array
    {
        return $this->sendMessage($tenant, $to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'cta_url',
                'body' => ['text' => $body],
                'action' => [
                    'name' => 'cta_url',
                    'parameters' => [
                        'display_text' => substr($buttonText, 0, 20),
                        'url' => $url,
                    ],
                ],
            ],
        ]);
    }

    public function sendInteractiveList(Tenant $tenant, string $to, string $body, string $buttonText, array $sections): ?array
    {
        return $this->sendMessage($tenant, $to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $body],
                'action' => [
                    'button' => substr($buttonText, 0, 20),
                    'sections' => $sections,
                ],
            ],
        ]);
    }

    private function sendMessage(Tenant $tenant, string $to, array $messageData): ?array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$tenant->whatsapp_phone_number_id}/messages";

        $payload = array_merge([
            'messaging_product' => 'whatsapp',
            'to' => $to,
        ], $messageData);

        try {
            $response = Http::withToken($tenant->whatsapp_access_token)
                ->timeout(10)
                ->post($url, $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('WhatsApp API error', [
                'status' => $response->status(),
                'body' => $response->json(),
                'to' => $to,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('WhatsApp API exception', [
                'error' => $e->getMessage(),
                'to' => $to,
            ]);

            return null;
        }
    }
}
