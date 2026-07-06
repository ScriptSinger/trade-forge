<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BotRunStatus;
use App\Enums\BotStatus;
use App\Enums\ExchangeAccountStatus;
use App\Enums\ExchangeProvider;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PositionStatus;
use App\Enums\TradeSignal;
use App\Models\Bot;
use App\Models\BotRun;
use App\Models\BotStat;
use App\Models\ExchangeAccount;
use App\Models\Order;
use App\Models\Position;
use App\Models\Strategy;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TradingDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_trading_graph_is_related_and_casted_correctly(): void
    {
        $user = User::factory()->create();
        $strategy = Strategy::factory()->create();
        $exchangeAccount = ExchangeAccount::factory()->for($user)->create([
            'exchange' => ExchangeProvider::Bybit->value,
            'status' => ExchangeAccountStatus::Active->value,
            'api_key' => 'public-key',
            'api_secret' => 'super-secret',
        ]);
        $bot = Bot::factory()->for($user)->create([
            'exchange_account_id' => $exchangeAccount->id,
            'strategy_id' => $strategy->id,
            'status' => BotStatus::Paused->value,
        ]);

        $run = BotRun::factory()->create([
            'bot_id' => $bot->id,
            'signal' => TradeSignal::Buy->value,
            'status' => BotRunStatus::Processing->value,
        ]);

        $order = Order::factory()->create([
            'bot_id' => $bot->id,
            'exchange_account_id' => $exchangeAccount->id,
            'side' => OrderSide::Buy->value,
            'type' => OrderType::Market->value,
            'status' => OrderStatus::Placed->value,
        ]);

        $position = Position::factory()->create([
            'bot_id' => $bot->id,
            'status' => PositionStatus::Open->value,
        ]);

        $trade = Trade::factory()->create([
            'bot_id' => $bot->id,
        ]);

        $stat = BotStat::factory()->create([
            'bot_id' => $bot->id,
        ]);

        $rawSecret = DB::table('exchange_accounts')->where('id', $exchangeAccount->id)->value('api_secret');

        $this->assertNotSame('super-secret', $rawSecret);
        $this->assertSame('super-secret', ExchangeAccount::findOrFail($exchangeAccount->id)->api_secret);

        $this->assertInstanceOf(BotStatus::class, $bot->status);
        $this->assertSame(BotStatus::Paused, $bot->status);
        $this->assertInstanceOf(ExchangeProvider::class, $exchangeAccount->exchange);
        $this->assertSame(ExchangeProvider::Bybit, $exchangeAccount->exchange);
        $this->assertInstanceOf(BotRunStatus::class, $run->status);
        $this->assertSame(BotRunStatus::Processing, $run->status);
        $this->assertInstanceOf(OrderSide::class, $order->side);
        $this->assertSame(OrderSide::Buy, $order->side);
        $this->assertInstanceOf(OrderType::class, $order->type);
        $this->assertSame(OrderType::Market, $order->type);
        $this->assertInstanceOf(OrderStatus::class, $order->status);
        $this->assertSame(OrderStatus::Placed, $order->status);
        $this->assertInstanceOf(PositionStatus::class, $position->status);
        $this->assertSame(PositionStatus::Open, $position->status);
        $this->assertSame($bot->id, $run->bot->id);
        $this->assertSame($bot->id, $order->bot->id);
        $this->assertSame($exchangeAccount->id, $order->exchangeAccount->id);
        $this->assertSame($bot->id, $position->bot->id);
        $this->assertSame($bot->id, $trade->bot->id);
        $this->assertSame($bot->id, $stat->bot->id);

        $this->assertDatabaseCount('bots', 1);
        $this->assertDatabaseCount('bot_runs', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('positions', 1);
        $this->assertDatabaseCount('trades', 1);
        $this->assertDatabaseCount('bot_stats', 1);
    }
}
