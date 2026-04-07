<?php

use App\Ai\Tools\ReturnRequestTool;
use App\Services\ChatService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('order:status', function (ChatService $chatService) {

    $output = $chatService->sendMessage('I want to return my order that I placed on Friday.');

    dd($output);
})->purpose('Get order status');
