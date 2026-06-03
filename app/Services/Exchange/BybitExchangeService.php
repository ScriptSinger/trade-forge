<?php

namespace App\Services\Exchange;

use App\Models\ExchangeAccount;
use Illuminate\Support\Facades\Http;

class BybitExchangeService
{
    /*
    |--------------------------------------------------------------------------
    | MARKET DATA (SPOT)
    |--------------------------------------------------------------------------
    */

    public function getMarketData(string $symbol, string $interval = '1'): array
    {
        return [
            'price' => $this->getTicker($symbol),
            'candles' => $this->getKlines($symbol, $interval),
        ];
    }

    public function getTicker(string $symbol): float
    {
        $response = Http::get($this->baseUrl(null) . '/v5/market/tickers', [
            'category' => 'spot',
            'symbol' => $symbol,
        ]);

        return (float) ($response->json('result.list.0.lastPrice') ?? 0);
    }

    public function getKlines(string $symbol, string $interval = '1', int $limit = 100): array
    {
        $response = Http::get($this->baseUrl(null) . '/v5/market/kline', [
            'category' => 'spot',
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
        ]);

        return $response->json('result.list') ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER EXECUTION (SPOT)
    |--------------------------------------------------------------------------
    */

    public function placeMarketOrder(
        ExchangeAccount $account,
        string $symbol,
        string $side,
        float $qty
    ): array {
        $payload = [
            'category' => 'spot',
            'symbol' => $symbol,
            'side' => ucfirst($side), // Buy / Sell
            'orderType' => 'Market',
            'qty' => (string) $qty,
        ];

        $response = Http::withHeaders(
            $this->authHeaders($account, $payload)
        )->post(
            $this->baseUrl($account) . '/v5/order/create',
            $payload
        );

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | BASE URL
    |--------------------------------------------------------------------------
    */

    private function baseUrl(?ExchangeAccount $account): string
    {
        if ($account && $account->testnet) {
            return 'https://api-testnet.bybit.com';
        }

        return 'https://api.bybit.com';
    }

    /*
    |--------------------------------------------------------------------------
    | AUTH (HMAC V5)
    |--------------------------------------------------------------------------
    */

    private function authHeaders(ExchangeAccount $account, array $payload): array
    {
        $timestamp = (string) round(microtime(true) * 1000);
        $body = json_encode($payload);

        $apiKey = decrypt($account->api_key);
        $apiSecret = decrypt($account->api_secret);

        $sign = $this->sign(
            $timestamp,
            $apiKey,
            $apiSecret,
            $body
        );

        return [
            'X-BAPI-API-KEY' => $apiKey,
            'X-BAPI-TIMESTAMP' => $timestamp,
            'X-BAPI-SIGN' => $sign,
            'X-BAPI-RECV-WINDOW' => '5000',
            'Content-Type' => 'application/json',
        ];
    }

    private function sign(
        string $timestamp,
        string $apiKey,
        string $apiSecret,
        string $body
    ): string {
        $payload = $timestamp . $apiKey . '5000' . $body;

        return hash_hmac('sha256', $payload, $apiSecret);
    }
}
