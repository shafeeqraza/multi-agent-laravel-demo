<?php

namespace App\Services;

use App\Ai\Agents\CouponAgent;
use App\Ai\Agents\OrchestratorAgent;
use App\Ai\Agents\OrderStatusAgent;
use App\Ai\Agents\ReturnsAgent;
use InvalidArgumentException;
use Laravel\Ai\Responses\StructuredAgentResponse;

class ChatService
{
    private const AUTH_REQUIRED_FALLBACK = 'Please log in first so I can access your recent orders.';

    private array $actions = [
        'order_status' => OrderStatusAgent::class,
        'return_request' => ReturnsAgent::class,
        'discount_check' => CouponAgent::class,
    ];

    public function __construct(
        private OrchestratorAgent $orchestratorAgent,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sendMessage(string $message): array
    {
        $response = $this->orchestratorAgent->prompt($message, provider: config('chat.ai_text_providers'));

        $response = $this->decodeResponse($response, 'Empty response from orchestrator agent', 'Invalid JSON from orchestrator agent');
        $action = $response['action'];
        $params = $response['parameters'] ?? [];
        logger()->info('action', ['action' => $action, 'params' => $params]);

        if ($action === 'auth_required') {
            $reply = trim((string) ($params['message'] ?? ''));

            return [
                'auth_required' => true,
                'reply' => $reply !== '' ? $reply : self::AUTH_REQUIRED_FALLBACK,
            ];
        }

        if (! isset($this->actions[$action])) {
            throw new InvalidArgumentException('Unknown orchestrator action: ' . $action);
        }



        $agent = app()->make($this->actions[$action], $params);

        return $agent->run($params);
    }

    public function decodeResponse(mixed $response, string $emptyErrorMessage, string $jsonErrorMessage): array
    {
        if (is_array($response)) {
            return $response;
        }

        if ($response instanceof StructuredAgentResponse) {
            return $response->toArray();
        }

        $content = trim((string) $response);

        if ($content === '') {
            throw new InvalidArgumentException($emptyErrorMessage);
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException($jsonErrorMessage);
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Invalid response payload type.');
        }

        return $decoded;
    }
}
