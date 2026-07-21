<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Telegram
{
    /**
     * Sends a MarkdownV2 message and returns the Telegram message id.
     *
     * @throws RuntimeException on connection or API errors
     */
    public function send(string $chatId, string $text): int
    {
        try {
            $response = Http::timeout(15)->post($this->endpoint('sendMessage'), $this->payload($chatId, $text));
        } catch (ConnectionException $e) {
            throw new RuntimeException("Telegram connection failed: {$e->getMessage()}");
        }

        if (! $response->json('ok')) {
            $description = $response->json('description', 'unknown error');

            throw new RuntimeException("Telegram API error ({$response->status()}): {$description}");
        }

        return $response->json('result.message_id');
    }

    public function payload(string $chatId, string $text): array
    {
        return [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'MarkdownV2',
        ];
    }

    private function endpoint(string $method): string
    {
        $token = config('telegram.token');

        if (blank($token)) {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not set');
        }

        return "https://api.telegram.org/bot{$token}/{$method}";
    }
}
