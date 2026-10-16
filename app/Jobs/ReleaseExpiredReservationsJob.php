<?php

namespace App\Jobs;

use App\Models\Cart;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredReservationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // 1. نجيب كل السلات اللي حالتها 'reserved' ومر على حجزها أكثر من 15 دقيقة
        $expiredCarts = Cart::where('status', 'reserved')
                            ->where('reserved_at', '<', now()->subMinutes(15))
                            ->get();

        foreach ($expiredCarts as $cart) {
            try {
                // 2. نبدأ Transaction لكل سلة لحالها عشان إذا فشلت وحدة ما توقف الباقي
                DB::transaction(function () use ($cart) {
                    
                    // نقفل السلة
                    $lockedCart = Cart::where('id', $cart->id)->lockForUpdate()->first();
                    
                    // نتأكد إنها لسا محجوزة (ممكن العميل دفع بآخر ثانية)
                    if (!$lockedCart || $lockedCart->status !== 'reserved') {
                        return;
                    }

                    // 3. نمر على المنتجات اللي جوا السلة ونرجع المخزون المحجوز
                    foreach ($lockedCart->items as $item) {
                        if ($item->reserved_quantity > 0) {
                            // نقفل المنتج المعين
                            $variant = ProductVariant::where('id', $item->product_variant_id)
                                                     ->lockForUpdate()
                                                     ->first();

                            if ($variant) {
                                // نطرح الكمية من المخزون المحجوز (ترجع للرف)
                                $variant->decrement('reserved_stock', $item->reserved_quantity);
                            }

                            // نصفّر الكمية المحجوزة في السلة
                            $item->update(['reserved_quantity' => 0]);
                        }
                    }

                    // 4. نرجع حالة السلة لـ 'active' عشان لو العميل رجع يكمل يقدر يحاول من جديد
                    $lockedCart->update([
                        'status' => 'active',
                        'reserved_at' => null
                    ]);
                });

            } catch (\Exception $e) {
                // نسجل الخطأ بالـ Log ونكمل للسلة اللي بعدها بدون ما يوقف الـ Job
                Log::error("فشل فك الحجز للسلة رقم {$cart->id}: " . $e->getMessage());
            }
        }
    }
}