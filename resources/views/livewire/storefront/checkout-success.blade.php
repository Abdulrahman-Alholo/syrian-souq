<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Modules\Order\Models\Order;

new #[Layout('layouts.app')] class extends Component
{
    public $order;

    public function mount()
    {
        // جلب رقم الطلب من الجلسة (لضمان أن العميل لا يدخل الصفحة عشوائياً)
        $orderId = session('success_order_id');

        if (!$orderId) {
            return redirect()->route('cart');
        }

        // جلب الطلب مع عناصره
        $this->order = Order::with('items')->findOrFail($orderId);
    }
};
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="bg-white shadow-xl sm:rounded-2xl overflow-hidden">
        
        <div class="bg-green-600 px-6 py-10 text-center">
            <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-green-100 mb-6 shadow-lg">
                <svg class="h-12 w-12 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h1 class="text-3xl font-extrabold text-white mb-2">شكراً لك! تم استلام طلبك بنجاح</h1>
            <p class="text-green-100 text-lg">رقم الطلب الخاص بك هو: <span class="font-bold text-white tracking-wider">{{ $order->order_number }}</span></p>
        </div>

        <div class="px-6 py-8 sm:p-10 bg-gray-50">
            
            <div class="mb-10">
                <h3 class="text-lg font-bold text-gray-900 mb-5">ماذا سيحدث الآن؟</h3>
                <div class="relative">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-between">
                        <div>
                            <span class="bg-green-600 h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-gray-50">
                                <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            </span>
                            <p class="mt-2 text-sm font-medium text-green-600">تم الاستلام</p>
                        </div>
                        <div>
                            <span class="bg-blue-600 h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-gray-50 shadow-md">
                                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                            </span>
                            <p class="mt-2 text-sm font-medium text-gray-900 text-center">قيد التجهيز</p>
                        </div>
                        <div>
                            <span class="bg-white border-2 border-gray-300 h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-gray-50">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                            </span>
                            <p class="mt-2 text-sm font-medium text-gray-500 text-left">قيد الشحن</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-4 py-5 sm:px-6 bg-gray-100 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">ملخص الفاتورة</h3>
                    <span class="text-sm text-gray-500">{{ $order->created_at->format('Y-m-d H:i') }}</span>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <ul class="divide-y divide-gray-200 -mt-4">
                        @foreach($order->items as $item)
                        <li class="py-4 flex justify-between">
                            <div class="flex flex-col">
                                <span class="font-medium text-gray-900">{{ $item->product_name }}</span>
                                <span class="text-sm text-gray-500">{{ $item->variant_description }} (الكمية: {{ $item->quantity }})</span>
                            </div>
                            <span class="text-gray-900 font-medium">{{ number_format($item->unit_price * $item->quantity, 0) }} SYP</span>
                        </li>
                        @endforeach
                    </ul>
                    
                    <dl class="border-t border-gray-200 pt-4 mt-4 space-y-3">
                        <div class="flex items-center justify-between text-sm">
                            <dt class="text-gray-600">المجموع الفرعي</dt>
                            <dd class="text-gray-900">{{ number_format($order->subtotal, 0) }} SYP</dd>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <dt class="text-gray-600">أجور التوصيل</dt>
                            <dd class="text-gray-900">{{ number_format($order->shipping_price, 0) }} SYP</dd>
                        </div>
                        <div class="flex items-center justify-between text-lg font-bold border-t border-gray-200 pt-3 mt-3">
                            <dt class="text-gray-900">الإجمالي النهائي</dt>
                            <dd class="text-green-600">{{ number_format($order->total_price, 0) }} SYP</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="mt-8 flex justify-center space-x-4 space-x-reverse">
                <a href="/" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
                    العودة للرئيسية والتسوق
                </a>
            </div>

        </div>
    </div>
</div>