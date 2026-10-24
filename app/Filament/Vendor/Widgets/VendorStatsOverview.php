<?php

namespace App\Filament\Vendor\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Modules\Finance\Models\VendorTransaction;
use App\Modules\Finance\Models\PayoutRequest;
use App\Modules\Order\Models\VendorOrder;

class VendorStatsOverview extends BaseWidget
{
    // 🚀 تحديث الأرقام تلقائياً كل 15 ثانية (Live Polling)
    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $vendor = auth()->user()?->vendor;

        // حماية: إذا لم يكن لديه متجر، لا نعرض شيئاً
        if (!$vendor) {
            return [];
        }

        // 1. حساب السحوبات المعلقة (قيد المراجعة أو التحويل)
        $pendingPayouts = (float) PayoutRequest::where('vendor_id', $vendor->id)
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount');

        // 2. الرصيد الفعلي الجاهز للسحب فوراً (مطابق لصفحة طلب السحب 100%)
        $walletBalance = (float) ($vendor->wallet_balance ?? 0);
        $withdrawableBalance = max(0, $walletBalance - $pendingPayouts);

        // 3. الأرباح المعلقة في الطلبيات الجارية (Escrow - قيد التجهيز أو الشحن)
        $escrowEarnings = (float) VendorOrder::where('vendor_id', $vendor->id)
            ->whereIn('status', ['pending', 'processing', 'shipped'])
            ->sum('vendor_earnings');

        // 4. حساب إجمالي أرباح المبيعات المستلمة فعلياً (من السجل المالي المركزي)
        $totalEarnings = (float) VendorTransaction::where('vendor_id', $vendor->id)
            ->where('type', 'credit')
            ->where('transaction_type', 'order_earning')
            ->sum('amount');

        return [
            // 🟢 1. بطاقة الرصيد الجاهز للسحب
            Stat::make('الرصيد القابل للسحب', number_format($withdrawableBalance, 0) . ' SYP')
                ->description($pendingPayouts > 0 
                    ? 'الرصيد الكلي: ' . number_format($walletBalance, 0) . ' SYP' 
                    : 'جاهز للسحب فوراً')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            // 🟣 2. بطاقة أرباح الطلبيات الجارية (Escrow)
            Stat::make('أرباح قيد التحصيل', number_format($escrowEarnings, 0) . ' SYP')
                ->description('تُضاف للمحفظة فور تسليم الطلبات')
                ->descriptionIcon('heroicon-m-truck')
                ->color('primary')
                ->chart([3, 5, 8, 12, 6, 14, 18]),

            // 🟠 3. بطاقة السحوبات المعلقة
            Stat::make('سحوبات قيد المراجعة', number_format($pendingPayouts, 0) . ' SYP')
                ->description('بانتظار تحويل الإدارة')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            // 🔵 4. بطاقة إجمالي الأرباح التاريخية
            Stat::make('إجمالي أرباح المبيعات', number_format($totalEarnings, 0) . ' SYP')
                ->description('صافي أرباح الطلبات المسلّمة')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info')
                ->chart([1, 4, 2, 8, 5, 12, 10]),
        ];
    }
}