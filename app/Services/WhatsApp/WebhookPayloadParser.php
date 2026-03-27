<?php

namespace App\Services\WhatsApp;

class WebhookPayloadParser
{
    /**
     * Extract messages from Meta webhook payload.
     * Returns array of normalized message objects.
     */
    public static function extractMessages(array $payload): array
    {
        $messages = [];

        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                if (($change['field'] ?? '') !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $metadata = $value['metadata'] ?? [];
                $phoneNumberId = $metadata['phone_number_id'] ?? null;
                $incomingMessages = $value['messages'] ?? [];

                foreach ($incomingMessages as $msg) {
                    $messages[] = self::normalizeMessage($msg, $phoneNumberId);
                }
            }
        }

        return $messages;
    }

    /**
     * Extract status updates from webhook payload.
     */
    public static function extractStatuses(array $payload): array
    {
        $statuses = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') !== 'messages') {
                    continue;
                }

                foreach ($change['value']['statuses'] ?? [] as $status) {
                    $statuses[] = [
                        'message_id' => $status['id'] ?? null,
                        'status' => $status['status'] ?? null, // sent, delivered, read, failed
                        'timestamp' => $status['timestamp'] ?? null,
                        'recipient' => $status['recipient_id'] ?? null,
                    ];
                }
            }
        }

        return $statuses;
    }

    private static function normalizeMessage(array $msg, ?string $phoneNumberId): array
    {
        $type = $msg['type'] ?? 'text';
        $content = '';

        switch ($type) {
            case 'text':
                $content = $msg['text']['body'] ?? '';
                break;

            case 'interactive':
                $interactiveType = $msg['interactive']['type'] ?? '';
                if ($interactiveType === 'button_reply') {
                    // Use the button ID as content so handlers can match on it
                    $content = $msg['interactive']['button_reply']['id'] ?? $msg['interactive']['button_reply']['title'] ?? '';
                } elseif ($interactiveType === 'list_reply') {
                    // Use the list item ID as content so handlers can match on it
                    $content = $msg['interactive']['list_reply']['id'] ?? $msg['interactive']['list_reply']['title'] ?? '';
                }
                break;

            case 'location':
                $location = $msg['location'] ?? [];
                $content = json_encode([
                    'latitude' => $location['latitude'] ?? null,
                    'longitude' => $location['longitude'] ?? null,
                    'name' => $location['name'] ?? null,
                    'address' => $location['address'] ?? null,
                ]);
                break;

            case 'image':
            case 'document':
            case 'audio':
            case 'video':
                $content = $msg[$type]['caption'] ?? "[{$type}]";
                break;

            default:
                $content = "[unsupported: {$type}]";
        }

        return [
            'message_id' => $msg['id'] ?? null,
            'from' => $msg['from'] ?? '',
            'timestamp' => $msg['timestamp'] ?? '',
            'type' => $type,
            'content' => $content,
            'phone_number_id' => $phoneNumberId,
            'raw' => $msg,
        ];
    }
}
