<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramControlWebhookCommand extends Command
{
    protected $signature = 'telegram:control-webhook
                            {--setup : Register webhook URL at Telegram (use after ngrok is running)}
                            {--from-ngrok : Resolve HTTPS URL from ngrok API (docker: NGROK_API_URL=http://ngrok:4040)}
                            {--remove : Remove webhook from Telegram}
                            {--info : Show current webhook status}';

    protected $description = 'Setup Trade Forge Telegram control panel webhook (ngrok / production HTTPS)';

    public function handle(): int
    {
        $botName = (string) config('telegram.default', 'mybot');
        $botConfig = config("telegram.bots.{$botName}");

        if (!is_array($botConfig)) {
            $this->error("Bot config [telegram.bots.{$botName}] not found.");

            return self::FAILURE;
        }

        if ($this->option('setup')) {
            return $this->setupWebhook($botConfig);
        }

        if ($this->option('remove')) {
            return $this->removeWebhook();
        }

        if ($this->option('info')) {
            return $this->showInfo($botName);
        }

        $this->line('Usage (docker ngrok):');
        $this->line('  1. Set NGROK_AUTHTOKEN in .env');
        $this->line('  2. docker compose up -d ngrok');
        $this->line('  3. docker compose exec php php artisan telegram:control-webhook --setup --from-ngrok');
        $this->line('  4. docker compose exec php php artisan telegram:control-webhook --info');
        $this->line('');
        $this->line('Or set TELEGRAM_WEBHOOK_URL manually and run --setup');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $botConfig
     */
    private function setupWebhook(array $botConfig): int
    {
        $webhookUrl = $this->option('from-ngrok')
            ? $this->resolveNgrokWebhookUrl()
            : ($botConfig['webhook_url'] ?? null);

        if ($webhookUrl === null) {
            return self::FAILURE;
        }

        if (!is_string($webhookUrl) || !Str::startsWith($webhookUrl, 'https://')) {
            $this->error('Webhook URL must be HTTPS, e.g. https://xxxx.ngrok-free.app/telegram/webhook');

            return self::FAILURE;
        }

        $this->line("Webhook URL: {$webhookUrl}");

        $params = ['url' => $webhookUrl];

        $allowedUpdates = $botConfig['allowed_updates'] ?? null;

        if (is_array($allowedUpdates) && $allowedUpdates !== []) {
            $params['allowed_updates'] = $allowedUpdates;
        }

        $secret = $botConfig['webhook_secret'] ?? null;

        if (filled($secret)) {
            $params['secret_token'] = $secret;
        }

        $this->info('Setting webhook...');

        if (!Telegram::setWebhook($params)) {
            $this->error('Webhook could not be registered. Check TELEGRAM_BOT_TOKEN and URL.');

            return self::FAILURE;
        }

        $this->info('Webhook registered.');
        $this->line('Ensure TELEGRAM_CONTROL_MODE=webhook in .env.');

        return self::SUCCESS;
    }

    private function removeWebhook(): int
    {
        if (!$this->confirm('Remove Telegram webhook?')) {
            return self::SUCCESS;
        }

        if (Telegram::removeWebhook()) {
            $this->info('Webhook removed.');

            return self::SUCCESS;
        }

        $this->error('Webhook removal failed.');

        return self::FAILURE;
    }

    private function resolveNgrokWebhookUrl(): ?string
    {
        $apiUrl = rtrim((string) config('services.ngrok.api_url'), '/');

        try {
            $response = Http::timeout(5)->get("{$apiUrl}/api/tunnels");
        } catch (\Throwable $e) {
            $this->error("Cannot reach ngrok API at {$apiUrl}: {$e->getMessage()}");

            return null;
        }

        if (!$response->successful()) {
            $this->error("ngrok API returned HTTP {$response->status()}.");

            return null;
        }

        $tunnels = $response->json('tunnels');

        if (!is_array($tunnels)) {
            $this->error('ngrok API response has no tunnels. Is the ngrok container running?');

            return null;
        }

        foreach ($tunnels as $tunnel) {
            if (!is_array($tunnel)) {
                continue;
            }

            $publicUrl = $tunnel['public_url'] ?? null;

            if (is_string($publicUrl) && Str::startsWith($publicUrl, 'https://')) {
                return rtrim($publicUrl, '/') . '/telegram/webhook';
            }
        }

        $this->error('No HTTPS tunnel found in ngrok. Run: docker compose up -d ngrok');

        return null;
    }

    private function showInfo(string $botName): int
    {
        $info = Telegram::bot($botName)->getWebhookInfo();

        $this->table(
            ['Key', 'Value'],
            collect($info->toArray())->map(fn (mixed $value, string $key): array => [
                Str::title(str_replace('_', ' ', $key)),
                is_bool($value) ? ($value ? 'Yes' : 'No') : (is_array($value) ? implode(', ', $value) : (string) $value),
            ])->values()->all(),
        );

        return self::SUCCESS;
    }
}