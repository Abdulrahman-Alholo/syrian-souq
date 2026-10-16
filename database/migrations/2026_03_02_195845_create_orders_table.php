<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            
            // 🚀 رقم الطلب المميز (مثل: ORD-2026-0012)
            $table->string('order_number')->unique();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Index تلقائي
            $table->foreignId('cart_id')->nullable()->constrained('carts')->nullOnDelete();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('status')->default('pending'); 

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('shipping_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);

            $table->string('payment_method')->default('cod');
            $table->string('currency')->default('SYP'); 

            $table->string('shipping_name');
            $table->string('shipping_phone');
            $table->string('shipping_city');
            $table->text('shipping_address_line');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes(); // 🗑️ حماية الفواتير من الحذف النهائي (قانونياً)

            // ⚡ تسريع البحث والفلترة
            $table->index('status'); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};