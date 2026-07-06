<?php

use App\Http\Controllers\TelegramWebhookController;
use App\Services\Bot\BotEngine;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware('telegram.webhook')
    ->name('telegram.webhook');

Route::get('/bot/run/{bot}', function (\App\Models\Bot $bot, BotEngine $engine) {
    $engine->run($bot);

    return back();
})->name('bot.run.manual');