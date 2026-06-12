<?php

namespace App\Services\Exchange;

use App\Models\ExchangeAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BybitExchangeService
{
    /*
    |--------------------------------------------------------------------------
    | MARKET DATA (SPOT)
    |--------------------------------------------------------------------------
    */

    public function getMarketData(ExchangeAccount $account, string $symbol, string $interval = '1'): array
    {
        return [
            'price' => $this->getTicker($account, $symbol),
            'candles' => $this->getKlines($account, $symbol, $interval),
        ];
    }

    public function getTicker(ExchangeAccount $account, string $symbol): float
    {
        $url = $this->baseUrl($account) . '/v5/market/tickers';
        
        Log::info("Bybit Request: GET {$url}", [
            'category' => 'spot',
            'symbol' => $symbol
        ]);

        $response = Http::get($url, [
            'category' => 'spot',
            'symbol' => $symbol,
        ]);

        return (float) ($response->json('result.list.0.lastPrice') ?? 0);
    }

    public function getAllTickers(ExchangeAccount $account): array
    {
        $url = $this->baseUrl($account) . '/v5/market/tickers';

        Log::info("Bybit Request: GET {$url}", [
            'category' => 'spot'
        ]);

        $response = Http::get($url, [
            'category' => 'spot',
        ]);

        return $response->json('result.list') ?? [];
    }

    public function getKlines(ExchangeAccount $account, string $symbol, string $interval = '15', int $limit = 250): array
    {
        $url = $this->baseUrl($account) . '/v5/market/kline';

        Log::info("Bybit Request: GET {$url}", [
            'category' => 'spot',
            'symbol' => $symbol,
            'interval' => $interval
        ]);

        $response = Http::get($url, [
            'category' => 'spot',
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
        ]);

        return $response->json('result.list') ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | ACCOUNT DATA
    |--------------------------------------------------------------------------
    */

    public function getWalletBalance(ExchangeAccount $account, string $coin = 'USDT'): array
    {
        $url = $this->baseUrl($account) . '/v5/account/wallet-balance';
        
        $params = [
            'accountType' => 'UNIFIED', 
            'coin' => $coin,
        ];

        Log::info("Bybit Request: GET {$url}", $params);

        $response = Http::withHeaders(
            $this->authHeaders($account, $params, 'GET')
        )->get($url, $params);

        return $response->json();
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
        $url = $this->baseUrl($account) . '/v5/order/create';

        $payload = [
            'category' => 'spot',
            'symbol' => $symbol,
            'side' => ucfirst(strtolower($side)), // Buy / Sell
            'orderType' => 'Market',
            'qty' => (string) $qty,
        ];

        Log::info("Bybit Request: POST {$url}", $payload);

        try {
            $response = Http::withHeaders(
                $this->authHeaders($account, $payload)
            )->post($url, $payload);

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Bybit Order Error: " . $e->getMessage());
            return [
                'retCode' => -1,
                'retMsg' => 'Exception: ' . $e->getMessage(),
                'result' => []
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BASE URL
    |--------------------------------------------------------------------------
    */

    private function baseUrl(ExchangeAccount $account): string
    {
        return rtrim($account->api_url, '/');
    }

    /*
    |--------------------------------------------------------------------------
    | AUTH (HMAC V5)
    |--------------------------------------------------------------------------
    */

    private function authHeaders(ExchangeAccount $account, array $payload, string $method = 'POST'): array
    {
        $timestamp = (string) round(microtime(true) * 1000);
        
        if ($method === 'GET') {
            $body = http_build_query($payload);
        } else {
            $body = json_encode($payload);
        }

        $apiKey = $account->api_key;
        $apiSecret = $account->api_secret;

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
