<?php

namespace App\Ai\Concerns;

trait ResolvesChatAiTextProviders
{
    /**
     * @return list<string>
     */
    protected function chatAiTextProviders(): array
    {
        return config('chat.ai_text_providers');
    }
}
