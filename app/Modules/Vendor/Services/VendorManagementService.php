<?php
namespace App\Modules\Vendor\Services;

use App\Modules\Vendor\Models\Vendor;
use Illuminate\Support\Facades\DB;

class VendorManagementService
{
    /**
     * موافقة الإدارة على متجر التاجر وتحديد العمولة
     */
    public function approveVendor(Vendor $vendor, float $commissionRate): Vendor
    {
        $vendor->update([
            'status' => 'approved',
            'commission_rate' => $commissionRate,
            'approved_at' => now(),
            'is_active' => true,
        ]);

        // يمكننا هنا مستقبلاً إرسال إشعار (Notification) للتاجر

        return $vendor;
    }

    /**
     * إيقاف متجر التاجر (بسبب مخالفة مثلاً)
     */
    public function suspendVendor(Vendor $vendor): Vendor
    {
        $vendor->update([
            'status' => 'suspended',
            'is_active' => false,
            'suspended_at' => now(),
        ]);

        // إخفاء كل منتجات التاجر تلقائياً عند إيقافه
        $vendor->products()->update(['is_active' => false]);

        return $vendor;
    }
}