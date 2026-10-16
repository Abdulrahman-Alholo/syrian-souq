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
       Schema::create('vendor_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('vendor_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unique(['order_id', 'vendor_id'], 'unique_vendor_order');
            $table->decimal('subtotal', 14, 2);

            $table->decimal('delivery_fee', 14, 2)->default(0);
            $table->string('tracking_number')->nullable();

            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->decimal('vendor_earnings', 14, 2);

            $table->enum('status', [
                'pending',
                'processing',
                'shipped',
                'completed',
                'cancelled',
                'delivered',
                'failed_delivery'
            ])->default('pending');

            $table->timestamps();

            $table->index('order_id');
            $table->index(['vendor_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor__orders');
    }
};
