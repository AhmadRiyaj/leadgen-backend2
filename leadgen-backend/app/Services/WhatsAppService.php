<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $token;
    private string $phoneNumberId;

    public function __construct()
    {
        $this->token         = config('services.whatsapp.token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
    }

    public function sendText(string $toMobile, string $message): array
    {
        $to = '91' . ltrim($toMobile, '+91');

        $response = Http::withToken($this->token)
            ->post("https://graph.facebook.com/v19.0/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['body' => $message],
            ]);

        $data = $response->json();

        if (!$response->ok()) {
            Log::error('WhatsApp error', ['response' => $data]);
            return ['success' => false, 'error' => $data];
        }

        return ['success' => true, 'message_id' => $data['messages'][0]['id'] ?? null];
    }
}
