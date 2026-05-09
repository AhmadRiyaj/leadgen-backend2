<?php
// ═══════════════════════════════════════════════════════════════════════════════
// routes/api.php  — add these lines
// ═══════════════════════════════════════════════════════════════════════════════

use App\Http\Controllers\Api\LeadController;
use Illuminate\Support\Facades\Route;

// Scraper endpoint (called by Python)
Route::post('/leads', [LeadController::class, 'store']);

// WhatsApp webhook (called by Meta)
Route::get('/whatsapp/webhook', function (\Illuminate\Http\Request $r) {
    // Meta verification handshake
    $mode  = $r->query('hub_mode');
    $token = $r->query('hub_verify_token');
    $challenge = $r->query('hub_challenge');

    if ($mode === 'subscribe' && $token === config('app.whatsapp_verify_token')) {
        return response($challenge, 200);
    }
    return response('Forbidden', 403);
});
Route::post('/whatsapp/webhook', [LeadController::class, 'whatsappWebhook']);


// ═══════════════════════════════════════════════════════════════════════════════
// app/Console/Kernel.php  — scheduled follow-up automation
// ═══════════════════════════════════════════════════════════════════════════════

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Every day at 10:30 AM — send follow-ups to leads that haven't replied in 3 days
        $schedule->call(function () {
            $cutoff = now()->subDays(3);

            \App\Models\Business::where('status', 'message_sent')
                ->where('last_contacted_at', '<=', $cutoff)
                ->where('opted_out', false)
                ->whereNotNull('mobile')
                ->limit(20)
                ->get()
                ->each(function ($business) {
                    \App\Jobs\ScoreAndSendJob::dispatch($business->id);
                });
        })->dailyAt('10:30');
    }

    protected $commands = [];
}


// ═══════════════════════════════════════════════════════════════════════════════
// config/services.php  — add this block
// ═══════════════════════════════════════════════════════════════════════════════

// Inside the returned array:
return [
    // ... existing entries ...

    'whatsapp' => [
        'token'           => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    ],
];


// ═══════════════════════════════════════════════════════════════════════════════
// TERMINAL COMMANDS — run after all files are in place
// ═══════════════════════════════════════════════════════════════════════════════
/*

# 1. Run migrations
php artisan migrate

# 2. Start the queue worker (keep running in background)
php artisan queue:work redis --queue=default --tries=3 --timeout=120

# 3. Start Laravel Horizon (better queue dashboard)
php artisan horizon

# 4. Start the scheduler (add this to server crontab instead)
# * * * * * cd /var/www/leadgen-backend && php artisan schedule:run >> /dev/null 2>&1

# 5. Run the Python scraper
cd scraper
pip install playwright httpx
playwright install chromium
python scraper.py

*/
