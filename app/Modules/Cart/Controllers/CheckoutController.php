<?php

namespace App\Modules\Cart\Controllers; // عدل مسار الـ Namespace إذا لزم الأمر

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Cart\Services\CheckoutService;
use App\Modules\Cart\Models\Cart;
use Exception;

class CheckoutController extends Controller
{
    protected CheckoutService $checkoutService;

    // حقن الـ Service جوا الـ Controller
    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    public function submitCheckout(Request $request)
    {
        // 1. التحقق من البيانات الجاية من الفرونت إند
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

           // 3. نمرر البيانات للـ Service لتنفذ الشغل الثقيل
            $order = $this->checkoutService->processCheckout(
                $cart,
                $validated,
                $validated['payment_method'],
                $request->header('X-Idempotency-Key')
            );

            // 👇👇 ضيف هاد السطر هنا عشان لارافيل يسحب بيانات المنتجات والتجار من الداتا بيز
            $order->load('items.productVariant.product.vendor');

            // 4. 🔥 هنا نصنع الـ (Preview) الوهمي للفرونت إند...
            $previewVendors = $order->items->groupBy(function ($item) {
                return $item->productVariant->product->vendor_id;
                        })->map(function ($items, $vendorId) {
                            $vendor = $items->first()->productVariant->product->vendor;
                            return [
                                'vendor' => [
                                    'id'   => $vendor->id,
                                    'name' => $vendor->name_ar ?? 'تاجر غير محدد', // أو حسب حقل الاسم عندك
                                ],
                                'shipment_type'  => 'separate',
                                'shipment_label' => 'شحنة منفصلة',
                                'items_count'    => $items->count(),
                                'subtotal'       => $items->sum(fn($i) => $i->quantity * $i->unit_price),
                            ];
                        })->values();

            // 5. نرجع النتيجة للفرونت إند مع رسالة نجاح
            return response()->json([
                'success'       => true,
                'message'       => 'تم استلام طلبك بنجاح، سيصلك على عدة شحنات.',
                'order_id'      => $order->id,
                'order_number'  => $order->id, // أو إذا عندك حقل رقم طلب مخصص
                'split_preview' => $previewVendors // الفرونت إند بياخد هالمصفوفة وبيعرضها بشكل جميل
            ], 201);

        } catch (Exception $e) {
            // في حال فشل الحجز أو المخزون خلص بأخر ثانية، بنرجع رسالة الخطأ للعميل
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}