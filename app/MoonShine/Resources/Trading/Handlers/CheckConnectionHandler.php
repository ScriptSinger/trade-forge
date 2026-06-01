<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Trading\Handlers;

use App\Enums\ExchangeAccountStatus;
use App\Models\ExchangeAccount;
use App\Services\Exchange\ExchangeService;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\UI\Components\ActionButton;
use Symfony\Component\HttpFoundation\Response;

final class CheckConnectionHandler extends Handler
{
    public function handle(): Response
    {
        $wrapper = $this->getResource()?->findItem(true);
        $account = $wrapper?->getOriginal();

        if (! $account instanceof ExchangeAccount) {
            return redirect()
                ->back()
                ->with('toast', [
                    'type' => 'error',
                    'message' => 'Exchange account not found.',
                ]);
        }

        try {
            $result = app(ExchangeService::class)->checkConnection($account);

            $account->forceFill([
                'status' => $result['connected']
                    ? ExchangeAccountStatus::Active
                    : ExchangeAccountStatus::Error,
                'last_checked_at' => now(),
            ])->save();

            return redirect()
                ->back()
                ->with('toast', [
                    'type' => $result['connected'] ? 'success' : 'error',
                    'message' => $result['message'],
                ]);
        } catch (\Throwable $e) {
            $account->forceFill([
                'status' => ExchangeAccountStatus::Error,
                'last_checked_at' => now(),
            ])->save();

            return redirect()
                ->back()
                ->with('toast', [
                    'type' => 'error',
                    'message' => $e->getMessage(),
                ]);
        }
    }

    public function getButton(): ActionButtonContract
    {
        return $this->prepareButton(
            ActionButton::make(
                'Check connection',
                url: $this->getUrl(),
            )
                ->icon('signal')
                ->withoutLoading()
                ->canSee(fn (): bool => (bool) $this->getResource()?->getItemID())
        );
    }

    public function getUrl(): string
    {
        $resource = $this->getResource();
        $itemID = $resource?->getItemID();

        return $resource?->getRoute(
            'handler',
            $itemID,
            ['handlerUri' => $this->getUriKey()],
        ) ?? '';
    }
}
