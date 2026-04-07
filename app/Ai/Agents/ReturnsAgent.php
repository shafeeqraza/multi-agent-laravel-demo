<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\DecodesAgentResponse;
use App\Ai\Concerns\ResolvesChatAiTextProviders;
use App\Ai\Tools\ReturnRequestTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class ReturnsAgent implements Agent, HasStructuredOutput
{
    use DecodesAgentResponse, Promptable, ResolvesChatAiTextProviders;

    public function __construct(private ReturnRequestTool $returnRequestTool) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{eligible: bool, message: string}
     */
    public function run(array $params): array
    {
        return $this->handle([
            'order_id' => (string) ($params['order_id'] ?? ''),
        ]);
    }

    /**
     * @param  array{order_id: string}  $input
     * @return array{eligible: bool, message: string}
     */
    public function handle(array $input): array
    {
        $validator = Validator::make($input, [
            'order_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException('Missing required field: order_id.');
        }

        $orderId = $input['order_id'];
        $eligible = $this->returnRequestTool->isEligible($orderId);

        if ($eligible) {
            $this->returnRequestTool->createPending($orderId);
        }

        $response = $this->prompt($this->buildPrompt($orderId, $eligible), provider: $this->chatAiTextProviders());

        $payload = $this->decodeResponse(
            response: $response,
            emptyErrorMessage: 'Empty returns agent response.',
            jsonErrorMessage: 'Invalid JSON from returns agent.',
        );

        $resultValidator = Validator::make($payload, [
            'eligible' => ['required', 'boolean'],
            'message' => ['required', 'string'],
        ]);

        if ($resultValidator->fails()) {
            throw new InvalidArgumentException('Invalid returns agent response payload.');
        }

        $validated = $resultValidator->validated();

        logger()->info('returns-agent.handle', [
            'order_id' => $orderId,
            'eligible' => $eligible,
            'payload' => $validated,
        ]);

        return $validated;
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
You are a returns and refunds assistant for an e-commerce system.
Return valid JSON only.
No explanations, markdown, or extra text.

Required JSON shape:
{
  "eligible": true or false,
  "message": "string"
}

Rules:
- If eligible is true, message must be: "Return request created successfully"
- If eligible is false, message must be: "Return window has expired"
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'eligible' => $schema->boolean()->required(),
            'message' => $schema->string()->required(),
        ];
    }

    protected function buildPrompt(string $orderId, bool $eligible): string
    {
        $eligibleText = $eligible ? 'true' : 'false';

        return "Process a return request for order_id: {$orderId}. The order is eligible for return: {$eligibleText}. Return only the required JSON shape.";
    }
}
