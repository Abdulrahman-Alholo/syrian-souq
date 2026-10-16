<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\VendorOrder;
use App\Modules\Order\Models\OrderItem; // أو VendorOrderItem حسب تسميتك
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Catalog\Models\ProductVariant; // للتحقق من المخزون
use Illuminate\Support\Facades\DB;

class SimulateOrderCycle extends Command
{
    protected $signature = 'test:order-cycle {vendor_id?}';
    protected $description = 'محاكاة دورة حياة طلب كاملة لاختبار الـ State Machine والأرباح';

    public function handle()
    {
        $this->info('🚀 بدء محاكاة دورة حياة الطلب الخارقة...');

        // 1. جلب التاجر (بدون خزعبلات المحفظة!)
        $vendorId = $this->argument('vendor_id') ?? 1;
        $vendor = Vendor::find($vendorId);

        if (!$vendor) {
            $this->error('❌ لم يتم العثور على التاجر.');
            return;
        }

        $this->warn("⚙️ جاري اختبار الطلب للتاجر: [{$vendor->store_name}]");

        DB::beginTransaction();
        try {
            // 2. إنشاء طلب رئيسي وهمي
            $this->line('📦 1. إنشاء الطلب الرئيسي...');
            $order = Order::create([
    'user_id' => 1,
    'order_number' => 'SIM-' . time(), // أو استخدم الـ TEMP الخاص بك
    'status' => 'pending',
    'subtotal' => 200000,
    'total_price' => 200000,
    'shipping_name' => 'تجربة محاكاة',
    'shipping_phone' => '0999999999',
    'shipping_city' => 'دمشق',
    'payment_method' => 'cod',
    // 👇 هاد الحقل اللي عمل كل هالمشكلة
    'shipping_address_line' => 'دمشق - المزة - بناء رقم 10',
]);

           // 3. إنشاء طلب فرعي للتاجر
            $this->line('🛍️ 2. تحويل الطلب للتاجر (Pending)...');
            $vendorOrder = VendorOrder::create([
                'order_id' => $order->id,
                'vendor_id' => $vendor->id,
                'status' => 'pending',
                'subtotal' => 200000,
                // 👈 هنا سنضيف نسبة العمولة الناقصة
                'commission_rate' => 10, 
                'commission_amount' => 20000, 
                'vendor_earnings' => 180000,
            ]);

            // 4. تغيير الحالات بالترتيب (مسار التاجر)
            $this->line('⏳ 3. التاجر يغير الحالة إلى (Processing)...');
            $vendorOrder->update(['status' => 'processing']);
            sleep(1); 

            $this->line('🚚 4. التاجر يغير الحالة إلى (Shipped)...');
            $vendorOrder->update(['status' => 'shipped']);
            sleep(1);

            // 5. الضربة القاضية (تدخل الإدارة/النظام)
            $this->line('👑 5. الإدارة تؤكد التسليم (Delivered)...');
            $this->line('⚡ جرس الـ Observer يجب أن ينبض الآن وينادي الـ LedgerService!');
            
            $vendorOrder->update(['status' => 'delivered']); 
            
            DB::commit();
            $this->info('✅ تم اكتمال الدورة بنجاح وبدون أي أخطاء برمجية!');
            $this->line('🔔 اذهب الآن إلى لوحة التاجر، وافحص أيقونة الإشعارات وشاشة سحب الأرباح لتتأكد من نزول الـ 180,000 SYP!');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('💥 فشل الاختبار: ' . $e->getMessage());
            return;
        }
    }
}