<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->enum('channel', ['whatsapp', 'sms', 'email'])->default('whatsapp');
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'replied', 'failed'])->default('pending')->index();
            $table->string('whatsapp_message_id')->nullable();
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
