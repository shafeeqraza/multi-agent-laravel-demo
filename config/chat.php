<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chat request execution time (PHP)
    |--------------------------------------------------------------------------
    |
    | LLM orchestration can exceed default max_execution_time. This value is
    | applied to POST /chat via middleware. Docker nginx sets fastcgi_read_timeout
    | in docker/nginx/default.conf; keep CHAT_MAX_EXECUTION_SECONDS below that or
    | raise both together.
    |
    */

    'max_execution_seconds' => (int) env('CHAT_MAX_EXECUTION_SECONDS', 180),

    /*
    |--------------------------------------------------------------------------
    | Text AI providers (failover order)
    |--------------------------------------------------------------------------
    |
    | Comma-separated provider names from config/ai.php. Laravel AI tries the next
    | provider on failover (e.g. rate limits). Default is openai; set OPENAI_API_KEY.
    | Example failover: openai,gemini
    |
    */

    'ai_text_providers' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('CHAT_AI_TEXT_PROVIDERS', 'openai'))))) ?: ['openai'],
];
