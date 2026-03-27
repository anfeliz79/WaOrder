<?php

namespace App\Services\Menu;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MenuTokenService
{
    private const TOKEN_TTL = 900; // 15 minutes
    private const FULL_MENU_TOKEN_TTL = 1800; // 30 minutes

    /**
     * Generate a token for a specific menu item (used in item personalization).
     */
    public function generateItemToken(int $tenantId, int $sessionId, $itemId, string $phone): string
    {
        $token = Str::random(32);

        Cache::put("menu_token:{$token}", [
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'item_id' => $itemId,
            'phone' => $phone,
            'type' => 'item',
            'created_at' => now()->toIso8601String(),
        ], self::TOKEN_TTL);

        return $token;
    }

    /**
     * Generate a token for full menu browsing.
     */
    public function generateMenuToken(int $tenantId, int $sessionId, string $phone): string
    {
        $token = Str::random(32);

        Cache::put("menu_token:{$token}", [
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'phone' => $phone,
            'type' => 'menu',
            'created_at' => now()->toIso8601String(),
        ], self::FULL_MENU_TOKEN_TTL);

        return $token;
    }

    /**
     * Validate and retrieve token data. Returns null if invalid/expired.
     */
    public function validate(string $token): ?array
    {
        return Cache::get("menu_token:{$token}");
    }

    /**
     * Extend the token's TTL (e.g., when the user is actively browsing).
     */
    public function extend(string $token): void
    {
        $data = Cache::get("menu_token:{$token}");
        if ($data) {
            $ttl = ($data['type'] ?? 'item') === 'menu' ? self::FULL_MENU_TOKEN_TTL : self::TOKEN_TTL;
            Cache::put("menu_token:{$token}", $data, $ttl);
        }
    }

    /**
     * Invalidate a token after use.
     */
    public function invalidate(string $token): void
    {
        Cache::forget("menu_token:{$token}");
    }

    /**
     * Build the public URL for item personalization.
     */
    public function buildItemUrl(string $token): string
    {
        return rtrim(config('app.url'), '/') . "/m/{$token}";
    }

    /**
     * Build the public URL for full menu browsing.
     */
    public function buildMenuUrl(string $token): string
    {
        return rtrim(config('app.url'), '/') . "/menu/{$token}";
    }
}
