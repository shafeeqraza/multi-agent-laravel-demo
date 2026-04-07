<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CheckAuthenticationTool implements Tool
{
    public function __construct(protected bool $isAuthenticated) {}

    public function name(): string
    {
        return 'check_authentication';
    }

    public function description(): Stringable|string
    {
        return 'Returns whether the current visitor is logged in. Call this before any tool that reads order history (get_recent_orders, resolve_order_reference).';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        return json_encode(['authenticated' => $this->isAuthenticated], JSON_THROW_ON_ERROR);
    }
}
