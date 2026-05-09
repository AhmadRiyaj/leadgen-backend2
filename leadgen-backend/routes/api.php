<?php

use App\Http\Controllers\Api\LeadController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn() => response()->json(['status' => 'ok']));
Route::post('/leads', [LeadController::class, 'store']);

Route::get('/whatsapp/webhook', function (\Illuminate\Http\Request $r) {
    $mode      = $r->query('hub_mode');
    $token     = $r->query('hub_verify_token');
    $challenge = $r->query('hub_challenge');
    if ($mode === 'subscribe' && $token === config('app.whatsapp_verify_token')) {
        return response($challenge, 200);
    }
    return response('Forbidden', 403);
});

Route::post('/whatsapp/webhook', [LeadController::class, 'whatsappWebhook']);
