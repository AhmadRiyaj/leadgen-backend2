<?php

namespace App\Jobs;

use App\Models\Business;
use App\Models\Message;
use App\Services\AiLeadScorerService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScoreAndSendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(private int $businessId) {}

    public function handle(AiLeadScorerService $scorer, WhatsAppService $whatsapp): void
    {
        $business = Business::find($this->businessId);
        if (!$business || $business->opted_out || !$business->mobile) return;

        $analysis  = $scorer->score($business);
        $leadScore = (int)($analysis['lead_score'] ?? 5);

        $business->update(['lead_score' => $leadScore, 'ai_analysis' => $analysis, 'status' => 'scored']);

        if ($leadScore < 4) {
            Log::info("Low score skip: {$business->business_name}");
            return;
        }

        $messageText = $scorer->generateMessage($business, $analysis);
        $result      = $whatsapp->sendText($business->mobile, $messageText);

        Message::create([
            'business_id'         => $business->id,
            'message'             => $messageText,
            'status'              => $result['success'] ? 'sent' : 'failed',
            'whatsapp_message_id' => $result['message_id'] ?? null,
            'sent_at'             => $result['success'] ? now() : null,
        ]);

        $business->update([
            'status'            => $result['success'] ? 'message_sent' : 'scored',
            'last_contacted_at' => now(),
        ]);
    }
}
