<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\StrategyMode;
use App\Services\Bot\StrategyModeResolver;
use Tests\TestCase;

class StrategyModeResolverTest extends TestCase
{
    private StrategyModeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new StrategyModeResolver();
    }

    public function test_mode_1_is_always_surfer(): void
    {
        $this->assertSame('Surfer', $this->resolver->resolveRuntime(StrategyMode::Surfer, 20, 30));
        $this->assertSame('Surfer', $this->resolver->resolveRuntime(StrategyMode::Surfer, 40, 30));
    }

    public function test_mode_2_is_always_hybrid(): void
    {
        $this->assertSame('Hybrid', $this->resolver->resolveRuntime(StrategyMode::Hybrid, 20, 30));
        $this->assertSame('Hybrid', $this->resolver->resolveRuntime(StrategyMode::Hybrid, 40, 30));
    }

    public function test_mode_3_switches_between_surfer_and_sniper(): void
    {
        $this->assertSame('Sniper', $this->resolver->resolveRuntime(StrategyMode::SmartSurfer, 25, 30));
        $this->assertSame('Surfer', $this->resolver->resolveRuntime(StrategyMode::SmartSurfer, 35, 30));
    }

    public function test_mode_4_switches_between_hybrid_and_sniper(): void
    {
        $this->assertSame('Sniper', $this->resolver->resolveRuntime(StrategyMode::SmartHybrid, 25, 30));
        $this->assertSame('Hybrid', $this->resolver->resolveRuntime(StrategyMode::SmartHybrid, 35, 30));
    }
}