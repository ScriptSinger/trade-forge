<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotifier
{
    public function isConfigured(): bool
    {
        $token = config('trading.telegram.bot_token');
        $chatId = config('trading.telegram.chat_id');

        return filled($token) && filled($chatId);
    }

    public function send(string $message, string $parseMode = 'HTML'): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post($this->apiUrl('sendMessage'), [
                    'chat_id' => config('trading.telegram.chat_id'),
                    'text' => $message,
                    'parse_mode' => $parseMode,
                ]);

            if (!$response->successful()) {
                Log::warning('Telegram send failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Telegram send exception', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function apiUrl(string $method): string
    {
        return sprintf(
            'https://api.telegram.org/bot%s/%s',
            config('trading.telegram.bot_token'),
            $method,
        );
    }
}