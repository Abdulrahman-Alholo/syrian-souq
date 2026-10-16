<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            
            // الربط مع السلة الأب
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            
            // 🎯 الربط مع النسخة المحددة (وليس المنتج العام) كما نصح ChatGPT
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            
            $table->unsignedInteger('quantity')->default(1);
            $table->integer('reserved_quantity')->default(0);
            
            $table->timestamps();

            // 🛡️ حماية على مستوى الداتا بيز: يمنع تكرار نفس النسخة في السلة (نكتفي بزيادة الكمية برمجياً)
            $table->unique(['cart_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};