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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->decimal('wallet_balance', 12, 2)->default(0);
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('store_name');
            $table->string('store_slug')->unique();

            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('city')->nullable();

            $table->enum('status', ['pending', 'approved', 'suspended'])
                ->default('pending');

            $table->boolean('is_active')->default(true);

            // العملة الأساسية للمتجر
            $table->string('base_currency', 3)->default('USD');

            $table->decimal('commission_rate', 5, 2)->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('is_active');
            $table->index('base_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
