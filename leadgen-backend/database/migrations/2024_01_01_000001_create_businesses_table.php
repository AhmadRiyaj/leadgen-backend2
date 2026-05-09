<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('owner_name')->nullable();
            $table->string('mobile', 15)->nullable()->index();
            $table->string('city')->index();
            $table->string('category')->index();
            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->boolean('has_website')->default(false);
            $table->string('source')->default('google_maps');
            $table->integer('lead_score')->default(0)->index();
            $table->json('ai_analysis')->nullable();
            $table->enum('status', [
                'new',
                'scored',
                'message_sent',
                'replied',
                'interested',
                'meeting',
                'proposal',
                'client',
                'not_interested'
            ])->default('new')->index();
            $table->boolean('opted_out')->default(false);
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
