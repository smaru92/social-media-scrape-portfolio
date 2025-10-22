<?php

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/testss',  \App\Http\Controllers\DMSendController::class);

// Google Sheet Webhook API
Route::post('/api/webhook/google-sheet/personal-info', [\App\Http\Controllers\Api\GoogleSheetWebhookController::class, 'receivePersonalInfo'])
    ->name('api.webhook.google-sheet.personal-info');
