<?php

namespace App\Modules\Finance\Services;

use App\Modules\Order\Models\VendorOrder;
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Finance\Models\VendorTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Exception;

class LedgerService
{
    /**
     * 🔥 المحرك المركزي الوحيد لأي حركة مالية (إيداع أو سحب)
     */
    public function processTransaction(
        int $vendorId,
        string $type, // 'credit' أو 'debit'
        float $amount,
        string $transactionType, // 'payout', 'manual_adjustment', 'order_earning'
        string $referenceType,
        int $referenceId,
        string $description,
        string $idempotencyKey // 🛡️ مفتاح الحماية الفولاذي
    ) {
        // 1. فحص منع التكرار (Idempotency Check)
        $existingTransaction = VendorTransaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existingTransaction) {
            return $existingTransaction; 
        }

        return DB::transaction(function () use ($vendorId, $type, $amount, $transactionType, $referenceType, $referenceId, $description, $idempotencyKey) {
            
            // 2. قفل سجل التاجر (Pessimistic Lock)
            $vendor = Vendor::where('id', $vendorId)->lockForUpdate()->first();

            if (!$vendor) {
                throw new Exception("التاجر غير موجود.");
            }

            // 3. التحقق من الرصيد الفعلي في حالة "السحب" (True Balance)
            if ($type === 'debit') {
                $credits = VendorTransaction::where('vendor_id', $vendor->id)->where('type', 'credit')->sum('amount');
                $debits = VendorTransaction::where('vendor_id', $vendor->id)->where('type', 'debit')->sum('amount');
                $trueBalance = $credits - $debits;

                if ($trueBalance < $amount) {
                    throw new Exception("رصيد التاجر الفعلي غير كافٍ. المتاح حقيقةً: " . number_format($trueBalance, 0));
                }
            }

            // 4. إنشاء السجل المالي
            $transaction = VendorTransaction::create([
                'vendor_id'        => $vendor->id,
                'idempotency_key'  => $idempotencyKey,
                'type'             => $type,
                'transaction_type' => $transactionType,
                'amount'           => $amount,
                'currency'         => 'SYP',
                'status'           => 'completed',
                'description'      => $description,
                'reference_type'   => $referenceType,
                'reference_id'     => $referenceId,
            ]);

            // 5. تحديث محفظة الكاش (Cache) وإرسال الإشعارات
            if ($type === 'credit') {
                $vendor->increment('wallet_balance', $amount);
                
                if ($vendor->user) {
                    Notification::make()
                        ->title('إيداع مالي جديد')
                        ->body("تم إضافة " . number_format($amount, 0) . " SYP إلى رصيدك. \nالبيان: " . $description)
                        ->success()
                        ->icon('heroicon-o-arrow-trending-up')
                        ->sendToDatabase($vendor->user, isEventDispatched: true);
                }

            } else {
                $vendor->decrement('wallet_balance', $amount);
                
                if ($vendor->user) {
                    Notification::make()
                        ->title('خصم من الرصيد')
                        ->body("تم خصم " . number_format($amount, 0) . " SYP من رصيدك. \nالبيان: " . $description)
                        ->danger()
                        ->icon('heroicon-o-arrow-trending-down')
                        ->sendToDatabase($vendor->user, isEventDispatched: true);
                }
            }

            Log::info('✅ LedgerService: تم تسجيل الحركة المالية بنجاح', [
                'transaction_id' => $transaction->id,
                'vendor_id'      => $vendor->id,
                'amount'         => $amount,
            ]);

            return $transaction;
        });
    }

    /**
     * 📦 دالة مساعدة: إيداع أرباح الطلبيات
     */
    public function creditOrderEarnings(VendorOrder $vendorOrder): void
    {
        // ✅ التصحيح الحاسم: قبول 'delivered' (المعتمدة في الـ State Machine والـ Observer)
        if ($vendorOrder->vendor_earnings <= 0 || !in_array($vendorOrder->status, ['delivered', 'completed'])) {
            return;
        }

        $orderNum = $vendorOrder->order?->order_number ?? $vendorOrder->order_number ?? $vendorOrder->id;

        $this->processTransaction(
            vendorId: $vendorOrder->vendor_id,
            type: 'credit',
            amount: (float) $vendorOrder->vendor_earnings,
            transactionType: 'order_earning',
            referenceType: VendorOrder::class,
            referenceId: $vendorOrder->id,
            description: 'أرباح الطلب رقم: ' . $orderNum,
            idempotencyKey: 'order_earning_' . $vendorOrder->id
        );
    }
}