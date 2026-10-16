<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('order_id')->constrained()->cascadeOnDelete(); // Index تلقائي
            $table->unsignedBigInteger('vendor_order_id')->nullable();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete(); // Index تلقائي

            $table->string('product_name'); 
            $table->string('variant_description')->nullable(); 
            
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2); 

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};