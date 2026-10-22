<?php

namespace App\Modules\Cart\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Cart\Services\CheckoutService;
use App\Modules\Cart\Models\Cart;
use Exception;

class CheckoutController extends Controller
{
    protected CheckoutService $checkoutService;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    public function submitCheckout(Request $request)
    {
        // 1. التحقق من البيانات القادمة من الفرونت إند
        $validated = $request->validate([
            'cart_id'               => 'required|exists:carts,id',
            'payment_method'        => 'required|string',
            'shipping_name'         => 'required|string|max:255',
            'shipping_phone'        => 'required|string|max:20',
            'shipping_city'         => 'required|string|max:100',
            'shipping_address_line' => 'required|string',
            'notes'                 => 'nullable|string',
        ]);

        try {
            // 2. جلب السلة المطلوبة
            $cart = Cart::findOrFail($validated['cart_id']);

            // 3. تمرير البيانات للـ Service
            $order = $this->checkoutService->processCheckout(
                $cart,
                $validated,
                $validated['payment_method'],
                $request->header('X-Idempotency-Key')
            );

            $order->load('items.productVariant.product.vendor');

            // 4. تجهيز معاينة تقسيم الشحنات للفرونت إند
            $previewVendors = $order->items->groupBy(function ($item) {
                return $item->productVariant->product->vendor_id;
            })->map(function ($items, $vendorId) {
                $vendor = $items->first()->productVariant->product->vendor;
                return [
                    'vendor' => [
                        'id'   => $vendor->id,
                        'name' => $vendor->store_name ?? 'تاجر غير محدد',
                    ],
                    'shipment_type'  => 'separate',
                    'shipment_label' => 'شحنة منفصلة',
                    'items_count'    => $items->count(),
                    'subtotal'       => $items->sum(fn($i) => $i->quantity * $i->unit_price),
                ];
            })->values();

            // 5. إرجاع النتيجة
            return response()->json([
                'success'       => true,
                'message'       => 'تم استلام طلبك بنجاح، سيصلك على عدة شحنات.',
                'order_id'      => $order->id,
                'order_number'  => $order->order_number,
                'split_preview' => $previewVendors,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}