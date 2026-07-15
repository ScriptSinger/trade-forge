<?php

namespace App\Services\Exchange\Bybit;

use App\Models\ExchangeAccount;
use App\Services\Bot\Concerns\ResolvesTradingLogger;
use App\Services\Exchange\Balance\AccountBalanceQuery;
use App\Services\Exchange\Balance\AccountBalanceSnapshot;
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
        $url = $this->baseUrl($account).'/v5/market/tickers';

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
        $url = $this->baseUrl($account).'/v5/market/tickers';

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
    ): array {
        $url = $this->baseUrl($account).'/v5/market/kline';

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

    private function fetchWalletBalanceResponse(ExchangeAccount $account, string $coin = 'USDT'): array
    {
        $url = $this->baseUrl($account).'/v5/account/wallet-balance';

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

    public function queryAccountBalance(ExchangeAccount $account, string $coin = 'USDT'): AccountBalanceQuery
    {
        $response = $this->fetchWalletBalanceResponse($account, $coin);
        $retCode = (int) ($response['retCode'] ?? -1);
        $retMsg = (string) ($response['retMsg'] ?? 'Unknown error');

        if ($retCode !== 0) {
            return new AccountBalanceQuery(snapshot: null, retCode: $retCode, retMsg: $retMsg);
        }

        $coinData = $this->extractCoinFromWalletResponse($response, $coin);

        if ($coinData === null) {
            return new AccountBalanceQuery(
                snapshot: null,
                retCode: $retCode,
                retMsg: "Coin {$coin} not found in wallet response",
            );
        }

        return new AccountBalanceQuery(
            snapshot: AccountBalanceSnapshot::fromCoinData($coin, $coinData),
            retCode: $retCode,
            retMsg: $retMsg,
        );
    }

    public function getAccountBalance(ExchangeAccount $account, string $coin = 'USDT'): ?AccountBalanceSnapshot
    {
        return $this->queryAccountBalance($account, $coin)->snapshot;
    }

    public function getUsdtBalance(ExchangeAccount $account): ?AccountBalanceSnapshot
    {
        return $this->getAccountBalance($account, 'USDT');
    }

    public function normalizeQuantity(ExchangeAccount $account, string $symbol, float $quantity): float
    {
        return $this->normalizeQuantityWithFilter(
            $this->getLotSizeFilter($account, $symbol),
            $quantity,
        );
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function normalizeQuantityWithFilter(array $filter, float $quantity): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }

        $step = $this->resolveQtyStepFromFilter($filter);

        if ($step <= 0) {
            return $quantity;
        }

        $normalized = floor($quantity / $step) * $step;

        return $normalized > 0 ? $normalized : 0.0;
    }

    /**
     * Spot instruments-info uses basePrecision; legacy fields may use qtyStep.
     *
     * @param  array<string, mixed>  $filter
     */
    private function resolveQtyStepFromFilter(array $filter): float
    {
        $raw = $filter['qtyStep'] ?? $filter['basePrecision'] ?? null;

        if ($raw === null || $raw === '') {
            return 0.0;
        }

        return (float) $raw;
    }

    private function getLotSizeFilter(ExchangeAccount $account, string $symbol): array
    {
        $cacheKey = "bybit_lot_size_{$account->id}_{$symbol}";

        return Cache::remember($cacheKey, 3600, function () use ($account, $symbol) {
            $url = $this->baseUrl($account).'/v5/market/instruments-info';

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

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    private function extractCoinFromWalletResponse(array $response, string $coin): ?array
    {
        $coins = $response['result']['list'][0]['coin'] ?? [];

        foreach ($coins as $coinData) {
            if (($coinData['coin'] ?? '') === $coin) {
                return $coinData;
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
        $url = $this->baseUrl($account).'/v5/order/create';
        $filter = $this->getLotSizeFilter($account, $symbol);
        $normalizedQty = $this->normalizeQuantityWithFilter($filter, $qty);
        $step = $this->resolveQtyStepFromFilter($filter);

        $payload = [
            'category' => 'spot',
            'symbol' => $symbol,
            'side' => ucfirst(strtolower($side)),
            'orderType' => 'Market',
            // Spot market buy defaults qty to quote (USDT); we pass base-coin quantity.
            'marketUnit' => 'baseCoin',
            'qty' => $this->formatQuantity($normalizedQty, $step),
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
                'retMsg' => 'Exception: '.$e->getMessage(),
                'result' => [],
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BASE URL
    |--------------------------------------------------------------------------
    */

    private function formatQuantity(float $qty, float $step = 0): string
    {
        if ($step > 0) {
            $decimals = $this->decimalPlacesFromStep($step);
            $formatted = number_format($qty, $decimals, '.', '');

            return rtrim(rtrim($formatted, '0'), '.') ?: '0';
        }

        return rtrim(rtrim(sprintf('%.8F', $qty), '0'), '.');
    }

    private function decimalPlacesFromStep(float $step): int
    {
        $stepStr = rtrim(sprintf('%.12F', $step), '0');
        $dotPos = strpos($stepStr, '.');

        if ($dotPos === false) {
            return 0;
        }

        return strlen(rtrim(substr($stepStr, $dotPos + 1), '0'));
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
        $payload = $timestamp.$apiKey.'5000'.$body;

        return hash_hmac('sha256', $payload, $apiSecret);
    }
}
