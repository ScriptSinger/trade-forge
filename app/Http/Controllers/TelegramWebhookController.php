<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramControlPanelService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramControlPanelService $panel): Response
    {
        if (config('trading.telegram.control_mode', 'webhook') !== 'webhook') {
            return response('OK', 200);
        }

        if (!$panel->isEnabled()) {
            return response('OK', 200);
        }

        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return response('OK', 200);
        }

        $panel->handleUpdate($payload);

        return response('OK', 200);
    }
}