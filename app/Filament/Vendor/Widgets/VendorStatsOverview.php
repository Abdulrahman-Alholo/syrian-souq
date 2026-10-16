<?php

namespace App\Filament\Vendor\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Modules\Finance\Models\VendorTransaction;
use App\Modules\Finance\Models\PayoutRequest;

class VendorStatsOverview extends BaseWidget
{
    // 🚀 تحديث الأرقام تلقائياً كل 15 ثانية (Live Polling)
    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $vendor = auth()->user()->vendor;

        // حماية: إذا لم يكن لديه متجر، لا نعرض شيئاً
        if (!$vendor) {
            return [];
        }

        // 1. حساب الرصيد المتاح (من الـ Cache السريع)
        $availableBalance = $vendor->wallet_balance ?? 0;

        // 2. حساب إجمالي أرباح الطلبيات (من الخزنة المركزية مباشرة)
        $totalEarnings = VendorTransaction::where('vendor_id', $vendor->id)
            ->where('type', 'credit')
            ->where('transaction_type', 'order_earning') // نأخذ أرباح المبيعات فقط
            ->sum('amount');

        // 3. حساب المبالغ قيد الانتظار
        $pendingPayouts = PayoutRequest::where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->sum('amount');

        return [
            // 🟢 بطاقة الرصيد المتاح
            Stat::make('الرصيد المتاح', number_format($availableBalance, 0) . ' SYP')
                ->description('جاهز للسحب فوراً')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]), // رسم بياني جمالي (Sparkline)

            // 🔵 بطاقة إجمالي الأرباح
            Stat::make('إجمالي أرباح المبيعات', number_format($totalEarnings, 0) . ' SYP')
                ->description('منذ بداية عمل المتجر')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info')
                ->chart([1, 4, 2, 8, 5, 12, 10]), 

            // 🟠 بطاقة السحوبات المعلقة
            Stat::make('سحوبات معلقة', number_format($pendingPayouts, 0) . ' SYP')
                ->description('بانتظار موافقة الإدارة')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}