<?php

namespace App\Services\Exchange;

use App\Models\ExchangeAccount;
use App\Services\Bot\Concerns\ResolvesTradingLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BybitExchangeService
{
    use ResolvesTradingLogger;

    public const DEFAULT_KLINE_LIMIT = 1000;

    /*
    |--------------------------------------------------------------------------
    | MARKET DATA (SPOT)
    |--------------------------------------------------------------------------
    */

    public function getMarketData(
        ExchangeAccount $account,
        string $symbol,
        string $interval = '1',
        int $limit = self::DEFAULT_KLINE_LIMIT,
    ): array {
        return [
            'price' => $this->getTicker($account, $symbol),
            'candles' => $this->getKlines($account, $symbol, $interval, $limit),
        ];
    }

    public function getTicker(ExchangeAccount $account, string $symbol): float
    {
        $url = $this->baseUrl($account) . '/v5/market/tickers';

        $this->tradingLog()->exchangeDebug('Bybit request', [
            'method' => 'GET',
            'url' => $url,
            'category' => 'spot',
            'symbol' => $symbol,
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

        $this->tradingLog()->exchangeDebug('Bybit request', [
            'method' => 'GET',
            'url' => $url,
            'category' => 'spot',
        ]);

        $response = Http::get($url, [
            'category' => 'spot',
        ]);

        return $response->json('result.list') ?? [];
    }

    public function getKlines(
        ExchangeAccount $account,
        string $symbol,
        string $interval = '15',
        int $limit = self::DEFAULT_KLINE_LIMIT,
    ): array
    {
        $url = $this->baseUrl($account) . '/v5/market/kline';

        $this->tradingLog()->exchangeDebug('Bybit request', [
            'method' => 'GET',
            'url' => $url,
            'category' => 'spot',
            'symbol' => $symbol,
            'interval' => $interval,
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

        $this->tradingLog()->exchangeDebug('Bybit request', [
            'method' => 'GET',
            'url' => $url,
            'params' => $params,
        ]);

        $response = Http::withHeaders(
            $this->authHeaders($account, $params, 'GET')
        )->get($url, $params);

        return $response->json();
    }

    public function getUsdtWalletBalance(ExchangeAccount $account): ?float
    {
        $coin = $this->findUsdtCoin($account);

        if ($coin === null) {
            return null;
        }

        $balance = $coin['walletBalance'] ?? $coin['equity'] ?? null;

        return $balance !== null ? (float) $balance : null;
    }

    public function getUsdtFreeBalance(ExchangeAccount $account): ?float
    {
        $coin = $this->findUsdtCoin($account);

        if ($coin === null) {
            return null;
        }

        $free = $coin['availableToWithdraw']
            ?? $coin['availableBalance']
            ?? $coin['free']
            ?? $coin['walletBalance']
            ?? null;

        return $free !== null ? (float) $free : null;
    }

    public function normalizeQuantity(ExchangeAccount $account, string $symbol, float $quantity): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }

        $filter = $this->getLotSizeFilter($account, $symbol);
        $step = (float) ($filter['qtyStep'] ?? 0);

        if ($step <= 0) {
            return $quantity;
        }

        $normalized = floor($quantity / $step) * $step;

        return $normalized > 0 ? $normalized : 0.0;
    }

    private function getLotSizeFilter(ExchangeAccount $account, string $symbol): array
    {
        $cacheKey = "bybit_lot_size_{$account->id}_{$symbol}";

        return Cache::remember($cacheKey, 3600, function () use ($account, $symbol) {
            $url = $this->baseUrl($account) . '/v5/market/instruments-info';

            $this->tradingLog()->exchangeDebug('Bybit request', [
                'method' => 'GET',
                'url' => $url,
                'category' => 'spot',
                'symbol' => $symbol,
            ]);

            $response = Http::get($url, [
                'category' => 'spot',
                'symbol' => $symbol,
            ]);

            return $response->json('result.list.0.lotSizeFilter') ?? [];
        });
    }

    private function findUsdtCoin(ExchangeAccount $account): ?array
    {
        $response = $this->getWalletBalance($account, 'USDT');
        $coins = $response['result']['list'][0]['coin'] ?? [];

        foreach ($coins as $coin) {
            if (($coin['coin'] ?? '') === 'USDT') {
                return $coin;
            }
        }

        return null;
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
        $normalizedQty = $this->normalizeQuantity($account, $symbol, $qty);

        $payload = [
            'category' => 'spot',
            'symbol' => $symbol,
            'side' => ucfirst(strtolower($side)),
            'orderType' => 'Market',
            'qty' => $this->formatQuantity($normalizedQty),
        ];

        $this->tradingLog()->exchangeDebug('Bybit request', [
            'method' => 'POST',
            'url' => $url,
            'payload' => $payload,
        ]);

        try {
            $response = Http::withHeaders(
                $this->authHeaders($account, $payload)
            )->post($url, $payload);

            return $response->json();
        } catch (\Exception $e) {
            $this->tradingLog()->exchangeError('Bybit order request failed', [
                'symbol' => $symbol,
                'exception' => $e->getMessage(),
            ]);

            return [
                'retCode' => -1,
                'retMsg' => 'Exception: ' . $e->getMessage(),
                'result' => [],
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BASE URL
    |--------------------------------------------------------------------------
    */

    private function formatQuantity(float $qty): string
    {
        return rtrim(rtrim(sprintf('%.8F', $qty), '0'), '.');
    }

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