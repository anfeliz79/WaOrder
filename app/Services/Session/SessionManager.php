<?php

namespace App\Services\Session;

use App\Models\ChatSession;
use App\Models\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SessionManager
{
    private const CACHE_TTL = 1800; // 30 minutes
    private const SESSION_EXPIRY_MINUTES = 30;

    public function getOrCreate(int $tenantId, string $phone): ChatSession
    {
        $cacheKey = $this->cacheKey($tenantId, $phone);

        // Try cache first
        $sessionId = Cache::get($cacheKey);
        if ($sessionId) {
            $session = ChatSession::find($sessionId);
            if ($session && $session->status === 'active' && !$session->isExpired()) {
                return $session;
            }
        }

        // Try DB
        $session = ChatSession::where('tenant_id', $tenantId)
            ->where('customer_phone', $phone)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if ($session) {
            $this->cacheSession($session);
            return $session;
        }

        // Create new session
        return $this->create($tenantId, $phone);
    }

    public function create(int $tenantId, string $phone): ChatSession
    {
        // Expire any existing active sessions for this phone+tenant
        ChatSession::where('tenant_id', $tenantId)
            ->where('customer_phone', $phone)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        // Find or create customer
        $customer = Customer::firstOrCreate(
            ['tenant_id' => $tenantId, 'phone' => $phone],
            ['name' => null]
        );

        $session = ChatSession::create([
            'tenant_id' => $tenantId,
            'customer_phone' => $phone,
            'customer_id' => $customer->id,
            'conversation_state' => 'greeting',
            'cart_data' => ['items' => [], 'subtotal' => 0, 'delivery_fee' => 0, 'total' => 0],
            'collected_info' => ['name' => $customer->name, 'address' => $customer->default_address, 'delivery_type' => null, 'payment_method' => null, 'notes' => null],
            'context_data' => ['last_viewed_category' => null, 'current_category_items' => null, 'retry_count' => 0],
            'status' => 'active',
            'message_count' => 0,
            'expires_at' => now()->addMinutes(self::SESSION_EXPIRY_MINUTES),
        ]);

        $this->cacheSession($session);

        Log::info('New chat session created', [
            'session_id' => $session->id,
            'tenant_id' => $tenantId,
            'phone' => $phone,
        ]);

        return $session;
    }

    public function update(ChatSession $session, array $data): ChatSession
    {
        $data['expires_at'] = now()->addMinutes(self::SESSION_EXPIRY_MINUTES);
        $data['message_count'] = $session->message_count + 1;

        $session->update($data);
        $this->cacheSession($session);

        return $session->fresh();
    }

    public function updateState(ChatSession $session, string $state, array $extraData = []): ChatSession
    {
        return $this->update($session, array_merge(['conversation_state' => $state], $extraData));
    }

    public function updateCart(ChatSession $session, array $cartData): ChatSession
    {
        return $this->update($session, ['cart_data' => $cartData]);
    }

    public function updateCollectedInfo(ChatSession $session, string $field, mixed $value): ChatSession
    {
        $info = $session->collected_info ?? [];
        $info[$field] = $value;

        return $this->update($session, ['collected_info' => $info]);
    }

    public function updateContext(ChatSession $session, string $field, mixed $value): ChatSession
    {
        $context = $session->context_data ?? [];
        $context[$field] = $value;

        return $this->update($session, ['context_data' => $context]);
    }

    public function setActiveOrder(ChatSession $session, int $orderId): ChatSession
    {
        return $this->update($session, [
            'active_order_id' => $orderId,
            'cart_data' => ['items' => [], 'subtotal' => 0, 'delivery_fee' => 0, 'total' => 0],
        ]);
    }

    public function destroy(ChatSession $session): void
    {
        $session->update(['status' => 'completed']);
        Cache::forget($this->cacheKey($session->tenant_id, $session->customer_phone));

        Log::info('Chat session destroyed', [
            'session_id' => $session->id,
            'tenant_id' => $session->tenant_id,
            'phone' => $session->customer_phone,
        ]);
    }

    public function cleanExpired(): int
    {
        $count = ChatSession::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        // Also force-expire sessions older than 4 hours
        $count += ChatSession::where('status', 'active')
            ->where('created_at', '<', now()->subHours(4))
            ->update(['status' => 'expired']);

        return $count;
    }

    private function cacheKey(int $tenantId, string $phone): string
    {
        return "session:{$tenantId}:{$phone}";
    }

    private function cacheSession(ChatSession $session): void
    {
        Cache::put(
            $this->cacheKey($session->tenant_id, $session->customer_phone),
            $session->id,
            self::CACHE_TTL
        );
    }
}
