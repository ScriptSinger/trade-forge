<?php

use App\Jobs\TestQueueJob;
use App\Services\Bot\BotEngine;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    TestQueueJob::dispatch();
    return view('welcome');
});

Route::get('/bybit-test', function () {

    $response = Http::withHeaders([
        'X-BAPI-API-KEY' => env('BYBIT_API_KEY'),
    ])->get('https://api.bybit.com/v5/market/tickers', [
        'category' => 'spot',
        'symbol' => 'BTCUSDT'
    ]);

    return $response->json();
});

Route::get('/debug/bybit', function (BotEngine $engine) {
    $bot = \App\Models\Bot::first();

    $engine->run($bot);

    return 'ok';
});

Route::get('/bot/run/{bot}', function (\App\Models\Bot $bot, BotEngine $engine) {
    $engine->run($bot);
    
    \MoonShine\Notifications\MoonShineNotification::make(
        "Бот {$bot->name} запущен вручную"
    )->show();

    return back();
})->name('bot.run.manual');
