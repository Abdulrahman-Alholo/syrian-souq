<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Modules\User\Models\User;
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Order\Models\VendorOrder;
use App\Modules\Order\Models\Order;

class OrderWorkflowTest extends TestCase
{
    // مسح الداتا بعد كل اختبار ليبقى النظام نظيفاً
    use RefreshDatabase; 

    /**
     * 🛠️ دالة مساعدة لإنشاء بيانات أساسية للاختبار بدون الحاجة لـ Factories
     */
    private function setupTestData()
    {
        // 1. إنشاء مستخدم
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test' . rand(1, 10000) . '@test.com',
            'password' => bcrypt('123456'),
            'phone' => '0999999999999'
        ]);

        // 2. إنشاء تاجر
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'store_name' => 'متجر الاختبار',
            'store_slug' => 'test-store-' . rand(1, 1000),
        ]);

        // 3. إنشاء طلب أب
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'SIM-' . rand(1000, 9999),
            'status' => 'pending',
            'subtotal' => 200000,
            'total_price' => 200000,
            'shipping_name' => 'تجربة',
            'shipping_phone' => '0999999999',
            'shipping_city' => 'دمشق',
            'payment_method' => 'cod',
            'shipping_address_line' => 'المزة - الاختبار',
        ]);

        return [$vendor, $order];
    }

    /**
     * 1️⃣ الاختبار الذهبي: الأرباح تنزل فعلاً عند التسليم
     */
    public function test_vendor_gets_earnings_when_order_delivered()
    {
        [$vendor, $order] = $this->setupTestData();
        
        $vendorOrder = VendorOrder::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => 'shipped',
            'subtotal' => 200000,
            'commission_rate' => 10,
            'commission_amount' => 20000,
            'vendor_earnings' => 180000,
        ]);

        // التنفيذ (تغيير الحالة إلى تم التسليم)
        $vendorOrder->update(['status' => 'delivered']);

        // التحقق من السجل المالي
        // ⚠️ ملاحظة: تأكد أن اسم الجدول هو 'ledgers' أو غيره إلى اسم جدول الحركات المالية لديك
        $this->assertDatabaseHas('vendor_transactions', [ 
            'vendor_id' => $vendor->id,
            'amount' => 180000,
        ]);
    }

    /**
     * 2️⃣ اختبار الحماية من التكرار (Idempotency)
     */
    public function test_earnings_are_not_duplicated()
    {
        [$vendor, $order] = $this->setupTestData();
        
        $vendorOrder = VendorOrder::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => 'shipped',
            'subtotal' => 50000,
            'commission_rate' => 10,
            'commission_amount' => 5000,
            'vendor_earnings' => 45000,
        ]);

        // أول مرة (يجب أن تنجح)
        $vendorOrder->update(['status' => 'delivered']);

        // محاولة ثانية خبيثة لتكرار الأرباح
        $vendorOrder->update(['status' => 'delivered']);

        // يجب أن يكون هناك سجل مالي واحد فقط!
        $transactionCount = \Illuminate\Support\Facades\DB::table('vendor_transactions')
                                ->where('reference_id', $vendorOrder->id)
                                ->count();

        $this->assertEquals(1, $transactionCount);
    }

    /**
     * 3️⃣ اختبار منع القفز العشوائي للحالات
     */
    public function test_invalid_status_transition_is_blocked()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        [$vendor, $order] = $this->setupTestData();
        
        $vendorOrder = VendorOrder::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => 'pending', // قيد الانتظار
            'subtotal' => 50000,
            'commission_rate' => 10,
            'commission_amount' => 5000,
            'vendor_earnings' => 45000,
        ]);

        // محاولة القفز لـ Delivered مباشرة
        $vendorOrder->update(['status' => 'delivered']);
    }

    /**
     * 4️⃣ اختبار منع سحب رصيد أكثر من المتاح
     */
    public function test_vendor_cannot_withdraw_more_than_balance()
    {
        $this->expectException(\Exception::class);

        [$vendor, $order] = $this->setupTestData();
        
        // محاولة سحب مبلغ 50000 ورصيده 0
        app(\App\Modules\Finance\Services\LedgerService::class)
            ->processTransaction(
                vendorId: $vendor->id,
                amount: 50000,
                type: 'debit',
                transactionType: 'payout',
                referenceId: 999,
                description: 'test withdraw'
            );
    }
}