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
        $chatId = config('trading.telegram.chat_id');

        if (!filled($chatId)) {
            return false;
        }

        return $this->sendTo($chatId, $message, null, $parseMode);
    }

    public function isAuthorizedChat(int|string $chatId): bool
    {
        $ownerChatId = config('trading.telegram.chat_id');

        return filled($ownerChatId) && (string) $chatId === (string) $ownerChatId;
    }

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function sendTo(
        int|string $chatId,
        string $message,
        ?array $replyMarkup = null,
        string $parseMode = 'HTML',
    ): bool {
        if (!$this->isConfigured()) {
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => $parseMode,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_THROW_ON_ERROR);
        }

        $response = $this->request('sendMessage', $payload);

        return $response !== null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUpdates(int $offset = 0, int $timeout = 25): array
    {
        $params = [
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message'], JSON_THROW_ON_ERROR),
        ];

        if ($offset > 0) {
            $params['offset'] = $offset;
        }

        $response = $this->request('getUpdates', $params, $timeout + 5);

        return is_array($response) ? $response : [];
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array<string, mixed>
     */
    public function replyKeyboard(array $rows): array
    {
        return [
            'keyboard' => array_map(
                static fn (array $row): array => array_map(
                    static fn (string $label): array => ['text' => $label],
                    $row,
                ),
                $rows,
            ),
            'resize_keyboard' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public function request(string $method, array $params = [], int $timeout = 5): ?array
    {
        if (!filled(config('trading.telegram.bot_token'))) {
            return null;
        }

        try {
            $response = Http::asForm()
                ->timeout($timeout)
                ->post($this->apiUrl($method), $params);

            if (!$response->successful()) {
                Log::warning('Telegram API request failed', [
                    'method' => $method,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $body = $response->json();

            if (!is_array($body) || !($body['ok'] ?? false)) {
                Log::warning('Telegram API returned error', [
                    'method' => $method,
                    'body' => $body,
                ]);

                return null;
            }

            $result = $body['result'] ?? null;

            return is_array($result) ? $result : null;
        } catch (\Throwable $e) {
            Log::warning('Telegram API exception', [
                'method' => $method,
                'message' => $e->getMessage(),
            ]);

            return null;
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