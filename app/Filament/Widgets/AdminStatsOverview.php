<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\VendorOrder;
use App\Modules\Finance\Models\PayoutRequest;
use App\Modules\Vendor\Models\Vendor;

class AdminStatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = '15s';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $platformCommissions = (float) VendorOrder::whereIn('status', ['delivered', 'completed'])
            ->sum('commission_amount');

        $pendingCommissions = (float) VendorOrder::whereIn('status', ['pending', 'processing', 'shipped'])
            ->sum('commission_amount');

        $totalGmv = (float) Order::whereIn('status', ['delivered', 'completed'])
            ->sum('total_price');

        $activeOrdersCount = Order::whereIn('status', ['pending', 'processing', 'shipped'])->count();

        $pendingPayoutsQuery = PayoutRequest::where('status', 'pending');
        $pendingPayoutsAmount = (float) $pendingPayoutsQuery->sum('amount');
        $pendingPayoutsCount = $pendingPayoutsQuery->count();

        $approvedVendorsCount = Vendor::where('status', 'approved')->count();
        $pendingVendorsCount = Vendor::where('status', 'pending')->count();

        return [
            // 🟢 1. صافي عمولات المنصة
            Stat::make('صافي أرباح المنصة', number_format($platformCommissions, 0) . ' SYP')
                ->description($pendingCommissions > 0 
                    ? 'عمولات بالطريق: ' . number_format($pendingCommissions, 0) . ' SYP' 
                    : 'العمولات المحققة من الطلبات المسلّمة')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([4, 8, 6, 12, 10, 16, 22]),

            // 🔵 2. إجمالي حجم المبيعات
            Stat::make('إجمالي حجم المبيعات', number_format($totalGmv, 0) . ' SYP')
                ->description("الطلبات النشطة حالياً: {$activeOrdersCount} طلب")
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info')
                ->chart([10, 15, 12, 18, 14, 22, 28]),

            // 🟠 3. طلبات السحب المعلقة
            Stat::make('سحوبات التجار المعلقة', number_format($pendingPayoutsAmount, 0) . ' SYP')
                ->description($pendingPayoutsCount > 0 
                    ? "يوجد ({$pendingPayoutsCount}) طلب بانتظار التحويل" 
                    : 'لا توجد مطالبات معلقة حالياً')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingPayoutsCount > 0 ? 'warning' : 'primary')
                ->chart([2, 4, 3, 6, 4, 5, 3]),

            // 🟣 4. المتاجر الشريكة
            Stat::make('المتاجر الشريكة المعتمدة', $approvedVendorsCount . ' متجر')
                ->description($pendingVendorsCount > 0 
                    ? "طلبات انضمام جديدة: {$pendingVendorsCount}" 
                    : 'جميع المتاجر نشطة ومفعّلة')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary')
                ->chart([1, 2, 2, 3, 4, 4, 5]),
        ];
    }
}