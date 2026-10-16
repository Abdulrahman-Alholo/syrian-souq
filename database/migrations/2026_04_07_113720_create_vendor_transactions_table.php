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
        Schema::create('vendor_transactions', function (Blueprint $table) {
            $table->id();
           $table->uuid('uuid')->unique();
            $table->string('idempotency_key')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            
            // credit / debit (تُستخدم للعمليات الحسابية فقط)
            $table->enum('type', ['credit', 'debit']); 
            
            // التصنيف الدقيق للحركة (يمنع تضارب القيود)
            $table->string('transaction_type'); // أمثلة: 'order_earning', 'payout', 'refund'
            
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('SYP');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('completed');
            
            $table->nullableMorphs('reference'); 
            $table->string('description')->nullable();
            
            $table->timestamps();

            // 🔥 القيد المحدث: لا يمكن تكرار (نفس نوع الحركة) لـ (نفس المرجع)
            $table->unique(
                ['reference_type', 'reference_id', 'transaction_type'], 
                'idx_unique_transaction'
            );

            $table->index(['vendor_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_transactions');
    }
};
