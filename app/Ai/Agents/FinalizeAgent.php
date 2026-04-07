<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class FinalizeAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
You are a customer-facing e-commerce assistant.
You will receive JSON data.
Write one short, natural response for the customer.
Do not invent values.
If data is missing, politely ask for what is needed.
PROMPT;
    }
}
