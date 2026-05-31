<?php

use App\Jobs\TestQueueJob;
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
