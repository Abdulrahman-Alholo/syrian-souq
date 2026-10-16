<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('guest_token')->nullable()->index(); 
            
            // 🟢 التعديل الجوهري: إضافة حالة السلة
            $table->enum('status', ['active', 'converted', 'abandoned'])->default('active');
            $table->timestamp('reserved_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes(); // 🟢 حماية السلات المتروكة من الحذف النهائي
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};