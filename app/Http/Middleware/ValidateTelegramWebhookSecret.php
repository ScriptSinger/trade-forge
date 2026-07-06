<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateTelegramWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('telegram.bots.' . config('telegram.default') . '.webhook_secret');

        if (!filled($secret)) {
            return $next($request);
        }

        $header = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (!is_string($header) || !hash_equals($secret, $header)) {
            return response('Forbidden', 403);
        }

        return $next($request);
    }
}