<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $tenantId,
        public string $phone,
        public string $message,
        public ?array $buttons = null,
    ) {}

    public function handle(WhatsAppClient $client): void
    {
        $tenant = Tenant::find($this->tenantId);

        if (!$tenant) {
            Log::error('SendWhatsAppNotification: Tenant not found', ['tenant_id' => $this->tenantId]);
            return;
        }

        $result = $this->buttons
            ? $client->sendInteractiveButtons($tenant, $this->phone, $this->message, $this->buttons)
            : $client->sendTextMessage($tenant, $this->phone, $this->message);

        if (!$result) {
            Log::warning('SendWhatsAppNotification: Failed to send', [
                'tenant_id' => $this->tenantId,
                'phone' => $this->phone,
            ]);
        }
    }
}
