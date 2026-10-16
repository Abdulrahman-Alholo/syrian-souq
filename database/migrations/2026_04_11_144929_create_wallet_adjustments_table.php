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
       Schema::create('wallet_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users'); // الموظف الذي قام بالعملية
            $table->enum('type', ['credit', 'debit']); // إيداع أو خصم
            $table->decimal('amount', 15, 2);
            $table->string('reason'); // سبب التسوية
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_adjustments');
    }
};
