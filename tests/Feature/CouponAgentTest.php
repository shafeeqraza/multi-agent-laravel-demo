<?php

use App\Ai\Agents\CouponAgent;
use App\Models\Coupon;
use Laravel\Ai\Ai;

beforeEach(function () {
    $this->agent = new CouponAgent;
});

it('returns the fallback coupon when no active coupons exist in the database', function () {
    $mockChain = Mockery::mock();
    $mockChain->shouldReceive('instructions')->andReturnSelf();
    $mockChain->shouldReceive('asJson')->andReturnSelf();
    $mockChain->shouldReceive('prompt')->andReturn([
        'code' => 'WELCOME10',
        'discount' => '10%',
    ]);

    Ai::shouldReceive('agent')->andReturn($mockChain);

    $result = $this->agent->handle();

    expect($result['code'])->toBe('WELCOME10');
    expect($result['discount'])->toBe('10%');
});

it('returns an active coupon found in the database', function () {
    Coupon::query()->create([
        'code' => 'SAVE20',
        'discount' => '20%',
        'is_active' => true,
        'expires_at' => null,
    ]);

    $mockChain = Mockery::mock();
    $mockChain->shouldReceive('instructions')->andReturnSelf();
    $mockChain->shouldReceive('asJson')->andReturnSelf();
    $mockChain->shouldReceive('prompt')->andReturn([
        'code' => 'SAVE20',
        'discount' => '20%',
    ]);

    Ai::shouldReceive('agent')->andReturn($mockChain);

    $result = $this->agent->handle();

    expect($result['code'])->toBe('SAVE20');
    expect($result['discount'])->toBe('20%');
});

it('ignores expired coupons and falls back to the default', function () {
    Coupon::query()->create([
        'code' => 'EXPIRED50',
        'discount' => '50%',
        'is_active' => true,
        'expires_at' => now()->subDay(),
    ]);

    $mockChain = Mockery::mock();
    $mockChain->shouldReceive('instructions')->andReturnSelf();
    $mockChain->shouldReceive('asJson')->andReturnSelf();
    $mockChain->shouldReceive('prompt')->andReturn([
        'code' => 'WELCOME10',
        'discount' => '10%',
    ]);

    Ai::shouldReceive('agent')->andReturn($mockChain);

    $result = $this->agent->handle();

    expect($result['code'])->toBe('WELCOME10');
});

it('ignores inactive coupons and falls back to the default', function () {
    Coupon::query()->create([
        'code' => 'INACTIVE30',
        'discount' => '30%',
        'is_active' => false,
        'expires_at' => null,
    ]);

    $mockChain = Mockery::mock();
    $mockChain->shouldReceive('instructions')->andReturnSelf();
    $mockChain->shouldReceive('asJson')->andReturnSelf();
    $mockChain->shouldReceive('prompt')->andReturn([
        'code' => 'WELCOME10',
        'discount' => '10%',
    ]);

    Ai::shouldReceive('agent')->andReturn($mockChain);

    $result = $this->agent->handle();

    expect($result['code'])->toBe('WELCOME10');
});

it('returns a future-dated coupon as active', function () {
    Coupon::query()->create([
        'code' => 'FUTURE25',
        'discount' => '25%',
        'is_active' => true,
        'expires_at' => now()->addDays(30),
    ]);

    $mockChain = Mockery::mock();
    $mockChain->shouldReceive('instructions')->andReturnSelf();
    $mockChain->shouldReceive('asJson')->andReturnSelf();
    $mockChain->shouldReceive('prompt')->andReturn([
        'code' => 'FUTURE25',
        'discount' => '25%',
    ]);

    Ai::shouldReceive('agent')->andReturn($mockChain);

    $result = $this->agent->handle();

    expect($result['code'])->toBe('FUTURE25');
    expect($result['discount'])->toBe('25%');
});
