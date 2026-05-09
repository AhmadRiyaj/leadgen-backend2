<?php
// ═══════════════════════════════════════════════════════════════════════════════
// database/migrations/2024_01_01_000002_create_messages_table.php
// ═══════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->enum('channel', ['whatsapp', 'sms', 'email'])->default('whatsapp');
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'replied', 'failed'])
                  ->default('pending')->index();
            $table->string('whatsapp_message_id')->nullable();     // from Meta API response
            $table->text('reply_text')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};


// ═══════════════════════════════════════════════════════════════════════════════
// app/Models/Business.php
// ═══════════════════════════════════════════════════════════════════════════════

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_name', 'owner_name', 'mobile', 'city', 'category',
        'address', 'website', 'has_website', 'source',
        'lead_score', 'ai_analysis', 'status',
        'opted_out', 'last_contacted_at',
    ];

    protected $casts = [
        'has_website'       => 'boolean',
        'opted_out'         => 'boolean',
        'ai_analysis'       => 'array',
        'last_contacted_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
// app/Models/Message.php
// ═══════════════════════════════════════════════════════════════════════════════

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'business_id', 'message', 'channel', 'status',
        'whatsapp_message_id', 'reply_text',
        'sent_at', 'delivered_at', 'replied_at',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'replied_at'   => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
// app/Services/AiLeadScorerService.php
// ═══════════════════════════════════════════════════════════════════════════════

namespace App\Services;

use App\Models\Business;
use OpenAI\Laravel\Facades\OpenAI;

class AiLeadScorerService
{
    public function score(Business $business): array
    {
        $prompt = <<<EOT
You are a B2B software sales analyst for the Indian SMB market.

Analyze this business and return ONLY valid JSON (no markdown).

Business details:
- Name: {$business->business_name}
- Category: {$business->category}
- City: {$business->city}
- Has website: {$business->has_website}
- Website URL: {$business->website}

Return JSON:
{
  "lead_score": <integer 1-10>,
  "needs_website": <integer 1-10>,
  "needs_billing_software": <integer 1-10>,
  "needs_crm": <integer 1-10>,
  "needs_inventory": <integer 1-10>,
  "top_recommendation": "<one short sentence>",
  "pain_points": ["<point 1>", "<point 2>"]
}
EOT;

        $response = OpenAI::chat()->create([
            'model'    => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $raw  = $response->choices[0]->message->content;
        $data = json_decode($raw, true) ?? [];

        return $data;
    }

    public function generateMessage(Business $business, array $analysis): string
    {
        $rec = $analysis['top_recommendation'] ?? 'improve digital operations';

        $prompt = <<<EOT
Write a short, polite WhatsApp message in Hinglish (mix of Hindi and English) for a software company reaching out to a small business in India.

Business: {$business->business_name}
Category: {$business->category}
City: {$business->city}
Recommendation: {$rec}

Rules:
- Max 4 short paragraphs
- Friendly, not salesy
- Mention ONE specific benefit relevant to their business type
- End with a soft call to action (free consultation)
- Include opt-out: "Reply STOP to unsubscribe"
- Do NOT use emojis
EOT;

        $response = OpenAI::chat()->create([
            'model'    => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return trim($response->choices[0]->message->content);
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
// app/Services/WhatsAppService.php
// ═══════════════════════════════════════════════════════════════════════════════

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $token;
    private string $phoneNumberId;
    private string $apiVersion = 'v19.0';

    public function __construct()
    {
        $this->token         = config('services.whatsapp.token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
    }

    public function sendText(string $toMobile, string $message): array
    {
        $to = '91' . ltrim($toMobile, '+91');  // ensure +91 prefix

        $response = Http::withToken($this->token)
            ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['body' => $message],
            ]);

        $data = $response->json();

        if (! $response->ok()) {
            Log::error('WhatsApp API error', ['response' => $data, 'to' => $to]);
            return ['success' => false, 'error' => $data];
        }

        return [
            'success'    => true,
            'message_id' => $data['messages'][0]['id'] ?? null,
        ];
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
// app/Jobs/ScoreAndSendJob.php
// ═══════════════════════════════════════════════════════════════════════════════

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

        if (! $business || $business->opted_out || ! $business->mobile) {
            return;
        }

        // 1. AI scoring
        $analysis  = $scorer->score($business);
        $leadScore = (int) ($analysis['lead_score'] ?? 5);

        $business->update([
            'lead_score'  => $leadScore,
            'ai_analysis' => $analysis,
            'status'      => 'scored',
        ]);

        // Skip low-value leads
        if ($leadScore < 4) {
            Log::info("Skipping low-score lead: {$business->business_name} (score={$leadScore})");
            return;
        }

        // 2. Generate personalised message
        $messageText = $scorer->generateMessage($business, $analysis);

        // 3. Send via WhatsApp
        $result = $whatsapp->sendText($business->mobile, $messageText);

        // 4. Record in messages table
        Message::create([
            'business_id'         => $business->id,
            'message'             => $messageText,
            'status'              => $result['success'] ? 'sent' : 'failed',
            'whatsapp_message_id' => $result['message_id'] ?? null,
            'sent_at'             => $result['success'] ? now() : null,
        ]);

        $business->update([
            'status'           => $result['success'] ? 'message_sent' : 'scored',
            'last_contacted_at' => now(),
        ]);
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
// app/Http/Controllers/Api/LeadController.php
// ═══════════════════════════════════════════════════════════════════════════════

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ScoreAndSendJob;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadController extends Controller
{
    // Called by the Python scraper: POST /api/leads
    public function store(Request $request): JsonResponse
    {
        // Simple API key guard
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

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        // Upsert (avoid duplicate rows)
        $business = Business::firstOrCreate(
            [
                'business_name' => $request->business_name,
                'mobile'        => $request->mobile,
                'city'          => $request->city,
            ],
            $v->validated()
        );

        if ($business->wasRecentlyCreated) {
            // Dispatch AI scoring + WhatsApp job (runs async in queue)
            ScoreAndSendJob::dispatch($business->id)->delay(now()->addSeconds(30));
        }

        return response()->json([
            'id'      => $business->id,
            'created' => $business->wasRecentlyCreated,
        ], $business->wasRecentlyCreated ? 201 : 200);
    }

    // WhatsApp webhook (Meta sends delivery + reply events here)
    // POST /api/whatsapp/webhook
    public function whatsappWebhook(Request $request): JsonResponse
    {
        $body = $request->all();

        foreach ($body['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                // Status updates (sent → delivered → read)
                foreach ($value['statuses'] ?? [] as $status) {
                    \App\Models\Message::where('whatsapp_message_id', $status['id'])
                        ->update(['status' => $status['status']]);
                }

                // Incoming reply messages
                foreach ($value['messages'] ?? [] as $msg) {
                    $from    = preg_replace('/^91/', '', $msg['from'] ?? '');
                    $text    = $msg['text']['body'] ?? '';
                    $business = Business::where('mobile', $from)->first();

                    if ($business) {
                        // Mark opt-out
                        if (str_contains(strtolower($text), 'stop')) {
                            $business->update(['opted_out' => true]);
                            continue;
                        }

                        $business->update(['status' => 'replied']);

                        \App\Models\Message::where('business_id', $business->id)
                            ->where('status', 'delivered')
                            ->latest()
                            ->first()
                            ?->update(['reply_text' => $text, 'replied_at' => now(), 'status' => 'replied']);
                    }
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
