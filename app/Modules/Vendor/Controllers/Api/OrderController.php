<?php

namespace App\Modules\Vendor\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Order\Models\VendorOrder;
use App\Modules\Order\Services\VendorOrderService;
use Exception;

class OrderController extends Controller
{
    protected VendorOrderService $vendorOrderService;

    public function __construct(VendorOrderService $vendorOrderService)
    {
        $this->vendorOrderService = $vendorOrderService;
    }

    /**
     * عرض قائمة طلبات التاجر الحالي
     */
    public function index(Request $request)
    {
        // نفترض أنك تستخدم Auth وتخزن vendor_id في جدول users أو كـ Guard منفصل
        // $vendorId = auth()->user()->vendor_id; 
        $vendorId = 1; 

        // جلب الطلبات الخاصة بهذا التاجر فقط، مع بيانات المنتجات وتفاصيل الشحن من الطلب الرئيسي
        $orders = VendorOrder::with([
            'items.productVariant.product', // لمعرفة ماذا اشترى العميل
            'parentOrder:id,shipping_name,shipping_phone,shipping_city,shipping_address_line,notes' // لمعرفة أين نشحن (نجلب حقول محددة فقط للحماية)
        ])
        ->where('vendor_id', $vendorId)
        ->latest()
        ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $orders
        ]);
    }

    /**
     * تحديث حالة الطلب من قبل التاجر (مثلاً من قيد التجهيز إلى تم الشحن)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:processing,shipped,delivered'
        ]);

        $vendorId = auth()->user()->vendor_id;

        try {
            $vendorOrder = VendorOrder::findOrFail($id);

            // إرسال للـ Service للقيام بالتحديث والمزامنة
            $updatedOrder = $this->vendorOrderService->updateStatus(
                $vendorOrder, 
                $request->status, 
                $vendorId
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة الطلب بنجاح.',
                'data'    => $updatedOrder
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        }
    }
}