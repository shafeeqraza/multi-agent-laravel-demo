<?php

namespace App\Http\Controllers;

use App\Ai\Agents\CouponAgent;
use App\Ai\Agents\OrchestratorAgent;
use App\Ai\Agents\OrderStatusAgent;
use App\Ai\Agents\ReturnsAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class ChatController extends Controller
{
    public function __invoke(
        Request $request,
        OrchestratorAgent $orchestrator,
        OrderStatusAgent $orderStatusAgent,
        ReturnsAgent $returnsAgent,
        CouponAgent $couponAgent,
    ): JsonResponse {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = trim($validated['message']);

        logger()->info('chat.request.received', ['message' => $message]);

        try {
            [$route, $data, $finalMessage] = retry(
                times: 3,
                callback: function () use ($orchestrator, $orderStatusAgent, $returnsAgent, $couponAgent, $message) {
                    $route = $orchestrator->route($message);
                    $action = $route['action'];
                    $parameters = $route['parameters'] ?? [];
                    $orderId = $parameters['order_id'] ?? null;

                    $data = match ($action) {
                        'order_status' => $this->handleOrderStatus($orderStatusAgent, $orderId),
                        'return_request' => $this->handleReturnRequest($returnsAgent, $orderId),
                        'discount_check' => $this->handleDiscountCheck($couponAgent),
                        default => throw new InvalidArgumentException('Unsupported action.'),
                    };

                    $finalMessage = $orchestrator->finalize([
                        'action' => $action,
                        'parameters' => $parameters,
                        'result' => $data,
                    ]);

                    return [$route, $data, $finalMessage];
                },
                sleepMilliseconds: function (int $attempt) {
                    return $attempt * 2000;
                },
                when: fn (Throwable $e) => str_contains($e->getMessage(), 'rate limit') || str_contains($e->getMessage(), 'rate limited'),
            );

            $action = $route['action'];
            $orderId = ($route['parameters'] ?? [])['order_id'] ?? null;

            logger()->info('chat.request.completed', [
                'action' => $action,
                'order_id' => $orderId,
            ]);

            return response()->json([
                'message' => $finalMessage,
                'data' => $data,
            ]);
        } catch (Throwable $exception) {
            $isRateLimit = str_contains($exception->getMessage(), 'rate limit')
                || str_contains($exception->getMessage(), 'rate limited');

            logger()->info('chat.request.failed', [
                'error' => $exception->getMessage(),
            ]);

            if ($isRateLimit) {
                return response()->json([
                    'message' => 'The assistant is busy right now. Please wait a moment and try again.',
                ], 429);
            }

            return response()->json([
                'message' => 'Unable to process your request right now.',
            ], 422);
        }
    }

    /**
     * @return array{status: string, estimated_delivery: string}|array{status: string, message: string}
     */
    protected function handleOrderStatus(OrderStatusAgent $orderStatusAgent, ?string $orderId): array
    {
        if ($orderId === null || $orderId === '') {
            return [
                'status' => 'missing_order_id',
                'message' => 'Please provide your order ID.',
            ];
        }

        return $orderStatusAgent->handle($orderId);
    }

    /**
     * @return array{eligible: bool, message: string}|array{status: string, message: string}
     */
    protected function handleReturnRequest(ReturnsAgent $returnsAgent, ?string $orderId): array
    {
        if ($orderId === null || $orderId === '') {
            return [
                'status' => 'missing_order_id',
                'message' => 'Please provide your order ID.',
            ];
        }

        return $returnsAgent->handle(['order_id' => $orderId]);
    }

    /**
     * @return array{code: string, discount: string}
     */
    protected function handleDiscountCheck(CouponAgent $couponAgent): array
    {
        return $couponAgent->handle();
    }
}
