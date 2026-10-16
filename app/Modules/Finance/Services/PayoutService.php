<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\VendorTransaction;
use App\Modules\Finance\Models\PayoutRequest;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Vendor\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class PayoutService
{
    /**
     * الموافقة على طلب السحب واقتطاع الرصيد بأمان مطلق
     */
    public function approvePayout(PayoutRequest $payout, int $adminId): void
    {
        DB::transaction(function () use ($payout, $adminId) {
            
            // 1. قفل الطلب لمنع التكرار (Double Click) على مستوى الواجهة
            $lockedPayout = PayoutRequest::where('id', $payout->id)->lockForUpdate()->first();
            
            if ($lockedPayout->status === 'approved') {
                return; // امتصاص الصدمة بصمت
            }

            if ($lockedPayout->status !== 'pending') {
                throw new Exception("لا يمكن تنفيذ العملية. حالة الطلب الحالية: " . $lockedPayout->status);
            }

            // 🔥 2. نداء المحرك المركزي (LedgerService)
            // المحرك سيتولى: فحص الرصيد الحقيقي، قفل التاجر، إنشاء السجل، وخصم المحفظة!
            $ledger = app(LedgerService::class);
            
            $ledger->processTransaction(
                vendorId: $lockedPayout->vendor_id,
                type: 'debit', // سحب (خصم)
                amount: $lockedPayout->amount,
                transactionType: 'payout',
                referenceType: get_class($lockedPayout),
                referenceId: $lockedPayout->id,
                description: 'سحب أرباح - طلب رقم: ' . $lockedPayout->id,
                // 🛡️ مفتاح الحماية الفولاذي: كلمة payout + رقم الطلب (يستحيل تكرار الخصم)
                idempotencyKey: 'payout_' . $lockedPayout->id
            );

            // 3. إنهاء الطلب وتوثيق من قام بالموافقة
            $lockedPayout->update([
                'status'       => 'approved',
                'processed_by' => $adminId,
                'processed_at' => now(),
            ]);

        }); // لا نحتاج لـ 5 محاولات (Deadlock Retry) هنا لأن المحرك المركزي سريع جداً
    }

    /**
     * رفض طلب السحب (إذا كانت تفاصيل البنك خاطئة مثلاً)
     */
    public function rejectPayout(PayoutRequest $payout, int $adminId, string $reason): void
    {
        DB::transaction(function () use ($payout, $adminId, $reason) {
            $lockedPayout = PayoutRequest::where('id', $payout->id)->lockForUpdate()->first();
            
            if ($lockedPayout->status !== 'pending') {
                throw new Exception("هذا الطلب تمت معالجته مسبقاً.");
            }

            $lockedPayout->update([
                'status'       => 'rejected',
                'admin_notes'  => $reason,
                'processed_by' => $adminId,
                'processed_at' => now(),
            ]);

            // 🔔 إرسال إشعار فوري للتاجر بسبب الرفض
            if ($lockedPayout->vendor?->user) {
                \Filament\Notifications\Notification::make()
                    ->title('تم رفض طلب سحب الأرباح')
                    ->body("تم رفض طلب السحب رقم #{$lockedPayout->id} بقيمة " . number_format($lockedPayout->amount, 0) . " SYP.\nالسبب: {$reason}")
                    ->danger()
                    ->icon('heroicon-o-x-circle')
                    ->sendToDatabase($lockedPayout->vendor->user, isEventDispatched: true);
            }
        });
    }
}