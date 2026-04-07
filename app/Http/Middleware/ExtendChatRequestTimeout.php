<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtendChatRequestTimeout
{
    /**
     * Raise PHP's execution time limit for long-running LLM calls.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $seconds = (int) config('chat.max_execution_seconds', 180);

        if ($seconds > 0) {
            set_time_limit($seconds);
        }

        return $next($request);
    }
}
