<?php

declare(strict_types=1);

namespace App\Services\Exchange;

use App\Enums\ExchangeProvider;
use App\Models\ExchangeAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

final class ExchangeService
{
    /**
     * Проверяет, что Bybit API доступен и ключи валидны.
     *
     * @return array{
     *     connected: bool,
     *     message: string,
     *     status: ?int,
     *     data: array<string, mixed>|null
     * }
     */
    public function checkConnection(ExchangeAccount $account): array
    {
        [$baseUrl, $apiKey, $apiSecret, $recvWindow] = $this->resolveCredentials($account);

        $endpoint = '/v5/user/query-api';
        $queryString = '';
        $timestamp = $this->timestamp();
        $sign = $this->sign($timestamp, $apiKey, $apiSecret, $recvWindow, $queryString);

        $response = Http::baseUrl($baseUrl)
            ->timeout(15)
            ->retry(2, 250)
            ->withHeaders([
                'X-BAPI-API-KEY' => $apiKey,
                'X-BAPI-TIMESTAMP' => (string) $timestamp,
                'X-BAPI-RECV-WINDOW' => (string) $recvWindow,
                'X-BAPI-SIGN' => $sign,
            ])
            ->get($endpoint);

        return $this->formatResponse($response);
    }

    /**
     * @return array{0:string,1:string,2:string,3:int}
     */
    private function resolveCredentials(ExchangeAccount $account): array
    {
        if ($account->exchange !== ExchangeProvider::Bybit) {
            throw new InvalidArgumentException('Only Bybit accounts are supported for now.');
        }

        $baseUrl = rtrim($account->api_url, '/');

        $apiKey = $account->api_key;
        $apiSecret = $account->api_secret;
        $recvWindow = 5000;

        if (empty($apiKey) || empty($apiSecret)) {
            throw new RuntimeException('Bybit credentials are not configured on the exchange account.');
        }

        return [$baseUrl, $apiKey, $apiSecret, $recvWindow];
    }

    private function timestamp(): int
    {
        return (int) round(microtime(true) * 1000);
    }

    private function sign(int $timestamp, string $apiKey, string $apiSecret, int $recvWindow, string $queryString): string
    {
        $payload = $timestamp.$apiKey.$recvWindow.$queryString;

        return hash_hmac('sha256', $payload, $apiSecret);
    }

    /**
     * @return array{
     *     connected: bool,
     *     message: string,
     *     status: ?int,
     *     data: array<string, mixed>|null
     * }
     */
    private function formatResponse(Response $response): array
    {
        $json = $response->json();
        $retCode = (int) Arr::get($json, 'retCode', -1);
        $retMsg = (string) Arr::get($json, 'retMsg', $response->reason());

        return [
            'connected' => $response->successful() && $retCode === 0,
            'message' => $response->successful() && $retCode === 0
                ? 'Bybit API connection is valid.'
                : $retMsg,
            'status' => $response->status(),
            'data' => is_array($json) ? $json : null,
        ];
    }
}
