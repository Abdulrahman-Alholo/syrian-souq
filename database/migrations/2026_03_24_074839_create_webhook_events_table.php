<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();

            // 🌍 مصدر الحدث
            $table->string('gateway'); // stripe, paypal

            // 🆔 ID من البوابة
            $table->string('event_id');

            // 📌 نوع الحدث
            $table->string('event_type'); // payment_intent.succeeded

            // 🔗 ربط الدفع (اختياري)
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            // 📦 payload كامل
            $table->json('payload');

            // 📊 حالة المعالجة
            $table->enum('status', [
                'pending',
                'processed',
                'failed',
            ])->default('pending');

            // 🐞 تسجيل الأخطاء
            $table->text('error_message')->nullable();

            // ⏱️ وقت المعالجة
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // 🔒 أهم سطر في الجدول كله
            $table->unique(['gateway', 'event_id']);

            // ⚡ Indexes
            $table->index(['status']);
            $table->index(['event_type']);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
