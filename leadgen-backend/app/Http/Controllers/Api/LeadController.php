<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ScoreAndSendJob;
use App\Models\Business;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if ($request->bearerToken() !== config('app.scraper_api_key')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $v = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'mobile'        => 'nullable|string|max:15',
            'city'          => 'required|string|max:100',
            'category'      => 'required|string|max:100',
            'address'       => 'nullable|string',
            'website'       => 'nullable|string|max:500',
            'has_website'   => 'nullable|boolean',
            'source'        => 'nullable|string',
        ]);

        if ($v->fails()) return response()->json(['errors' => $v->errors()], 422);

        $business = Business::firstOrCreate(
            ['business_name' => $request->business_name, 'mobile' => $request->mobile, 'city' => $request->city],
            $v->validated()
        );

        if ($business->wasRecentlyCreated) {
            ScoreAndSendJob::dispatch($business->id)->delay(now()->addSeconds(30));
        }

        return response()->json(
            ['id' => $business->id, 'created' => $business->wasRecentlyCreated],
            $business->wasRecentlyCreated ? 201 : 200
        );
    }

    public function whatsappWebhook(Request $request): JsonResponse
    {
        $body = $request->all();

        foreach ($body['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['statuses'] ?? [] as $status) {
                    Message::where('whatsapp_message_id', $status['id'])
                        ->update(['status' => $status['status']]);
                }

                foreach ($value['messages'] ?? [] as $msg) {
                    $from     = preg_replace('/^91/', '', $msg['from'] ?? '');
                    $text     = $msg['text']['body'] ?? '';
                    $business = Business::where('mobile', $from)->first();

                    if ($business) {
                        if (str_contains(strtolower($text), 'stop')) {
                            $business->update(['opted_out' => true]);
                            continue;
                        }
                        $business->update(['status' => 'replied']);
                        Message::where('business_id', $business->id)
                            ->where('status', 'delivered')->latest()->first()
                            ?->update(['reply_text' => $text, 'replied_at' => now(), 'status' => 'replied']);
                    }
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
