<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\VendorOrder;
use App\Modules\Order\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorOrderSplitterService
{
    public function __construct(
        protected CommissionCalculatorService $commissionService
    ) {}

    /**
     * @return Collection<VendorOrder>
     */
    public function split(Order $order): Collection
    {
        if (!$order->items()->exists()) {
            Log::warning("محاولة تقسيم طلب فارغ.", ['order_id' => $order->id]);
            return collect();
        }

        if ($order->vendorOrders()->exists()) {
            Log::info("الطلب مقسم مسبقاً، تم تخطي العملية.", ['order_id' => $order->id]);
            return $order->vendorOrders;
        }

        $order->loadMissing('items.productVariant.product');

        return DB::transaction(function () use ($order) {
            
            $createdVendorOrders = collect();
            $itemsByVendor = $order->items->groupBy(fn($item) => $item->productVariant->product->vendor_id);

            foreach ($itemsByVendor as $vendorId => $group) {
                
                $vendorTotal = (float) $group->sum(fn($i) => $i->quantity * $i->unit_price);
                
                // 3. استدعاء الحسبة المالية الكاملة من خدمة العمولات
                $financials = $this->commissionService->calculate($vendorId, $vendorTotal);

                $vendorOrder = VendorOrder::firstOrCreate(
                    [
                        'order_id'  => $order->id,
                        'vendor_id' => $vendorId,
                    ],
                    [
                        'status'            => Order::STATUS_PROCESSING,
                        'subtotal'          => $vendorTotal,
                        'order_number'      => $order->id . '-' . $vendorId,
                        'commission_rate'   => $financials['rate'],
                        'commission_amount' => $financials['amount'], // 👈 الحقل الذي كان مفقوداً!
                        'vendor_earnings'   => $financials['earnings'],
                    ]
                );

                OrderItem::whereIn('id', $group->pluck('id'))
                         ->update(['vendor_order_id' => $vendorOrder->id]);

                $createdVendorOrders->push($vendorOrder);

                Log::info('تم إنشاء طلب فرعي للتاجر', [
                    'order_id'          => $order->id,
                    'vendor_id'         => $vendorId,
                    'vendor_order_id'   => $vendorOrder->id,
                    'commission_amount' => $financials['amount'],
                    'vendor_earnings'   => $financials['earnings'],
                ]);
            }

            return $createdVendorOrders;
        });
    }
}