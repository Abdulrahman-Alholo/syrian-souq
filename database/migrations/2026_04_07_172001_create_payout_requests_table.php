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
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('SYP');
            
            // أضفنا حالة processing لعمليات التحويل التي تأخذ وقتاً (مثل الحوالات البنكية)
            $table->enum('status', ['pending', 'processing', 'approved', 'rejected', 'failed'])->default('pending'); 
            
            $table->string('bank_details')->nullable();
            $table->text('admin_notes')->nullable();
            
            // 🛡️ حقول التدقيق (Audit)
            $table->foreignId('processed_by')->nullable()->constrained('users'); // رقم الأدمن
            $table->timestamp('processed_at')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
