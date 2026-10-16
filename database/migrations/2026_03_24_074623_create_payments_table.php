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
            Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // 🔗 ربط الطلب
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // 💳 معلومات البوابة
            $table->string('gateway'); // stripe, paypal, cod
            $table->string('method')->nullable(); // card, apple_pay...

            // 📊 الحالة
            $table->enum('status', [
                'pending',
                'requires_action',
                'processing',
                'succeeded',
                'failed',
                'cancelled',
                'refunded',
            ])->default('pending');

            // 💰 المال
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10);

            // 🔐 Idempotency
            $table->string('idempotency_key')->nullable();

            // 🧾 من البوابة
            $table->string('transaction_id')->nullable(); // payment_intent_id
            $table->string('gateway_reference')->nullable(); // charge id

            // 🧠 Metadata
            $table->json('meta')->nullable(); // أي data إضافي من البوابة

            // 🐞 Debug / audit
            $table->json('response_payload')->nullable();

            $table->timestamps();

            // ⚡ Indexes
            $table->index(['order_id']);
            $table->index(['status']);
            $table->index(['gateway']);

            // 🔒 Idempotency protection
            $table->unique('idempotency_key');

            // 🔒 منع تكرار نفس العملية من البوابة
            $table->unique('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
