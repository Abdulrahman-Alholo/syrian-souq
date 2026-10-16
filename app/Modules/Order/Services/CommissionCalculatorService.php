<?php

namespace App\Modules\Order\Services;

use App\Modules\Vendor\Models\Vendor;

class CommissionCalculatorService
{
    public function getRateForVendor(int $vendorId): float
    {
        $vendor = Vendor::find($vendorId);
        return (float) ($vendor->commission_rate ?? 0.0);
    }

    /**
     * حساب كافة التفاصيل المالية للطلب الفرعي بدقة محاسبية
     *
     * @return array{rate: float, amount: float, earnings: float}
     */
    public function calculate(int $vendorId, float $subtotal): array
    {
        $rate = $this->getRateForVendor($vendorId);
        
        // حساب قيمة العمولة بدقة منزلتين عشريتين
        $commissionAmount = round($subtotal * ($rate / 100), 2);
        
        // صافي ربح التاجر
        $vendorEarnings = round($subtotal - $commissionAmount, 2);

        return [
            'rate'     => $rate,
            'amount'   => $commissionAmount,
            'earnings' => $vendorEarnings,
        ];
    }
}