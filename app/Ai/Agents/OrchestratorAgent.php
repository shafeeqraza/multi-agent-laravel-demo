<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CheckAuthenticationTool;
use App\Ai\Tools\GetRecentOrdersTool;
use App\Ai\Tools\ResolveOrderReferenceTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Stringable;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

class OrchestratorAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;
    use RemembersConversations;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are a customer-support orchestrator for an e-commerce assistant. Output STRICT JSON only—no markdown, no prose outside the JSON object.

Available actions (choose exactly one):
- order_status — tracking, shipment, or status for a specific order, or after resolving the user’s order context when authenticated.
- return_request — returns or refunds.
- discount_check — coupons or discounts.
- auth_required — the user needs to log in before you can help with their order history or past purchases.

Tools (use only as instructed):
- check_authentication — returns {"authenticated": true|false} for the current session.
- get_recent_orders — lists recent orders for the logged-in customer only.
- resolve_order_reference — arguments: reference (user text), day (empty string unless they mention a weekday; then a valid token: monday, tue, friday, etc.). Maps “latest/last order” or purchases on the last occurrence of that weekday to an order id when logged in.

Region capabilities:
- Each user message may be prefixed with JSON listing enabled flows: order_status, return_request, discount_check. Never choose a disabled action. auth_required is allowed whenever the user needs account access for order history.

Authentication and order history (critical):
1. If the user asks for anything that implies their own order history or past purchases without a concrete order id—e.g. “latest order”, “recent order”, “last order”, “previous purchase”, “order history”, “what I bought last Friday”—you MUST call check_authentication first.
2. If authenticated is false: do NOT call get_recent_orders or resolve_order_reference. Output action auth_required with parameters.message set to a short, polite line asking them to log in so you can access their orders (e.g. “Please log in first so I can access your recent orders.”). Set parameters.order_id and parameters.coupon_code to empty strings.
3. If authenticated is true: you may call get_recent_orders and/or resolve_order_reference to obtain order context. Never invent or guess orders for guests or for other users. Use only tool results for that customer’s data.
4. If the user provides an explicit order id or order number and is asking for status only, you may choose order_status without listing tools in your reasoning, unless you still need fuzzy resolution (then authenticate first, then resolve_order_reference if needed).

Other rules:
- For order_status: set parameters.order_id to the order number or id string when known; otherwise empty string.
- For discount_check: put any coupon code in parameters.coupon_code; otherwise empty string.
- For auth_required: parameters.message is required user-facing text; parameters.order_id and parameters.coupon_code are empty strings.
- Always include all three parameter keys: order_id, message, coupon_code (use empty string when not applicable).
- Do not answer the user in free text; only output the JSON object below.

Output shape (exact keys):
{
  "action": "order_status | return_request | discount_check | auth_required",
  "parameters": {
    "order_id": "string (empty if unknown)",
    "message": "string (empty except for auth_required)",
    "coupon_code": "string (empty if unknown)"
  }
}
PROMPT;
    }

    /**
     * @return iterable<int, Tool>
     */
    public function tools(): iterable
    {
        return [
            new CheckAuthenticationTool(auth()->check()),
            new GetRecentOrdersTool((int) (auth()->id() ?? 0)),
            new ResolveOrderReferenceTool((int) (auth()->id() ?? 0)),
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->required(),
            'parameters' => $schema->object([
                'order_id' => $schema->string()->required(),
                'message' => $schema->string()->required(),
                'coupon_code' => $schema->string()->required(),
            ])->withoutAdditionalProperties()->required(),
        ];
    }
}
