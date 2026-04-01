<?php

namespace App\Services\AI;

/**
 * Conversational intent detection using the configured AI provider.
 *
 * Wraps AiService with prompt templates tailored to each step of the
 * ordering flow. All methods return null silently when AI is unavailable
 * so callers can fall back to their existing rule-based responses.
 */
class AiIntentService
{
    public function __construct(private AiService $ai) {}

    public function isAvailable(): bool
    {
        return $this->ai->isAvailable();
    }

    /**
     * Detect delivery preference from free text.
     * Returns 'delivery', 'pickup', or null.
     */
    public function interpretDeliveryType(string $message): ?string
    {
        if (!$this->ai->isAvailable()) return null;

        $prompt = "A restaurant WhatsApp customer is choosing how to receive their order. They said: \"{$message}\"\n\nReply with ONLY one word:\n- DELIVERY if they want it brought to their location\n- PICKUP if they will go to the restaurant to pick it up\n- UNKNOWN if it is not clear";

        $reply = $this->ai->complete($prompt, 10);
        if (!$reply) return null;

        return match (strtoupper(trim($reply))) {
            'DELIVERY' => 'delivery',
            'PICKUP'   => 'pickup',
            default    => null,
        };
    }

    /**
     * Detect order confirmation intent from free text.
     * Returns 'confirm', 'modify', 'cancel', or null.
     */
    public function interpretConfirmation(string $message): ?string
    {
        if (!$this->ai->isAvailable()) return null;

        $prompt = "A restaurant customer is reviewing their order and says: \"{$message}\"\n\nClassify their intent:\n- CONFIRM: they want to place / confirm the order (yes, ok, go ahead, dale, etc.)\n- MODIFY: they want to change something in the order\n- CANCEL: they want to cancel / not order\n- UNKNOWN: unclear\n\nReply with ONLY one word.";

        $reply = $this->ai->complete($prompt, 10);
        if (!$reply) return null;

        return match (strtoupper(trim($reply))) {
            'CONFIRM' => 'confirm',
            'MODIFY'  => 'modify',
            'CANCEL'  => 'cancel',
            default   => null,
        };
    }

    /**
     * Detect address-out-of-range intent from free text.
     * Returns 'retry' (try another address), 'pickup' (switch to pickup), or null.
     */
    public function interpretAddressRetry(string $message): ?string
    {
        if (!$this->ai->isAvailable()) return null;

        $prompt = "A delivery customer's address was out of range. They said: \"{$message}\"\n\nClassify:\n- RETRY: they want to try a different address\n- PICKUP: they prefer to pick up from the restaurant instead\n- UNKNOWN\n\nReply with ONLY one word.";

        $reply = $this->ai->complete($prompt, 10);
        if (!$reply) return null;

        return match (strtoupper(trim($reply))) {
            'RETRY'  => 'retry',
            'PICKUP' => 'pickup',
            default  => null,
        };
    }

    /**
     * Detect payment method from free text.
     *
     * @param array<string,string> $methodLabels  e.g. ['cash' => 'Efectivo', 'transfer' => 'Transferencia']
     * @return string|null  the matched method key or null
     */
    public function interpretPaymentMethod(string $message, array $methodLabels): ?string
    {
        if (!$this->ai->isAvailable() || empty($methodLabels)) return null;

        $list = implode(', ', array_map(
            fn ($k, $v) => "{$k} ({$v})",
            array_keys($methodLabels),
            $methodLabels
        ));

        $prompt = "Available payment methods: {$list}\n\nCustomer said: \"{$message}\"\n\nWhich payment method key matches? Reply with ONLY the exact key (e.g. cash) or NONE.";

        $reply = $this->ai->complete($prompt, 20);
        if (!$reply || strtoupper(trim($reply)) === 'NONE') return null;

        $key = strtolower(trim($reply));

        return isset($methodLabels[$key]) ? $key : null;
    }

    /**
     * Generate a short, friendly Spanish message that acknowledges what the
     * user said and guides them to complete the current step.
     *
     * Use this as the last resort when all rule-based matches fail and the
     * other interpret* methods return null.
     *
     * @param string $message        What the user said
     * @param string $awaitingField  The step the bot is waiting on (e.g. 'delivery_type', 'payment_method')
     * @param string $stepHint       Human-readable description of what is needed, included verbatim in the prompt
     */
    public function guideUser(string $message, string $awaitingField, string $stepHint): ?string
    {
        if (!$this->ai->isAvailable()) return null;

        $tenant = app('tenant');
        $restaurantName = $tenant?->name ?? 'el restaurante';

        $prompt = "You are a friendly WhatsApp ordering assistant for \"{$restaurantName}\".\n\nThe customer needs to: {$stepHint}\nThey said instead: \"{$message}\"\n\nWrite a SHORT response in Spanish (2 sentences max) that:\n1. Briefly acknowledges or clarifies if needed\n2. Politely redirects them to complete the required step\n\nDo not invent options. Do not use quotes around your response.";

        return $this->ai->complete($prompt, 100);
    }
}
