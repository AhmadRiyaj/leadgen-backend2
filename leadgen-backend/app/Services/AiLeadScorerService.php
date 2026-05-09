<?php

namespace App\Services;

use App\Models\Business;
use OpenAI\Laravel\Facades\OpenAI;

class AiLeadScorerService
{
    public function score(Business $business): array
    {
        $prompt = "You are a B2B software sales analyst for Indian SMB market.\n\nAnalyze this business and return ONLY valid JSON (no markdown).\n\nBusiness:\n- Name: {$business->business_name}\n- Category: {$business->category}\n- City: {$business->city}\n- Has website: " . ($business->has_website ? 'yes' : 'no') . "\n\nReturn JSON:\n{\n  \"lead_score\": <1-10>,\n  \"needs_website\": <1-10>,\n  \"needs_billing_software\": <1-10>,\n  \"needs_crm\": <1-10>,\n  \"top_recommendation\": \"<one sentence>\",\n  \"pain_points\": [\"<point1>\",\"<point2>\"]\n}";

        $response = OpenAI::chat()->create([
            'model'    => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return json_decode($response->choices[0]->message->content, true) ?? [];
    }

    public function generateMessage(Business $business, array $analysis): string
    {
        $rec = $analysis['top_recommendation'] ?? 'improve digital operations';

        $prompt = "Write a short friendly WhatsApp message in Hinglish for a software company reaching out to a small business in India.\n\nBusiness: {$business->business_name}\nType: {$business->category}\nCity: {$business->city}\nSuggestion: {$rec}\n\nRules:\n- Max 4 short paragraphs\n- Friendly not salesy\n- Mention ONE specific benefit\n- End with free consultation offer\n- Add: Reply STOP to unsubscribe\n- No emojis";

        $response = OpenAI::chat()->create([
            'model'    => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return trim($response->choices[0]->message->content);
    }
}
