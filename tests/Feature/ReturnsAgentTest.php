<?php

use App\Ai\Agents\ReturnsAgent;
use App\Models\ReturnRequest;
use InvalidArgumentException;
use Laravel\Ai\Ai;

beforeEach(function () {
    $this->agent = new ReturnsAgent;
});

it('throws when order_id is missing', function () {
    $this->agent->handle([]);
})->throws(InvalidArgumentException::class);

it('returns not eligible when no prior request exists for order', function () {
    $mockChain = Mockery::mock();
    $mockChain->shouldReceive('instructions')->andReturnSelf();
    $mockChain->shouldReceive('asJson')->andReturnSelf();
    $mockChain->shouldReceive('prompt')->andReturn([
        'eligible' => false,
        'message' => 'Return window has expired',
    ]);

    Ai::shouldReceive('agent')->andReturn($mockChain);

    $result = $this->agent->handle(['order_id' => 'ORD-UNKNOWN']);

    expect($result['eligible'])->toBeFalse();
    expect($result['message'])->toBe('Return window has expired');
    expect(ReturnRequest::query()->where('order_id', 'ORD-UNKNOWN')->count())->toBe(0);
});

it('creates a return_request record and returns eligible when order is within 7-day window', function () {
    ReturnRequest::query()->create([
        'order_id' => 'ORD-123',
        'status' => 'pending',
        'requested_at' => now()->subDays(3),
    ]);

    $mockChain = Mockery::mock();
    $mockChain->shouldReceive('instructions')->andReturnSelf();
    $mockChain->shouldReceive('asJson')->andReturnSelf();
    $mockChain->shouldReceive('prompt')->andReturn([
        'eligible' => true,
        'message' => 'Return request created successfully',
    ]);

    Ai::shouldReceive('agent')->andReturn($mockChain);

    $result = $this->agent->handle(['order_id' => 'ORD-123']);

    expect($result['eligible'])->toBeTrue();
    expect($result['message'])->toBe('Return request created successfully');
    expect(ReturnRequest::query()->where('order_id', 'ORD-123')->count())->toBe(2);
    expect(ReturnRequest::query()->where('order_id', 'ORD-123')->latest()->first()->status)->toBe('pending');
});

it('returns not eligible and does not create a record when order is outside the 7-day window', function () {
    ReturnRequest::query()->create([
        'order_id' => 'ORD-OLD',
        'status' => 'pending',
        'requested_at' => now()->subDays(10),
    ]);

    $mockChain = Mockery::mock();
    $mockChain->shouldReceive('instructions')->andReturnSelf();
    $mockChain->shouldReceive('asJson')->andReturnSelf();
    $mockChain->shouldReceive('prompt')->andReturn([
        'eligible' => false,
        'message' => 'Return window has expired',
    ]);

    Ai::shouldReceive('agent')->andReturn($mockChain);

    $result = $this->agent->handle(['order_id' => 'ORD-OLD']);

    expect($result['eligible'])->toBeFalse();
    expect(ReturnRequest::query()->where('order_id', 'ORD-OLD')->count())->toBe(1);
});
